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
    if ($action === 'register') {
        // Le nuove registrazioni passano dall'account unico fmenegazzi
        if (oidcEnabled()) { echo json_encode(['ok' => false, 'error' => 'registration_closed']); exit; }
        [$ok, $res] = register($_POST['email'] ?? '', $_POST['password'] ?? '');
        if ($ok) { track('signup'); echo json_encode(['ok' => true, 'verify' => true]); }
        else      echo json_encode(['ok' => false, 'error' => $res]);
        exit;
    }
    if ($action === 'login') {
        [$ok, $res] = login($_POST['email'] ?? '', $_POST['password'] ?? '', !empty($_POST['remember']));
        if ($ok) { track('login'); echo json_encode(['ok' => true, 'user' => currentUser()]); }
        else      echo json_encode(['ok' => false, 'error' => $res]);
        exit;
    }
    if ($action === 'request_reset') {
        createPasswordReset($_POST['email'] ?? '');
        echo json_encode(['ok' => true]); // sempre ok (anti-enumeration)
        exit;
    }
    if ($action === 'do_reset') {
        [$ok, $res] = resetPasswordWithToken($_POST['token'] ?? '', $_POST['password'] ?? '');
        echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $res]);
        exit;
    }
    if ($action === 'resend_verify') {
        if (isLoggedIn()) {
            $u = currentUser();
            if ($u) sendVerifyEmail($u['id'], $u['email']);
        } else {
            // Non loggato: reinvio solo con credenziali valide (anti-abuso/enumeration)
            $em = trim($_POST['email'] ?? ''); $pw = $_POST['password'] ?? '';
            if (validEmail($em) && is_string($pw) && $pw !== '') {
                try {
                    $st = pdo()->prepare('SELECT id, password_hash, email_verified::int AS ev FROM users WHERE lower(email)=lower(:e)');
                    $st->execute([':e' => $em]);
                    $u = $st->fetch();
                    if ($u && password_verify($pw, $u['password_hash']) && (int)$u['ev'] !== 1) {
                        sendVerifyEmail($u['id'], $em);
                    }
                } catch (Throwable $e) {}
            }
        }
        echo json_encode(['ok' => true]); // sempre ok (anti-enumeration)
        exit;
    }
    if ($action === 'logout_all') {
        if (!isLoggedIn()) { echo json_encode(['ok' => false, 'error' => 'auth']); exit; }
        try { pdo()->prepare('DELETE FROM auth_tokens WHERE user_id=:u')->execute([':u' => currentUserId()]); } catch (Throwable $e) {}
        logout();
        echo json_encode(['ok' => true]);
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

// ------- Verifica email via link (GET ?verify=) -------
if (isset($_GET['verify'])) {
    $ok = verifyEmailToken($_GET['verify']);
    header('Location: /account?verified=' . ($ok ? '1' : '0'));
    exit;
}

// ------- Pagina reset password (GET ?reset=TOKEN) -------
if (isset($_GET['reset'])) {
    $rl = function_exists('currentLang') ? currentLang() : 'it';
    $rde = $rl === 'de';
    $rcsrf = csrfToken();
    $rtoken = $_GET['reset'];
    ?><!DOCTYPE html><html lang="<?= htmlspecialchars($rl) ?>"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FuelFinder — <?= $rde ? 'Neues Passwort' : 'Nuova password' ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="img/logo.svg">
<link rel="stylesheet" href="/fonts/fonts.css"><link rel="stylesheet" href="style.css">
<style>html,body{height:auto;min-height:100%;overflow:auto}</style></head><body>
<div class="auth-page"><div class="auth-card">
    <a class="auth-logo" href="/"><img src="img/logo.svg" width="40" height="40" alt=""><span>Fuel<b>Finder</b></span></a>
    <form class="auth-form" id="resetForm">
        <input type="hidden" name="token" value="<?= htmlspecialchars($rtoken) ?>">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($rcsrf) ?>">
        <label><?= $rde ? 'Neues Passwort' : 'Nuova password' ?> <span class="auth-hint">(<?= $rde ? 'min. 8 Zeichen' : 'min. 8 caratteri' ?>)</span></label>
        <input type="password" name="password" minlength="8" autocomplete="new-password" required>
        <button type="submit"><?= $rde ? 'Passwort ändern' : 'Cambia password' ?></button>
        <div class="auth-msg" id="resetMsg"></div>
    </form>
    <a class="auth-back" href="/">&larr; <?= $rde ? 'Zurück' : 'Torna al sito' ?></a>
</div></div>
<script>
document.getElementById('resetForm').addEventListener('submit', function(e){
    e.preventDefault();
    var el=document.getElementById('resetMsg'); el.textContent='';
    var fd=new FormData(this); fd.append('action','do_reset');
    fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
        if(d.ok){ el.style.color='#10b981'; el.textContent=<?= json_encode($rde ? 'Passwort geändert. Du kannst dich jetzt anmelden.' : 'Password cambiata. Ora puoi accedere.') ?>; setTimeout(function(){location.href='/account';},1600); }
        else { el.style.color=''; el.textContent=<?= json_encode($rde ? 'Link ungültig oder abgelaufen.' : 'Link non valido o scaduto.') ?>; }
    }).catch(function(){ el.textContent='Errore'; });
});
</script></body></html><?php
    exit;
}

