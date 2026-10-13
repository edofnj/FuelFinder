<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/mailer.php';

// L'email che diventa admin automaticamente alla registrazione.
if (!defined('ADMIN_EMAIL')) define('ADMIN_EMAIL', 'edoardo@fmenegazzi.it');

function requestIsHttps() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

// Avvia la sessione con cookie sicuri. No-op in CLI.
function authBoot() {
    if (PHP_SAPI === 'cli') return;
    if (session_status() === PHP_SESSION_ACTIVE) return;
    if (headers_sent()) return;
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'secure'   => requestIsHttps(),
        'samesite' => 'Lax',
    ]);
    session_name('ff_sess');
    session_start();
    // Login persistente via cookie "ricordami"
    if (empty($_SESSION['uid']) && !empty($_COOKIE['ff_remember'])) {
        rememberLogin($_COOKIE['ff_remember']);
    }
}

function currentUserId() { return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null; }
function isLoggedIn()    { return !empty($_SESSION['uid']); }

function currentUser() {
    static $cache = false;
    if ($cache !== false) return $cache;
    if (empty($_SESSION['uid'])) return $cache = null;
    try {
        // is_admin::int per evitare l'ambiguità del boolean PG via PDO ('f' è truthy in PHP)
        $st = pdo()->prepare('SELECT id, email, is_admin::int AS is_admin, email_verified::int AS email_verified FROM users WHERE id = :id');
        $st->execute([':id' => $_SESSION['uid']]);
        $u = $st->fetch();
        $cache = $u ?: null;
    } catch (Throwable $e) { $cache = null; }
    return $cache;
}

function isAdmin() { $u = currentUser(); return $u && (int)$u['is_admin'] === 1; }

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: /account?next=' . urlencode($_SERVER['REQUEST_URI'] ?? '/stats'));
        exit;
    }
}

// ---- CSRF ----
function csrfToken() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrfCheck($t) {
    return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t);
}

function validEmail($e) {
    return is_string($e) && strlen($e) <= 254 && filter_var($e, FILTER_VALIDATE_EMAIL);
}

// ---- Registrazione / Login / Logout ----
function register($email, $password) {
    $email = trim((string)$email);
    if (!validEmail($email))                       return [false, 'email_invalid'];
    if (!is_string($password) || strlen($password) < 8) return [false, 'password_short'];

    $hash    = password_hash($password, PASSWORD_DEFAULT);
    $isAdmin = (strtolower($email) === strtolower(ADMIN_EMAIL));
    try {
        $st = pdo()->prepare('INSERT INTO users (email, password_hash, is_admin) VALUES (:e,:h,:a) RETURNING id');
        $st->execute([':e' => $email, ':h' => $hash, ':a' => $isAdmin ? 'true' : 'false']);
        $id = (int)$st->fetchColumn();
    } catch (PDOException $e) {
        if ($e->getCode() === '23505') return [false, 'email_taken'];
        return [false, 'db_error'];
    }
    // Verifica email obbligatoria: NON si effettua il login automatico alla
    // registrazione; l'utente deve confermare l'email prima di poter accedere.
    sendVerifyEmail($id, $email);
    return [true, $id];
}

function login($email, $password, $remember = false) {
    $email = trim((string)$email);
    if (!validEmail($email)) return [false, 'invalid'];
    if (rateLimited())       return [false, 'rate_limited'];
    try {
        $st = pdo()->prepare('SELECT id, password_hash, email_verified::int AS email_verified FROM users WHERE lower(email) = lower(:e)');
        $st->execute([':e' => $email]);
        $u = $st->fetch();
    } catch (Throwable $e) { return [false, 'db_error']; }

    $ok = $u && password_verify($password, $u['password_hash']);
    if (!$ok) { recordAttempt($email, false); return [false, 'invalid']; }
    // Verifica email obbligatoria: niente login finché non confermata.
    if ((int)$u['email_verified'] !== 1) return [false, 'unverified'];
    recordAttempt($email, true);

    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    try { pdo()->prepare('UPDATE users SET last_login = now() WHERE id = :id')->execute([':id' => $u['id']]); } catch (Throwable $e) {}
    if ($remember) setRememberCookie((int)$u['id']);
    return [true, (int)$u['id']];
}

function logout() {
    if (!empty($_COOKIE['ff_remember'])) {
        $parts = explode(':', $_COOKIE['ff_remember'], 2);
        if (count($parts) === 2) {
            try { pdo()->prepare('DELETE FROM auth_tokens WHERE id = :id')->execute([':id' => (int)$parts[0]]); } catch (Throwable $e) {}
        }
        setcookie('ff_remember', '', time() - 3600, '/');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', !empty($p['secure']), !empty($p['httponly']));
    }
    @session_destroy();
}

