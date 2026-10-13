<?php
require_once __DIR__ . '/includes/config.php'; // bootstrap db/metrics/auth + sessione
require_once __DIR__ . '/includes/i18n.php';

// Pagina riservata: senza login si torna alla schermata di accesso.
if (!isLoggedIn()) { header('Location: /account?next=' . urlencode('/profile')); exit; }

$user = currentUser();
if (!$user) { header('Location: /account?next=' . urlencode('/profile')); exit; }

$lang = function_exists('currentLang') ? currentLang() : 'it';
$de   = $lang === 'de';
$csrf = csrfToken();
function L($it, $deTxt, $de) { return $de ? $deTxt : $it; }

// Dati extra: registrazione, ultimo accesso, n. veicoli, n. dispositivi "ricordami"
$created = $lastLogin = null; $nVehicles = 0; $nDevices = 0;
try {
    $st = pdo()->prepare('SELECT created_at, last_login FROM users WHERE id=:id');
    $st->execute([':id' => $user['id']]);
    if ($row = $st->fetch()) { $created = $row['created_at']; $lastLogin = $row['last_login']; }
    $st = pdo()->prepare('SELECT count(*) FROM vehicles WHERE user_id=:id');
    $st->execute([':id' => $user['id']]);
    $nVehicles = (int)$st->fetchColumn();
    $st = pdo()->prepare('SELECT count(*) FROM auth_tokens WHERE user_id=:id AND expires_at > now()');
    $st->execute([':id' => $user['id']]);
    $nDevices = (int)$st->fetchColumn();
} catch (Throwable $e) {}

