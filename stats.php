<?php
require_once __DIR__ . '/includes/config.php'; // bootstrap db/metrics/auth + sessione
requireAdmin(); // redirect a /account se non admin

function q($sql, $params = []) {
    try { $st = pdo()->prepare($sql); $st->execute($params); return $st->fetchAll(); }
    catch (Throwable $e) { return []; }
}
function scalar($sql, $params = []) {
    try { $st = pdo()->prepare($sql); $st->execute($params); return $st->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}
function maskEmail($e) {
    $p = explode('@', $e);
    if (count($p) !== 2) return '***';
    $u = $p[0];
    $vis = mb_substr($u, 0, 2);
    return $vis . str_repeat('*', max(1, mb_strlen($u) - 2)) . '@' . $p[1];
}

// ---- KPI ----
$kpi = [
    'pageviews'  => (int)scalar("SELECT count(*) FROM events WHERE type='pageview'"),
    'visitors'   => (int)scalar("SELECT count(DISTINCT visitor_hash) FROM events"),
    'searches'   => (int)scalar("SELECT count(*) FROM events WHERE type IN ('search','sos')"),
    'routes'     => (int)scalar("SELECT count(*) FROM events WHERE type='route'"),
    'users'      => (int)scalar("SELECT count(*) FROM users"),
    'vehicles'   => (int)scalar("SELECT count(*) FROM vehicles"),
    'signups30'  => (int)scalar("SELECT count(*) FROM users WHERE created_at > now() - interval '30 days'"),
];
// engagement utenti registrati
$dau = (int)scalar("SELECT count(DISTINCT user_id) FROM events WHERE user_id IS NOT NULL AND ts > now() - interval '1 day'");
$wau = (int)scalar("SELECT count(DISTINCT user_id) FROM events WHERE user_id IS NOT NULL AND ts > now() - interval '7 days'");
$mau = (int)scalar("SELECT count(DISTINCT user_id) FROM events WHERE user_id IS NOT NULL AND ts > now() - interval '30 days'");

// funnel 30 giorni
$fVisitors = (int)scalar("SELECT count(DISTINCT visitor_hash) FROM events WHERE ts > now() - interval '30 days'");
$fSearchers= (int)scalar("SELECT count(DISTINCT visitor_hash) FROM events WHERE type IN ('search','sos') AND ts > now() - interval '30 days'");
$fSignups  = (int)scalar("SELECT count(*) FROM users WHERE created_at > now() - interval '30 days'");

// ---- Serie temporale 30 giorni ----
$series = q("
    SELECT to_char(d, 'YYYY-MM-DD') AS day,
           count(e.id) FILTER (WHERE e.type='pageview')          AS pv,
           count(DISTINCT e.visitor_hash)                        AS uniq,
           count(e.id) FILTER (WHERE e.type IN ('search','sos'))  AS searches
    FROM generate_series(current_date - interval '29 days', current_date, interval '1 day') d
    LEFT JOIN events e ON e.ts::date = d::date
    GROUP BY d ORDER BY d
");

// ---- Breakdown ----
$byFuel    = q("SELECT coalesce(fuel,'?') k, count(*) c FROM events WHERE type IN ('search','sos') AND fuel IS NOT NULL GROUP BY 1 ORDER BY c DESC");
$byCountry = q("SELECT coalesce(country,'?') k, count(*) c FROM events WHERE type IN ('search','sos','route') AND country IS NOT NULL GROUP BY 1 ORDER BY c DESC");
$byRadius  = q("SELECT radius::text k, count(*) c FROM events WHERE type='search' AND radius IS NOT NULL GROUP BY radius ORDER BY radius");
$byMode    = q("SELECT coalesce(mode,'?') k, count(*) c FROM events WHERE type='search' AND mode IS NOT NULL GROUP BY 1 ORDER BY c DESC");
$byDevice  = q("SELECT coalesce(ua_device,'?') k, count(*) c FROM events WHERE type='pageview' GROUP BY 1 ORDER BY c DESC");
$byBrowser = q("SELECT coalesce(ua_browser,'?') k, count(*) c FROM events WHERE type='pageview' GROUP BY 1 ORDER BY c DESC");
$byOs      = q("SELECT coalesce(ua_os,'?') k, count(*) c FROM events WHERE type='pageview' GROUP BY 1 ORDER BY c DESC");
$byRef     = q("SELECT coalesce(referrer_host,'(diretto)') k, count(*) c FROM events WHERE type='pageview' GROUP BY 1 ORDER BY c DESC LIMIT 10");
$byType    = q("SELECT type k, count(*) c FROM events GROUP BY type ORDER BY c DESC");
$recentUsers = q("SELECT email, to_char(created_at,'DD/MM/YYYY HH24:MI') created, to_char(last_login,'DD/MM/YYYY HH24:MI') last_login FROM users ORDER BY created_at DESC LIMIT 25");

function col($rows, $key) { return array_map(fn($r) => $r[$key], $rows); }
$convSearch = $fVisitors > 0 ? round(100 * $fSearchers / $fVisitors, 1) : 0;
$convSignup = $fVisitors > 0 ? round(100 * $fSignups / $fVisitors, 1) : 0;
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FuelFinder — Metriche</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="img/logo.svg">
<script src="/libs/chart.umd.min.js"></script>
<style>
  :root{--bg:#0d0d1a;--card:#131c2e;--card2:#1b2638;--line:#2c3a50;--txt:#f1f5f9;--muted:#94a3b8;--accent:#10b981;--green:#10b981;--blue:#3b82f6;--violet:#8b5cf6}
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--txt);font-family:Inter,system-ui,sans-serif;padding:24px;max-width:1200px;margin:0 auto}
  h1{font-size:22px;margin:0} h2{font-size:15px;color:var(--muted);margin:28px 0 12px;text-transform:uppercase;letter-spacing:.5px}
  .top{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
  .top .logo{display:flex;align-items:center;gap:10px} .top a{color:var(--muted);text-decoration:none;font-size:13px}
  .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
  .kpi{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px}
  .kpi .v{font-size:28px;font-weight:700} .kpi .l{font-size:12px;color:var(--muted);margin-top:4px}
  .kpi .s{font-size:11px;color:var(--green);margin-top:2px}
  .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px} .grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
  @media(max-width:820px){.grid2,.grid3{grid-template-columns:1fr}}
  .panel{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:16px}
  .panel h3{margin:0 0 12px;font-size:14px}
  canvas{max-height:280px}
  .funnel{display:flex;gap:10px;align-items:flex-end;height:140px}
  .funnel .bar{flex:1;background:linear-gradient(180deg,var(--accent),#059669);border-radius:8px 8px 0 0;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;padding:8px;min-height:24px}
  .funnel .bar small{color:#04211a;font-weight:700;font-size:13px}
  .funnel .lbl{font-size:11px;color:var(--muted);text-align:center;margin-top:6px}
  table{width:100%;border-collapse:collapse;font-size:13px} th,td{text-align:left;padding:8px 6px;border-bottom:1px solid var(--line)} th{color:var(--muted);font-weight:500}
  .note{font-size:11px;color:var(--muted);margin-top:6px}
</style>
</head>
<body>
<div class="top">
  <div class="logo"><img src="img/logo.svg" width="32" height="32" alt=""><h1>FuelFinder · Metriche</h1></div>
  <div><a href="/">← App</a> &nbsp;·&nbsp; <a href="#" id="logoutLink">Logout</a></div>
</div>

<h2>Panoramica</h2>
<div class="cards">
  <div class="kpi"><div class="v"><?= number_format($kpi['pageviews']) ?></div><div class="l">Pageview totali</div></div>
  <div class="kpi"><div class="v"><?= number_format($kpi['visitors']) ?></div><div class="l">Visitatori unici*</div></div>
  <div class="kpi"><div class="v"><?= number_format($kpi['searches']) ?></div><div class="l">Ricerche</div></div>
  <div class="kpi"><div class="v"><?= number_format($kpi['routes']) ?></div><div class="l">Rotte calcolate</div></div>
  <div class="kpi"><div class="v"><?= number_format($kpi['users']) ?></div><div class="l">Utenti registrati</div><div class="s">+<?= $kpi['signups30'] ?> (30gg)</div></div>
  <div class="kpi"><div class="v"><?= number_format($kpi['vehicles']) ?></div><div class="l">Veicoli salvati</div></div>
</div>

<h2>Engagement utenti registrati</h2>
<div class="cards">
  <div class="kpi"><div class="v"><?= $dau ?></div><div class="l">DAU (24h)</div></div>
  <div class="kpi"><div class="v"><?= $wau ?></div><div class="l">WAU (7gg)</div></div>
  <div class="kpi"><div class="v"><?= $mau ?></div><div class="l">MAU (30gg)</div></div>
  <div class="kpi"><div class="v"><?= $convSearch ?>%</div><div class="l">Visitatori → ricerca (30gg)</div></div>
  <div class="kpi"><div class="v"><?= $convSignup ?>%</div><div class="l">Visitatori → registrazione (30gg)</div></div>
</div>

<h2>Traffico (ultimi 30 giorni)</h2>
<div class="panel"><canvas id="cTraffic"></canvas></div>

<h2>Comportamento</h2>
<div class="grid3">
  <div class="panel"><h3>Funnel 30gg</h3>
    <div class="funnel">
      <div style="flex:1"><div class="bar" style="height:100%"><small><?= $fVisitors ?></small></div><div class="lbl">Visitatori</div></div>
      <div style="flex:1"><div class="bar" style="height:<?= $fVisitors? max(18,round(100*$fSearchers/max($fVisitors,1))):0 ?>%"><small><?= $fSearchers ?></small></div><div class="lbl">Hanno cercato</div></div>
      <div style="flex:1"><div class="bar" style="height:<?= $fVisitors? max(12,round(100*$fSignups/max($fVisitors,1))):0 ?>%"><small><?= $fSignups ?></small></div><div class="lbl">Registrati</div></div>
    </div>
  </div>
  <div class="panel"><h3>Carburante cercato</h3><canvas id="cFuel"></canvas></div>
  <div class="panel"><h3>Paese</h3><canvas id="cCountry"></canvas></div>
</div>

<div class="grid3" style="margin-top:16px">
  <div class="panel"><h3>Raggio (km)</h3><canvas id="cRadius"></canvas></div>
  <div class="panel"><h3>Modalità</h3><canvas id="cMode"></canvas></div>
  <div class="panel"><h3>Eventi per tipo</h3><canvas id="cType"></canvas></div>
</div>

<h2>Pubblico</h2>
<div class="grid3">
  <div class="panel"><h3>Dispositivo</h3><canvas id="cDevice"></canvas></div>
  <div class="panel"><h3>Browser</h3><canvas id="cBrowser"></canvas></div>
  <div class="panel"><h3>Sistema operativo</h3><canvas id="cOs"></canvas></div>
</div>

<div class="grid2" style="margin-top:16px">
  <div class="panel"><h3>Sorgenti (referrer)</h3>
    <table><tr><th>Host</th><th style="text-align:right">Pageview</th></tr>
    <?php foreach ($byRef as $r): ?><tr><td><?= htmlspecialchars($r['k']) ?></td><td style="text-align:right"><?= number_format($r['c']) ?></td></tr><?php endforeach; ?>
    <?php if (!$byRef): ?><tr><td colspan="2" class="note">Nessun dato</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="panel"><h3>Ultimi utenti registrati</h3>
    <table><tr><th>Email</th><th>Registrato</th><th>Ultimo accesso</th></tr>
    <?php foreach ($recentUsers as $u): ?><tr><td><?= htmlspecialchars(maskEmail($u['email'])) ?></td><td><?= htmlspecialchars($u['created']) ?></td><td><?= htmlspecialchars($u['last_login'] ?? '—') ?></td></tr><?php endforeach; ?>
    <?php if (!$recentUsers): ?><tr><td colspan="3" class="note">Nessun utente</td></tr><?php endif; ?>
    </table>
  </div>
</div>

<p class="note">* Visitatori unici: conteggio su hash anonimo con salt giornaliero (privacy-friendly, stile Plausible). Il salt ruota ogni giorno, quindi il totale all-time è la somma degli unici giornalieri. Nessun IP grezzo è memorizzato.</p>

<script>
const PALETTE=['#10b981','#3b82f6','#10b981','#8b5cf6','#f59e0b','#db61a2','#56d4dd','#8b949e'];
const S=<?= json_encode([
  'days'=>col($series,'day'),'pv'=>array_map('intval',col($series,'pv')),'uniq'=>array_map('intval',col($series,'uniq')),'searches'=>array_map('intval',col($series,'searches')),
  'fuel'=>['l'=>col($byFuel,'k'),'c'=>array_map('intval',col($byFuel,'c'))],
  'country'=>['l'=>col($byCountry,'k'),'c'=>array_map('intval',col($byCountry,'c'))],
  'radius'=>['l'=>col($byRadius,'k'),'c'=>array_map('intval',col($byRadius,'c'))],
  'mode'=>['l'=>col($byMode,'k'),'c'=>array_map('intval',col($byMode,'c'))],
  'type'=>['l'=>col($byType,'k'),'c'=>array_map('intval',col($byType,'c'))],
  'device'=>['l'=>col($byDevice,'k'),'c'=>array_map('intval',col($byDevice,'c'))],
  'browser'=>['l'=>col($byBrowser,'k'),'c'=>array_map('intval',col($byBrowser,'c'))],
  'os'=>['l'=>col($byOs,'k'),'c'=>array_map('intval',col($byOs,'c'))],
], JSON_UNESCAPED_UNICODE) ?>;
Chart.defaults.color='#94a3b8'; Chart.defaults.borderColor='#2c3a50'; Chart.defaults.font.family='Inter,sans-serif';
const noLeg={plugins:{legend:{display:false}}};
new Chart(cTraffic,{type:'line',data:{labels:S.days,datasets:[
  {label:'Pageview',data:S.pv,borderColor:'#10b981',backgroundColor:'rgba(16,185,129,.1)',tension:.3,fill:true},
  {label:'Visitatori unici',data:S.uniq,borderColor:'#3b82f6',tension:.3},
  {label:'Ricerche',data:S.searches,borderColor:'#10b981',tension:.3}]},options:{responsive:true,interaction:{mode:'index'}}});
function bar(id,d){new Chart(id,{type:'bar',data:{labels:d.l,datasets:[{data:d.c,backgroundColor:PALETTE}]},options:noLeg});}
function pie(id,d){new Chart(id,{type:'doughnut',data:{labels:d.l,datasets:[{data:d.c,backgroundColor:PALETTE}]}});}
bar('cFuel',S.fuel); pie('cCountry',S.country); bar('cRadius',S.radius); pie('cMode',S.mode); bar('cType',S.type);
pie('cDevice',S.device); pie('cBrowser',S.browser); pie('cOs',S.os);
document.getElementById('logoutLink').addEventListener('click',function(e){
  e.preventDefault();
  var fd=new FormData(); fd.append('action','logout');
  fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(){location.href='/';});
});
</script>
</body>
</html>