// ---- "Ricordami": token = id:validator, in DB salviamo solo l'hash del validator ----
function setRememberCookie($uid) {
    $validator = bin2hex(random_bytes(32));
    $hash = hash('sha256', $validator);
    $exp  = time() + 60 * 60 * 24 * 30; // 30 giorni
    try {
        $st = pdo()->prepare('INSERT INTO auth_tokens (user_id, token_hash, expires_at) VALUES (:u,:h, to_timestamp(:e)) RETURNING id');
        $st->execute([':u' => $uid, ':h' => $hash, ':e' => $exp]);
        $id = (int)$st->fetchColumn();
    } catch (Throwable $e) { return; }
    setcookie('ff_remember', $id . ':' . $validator, [
        'expires'  => $exp,
        'path'     => '/',
        'httponly' => true,
        'secure'   => requestIsHttps(),
        'samesite' => 'Lax',
    ]);
}

function rememberLogin($cookie) {
    $parts = explode(':', (string)$cookie, 2);
    if (count($parts) !== 2) return;
    [$id, $validator] = $parts;
    try {
        $st = pdo()->prepare('SELECT user_id, token_hash, extract(epoch from expires_at) AS exp FROM auth_tokens WHERE id = :id');
        $st->execute([':id' => (int)$id]);
        $row = $st->fetch();
        if (!$row) return;
        if ((float)$row['exp'] < time()) {
            pdo()->prepare('DELETE FROM auth_tokens WHERE id = :id')->execute([':id' => (int)$id]);
            return;
        }
        if (!hash_equals($row['token_hash'], hash('sha256', $validator))) return;
        // Rotazione: ogni uso consuma il token ed emette un nuovo cookie,
        // così un cookie rubato smette di funzionare al primo uso legittimo.
        pdo()->prepare('DELETE FROM auth_tokens WHERE id = :id')->execute([':id' => (int)$id]);
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$row['user_id'];
        setRememberCookie((int)$row['user_id']);
    } catch (Throwable $e) { /* ignore */ }
}

// ---- Rate limit login (per ip_hash anonimo) ----
function recordAttempt($email, $success) {
    try {
        pdo()->prepare('INSERT INTO login_attempts (ip_hash, email, success) VALUES (:ip,:e,:s)')
             ->execute([':ip' => ipHashForRate(), ':e' => $email, ':s' => $success ? 'true' : 'false']);
    } catch (Throwable $e) {}
}
function rateLimited() {
    try {
        $st = pdo()->prepare("SELECT count(*) FROM login_attempts WHERE ip_hash = :ip AND success = false AND ts > now() - interval '15 minutes'");
        $st->execute([':ip' => ipHashForRate()]);
        return ((int)$st->fetchColumn()) >= 10;
    } catch (Throwable $e) { return false; }
}

// ---- Verifica email / reset password / cancellazione account ----
function baseUrl() {
    $proto = requestIsHttps() ? 'https' : 'http';
    $host  = $_SERVER['HTTP_HOST'] ?? 'fuelfinder.fmenegazzi.it';
    return $proto . '://' . $host;
}

function sendVerifyEmail($uid, $email) {
    try {
        $token = bin2hex(random_bytes(24));
        pdo()->prepare("UPDATE users SET verify_token=:t, verify_expires=now()+interval '2 days' WHERE id=:id")
             ->execute([':t' => $token, ':id' => $uid]);
        $link = baseUrl() . '/account?verify=' . $token;
        $de   = (function_exists('currentLang') && currentLang() === 'de');
        $subj = $de ? 'FuelFinder — E-Mail bestätigen' : 'FuelFinder — conferma la tua email';
        $body = ($de ? '<p>Bitte bestätige deine E-Mail-Adresse:</p>' : '<p>Conferma il tuo indirizzo email cliccando il pulsante:</p>')
              . '<p style="margin:20px 0"><a href="' . $link . '" style="background:#10b981;color:#04211a;padding:11px 18px;border-radius:8px;text-decoration:none;font-weight:700">'
              . ($de ? 'E-Mail bestätigen' : 'Conferma email') . '</a></p>'
              . '<p style="font-size:12px;color:#94a3b8;word-break:break-all">' . $link . '</p>';
        return sendMail($email, $subj, mailTemplate($de ? 'E-Mail bestätigen' : 'Conferma email', $body));
    } catch (Throwable $e) { return false; }
}

