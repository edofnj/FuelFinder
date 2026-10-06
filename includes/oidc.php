<?php
// Login con l'account unico fmenegazzi (Zitadel): OIDC authorization code + PKCE, client confidenziale.
// Incluso da auth.php. Entrypoint HTTP: /oidc (oidc.php).
//
// Profilo, email, password e sicurezza si gestiscono su https://www.fmenegazzi.it/account/ ;
// qui restano solo i dati di FuelFinder (garage, metriche).

if (!defined('ACCOUNT_PAGE_URL')) define('ACCOUNT_PAGE_URL', 'https://www.fmenegazzi.it/account/');

// Indizio condiviso tra tutti gli strumenti (*.fmenegazzi.it): "questo browser ha una sessione dell'account unico".
// Nessun dato dentro. Serve perché il login v2 di Zitadel non supporta prompt=none: vedendolo, il tool
// avvia il login e Zitadel lo fa rientrare subito senza chiedere nulla.
const SSO_HINT_COOKIE = 'fm_sso';
const SSO_HINT_MAX_AGE = 864000; // 10 giorni, come la sessione di login di Zitadel

function oidcConfig() {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = [
            'issuer'   => rtrim((string)getenv('OIDC_ISSUER'), '/'),
            'client'   => (string)getenv('OIDC_CLIENT_ID'),
            'secret'   => (string)getenv('OIDC_CLIENT_SECRET'),
            'redirect' => (string)getenv('OIDC_REDIRECT_URI'),
            'hint'     => (string)getenv('SSO_HINT_DOMAIN'), // es. .fmenegazzi.it (vuoto in sviluppo)
        ];
    }
    return $cfg;
}

function oidcEnabled() {
    $c = oidcConfig();
    return $c['issuer'] !== '' && $c['client'] !== '' && $c['secret'] !== '' && $c['redirect'] !== '';
}

function oidcHttp($url, $post = null, $basicAuth = null) {
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ];
    if ($post !== null) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
        $opts[CURLOPT_HTTPHEADER] = ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'];
    }
    if ($basicAuth !== null) {
        $opts[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        $opts[CURLOPT_USERPWD] = $basicAuth;
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $body === false ? null : json_decode($body, true)];
}

// Discovery con cache di un'ora in cache/ (cartella già bloccata da .htaccess)
function oidcDiscovery() {
    $file = __DIR__ . '/../cache/oidc-discovery.json';
    if (is_file($file) && filemtime($file) > time() - 3600) {
        $d = json_decode((string)file_get_contents($file), true);
        if (is_array($d)) return $d;
    }
    [$code, $d] = oidcHttp(oidcConfig()['issuer'] . '/.well-known/openid-configuration');
    if ($code !== 200 || !is_array($d) || empty($d['authorization_endpoint'])) return null;
    @file_put_contents($file, json_encode($d), LOCK_EX);
    return $d;
}

function b64url($bin) { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }

// Solo percorsi locali: niente schema/host, niente // iniziale, backslash o caratteri di controllo
// (i browser scartano tab e a capo: "/\t/sito" diventerebbe "//sito").
function safeNext($next) {
    if (!is_string($next) || $next === '' || $next[0] !== '/') return '/';
    if (preg_match('/[\x00-\x1F\x7F\\\\]/', $next) || strpos($next, '//') === 0) return '/';
    $u = parse_url($next);
    if ($u === false || isset($u['scheme']) || isset($u['host']) || isset($u['user'])) return '/';
    return $next;
}

function ssoSetHint($on) {
    $c = oidcConfig();
    $attrs = ['expires' => $on ? time() + SSO_HINT_MAX_AGE : time() - 3600, 'path' => '/', 'secure' => requestIsHttps(), 'samesite' => 'Lax'];
    if ($c['hint'] !== '') $attrs['domain'] = $c['hint'];
    setcookie(SSO_HINT_COOKIE, $on ? '1' : '', $attrs);
}

