<?php
require_once __DIR__ . '/includes/config.php'; // bootstrap db/metrics/auth + sessione
require_once __DIR__ . '/includes/i18n.php';

// ------- API JSON (POST) -------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'logout') {
        logout();
        echo json_encode(['ok' => true]);
        exit;
    }
    if (!csrfCheck($_POST['csrf'] ?? '')) {
        echo json_encode(['ok' => false, 'error' => 'csrf']);
        exit;
    }
    if ($action === 'delete_account') {
        // Elimina i dati di FuelFinder (account locale, garage). L'account fmenegazzi resta.
        if (!isLoggedIn()) { echo json_encode(['ok' => false, 'error' => 'auth']); exit; }
        $confirm = mb_strtoupper(trim((string)($_POST['confirm'] ?? '')));
        if (!in_array($confirm, ['ELIMINA', 'LÖSCHEN', 'LOESCHEN'], true)) {
            echo json_encode(['ok' => false, 'error' => 'confirm']); exit;
        }
        deleteAccount(currentUserId());
        // Tiene l'id_token: il client prosegue con /oidc?logout (esce anche dall'account unico,
        // altrimenti il rientro automatico ricreerebbe subito un account FuelFinder vuoto)
        unset($_SESSION['uid'], $_SESSION['sv']);
        echo json_encode(['ok' => true]);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'bad_action']);
    exit;
}

// ------- Vecchi link delle email di verifica e reset password: ora tutto passa dall'account unico -------
if (isset($_GET['verify']) || isset($_GET['reset'])) {
    header('Location: /account');
    exit;
}

// ------- Schermata di accesso con l'account unico (GET) -------
$lang = function_exists('currentLang') ? currentLang() : 'it';
$de   = $lang === 'de';
$next = $_GET['next'] ?? '/';
if (!is_string($next) || $next === '' || $next[0] !== '/' || strpos($next, '//') === 0) $next = '/';
$ssoError = isset($_GET['sso_error']) ? preg_replace('/[^a-z_]/', '', (string)$_GET['sso_error']) : null;
// /account?tab=register (vecchi link) → registrazione sull'account unico
if (($_GET['tab'] ?? '') === 'register' && oidcEnabled() && !isLoggedIn()) {
    header('Location: /oidc?start&register=1&next=' . urlencode($next)); exit;
}
if (isLoggedIn()) { header('Location: ' . $next); exit; }
function L($it, $deTxt, $de) { return $de ? $deTxt : $it; }
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FuelFinder — <?= L('Accedi','Anmelden',$de) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="img/logo.svg">
<link rel="stylesheet" href="/fonts/fonts.css">
<style>
:root{--bg:#0b1220;--fg:#f1f5f9;--muted:#94a3b8;--faint:#64748b;--line:#1e293b;--line2:#2c3a50;--card:#111a2b;--accent:#10b981}
*{box-sizing:border-box}
html,body{height:auto;min-height:100%;margin:0}
body{background:var(--bg);color:var(--fg);font-family:'Inter',system-ui,sans-serif;-webkit-font-smoothing:antialiased;display:flex;flex-direction:column;min-height:100vh;line-height:1.5}
.auth{flex:1;display:flex;align-items:center;justify-content:center;padding:32px 20px}
.auth-box{width:100%;max-width:380px}
.auth-brand{display:flex;align-items:center;justify-content:center;gap:10px;text-decoration:none;color:var(--fg);font-weight:700;font-size:1.2rem;letter-spacing:-.01em;margin-bottom:24px}
.auth-brand b{color:var(--accent)}
.auth-title{font-size:1.35rem;letter-spacing:-.02em;margin:0 0 18px;text-align:center}
.sso-btn{display:block;text-align:center;background:var(--accent);color:#04211a;border-radius:10px;padding:13px;font-weight:700;font-size:.92rem;text-decoration:none;margin-top:10px}
.sso-btn:hover{background:#34d399}
.sso-btn.alt{background:transparent;color:var(--fg);border:1px solid var(--line2)}
.sso-btn.alt:hover{border-color:var(--accent);background:rgba(16,185,129,.08)}
.sso-note{font-size:.8rem;color:var(--muted);text-align:center;margin:14px 0 0}
.sso-note a{color:var(--fg)}
.msg{font-size:.82rem;min-height:16px;margin-top:12px;text-align:center;color:#f0a0a0}
.msg a{color:var(--accent)}
.sso-err{margin:0 0 6px}
.back{display:block;text-align:center;margin-top:22px;color:var(--faint);text-decoration:none;font-size:.82rem}
.back:hover{color:var(--fg)}
.auth-foot{text-align:center;padding:20px;font-size:.76rem;color:var(--faint)}
.auth-foot a{color:var(--muted);text-decoration:none}.auth-foot a:hover{color:var(--accent)}
</style>
</head>
<body>
<main class="auth">
    <div class="auth-box">
        <a class="auth-brand" href="/"><img src="img/logo.svg" width="30" height="30" alt="">Fuel<b>Finder</b></a>
        <h1 class="auth-title"><?= L('Accedi','Anmelden',$de) ?></h1>
        <?php if ($ssoError): ?><div class="msg sso-err" role="alert"><?= htmlspecialchars([
            'email_not_verified' => L('Conferma prima la tua email sull\'account fmenegazzi, poi riprova.','Bestätige zuerst deine E-Mail im fmenegazzi-Konto und versuche es erneut.',$de),
            'email_in_use'       => L('Questa email è già collegata a un altro account fmenegazzi.','Diese E-Mail ist bereits mit einem anderen fmenegazzi-Konto verknüpft.',$de),
            'access_denied'      => L('Accesso annullato.','Anmeldung abgebrochen.',$de),
            'unavailable'        => L('L\'account unico non è raggiungibile. Riprova tra poco.','Das Konto ist gerade nicht erreichbar. Bitte später erneut versuchen.',$de),
        ][$ssoError] ?? L('Accesso non riuscito. Riprova.','Anmeldung fehlgeschlagen. Bitte erneut versuchen.',$de)) ?>
            <?php if ($ssoError === 'email_not_verified'): ?><a href="https://www.fmenegazzi.it/account/"><?= L('Conferma l\'email','E-Mail bestätigen',$de) ?></a><?php endif; ?></div><?php endif; ?>
        <a class="sso-btn" href="/oidc?start&amp;next=<?= urlencode($next) ?>"><?= L('Accedi con account fmenegazzi','Mit fmenegazzi-Konto anmelden',$de) ?></a>
        <a class="sso-btn alt" href="/oidc?start&amp;register=1&amp;next=<?= urlencode($next) ?>"><?= L('Crea un account gratuito','Kostenloses Konto erstellen',$de) ?></a>
        <p class="sso-note"><?= L('Un solo account per FuelFinder, SubTracker, FoodWaste e gli altri strumenti di','Ein Konto für FuelFinder, SubTracker, FoodWaste und die anderen Tools von',$de) ?> <a href="https://www.fmenegazzi.it">fmenegazzi.it</a>.</p>

        <a class="back" href="/">&larr; <?= L('Torna al sito','Zurück zur Seite',$de) ?></a>
    </div>
</main>
<footer class="auth-foot"><a href="/privacy">Privacy</a> · <a href="/tos"><?= L('Termini','AGB',$de) ?></a></footer>
</body>
</html>