function verifyEmailToken($token) {
    if (!is_string($token) || strlen($token) < 10) return false;
    try {
        // UPDATE atomico (niente finestra SELECT→UPDATE): il token si consuma una volta sola
        $st = pdo()->prepare('UPDATE users SET email_verified=true, verify_token=NULL, verify_expires=NULL
                              WHERE verify_token=:t AND verify_expires > now() RETURNING id');
        $st->execute([':t' => $token]);
        return $st->fetchColumn() !== false;
    } catch (Throwable $e) { return false; }
}

function createPasswordReset($email) {
    // Non rivela mai se l'email esiste (anti-enumeration): ritorna sempre true.
    try {
        $st = pdo()->prepare('SELECT id FROM users WHERE lower(email)=lower(:e)');
        $st->execute([':e' => trim((string)$email)]);
        $uid = $st->fetchColumn();
        if (!$uid) return true;
        $token = bin2hex(random_bytes(24));
        pdo()->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:u,:h, now()+interval '1 hour')")
             ->execute([':u' => $uid, ':h' => hash('sha256', $token)]);
        $link = baseUrl() . '/account?reset=' . $token;
        $de   = (function_exists('currentLang') && currentLang() === 'de');
        $subj = $de ? 'FuelFinder — Passwort zurücksetzen' : 'FuelFinder — reimposta la password';
        $body = ($de ? '<p>Klicke, um dein Passwort zurückzusetzen (1 Stunde gültig):</p>' : '<p>Clicca per reimpostare la password (link valido 1 ora):</p>')
              . '<p style="margin:20px 0"><a href="' . $link . '" style="background:#10b981;color:#04211a;padding:11px 18px;border-radius:8px;text-decoration:none;font-weight:700">'
              . ($de ? 'Passwort zurücksetzen' : 'Reimposta password') . '</a></p>'
              . '<p style="font-size:12px;color:#94a3b8;word-break:break-all">' . $link . '</p>';
        sendMail((string)$email, $subj, mailTemplate($de ? 'Passwort zurücksetzen' : 'Reimposta password', $body));
        return true;
    } catch (Throwable $e) { return true; }
}

function resetPasswordWithToken($token, $newPw) {
    if (!is_string($newPw) || strlen($newPw) < 8) return [false, 'password_short'];
    try {
        $st = pdo()->prepare('SELECT user_id FROM password_resets WHERE token_hash=:h AND expires_at > now()');
        $st->execute([':h' => hash('sha256', (string)$token)]);
        $uid = $st->fetchColumn();
        if (!$uid) return [false, 'invalid'];
        pdo()->prepare('UPDATE users SET password_hash=:h WHERE id=:id')
             ->execute([':h' => password_hash($newPw, PASSWORD_DEFAULT), ':id' => $uid]);
        pdo()->prepare('DELETE FROM password_resets WHERE user_id=:u')->execute([':u' => $uid]);
        // Il cambio password invalida anche i "ricordami" attivi (cookie rubati inclusi)
        pdo()->prepare('DELETE FROM auth_tokens WHERE user_id=:u')->execute([':u' => $uid]);
        return [true, $uid];
    } catch (Throwable $e) { return [false, 'db_error']; }
}

function deleteAccount($uid) {
    try { pdo()->prepare('DELETE FROM users WHERE id=:id')->execute([':id' => $uid]); return true; }
    catch (Throwable $e) { return false; }
}

// Verifica la password corrente di un utente (per azioni sensibili: cambio pw, delete).
function verifyUserPassword($uid, $password) {
    if (!is_string($password) || $password === '') return false;
    try {
        $st = pdo()->prepare('SELECT password_hash FROM users WHERE id=:id');
        $st->execute([':id' => $uid]);
        $h = $st->fetchColumn();
        return $h && password_verify($password, $h);
    } catch (Throwable $e) { return false; }
}

function changePassword($uid, $current, $new) {
    if (!is_string($new) || strlen($new) < 8) return [false, 'password_short'];
    if (!verifyUserPassword($uid, $current))  return [false, 'wrong_password'];
    try {
        pdo()->prepare('UPDATE users SET password_hash=:h WHERE id=:id')
             ->execute([':h' => password_hash($new, PASSWORD_DEFAULT), ':id' => $uid]);
        // Come per il reset: invalida i "ricordami" attivi su tutti i dispositivi
        pdo()->prepare('DELETE FROM auth_tokens WHERE user_id=:u')->execute([':u' => $uid]);
        return [true, null];
    } catch (Throwable $e) { return [false, 'db_error']; }
}
