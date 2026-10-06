<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/oidc.php';

// L'email che diventa admin automaticamente al primo accesso con l'account unico.
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
    // Già entrato in un altro strumento con l'account unico → rientro automatico
    ssoAutoLogin();
}

// Versione delle sessioni dell'utente: incrementandola (es. al collegamento con l'account unico)
// le sessioni aperte prima smettono di valere.
function currentSessionVersion($uid) {
    try {
        $st = pdo()->prepare('SELECT session_version FROM users WHERE id = :id');
        $st->execute([':id' => $uid]);
        return (int)$st->fetchColumn();
    } catch (Throwable $e) { return 0; }
}

// Passano da currentUser(): una sessione decaduta (session_version superata) risulta subito non loggata
function currentUserId() { $u = currentUser(); return $u ? (int)$u['id'] : null; }
function isLoggedIn()    { return currentUser() !== null; }

// $refresh: rilegge dopo un login avvenuto nella stessa richiesta
function currentUser($refresh = false) {
    static $cache = false;
    if ($refresh) $cache = false;
    if ($cache !== false) return $cache;
    if (empty($_SESSION['uid'])) return $cache = null;
    try {
        // is_admin::int per evitare l'ambiguità del boolean PG via PDO ('f' è truthy in PHP)
        $st = pdo()->prepare('SELECT id, email, is_admin::int AS is_admin, email_verified::int AS email_verified,
                              session_version FROM users WHERE id = :id');
        $st->execute([':id' => $_SESSION['uid']]);
        $u = $st->fetch();
        // Sessione aperta prima dell'ultimo cambio di versione (es. collegamento all'account unico): non vale più
        if ($u && (int)($_SESSION['sv'] ?? 0) !== (int)$u['session_version']) {
            unset($_SESSION['uid'], $_SESSION['sv'], $_SESSION['idt']);
            $u = null;
        }
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

// ---- Logout (l'accesso passa solo dall'account unico, includes/oidc.php) ----
function logout() {
    // Cookie "ricordami" del vecchio login con password, se ancora presente nel browser
    if (!empty($_COOKIE['ff_remember'])) setcookie('ff_remember', '', time() - 3600, '/');
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', !empty($p['secure']), !empty($p['httponly']));
    }
    @session_destroy();
}

// ---- Cancellazione dei dati FuelFinder (l'account unico resta) ----
function deleteAccount($uid) {
    try { pdo()->prepare('DELETE FROM users WHERE id=:id')->execute([':id' => $uid]); return true; }
    catch (Throwable $e) { return false; }
}