// ------- Vera schermata di login / registrazione (GET) -------
$lang = function_exists('currentLang') ? currentLang() : 'it';
$de   = $lang === 'de';
$next = $_GET['next'] ?? '/';
if (!is_string($next) || $next === '' || $next[0] !== '/' || strpos($next, '//') === 0) $next = '/';
$verifiedMsg = $_GET['verified'] ?? null; // '1' = email appena verificata, '0' = link non valido
$ssoError = isset($_GET['sso_error']) ? preg_replace('/[^a-z_]/', '', (string)$_GET['sso_error']) : null;
// /account?tab=register (vecchi link) → registrazione sull'account unico
if (($_GET['tab'] ?? '') === 'register' && oidcEnabled() && !isLoggedIn()) {
    header('Location: /oidc?start&register=1&next=' . urlencode($next)); exit;
}
$csrf = csrfToken();
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
.sso-err{margin:0 0 6px}
.legacy{margin-top:26px;padding-top:18px;border-top:1px solid var(--line)}
.legacy summary{cursor:pointer;font-size:.82rem;color:var(--muted);text-align:center;list-style:none}
.legacy summary::-webkit-details-marker{display:none}
.legacy summary:hover{color:var(--fg)}
.tabs{display:flex;background:var(--card);border:1px solid var(--line);border-radius:11px;padding:4px;margin-bottom:20px}
.tab{flex:1;padding:9px;border:none;background:none;color:var(--muted);border-radius:8px;cursor:pointer;font-family:inherit;font-weight:600;font-size:.86rem}
.tab.on{background:var(--accent);color:#04211a}
.form{display:flex;flex-direction:column;gap:7px}
.form.hide{display:none}
.form label{font-size:.75rem;color:var(--muted);margin-top:8px}
.hint{color:var(--faint)}
.form input[type=email],.form input[type=password]{background:var(--card);border:1px solid var(--line2);border-radius:10px;padding:12px 13px;color:var(--fg);font-family:inherit;font-size:.92rem;width:100%}
.form input:focus{outline:none;border-color:var(--accent)}
.check{display:flex;align-items:center;gap:8px;font-size:.8rem;color:var(--muted);margin-top:12px;cursor:pointer}
.check input{accent-color:var(--accent)}
.form button[type=submit]{margin-top:16px;background:var(--accent);color:#04211a;border:none;border-radius:10px;padding:13px;font-weight:700;font-family:inherit;font-size:.92rem;cursor:pointer}
.form button[type=submit]:hover{background:#34d399}
.msg{font-size:.82rem;min-height:16px;margin-top:12px;text-align:center;color:#f0a0a0}
.msg.ok{color:#69cf9b}
.msg a{color:var(--accent)}
.forgot{margin-top:12px;text-align:center}
.forgot a{font-size:.8rem;color:var(--muted);text-decoration:none}
.forgot a:hover{color:var(--accent)}
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
        ][$ssoError] ?? L('Accesso non riuscito. Riprova.','Anmeldung fehlgeschlagen. Bitte erneut versuchen.',$de)) ?></div><?php endif; ?>
        <a class="sso-btn" href="/oidc?start&amp;next=<?= urlencode($next) ?>"><?= L('Accedi con account fmenegazzi','Mit fmenegazzi-Konto anmelden',$de) ?></a>
        <a class="sso-btn alt" href="/oidc?start&amp;register=1&amp;next=<?= urlencode($next) ?>"><?= L('Crea un account gratuito','Kostenloses Konto erstellen',$de) ?></a>
        <p class="sso-note"><?= L('Un solo account per FuelFinder, SubTracker, FoodWaste e gli altri strumenti di','Ein Konto für FuelFinder, SubTracker, FoodWaste und die anderen Tools von',$de) ?> <a href="https://www.fmenegazzi.it">fmenegazzi.it</a>.</p>

        <details class="legacy"<?= ($verifiedMsg !== null || isset($_GET['legacy'])) ? ' open' : '' ?>>
        <summary><?= L('Hai un vecchio account FuelFinder con password?','Du hast ein altes FuelFinder-Konto mit Passwort?',$de) ?></summary>
        <p class="sso-note"><?= L('Se hai già usato l\'account fmenegazzi, la vecchia password non vale più: accedi con il pulsante qui sopra.','Wenn du schon das fmenegazzi-Konto genutzt hast, gilt das alte Passwort nicht mehr: melde dich oben an.',$de) ?></p>
        <form class="form" id="loginForm">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label><?= L('Email','E-Mail',$de) ?></label>
            <input type="email" name="email" autocomplete="email" required>
            <label>Password</label>
            <input type="password" name="password" autocomplete="current-password" required>
            <label class="check"><input type="checkbox" name="remember" value="1"> <?= L('Ricordami','Angemeldet bleiben',$de) ?></label>
            <button type="submit"><?= L('Accedi','Anmelden',$de) ?></button>
            <div class="msg<?= $verifiedMsg === '1' ? ' ok' : '' ?>" id="loginMsg"><?php
                if ($verifiedMsg === '1') echo L('✓ Email verificata! Ora puoi accedere.','✓ E-Mail bestätigt! Du kannst dich jetzt anmelden.',$de);
                elseif ($verifiedMsg === '0') echo L('Link di verifica non valido o scaduto.','Bestätigungslink ungültig oder abgelaufen.',$de);
            ?></div>
            <div class="forgot"><a href="#" id="forgotLink"><?= L('Password dimenticata?','Passwort vergessen?',$de) ?></a></div>
        </form>

        <form class="form hide" id="resetForm">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label><?= L('Inserisci la tua email','Gib deine E-Mail ein',$de) ?></label>
            <input type="email" name="email" autocomplete="email" required>
            <button type="submit"><?= L('Invia link di reset','Reset-Link senden',$de) ?></button>
            <div class="msg" id="resetMsg"></div>
            <div class="forgot"><a href="#" id="resetBack"><?= L('Torna al login','Zurück zur Anmeldung',$de) ?></a></div>
        </form>
        </details>

        <a class="back" href="/">&larr; <?= L('Torna al sito','Zurück zur Seite',$de) ?></a>
    </div>
</main>
<footer class="auth-foot"><a href="/privacy">Privacy</a> · <a href="/tos"><?= L('Termini','AGB',$de) ?></a></footer>
<script>
var NEXT = <?= json_encode($next) ?>;
var MSG = {
    csrf: <?= json_encode(L('Sessione scaduta, ricarica la pagina.','Sitzung abgelaufen, Seite neu laden.',$de)) ?>,
    invalid: <?= json_encode(L('Email o password non validi.','E-Mail oder Passwort ungültig.',$de)) ?>,
    rate_limited: <?= json_encode(L('Troppi tentativi, riprova più tardi.','Zu viele Versuche, später erneut.',$de)) ?>,
    email_invalid: <?= json_encode(L('Email non valida.','E-Mail ungültig.',$de)) ?>,
    password_short: <?= json_encode(L('Password troppo corta (min 8).','Passwort zu kurz (min. 8).',$de)) ?>,
    email_taken: <?= json_encode(L('Email già registrata.','E-Mail bereits registriert.',$de)) ?>,
    db_error: <?= json_encode(L('Errore temporaneo, riprova.','Temporärer Fehler, erneut versuchen.',$de)) ?>,
    unverified: <?= json_encode(L('Email non ancora verificata. Controlla la posta (anche spam).','E-Mail noch nicht bestätigt. Bitte Posteingang prüfen.',$de)) ?>,
    resend: <?= json_encode(L('Reinvia','Erneut senden',$de)) ?>,
    resent: <?= json_encode(L('✓ Email di verifica inviata.','✓ Bestätigungs-E-Mail gesendet.',$de)) ?>,
    verify_sent: <?= json_encode(L('✓ Ti abbiamo inviato un\'email di verifica. Confermala per accedere.','✓ Wir haben dir eine Bestätigungs-E-Mail geschickt. Bestätige sie, um dich anzumelden.',$de)) ?>,
    reset_sent: <?= json_encode(L('Se l\'email esiste, ti abbiamo inviato un link.','Falls die E-Mail existiert, wurde ein Link gesendet.',$de)) ?>
};
var lf=document.getElementById('loginForm'), zf=document.getElementById('resetForm');
function show(which){ lf.classList.toggle('hide',which!=='login'); zf.classList.toggle('hide',which!=='reset'); }
document.getElementById('forgotLink').addEventListener('click',function(e){e.preventDefault();show('reset');});
document.getElementById('resetBack').addEventListener('click',function(e){e.preventDefault();show('login');});

function post(form, action){ var fd=new FormData(form); fd.append('action',action); return fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}); }

lf.addEventListener('submit',function(e){ e.preventDefault(); var el=document.getElementById('loginMsg'); el.className='msg'; el.innerHTML='';
    post(lf,'login').then(function(d){
        if(d.ok){ window.location.href=NEXT; return; }
        if(d.error==='unverified'){ el.innerHTML=MSG.unverified+' <a href="#" id="rs">'+MSG.resend+'</a>';
            document.getElementById('rs').addEventListener('click',function(ev){ev.preventDefault();
                var fd=new FormData(); fd.append('action','resend_verify'); fd.append('csrf',<?= json_encode($csrf) ?>); fd.append('email',lf.email.value); fd.append('password',lf.password.value);
                fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(){el.className='msg ok';el.textContent=MSG.resent;}); });
        } else { el.textContent=MSG[d.error]||MSG.db_error; }
    }).catch(function(){el.textContent=MSG.db_error;});
});
zf.addEventListener('submit',function(e){ e.preventDefault(); var el=document.getElementById('resetMsg'); el.className='msg';
    post(zf,'request_reset').then(function(){ el.className='msg ok'; el.textContent=MSG.reset_sent; }).catch(function(){el.textContent=MSG.db_error;});
});
</script>
</body>
</html>
