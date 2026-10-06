<?php
require_once __DIR__ . '/includes/config.php'; // bootstrap db/metrics/auth + sessione
require_once __DIR__ . '/includes/i18n.php';
// "I miei dati FuelFinder": profilo, email, password e sicurezza sono sull'account unico fmenegazzi
// (ACCOUNT_PAGE_URL); qui restano solo i dati del tool.
if (!isLoggedIn()) { header('Location: /account?next=' . urlencode('/profile')); exit; }
$user = currentUser();
$lang = function_exists('currentLang') ? currentLang() : 'it';
$de   = $lang === 'de';
$csrf = csrfToken();
function L($it, $deTxt, $de) { return $de ? $deTxt : $it; }
$created = $lastLogin = null; $nVehicles = 0;
try {
    $st = pdo()->prepare('SELECT created_at, last_login FROM users WHERE id=:id');
    $st->execute([':id' => $user['id']]);
    if ($row = $st->fetch()) { $created = $row['created_at']; $lastLogin = $row['last_login']; }
    $st = pdo()->prepare('SELECT count(*) FROM vehicles WHERE user_id=:id');
    $st->execute([':id' => $user['id']]);
    $nVehicles = (int)$st->fetchColumn();
} catch (Throwable $e) {}
function fmtDate($ts, $de) {
    if (!$ts) return '—';
    $t = strtotime($ts);
    return $t ? date($de ? 'd.m.Y H:i' : 'd/m/Y H:i', $t) : '—';
}
$linked = (int)($user['linked'] ?? 0) === 1;
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FuelFinder — <?= L('I miei dati','Meine Daten',$de) ?></title>
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
input[type=password],input[type=text]{background:var(--bg);border:1px solid var(--line2);border-radius:10px;padding:12px 13px;color:var(--fg);font-family:inherit;font-size:.92rem;width:100%}
input:focus{outline:none;border-color:var(--accent)}
button,.btn-primary,.btn-line{font-family:inherit;cursor:pointer;border-radius:10px;font-size:.88rem;font-weight:600;border:none;padding:12px 16px;transition:background .2s,border-color .2s,color .2s}
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

