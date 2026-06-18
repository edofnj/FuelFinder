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
<title>FuelFinder — <?= $de ? 'Nutzungsbedingungen' : 'Termini di Servizio' ?></title>
<meta name="robots" content="noindex, follow">
<link rel="icon" type="image/svg+xml" href="img/logo.svg">
<link rel="stylesheet" href="/fonts/fonts.css">
<link rel="stylesheet" href="style.css">
<style>
  html,body{height:auto;min-height:100%;overflow:auto}
  .legal{max-width:780px;margin:0 auto;padding:28px 20px 60px;color:#f1f5f9;font-family:Inter,system-ui,sans-serif;line-height:1.6}
  .legal a.home{display:inline-flex;align-items:center;gap:8px;color:#f1f5f9;text-decoration:none;font-weight:700;font-size:1.2rem;margin-bottom:8px}
  .legal a.home b{color:var(--accent,#10b981)}
  .legal h1{font-size:1.5rem;margin:14px 0 4px}
  .legal .upd{color:var(--muted,#94a3b8);font-size:.8rem;margin-bottom:24px}
  .legal h2{font-size:1.05rem;margin:26px 0 8px;color:var(--accent,#10b981)}
  .legal p,.legal li{font-size:.92rem;color:#d4d4e4}
  .legal ul{padding-left:20px} .legal li{margin:4px 0}
  .legal .back{display:inline-block;margin-top:26px;color:var(--muted,#94a3b8);text-decoration:none}
</style>
</head>
<body>
<div class="legal">
<a class="home" href="/"><img src="img/logo.svg" width="32" height="32" alt="">Fuel<b>Finder</b></a>

<?php if ($de): ?>
<h1>Nutzungsbedingungen</h1>
<div class="upd">Zuletzt aktualisiert: <?= $updated ?></div>
<h2>1. Dienst</h2>
<p>FuelFinder ist ein kostenloser Informationsdienst, der Tankstellen und Spritpreise in Italien und Deutschland anzeigt und Kosten/Routen schätzt. Betreiber: <strong>Edoardo Menegazzi</strong> — Kontakt: <a href="mailto:edoardo@fmenegazzi.it">edoardo@fmenegazzi.it</a>.</p>
<h2>2. Keine Gewähr</h2>
<p>Preise und Daten stammen von Dritten (MIMIT, Tankerkönig/MTS-K, OpenStreetMap) und können ungenau oder veraltet sein. Routen und Kostenschätzungen sind Näherungen. Der Dienst wird „wie besehen“ ohne Gewährleistung bereitgestellt; Entscheidungen auf Basis der Angaben treffen Sie auf eigenes Risiko.</p>
<h2>3. Konten</h2>
<p>Für das Speichern von Fahrzeugen ist ein Konto nötig. Du bist für die Geheimhaltung deines Passworts verantwortlich. Wir können Konten bei Missbrauch sperren. Du kannst dein Konto jederzeit löschen.</p>
<h2>4. Zulässige Nutzung</h2>
<p>Keine automatisierte Massenabfrage, kein Reverse-Engineering, keine Überlastung der Infrastruktur, keine rechtswidrige Nutzung.</p>
<h2>5. Haftung</h2>
<p>Soweit gesetzlich zulässig, haften wir nicht für Schäden aus der Nutzung oder Nichtverfügbarkeit des Dienstes.</p>
<h2>6. Änderungen &amp; anwendbares Recht</h2>
<p>Diese Bedingungen können aktualisiert werden. Es gilt italienisches Recht. Siehe auch unsere <a href="/privacy">Datenschutzerklärung</a>.</p>

<?php else: ?>
<h1>Termini di Servizio</h1>
<div class="upd">Ultimo aggiornamento: <?= $updated ?></div>
<h2>1. Servizio</h2>
<p>FuelFinder è un servizio informativo gratuito che mostra distributori e prezzi dei carburanti in Italia e Germania e stima costi/percorsi. Gestore: <strong>Edoardo Menegazzi</strong> — contatto: <a href="mailto:edoardo@fmenegazzi.it">edoardo@fmenegazzi.it</a>.</p>
<h2>2. Assenza di garanzie</h2>
<p>I prezzi e i dati provengono da terze parti (MIMIT, Tankerkönig/MTS-K, OpenStreetMap) e possono essere inesatti o non aggiornati. Percorsi e stime di costo sono approssimazioni. Il servizio è fornito “così com'è”, senza garanzie: le decisioni basate sulle informazioni mostrate sono a tuo rischio.</p>
<h2>3. Account</h2>
<p>Per salvare i veicoli serve un account. Sei responsabile della riservatezza della tua password. Possiamo sospendere account in caso di abuso. Puoi cancellare il tuo account in qualsiasi momento.</p>
<h2>4. Uso consentito</h2>
<p>Vietati: interrogazioni massive automatizzate, reverse engineering, sovraccarico dell'infrastruttura, qualsiasi uso illecito.</p>
<h2>5. Responsabilità</h2>
<p>Nei limiti consentiti dalla legge, non siamo responsabili per danni derivanti dall'uso o dall'indisponibilità del servizio.</p>
<h2>6. Modifiche e legge applicabile</h2>
<p>I presenti termini possono essere aggiornati. Si applica la legge italiana. Vedi anche la nostra <a href="/privacy">Informativa Privacy</a>.</p>
<?php endif; ?>

<a class="back" href="/">&larr; <?= $de ? 'Zurück' : 'Torna al sito' ?></a>
</div>
</body>
</html>