function fmtDate($ts, $de) {
    if (!$ts) return '—';
    $t = strtotime($ts);
    return $t ? date($de ? 'd.m.Y H:i' : 'd/m/Y H:i', $t) : '—';
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FuelFinder — <?= L('Il tuo account','Dein Konto',$de) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="img/logo.svg">
<link rel="stylesheet" href="/fonts/fonts.css">
<style>
:root{--bg:#0b1220;--fg:#f1f5f9;--muted:#94a3b8;--faint:#64748b;--line:#1e293b;--line2:#2c3a50;--card:#111a2b;--accent:#10b981;--accent2:#34d399;--on-accent:#04211a;--red:#f87171}
*{box-sizing:border-box}
html,body{height:auto;min-height:100%;margin:0}
body{background:var(--bg);color:var(--fg);font-family:'Inter',system-ui,sans-serif;-webkit-font-smoothing:antialiased;min-height:100vh;line-height:1.55}
.wrap{max-width:560px;margin:0 auto;padding:36px 20px 60px}
.brand{display:flex;align-items:center;gap:10px;text-decoration:none;color:var(--fg);font-weight:700;font-size:1.15rem;letter-spacing:-.01em}
.brand b{color:var(--accent)}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:28px}
.topbar .back{color:var(--muted);text-decoration:none;font-size:.82rem}
.topbar .back:hover{color:var(--fg)}
h1{font-size:1.35rem;letter-spacing:-.02em;margin:0 0 22px}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:18px}
.card h2{font-family:'JetBrains Mono',monospace;font-size:.7rem;font-weight:600;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);margin:0 0 16px}
.kv{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:9px 0;border-bottom:1px solid var(--line);font-size:.88rem}
.kv:last-child{border-bottom:none}
.kv .k{color:var(--muted)}
.kv .v{text-align:right;word-break:break-all}
.kv .v.mono{font-family:'JetBrains Mono',monospace;font-size:.82rem}
.badge{display:inline-block;font-family:'JetBrains Mono',monospace;font-size:.62rem;font-weight:700;letter-spacing:.05em;padding:3px 9px;border-radius:6px}
.badge.ok{color:var(--accent2);background:rgba(16,185,129,.12);border:1px solid rgba(16,185,129,.35)}
.badge.warn{color:#fbbf24;background:rgba(251,191,36,.1);border:1px solid rgba(251,191,36,.3)}
.badge.admin{color:#60a5fa;background:rgba(96,165,250,.1);border:1px solid rgba(96,165,250,.3)}
form{display:flex;flex-direction:column;gap:7px}
label{font-size:.75rem;color:var(--muted);margin-top:8px}
.hint{color:var(--faint)}
input[type=password]{background:var(--bg);border:1px solid var(--line2);border-radius:10px;padding:12px 13px;color:var(--fg);font-family:inherit;font-size:.92rem;width:100%}
input:focus{outline:none;border-color:var(--accent)}
button{font-family:inherit;cursor:pointer;border-radius:10px;font-size:.88rem;font-weight:600;border:none;padding:12px 16px;transition:background .2s,border-color .2s,color .2s}
.btn-primary{margin-top:14px;background:var(--accent);color:var(--on-accent);font-weight:700}
.btn-primary:hover{background:var(--accent2)}
.btn-line{background:none;border:1px solid var(--line2);color:var(--fg)}
.btn-line:hover{border-color:var(--muted)}
.btn-danger{margin-top:14px;background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:var(--red);font-weight:700}
.btn-danger:hover{background:rgba(239,68,68,.2)}
.row-btns{display:flex;gap:10px;flex-wrap:wrap}
.msg{font-size:.82rem;min-height:16px;margin-top:10px;color:#f0a0a0}
.msg.ok{color:var(--accent2)}
.danger-card{border-color:rgba(239,68,68,.3)}
.danger-card h2{color:var(--red)}
.danger-note{font-size:.8rem;color:var(--muted);margin:0 0 6px}
.sess-note{font-size:.8rem;color:var(--muted);margin:0 0 12px}
.foot{text-align:center;font-size:.76rem;color:var(--faint);margin-top:8px}
.foot a{color:var(--muted);text-decoration:none}.foot a:hover{color:var(--accent)}
</style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <a class="brand" href="/"><img src="img/logo.svg" width="28" height="28" alt="">Fuel<b>Finder</b></a>
        <a class="back" href="/">&larr; <?= L('Torna all\'app','Zurück zur App',$de) ?></a>
    </div>

    <h1><?= L('Il tuo account','Dein Konto',$de) ?></h1>

    <div class="card">
        <h2><?= L('Profilo','Profil',$de) ?></h2>
        <div class="kv"><span class="k">Email</span><span class="v mono"><?= htmlspecialchars($user['email']) ?></span></div>
        <div class="kv"><span class="k"><?= L('Stato','Status',$de) ?></span><span class="v">
            <?php if ((int)$user['email_verified'] === 1): ?><span class="badge ok"><?= L('VERIFICATA','VERIFIZIERT',$de) ?></span>
            <?php else: ?><span class="badge warn"><?= L('NON VERIFICATA','NICHT VERIFIZIERT',$de) ?></span><?php endif; ?>
            <?php if ((int)$user['is_admin'] === 1): ?> <span class="badge admin">ADMIN</span><?php endif; ?>
        </span></div>
        <div class="kv"><span class="k"><?= L('Registrato il','Registriert am',$de) ?></span><span class="v mono"><?= fmtDate($created, $de) ?></span></div>
        <div class="kv"><span class="k"><?= L('Ultimo accesso','Letzte Anmeldung',$de) ?></span><span class="v mono"><?= fmtDate($lastLogin, $de) ?></span></div>
        <div class="kv"><span class="k"><?= L('Veicoli in garage','Fahrzeuge in der Garage',$de) ?></span><span class="v mono"><?= $nVehicles ?></span></div>
    </div>

    <div class="card">
        <h2><?= L('Cambia password','Passwort ändern',$de) ?></h2>
        <form id="pwForm" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label><?= L('Password attuale','Aktuelles Passwort',$de) ?></label>
            <input type="password" name="current" autocomplete="current-password" required>
            <label><?= L('Nuova password','Neues Passwort',$de) ?> <span class="hint">(<?= L('min. 8 caratteri','min. 8 Zeichen',$de) ?>)</span></label>
            <input type="password" name="password" autocomplete="new-password" minlength="8" required>
            <label><?= L('Conferma nuova password','Neues Passwort bestätigen',$de) ?></label>
            <input type="password" name="confirm" autocomplete="new-password" minlength="8" required>
            <button type="submit" class="btn-primary"><?= L('Aggiorna password','Passwort aktualisieren',$de) ?></button>
            <div class="msg" id="pwMsg"></div>
        </form>
    </div>

    <div class="card">
        <h2><?= L('Sessioni e dispositivi','Sitzungen & Geräte',$de) ?></h2>
        <p class="sess-note"><?= $nDevices > 0
            ? L('Hai <b>' . $nDevices . '</b> dispositivi con accesso "ricordami" attivo.','Du hast <b>' . $nDevices . '</b> Geräte mit aktivem „Angemeldet bleiben".',$de)
            : L('Nessun dispositivo con accesso "ricordami" attivo.','Keine Geräte mit aktivem „Angemeldet bleiben".',$de) ?></p>
        <div class="row-btns">
            <button type="button" class="btn-line" id="logoutBtn"><?= L('Esci','Abmelden',$de) ?></button>
            <button type="button" class="btn-line" id="logoutAllBtn"><?= L('Disconnetti tutti i dispositivi','Alle Geräte abmelden',$de) ?></button>
        </div>
        <div class="msg" id="sessMsg"></div>
    </div>

    <div class="card danger-card">
        <h2><?= L('Zona pericolosa','Gefahrenzone',$de) ?></h2>
        <p class="danger-note"><?= L('L\'eliminazione è definitiva: account, garage e dati associati vengono cancellati subito e non sono recuperabili.','Die Löschung ist endgültig: Konto, Garage und zugehörige Daten werden sofort und unwiderruflich gelöscht.',$de) ?></p>
        <form id="delForm">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label><?= L('Conferma con la tua password','Mit deinem Passwort bestätigen',$de) ?></label>
            <input type="password" name="password" autocomplete="current-password" required>
            <button type="submit" class="btn-danger"><?= L('Elimina definitivamente l\'account','Konto endgültig löschen',$de) ?></button>
            <div class="msg" id="delMsg"></div>
        </form>
    </div>

    <div class="foot"><a href="/privacy">Privacy</a> · <a href="/tos"><?= L('Termini','AGB',$de) ?></a></div>
</div>
<script>
var MSG = {
    csrf: <?= json_encode(L('Sessione scaduta, ricarica la pagina.','Sitzung abgelaufen, Seite neu laden.',$de)) ?>,
    wrong_password: <?= json_encode(L('Password attuale non corretta.','Aktuelles Passwort falsch.',$de)) ?>,
    password_short: <?= json_encode(L('Password troppo corta (min 8).','Passwort zu kurz (min. 8).',$de)) ?>,
    mismatch: <?= json_encode(L('Le nuove password non coincidono.','Die neuen Passwörter stimmen nicht überein.',$de)) ?>,
    db_error: <?= json_encode(L('Errore temporaneo, riprova.','Temporärer Fehler, erneut versuchen.',$de)) ?>,
    pw_ok: <?= json_encode(L('✓ Password aggiornata. Gli altri dispositivi dovranno riaccedere.','✓ Passwort aktualisiert. Andere Geräte müssen sich neu anmelden.',$de)) ?>,
    del_confirm: <?= json_encode(L('Eliminare definitivamente account e tutti i dati? L\'operazione non è reversibile.','Konto und alle Daten endgültig löschen? Dies kann nicht rückgängig gemacht werden.',$de)) ?>
};
function post(fd){ return fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}); }

var pwForm = document.getElementById('pwForm');
pwForm.addEventListener('submit', function(e){
    e.preventDefault();
    var el = document.getElementById('pwMsg'); el.className = 'msg'; el.textContent = '';
    if (pwForm.password.value !== pwForm.confirm.value) { el.textContent = MSG.mismatch; return; }
    var fd = new FormData(pwForm); fd.append('action','change_password');
    post(fd).then(function(d){
        if (d.ok) { el.className = 'msg ok'; el.textContent = MSG.pw_ok; pwForm.reset(); }
        else { el.textContent = MSG[d.error] || MSG.db_error; }
    }).catch(function(){ el.textContent = MSG.db_error; });
});

document.getElementById('logoutBtn').addEventListener('click', function(){
    var fd = new FormData(); fd.append('action','logout'); fd.append('csrf',<?= json_encode($csrf) ?>);
    post(fd).then(function(){ location.href = '/'; });
});
document.getElementById('logoutAllBtn').addEventListener('click', function(){
    var fd = new FormData(); fd.append('action','logout_all'); fd.append('csrf',<?= json_encode($csrf) ?>);
    post(fd).then(function(){ location.href = '/'; });
});

var delForm = document.getElementById('delForm');
delForm.addEventListener('submit', function(e){
    e.preventDefault();
    if (!confirm(MSG.del_confirm)) return;
    var el = document.getElementById('delMsg'); el.className = 'msg'; el.textContent = '';
    var fd = new FormData(delForm); fd.append('action','delete_account');
    post(fd).then(function(d){
        if (d.ok) { location.href = '/'; }
        else { el.textContent = MSG[d.error] || MSG.db_error; }
    }).catch(function(){ el.textContent = MSG.db_error; });
});
</script>
</body>
</html>
