<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';
$lang = function_exists('currentLang') ? currentLang() : 'it';
$de   = $lang === 'de';
$updated = '05/06/2026';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FuelFinder — <?= $de ? 'Datenschutz' : 'Privacy &amp; Cookie' ?></title>
<meta name="robots" content="noindex, follow">
<link rel="icon" type="image/svg+xml" href="img/logo.svg">
<link rel="stylesheet" href="/fonts/fonts.css">
<link rel="stylesheet" href="style.css">
<style>
  /* override del layout PWA a viewport fisso: pagina di contenuto scrollabile */
  html,body{height:auto;min-height:100%;overflow:auto}
  .legal{max-width:780px;margin:0 auto;padding:28px 20px 60px;color:#f1f5f9;font-family:Inter,system-ui,sans-serif;line-height:1.6}
  .legal a.home{display:inline-flex;align-items:center;gap:8px;color:#f1f5f9;text-decoration:none;font-weight:700;font-size:1.2rem;margin-bottom:8px}
  .legal a.home b{color:var(--accent,#10b981)}
  .legal h1{font-size:1.5rem;margin:14px 0 4px}
  .legal .upd{color:var(--muted,#94a3b8);font-size:.8rem;margin-bottom:24px}
  .legal h2{font-size:1.05rem;margin:26px 0 8px;color:var(--accent,#10b981)}
  .legal p,.legal li{font-size:.92rem;color:#d4d4e4}
  .legal ul{padding-left:20px} .legal li{margin:4px 0}
  .legal table{width:100%;border-collapse:collapse;margin:8px 0;font-size:.86rem}
  .legal th,.legal td{text-align:left;padding:8px;border-bottom:1px solid var(--glass-border,#2c3a50);vertical-align:top}
  .legal th{color:var(--muted,#94a3b8);font-weight:600}
  .legal .ph{background:rgba(16,185,129,.12);border:1px dashed var(--accent,#10b981);border-radius:6px;padding:1px 6px;font-size:.85em}
  .legal .back{display:inline-block;margin-top:26px;color:var(--muted,#94a3b8);text-decoration:none}
</style>
</head>
<body>
<div class="legal">
<a class="home" href="/"><img src="img/logo.svg" width="32" height="32" alt="">Fuel<b>Finder</b></a>

<?php if ($de): ?>
<h1>Datenschutzerklärung</h1>
<div class="upd">Zuletzt aktualisiert: <?= $updated ?></div>

<h2>1. Verantwortlicher</h2>
<p>Verantwortlich für die Datenverarbeitung ist <strong>Edoardo Menegazzi</strong>.
Kontakt für Datenschutzanfragen: <a href="mailto:edoardo@fmenegazzi.it">edoardo@fmenegazzi.it</a>.</p>

<h2>2. Welche Daten wir verarbeiten</h2>
<table>
<tr><th>Daten</th><th>Zweck</th><th>Rechtsgrundlage</th></tr>
<tr><td>Konto: E-Mail, Passwort (gehasht), Registrierungs-/Login-Datum</td><td>Konto erstellen und verwalten</td><td>Art. 6 Abs. 1 b DSGVO (Vertrag)</td></tr>
<tr><td>Garage: Fahrzeuge (Name, Kraftstoff, Verbrauch)</td><td>Funktion „Garage“</td><td>Art. 6 Abs. 1 b DSGVO</td></tr>
<tr><td>Anonyme Nutzungsstatistik (anonymer Tages-Hash – <b>keine rohe IP</b>, Seite, Ereignistyp, Gerät/Browser/OS, Referrer)</td><td>Aggregierte Statistik, Verbesserung des Dienstes</td><td>Art. 6 Abs. 1 f DSGVO (berechtigtes Interesse); keine Profilbildung</td></tr>
<tr><td>Standort (GPS/Adresse)</td><td>Suche von Tankstellen – nur in Echtzeit, <b>nicht gespeichert</b></td><td>Art. 6 Abs. 1 b/a DSGVO</td></tr>
</table>

<h2>3. Cookies und lokale Speicherung</h2>
<p>Wir verwenden ausschließlich technisch notwendige bzw. funktionale Cookies. <b>Keine Werbe- oder Profiling-Cookies.</b> Unsere Statistik ist anonym und benötigt kein Cookie.</p>
<ul>
<li><code>ff_sess</code> — Sitzungscookie (Login), notwendig.</li>
<li><code>ff_remember</code> — nur wenn Sie „Angemeldet bleiben“ wählen, funktional.</li>
<li>Sprach-Cookie — speichert die gewählte Sprache, funktional.</li>
<li>localStorage — Einstellungen / aktives Fahrzeug, technisch.</li>
</ul>

<h2>4. Empfänger / Drittdienste</h2>
<p>Zur Bereitstellung des Dienstes wird Ihre IP-Adresse technisch an folgende Dienste übermittelt: MIMIT/OSPZ (Preise IT), Tankerkönig (Preise DE), Geoapify (Adress-Autocomplete und Kartenkacheln; Daten &copy; OpenStreetMap), jsDelivr/unpkg (Bibliotheken). Routing und Schriftarten laufen auf unseren Servern (keine Übermittlung an Dritte). Es werden keine Daten zu Werbezwecken verkauft.</p>

<h2>5. Speicherdauer</h2>
<p>Kontodaten: bis zur Löschung des Kontos. Anonyme Statistik: aggregiert, ohne Personenbezug. Login-Versuche: kurzfristig (Sicherheit).</p>

<h2>6. Ihre Rechte</h2>
<p>Sie haben das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung, Datenübertragbarkeit und Widerspruch sowie auf Beschwerde bei einer Aufsichtsbehörde. Zur Löschung Ihres Kontos schreiben Sie an <a href="mailto:edoardo@fmenegazzi.it">edoardo@fmenegazzi.it</a>.</p>

<?php else: ?>
<h1>Informativa Privacy &amp; Cookie</h1>
<div class="upd">Ultimo aggiornamento: <?= $updated ?></div>

<h2>1. Titolare del trattamento</h2>
<p>Il titolare del trattamento è <strong>Edoardo Menegazzi</strong>.
Contatto per richieste privacy: <a href="mailto:edoardo@fmenegazzi.it">edoardo@fmenegazzi.it</a>.</p>

<h2>2. Quali dati trattiamo</h2>
<table>
<tr><th>Dato</th><th>Finalità</th><th>Base giuridica</th></tr>
<tr><td>Account: email, password (in forma cifrata/hash), data registrazione e ultimo accesso</td><td>Creazione e gestione dell'account</td><td>art. 6.1.b GDPR (contratto/servizio)</td></tr>
<tr><td>Garage: veicoli salvati (nome, carburante, consumi)</td><td>Funzione “Garage”</td><td>art. 6.1.b GDPR</td></tr>
<tr><td>Statistiche d'uso anonime (hash anonimo giornaliero — <b>nessun IP grezzo memorizzato</b>, pagina, tipo evento, tipo carburante/raggio/modalità/paese, dispositivo/browser/SO, referrer)</td><td>Statistiche aggregate e miglioramento del servizio</td><td>art. 6.1.f GDPR (legittimo interesse); nessuna profilazione</td></tr>
<tr><td>Posizione (GPS/indirizzo)</td><td>Ricerca distributori — usata solo in tempo reale, <b>non memorizzata</b> dal sito</td><td>art. 6.1.b/a GDPR</td></tr>
</table>

<h2>3. Cookie e archiviazione locale</h2>
<p>Usiamo esclusivamente cookie tecnici necessari o funzionali. <b>Nessun cookie pubblicitario o di profilazione.</b> Le nostre statistiche sono anonime e non richiedono cookie.</p>
<ul>
<li><code>ff_sess</code> — cookie di sessione (login), necessario.</li>
<li><code>ff_remember</code> — solo se scegli “Ricordami”, funzionale.</li>
<li>cookie lingua — memorizza la lingua scelta, funzionale.</li>
<li>localStorage — preferenze / veicolo attivo, tecnico.</li>
</ul>

<h2>4. Destinatari / servizi terzi</h2>
<p>Per erogare il servizio, il tuo indirizzo IP viene tecnicamente trasmesso a: MIMIT/OSPZ (prezzi IT), Tankerkönig (prezzi DE), Geoapify (autocomplete indirizzi e tile della mappa; dati &copy; OpenStreetMap), jsDelivr/unpkg (librerie JS). Il calcolo dei percorsi e i font sono ospitati sui nostri server (nessun invio a terzi). Nessun dato è venduto o usato per finalità pubblicitarie.</p>

<h2>5. Conservazione</h2>
<p>Dati account: fino alla cancellazione dell'account. Statistiche anonime: in forma aggregata, senza riferimento alla persona. Tentativi di login: breve periodo (sicurezza).</p>

<h2>6. I tuoi diritti</h2>
<p>Hai diritto di accesso, rettifica, cancellazione, limitazione, portabilità e opposizione, oltre al reclamo all'Autorità Garante. Per cancellare l'account scrivi a <a href="mailto:edoardo@fmenegazzi.it">edoardo@fmenegazzi.it</a>.</p>
<?php endif; ?>

<a class="back" href="/">&larr; <?= $de ? 'Zurück' : 'Torna al sito' ?></a>
</div>
</body>
</html>