.lead{color:var(--muted);font-size:.88rem;margin:0 0 16px}
.btn-primary,.btn-line{display:inline-block;text-decoration:none;text-align:center}
.notice{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.35);border-radius:10px;padding:12px 14px;font-size:.85rem;color:#fcd34d;margin:0 0 14px}
</style>
</head>
<body>
<div class="wrap">
    <div class="topbar">
        <a class="brand" href="/"><img src="img/logo.svg" width="28" height="28" alt="">Fuel<b>Finder</b></a>
        <a class="back" href="/">&larr; <?= L('Torna all\'app','Zurück zur App',$de) ?></a>
    </div>

    <h1><?= L('I miei dati FuelFinder','Meine FuelFinder-Daten',$de) ?></h1>

    <div class="card">
        <h2><?= L('Account fmenegazzi','fmenegazzi-Konto',$de) ?></h2>
        <div class="kv"><span class="k">Email</span><span class="v mono"><?= htmlspecialchars($user['email']) ?></span></div>
        <?php if ($linked): ?>
            <p class="lead" style="margin-top:14px"><?= L('Profilo, email, password e verifica in due passaggi si gestiscono sul tuo account fmenegazzi, valido per tutti gli strumenti.','Profil, E-Mail, Passwort und Zwei-Faktor-Anmeldung verwaltest du in deinem fmenegazzi-Konto, gültig für alle Tools.',$de) ?></p>
            <a class="btn-primary" href="<?= htmlspecialchars(ACCOUNT_PAGE_URL) ?>"><?= L('Gestisci il mio account','Mein Konto verwalten',$de) ?> &rarr;</a>
        <?php else: ?>
            <p class="notice" style="margin-top:14px"><?= L('Il tuo account FuelFinder non è ancora collegato all\'account unico fmenegazzi. Collegalo con la stessa email: garage e dati restano tuoi.','Dein FuelFinder-Konto ist noch nicht mit dem fmenegazzi-Konto verknüpft. Verknüpfe es mit derselben E-Mail: Garage und Daten bleiben erhalten.',$de) ?></p>
            <a class="btn-primary" href="/oidc?start&amp;force=1&amp;next=%2Fprofile"><?= L('Collega ora','Jetzt verknüpfen',$de) ?></a>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2><?= L('Dati FuelFinder','FuelFinder-Daten',$de) ?></h2>
        <div class="kv"><span class="k"><?= L('Registrato il','Registriert am',$de) ?></span><span class="v mono"><?= fmtDate($created, $de) ?></span></div>
        <div class="kv"><span class="k"><?= L('Ultimo accesso','Letzte Anmeldung',$de) ?></span><span class="v mono"><?= fmtDate($lastLogin, $de) ?></span></div>
        <div class="kv"><span class="k"><?= L('Veicoli in garage','Fahrzeuge in der Garage',$de) ?></span><span class="v mono"><?= $nVehicles ?></span></div>
        <?php if ((int)$user['is_admin'] === 1): ?>
        <div class="kv"><span class="k"><?= L('Ruolo','Rolle',$de) ?></span><span class="v"><span class="badge admin">ADMIN</span> <a href="/stats" style="color:var(--accent2)"><?= L('Metriche','Statistiken',$de) ?> &rarr;</a></span></div>
        <?php endif; ?>
        <div class="row-btns" style="margin-top:14px">
            <a class="btn-line" href="/oidc?logout"><?= L('Esci','Abmelden',$de) ?></a>
        </div>
    </div>

    <div class="card danger-card">
        <h2><?= L('Zona pericolosa','Gefahrenzone',$de) ?></h2>
        <p class="danger-note"><?= L('Cancella il tuo account FuelFinder e il garage, subito e senza possibilità di recupero. Il tuo account fmenegazzi e gli altri strumenti non vengono toccati.','Löscht dein FuelFinder-Konto und die Garage sofort und unwiderruflich. Dein fmenegazzi-Konto und die anderen Tools bleiben unberührt.',$de) ?></p>
        <form id="delForm">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <label><?= L('Scrivi ELIMINA per confermare','Zum Bestätigen LÖSCHEN eingeben',$de) ?></label>
            <input type="text" name="confirm" autocomplete="off" required>
            <button type="submit" class="btn-danger"><?= L('Elimina i miei dati FuelFinder','Meine FuelFinder-Daten löschen',$de) ?></button>
            <div class="msg" id="delMsg"></div>
        </form>
    </div>

    <div class="foot"><a href="/privacy">Privacy</a> · <a href="/tos"><?= L('Termini','AGB',$de) ?></a></div>
</div>
<script>
var MSG = {
    csrf: <?= json_encode(L('Sessione scaduta, ricarica la pagina.','Sitzung abgelaufen, Seite neu laden.',$de)) ?>,
    confirm: <?= json_encode(L('Scrivi ELIMINA per confermare.','Gib LÖSCHEN zum Bestätigen ein.',$de)) ?>,
    db_error: <?= json_encode(L('Errore temporaneo, riprova.','Temporärer Fehler, erneut versuchen.',$de)) ?>
};
var delForm = document.getElementById('delForm');
delForm.addEventListener('submit', function(e){
    e.preventDefault();
    var el = document.getElementById('delMsg'); el.className = 'msg'; el.textContent = '';
    var fd = new FormData(delForm); fd.append('action','delete_account');
    fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
        // Esce anche dall'account unico: altrimenti il rientro automatico ricreerebbe un account vuoto
        if (d.ok) { location.href = '/oidc?logout'; }
        else { el.textContent = MSG[d.error] || MSG.db_error; }
    }).catch(function(){ el.textContent = MSG.db_error; });
});
</script>
</body>
</html>