// Avvia il login: $register = schermata di registrazione. Esce con un redirect.
function oidcStart($next = '/', $register = false) {
    $d = oidcEnabled() ? oidcDiscovery() : null;
    if (!$d) { header('Location: /account?sso_error=unavailable'); exit; }
    $state = b64url(random_bytes(24));
    $nonce = b64url(random_bytes(24));
    $verifier = b64url(random_bytes(32));
    $_SESSION['oidc'] = ['state' => $state, 'nonce' => $nonce, 'verifier' => $verifier, 'next' => safeNext($next), 'ts' => time()];
    $params = [
        'client_id'             => oidcConfig()['client'],
        'response_type'         => 'code',
        'scope'                 => 'openid email profile',
        'redirect_uri'          => oidcConfig()['redirect'],
        'state'                 => $state,
        'nonce'                 => $nonce,
        'code_challenge'        => b64url(hash('sha256', $verifier, true)),
        'code_challenge_method' => 'S256',
        'ui_locales'            => (function_exists('currentLang') && currentLang() === 'de') ? 'de' : 'it',
    ];
    if ($register) $params['prompt'] = 'create';
    header('Location: ' . $d['authorization_endpoint'] . '?' . http_build_query($params));
    exit;
}

// Collega o crea l'utente locale dai claim verificati. Ritorna [user|null, errore|null].
// - già collegato (zitadel_sub) → stesso utente (aggiorna l'email se è cambiata sull'account unico)
// - email verificata e utente locale con la stessa email non collegato → lo collega: niente più password locale,
//   via i "ricordami" e le sessioni aperte (session_version + 1)
// - altrimenti nuovo utente senza password
function oidcLinkOrCreate($sub, $email, $emailVerified) {
    $db = pdo();
    try {
        $db->beginTransaction();
        $st = $db->prepare('SELECT id, email FROM users WHERE zitadel_sub = :s FOR UPDATE');
        $st->execute([':s' => $sub]);
        if ($u = $st->fetch()) {
            if ($emailVerified && strtolower($u['email']) !== strtolower($email)) {
                $db->prepare('UPDATE users SET email = :e WHERE id = :id AND NOT EXISTS (SELECT 1 FROM users WHERE lower(email) = lower(:e))')
                   ->execute([':e' => $email, ':id' => $u['id']]);
            }
            $db->commit();
            return [(int)$u['id'], null];
        }
        if (!$emailVerified) { $db->rollBack(); return [null, 'email_not_verified']; }

        $st = $db->prepare('SELECT id, zitadel_sub FROM users WHERE lower(email) = lower(:e) FOR UPDATE');
        $st->execute([':e' => $email]);
        $existing = $st->fetch();
        if ($existing && $existing['zitadel_sub']) { $db->rollBack(); return [null, 'email_in_use']; }

        if ($existing) {
            $id = (int)$existing['id'];
            $db->prepare('UPDATE users SET zitadel_sub = :s, password_hash = NULL, email_verified = true, verify_token = NULL,
                          verify_expires = NULL, session_version = session_version + 1 WHERE id = :id')
               ->execute([':s' => $sub, ':id' => $id]);
            $db->prepare('DELETE FROM auth_tokens WHERE user_id = :id')->execute([':id' => $id]);
            $db->prepare('DELETE FROM password_resets WHERE user_id = :id')->execute([':id' => $id]);
        } else {
            $isAdmin = strtolower($email) === strtolower(ADMIN_EMAIL);
            $st = $db->prepare('INSERT INTO users (email, password_hash, zitadel_sub, email_verified, is_admin)
                                VALUES (:e, NULL, :s, true, :a) RETURNING id');
            $st->execute([':e' => $email, ':s' => $sub, ':a' => $isAdmin ? 'true' : 'false']);
            $id = (int)$st->fetchColumn();
        }
        $db->commit();
        return [$id, null];
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[oidc] link: ' . $e->getMessage());
        return [null, 'db_error'];
    }
}

// Ritorno da account.fmenegazzi.it. Esce con un redirect.
function oidcCallback() {
    $saved = $_SESSION['oidc'] ?? null;
    unset($_SESSION['oidc']);
    // La pagina di login di Zitadel a volte richiama il callback due volte: il secondo trova lo stato già usato
    if (!$saved && isLoggedIn()) { header('Location: /'); exit; }
    $fail = function ($code) { header('Location: /account?sso_error=' . urlencode($code)); exit; };

    if (!oidcEnabled()) $fail('disabled');
    if (!$saved || !isset($_GET['state']) || !is_string($_GET['state']) || !hash_equals($saved['state'], $_GET['state'])) $fail('state');
    if (time() - (int)$saved['ts'] > 900) $fail('state');
    if (isset($_GET['error'])) $fail(substr(preg_replace('/[^a-z_]/', '', (string)$_GET['error']), 0, 40));
    if (empty($_GET['code']) || !is_string($_GET['code'])) $fail('code');

    $d = oidcDiscovery();
    if (!$d) $fail('unavailable');
    $c = oidcConfig();
    [$code, $tokens] = oidcHttp($d['token_endpoint'], [
        'grant_type'    => 'authorization_code',
        'code'          => $_GET['code'],
        'redirect_uri'  => $c['redirect'],
        'code_verifier' => $saved['verifier'],
    ], rawurlencode($c['client']) . ':' . rawurlencode($c['secret']));
    if ($code !== 200 || empty($tokens['id_token'])) { error_log('[oidc] token endpoint HTTP ' . $code); $fail('token'); }

    // id_token ricevuto direttamente dal token endpoint via TLS con autenticazione del client:
    // la validazione TLS sostituisce la firma (OIDC Core 3.1.3.7); controlliamo comunque iss/aud/exp/nonce.
    $parts = explode('.', $tokens['id_token']);
    $claims = count($parts) === 3 ? json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true) : null;
    if (!is_array($claims)) $fail('verify');
    $aud = is_array($claims['aud'] ?? null) ? $claims['aud'] : [$claims['aud'] ?? ''];
    if (($claims['iss'] ?? '') !== $c['issuer'] || !in_array($c['client'], $aud, true)
        || (int)($claims['exp'] ?? 0) < time() - 60 || !hash_equals($saved['nonce'], (string)($claims['nonce'] ?? ''))) {
        $fail('verify');
    }
    $email = strtolower(trim((string)($claims['email'] ?? '')));
    if (!validEmail($email) || empty($claims['sub'])) $fail('email');

    [$uid, $err] = oidcLinkOrCreate((string)$claims['sub'], $email, ($claims['email_verified'] ?? false) === true);
    if ($err) $fail($err);

    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    $_SESSION['sv'] = currentSessionVersion($uid);
    $_SESSION['idt'] = $tokens['id_token']; // per il logout senza pagina "scegli l'account"
    currentUser(true);
    try { pdo()->prepare('UPDATE users SET last_login = now() WHERE id = :id')->execute([':id' => $uid]); } catch (Throwable $e) {}
    if (function_exists('track')) track('login');
    ssoSetHint(true);
    header('Location: ' . safeNext($saved['next']));
    exit;
}

// Esce da FuelFinder e chiude la sessione dell'account unico. Esce con un redirect.
function oidcLogout() {
    $idt = $_SESSION['idt'] ?? null;
    logout();
    ssoSetHint(false);
    $d = oidcEnabled() ? oidcDiscovery() : null;
    if (!$d || empty($d['end_session_endpoint'])) { header('Location: /'); exit; }
    $redirect = preg_replace('#/oidc$#', '/', oidcConfig()['redirect']);
    $params = ['client_id' => oidcConfig()['client'], 'post_logout_redirect_uri' => $redirect];
    if ($idt) $params['id_token_hint'] = $idt;
    header('Location: ' . $d['end_session_endpoint'] . '?' . http_build_query($params));
    exit;
}

// Rientro automatico: chi ha già fatto l'accesso in un altro strumento entra senza fare nulla.
// Solo pagine HTML in GET, una volta per sessione (niente giri infiniti se la sessione Zitadel è scaduta).
function ssoAutoLogin() {
    if (empty($_COOKIE[SSO_HINT_COOKIE]) || !oidcEnabled() || isLoggedIn()) return;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || !empty($_SESSION['sso_tried'])) return;
    if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html') === false) return;
    $page = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!in_array($page, ['index.php', 'route.php', 'profile.php', 'stats.php'], true)) return;
    $_SESSION['sso_tried'] = 1;
    oidcStart($_SERVER['REQUEST_URI'] ?? '/');
}
