<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/i18n.php';

// Renderizzata da index.php per gli anonimi senza cookie ospite ("/" unica home).
if ($_SERVER['REQUEST_METHOD'] === 'GET') track('pageview', ['page' => 'landing']);

$lang = function_exists('currentLang') ? currentLang() : 'it';
$de   = $lang === 'de';
function L($it, $deTxt) { global $de; return $de ? $deTxt : $it; }
$canon = 'https://fuelfinder.fmenegazzi.it/';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= L('FuelFinder — il carburante più conveniente, viaggio incluso', 'FuelFinder — günstigster Sprit, Fahrt inklusive') ?></title>
<meta name="description" content="<?= L('Trova il distributore che ti costa davvero meno: prezzo del carburante più il costo del viaggio. Italia e Germania, dati ufficiali MIMIT e Tankerkönig.', 'Finde die wirklich günstigste Tankstelle: Spritpreis plus Fahrtkosten. Italien und Deutschland, offizielle MIMIT- und Tankerkönig-Daten.') ?>">
<meta name="robots" content="index, follow, max-image-preview:large">
<link rel="canonical" href="<?= $canon ?>">
<link rel="alternate" hreflang="it" href="https://fuelfinder.fmenegazzi.it/">
<link rel="alternate" hreflang="de" href="https://fuelfinder.fmenegazzi.it/?lang=de">
<meta property="og:type" content="website">
<meta property="og:title" content="FuelFinder">
<meta property="og:description" content="<?= L('Il distributore che ti costa meno davvero, considerando anche il viaggio.', 'Die Tankstelle, die dich wirklich am wenigsten kostet — inklusive Fahrt.') ?>">
<meta property="og:url" content="<?= $canon ?>">
<meta property="og:image" content="<?= $canon ?>img/apple-touch-icon.png">
<link rel="icon" type="image/svg+xml" href="img/logo.svg">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0b1220">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="/fonts/fonts.css">
<link rel="stylesheet" href="style.css">
<style>
/* landing nello stile dell'app (glassmorphism) — riusa le variabili di style.css */
html,body{height:auto;min-height:100vh;overflow-x:hidden;overflow-y:auto}
.lp{position:relative;z-index:1;max-width:1120px;margin:0 auto;padding:0 24px 60px}
.mono{font-family:'JetBrains Mono',monospace}
.gradtx{background:linear-gradient(135deg,#34d399,#38bdf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.glass{background:var(--glass);border:1px solid var(--glass-border);border-radius:var(--radius-xl);backdrop-filter:blur(20px)}

/* header (riusa .logo-icon/.logo-text/.header-badge/.lang-switcher di style.css) */
.lp-nav{display:flex;align-items:center;gap:14px;padding:26px 0}
.lp-nav .header-badge{margin-left:auto}
.lp-btn{height:40px;display:inline-flex;align-items:center;gap:7px;padding:0 18px;border-radius:var(--radius);font-family:'JetBrains Mono',monospace;font-size:.72rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;cursor:pointer;text-decoration:none;border:1px solid transparent;transition:all .25s cubic-bezier(.4,0,.2,1)}
.lp-btn.ghost{background:var(--glass);border-color:var(--glass-border);color:var(--text);backdrop-filter:blur(10px)}
.lp-btn.ghost:hover{border-color:var(--border-hi)}
.lp-btn.primary{background:var(--accent);color:var(--on-accent);box-shadow:0 1px 2px rgba(0,0,0,0.3)}
.lp-btn.primary:hover{background:var(--accent2);transform:translateY(-1px);box-shadow:0 4px 16px var(--accent-glow)}
.lp-btn.lg{height:50px;padding:0 26px;font-size:.8rem}

/* hero */
.hero{display:grid;grid-template-columns:1.05fr .95fr;gap:40px;align-items:center;padding:44px 0 26px}
.hero .kick{display:inline-flex;align-items:center;gap:9px;font-family:'JetBrains Mono',monospace;font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);background:var(--glass);border:1px solid var(--glass-border);border-radius:100px;padding:7px 14px;backdrop-filter:blur(10px)}
.hero .kick .dot{width:7px;height:7px;border-radius:50%;background:var(--accent);box-shadow:0 0 10px var(--accent-glow)}
.hero h1{font-family:'Inter',sans-serif;font-weight:700;font-size:clamp(2.1rem,4.4vw,3.3rem);line-height:1.08;letter-spacing:-.03em;margin:22px 0 18px}
.hero p.sub{font-size:1.08rem;color:var(--muted);line-height:1.65;max-width:34ch;margin:0 0 28px}
.hero .actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center}
.hero .try{font-family:'JetBrains Mono',monospace;font-size:.72rem;letter-spacing:.04em;color:var(--muted);text-decoration:none;border-bottom:1px dashed var(--border-hi);padding-bottom:2px}
.hero .try:hover{color:var(--accent2)}

/* result-card (stile card dell'app) */
.rc{padding:22px}
.rc .rc-top{display:flex;align-items:flex-start;justify-content:space-between;gap:14px}
.rc .rc-name{font-weight:600;font-size:1rem;display:flex;align-items:center;gap:8px;letter-spacing:-.01em}
.rc .rc-addr{font-size:.78rem;color:var(--muted);margin-top:4px}
.rc .rc-badge{font-family:'JetBrains Mono',monospace;font-size:.6rem;letter-spacing:.1em;text-transform:uppercase;color:var(--on-accent);background:var(--accent);padding:5px 9px;border-radius:100px}
.rc .rc-rows{font-family:'JetBrains Mono',monospace;font-size:.82rem;margin-top:18px}
.rc .rc-rows .r{display:flex;justify-content:space-between;padding:7px 0;color:var(--muted)}
.rc .rc-rows .r b{color:var(--text);font-weight:500}
.rc .rc-foot{display:flex;align-items:baseline;justify-content:space-between;border-top:1px solid var(--border);margin-top:10px;padding-top:14px}
.rc .rc-foot .lbl{font-family:'JetBrains Mono',monospace;font-size:.68rem;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
.rc .rc-foot .tot{font-family:'JetBrains Mono',monospace;font-weight:700;font-size:1.55rem;color:var(--accent2)}
.rc .rc-save{display:inline-flex;align-items:center;gap:6px;margin-top:14px;font-family:'JetBrains Mono',monospace;font-size:.72rem;color:var(--green);background:var(--green-dim);border:1px solid var(--green-glow);border-radius:var(--radius);padding:7px 11px}

/* sezioni */
.sec{padding:56px 0 0}
.sec .lbl{font-family:'JetBrains Mono',monospace;font-size:.7rem;letter-spacing:.1em;text-transform:uppercase;color:var(--accent2)}
.sec h2{font-family:'Inter',sans-serif;font-weight:700;font-size:clamp(1.5rem,2.6vw,2rem);letter-spacing:-.02em;margin:10px 0 28px}

/* griglia feature 3×2 — card glass */
.feat{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.fc{padding:24px;border-radius:var(--radius-lg);background:var(--glass);border:1px solid var(--glass-border);backdrop-filter:blur(20px);transition:transform .2s,border-color .2s}
.fc:hover{transform:translateY(-4px);border-color:var(--border-hi)}
.fc .ic{width:40px;height:40px;border-radius:11px;display:flex;align-items:center;justify-content:center;background:var(--accent-dim);color:var(--accent2)}
.fc .ic svg{width:20px;height:20px}
.fc h3{font-size:1.02rem;font-weight:600;margin:16px 0 7px;letter-spacing:-.01em}
.fc p{font-size:.9rem;color:var(--muted);line-height:1.55;margin:0}
@media(max-width:820px){.feat{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.feat{grid-template-columns:1fr}}

/* come funziona */
.steps{display:grid;grid-template-columns:repeat(3,1fr);gap:18px}
.stp{padding:22px;border-radius:var(--radius-lg);background:var(--surface);border:1px solid var(--border)}
.stp .n{font-family:'JetBrains Mono',monospace;font-size:1.6rem;font-weight:700;color:var(--accent2)}
.stp h4{font-size:1rem;font-weight:600;margin:10px 0 6px}
.stp p{font-size:.88rem;color:var(--muted);line-height:1.5;margin:0}
@media(max-width:680px){.steps{grid-template-columns:1fr}}

/* CTA finale */
.final{margin-top:60px;text-align:center;padding:54px 26px;border-radius:var(--radius-xl);background:radial-gradient(120% 140% at 50% 0,var(--accent-dim),var(--glass));border:1px solid var(--glass-border);backdrop-filter:blur(20px)}
.final h2{font-family:'Inter',sans-serif;font-weight:700;font-size:clamp(1.5rem,2.8vw,2.1rem);letter-spacing:-.02em;margin:0 0 8px}
.final p{color:var(--muted);margin:0 0 24px;font-size:.95rem}
.final .actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}

/* footer */
.lp-foot{margin-top:48px;padding:26px 0;border-top:1px solid var(--border);display:flex;flex-wrap:wrap;gap:8px 18px;align-items:center;font-family:'JetBrains Mono',monospace;font-size:.72rem;color:rgba(255,255,255,.3)}
.lp-foot a{color:var(--muted);text-decoration:none}.lp-foot a:hover{color:var(--accent2)}
.lp-foot .sp{flex:1}

.rise{opacity:0;transform:translateY(16px);animation:lr .6s ease forwards}
.d1{animation-delay:.05s}.d2{animation-delay:.14s}.d3{animation-delay:.24s}.d4{animation-delay:.34s}
@keyframes lr{to{opacity:1;transform:none}}
@media(max-width:900px){.hero{grid-template-columns:1fr;gap:28px}.hero p.sub{max-width:none}}
@media(prefers-reduced-motion:reduce){.rise{animation:none;opacity:1;transform:none}}
</style>
</head>
<body>
<div class="lp">
    <nav class="lp-nav">
        <a class="logo-icon" href="/"><img src="img/logo.svg" alt="FuelFinder"></a>
        <div class="logo-text">Fuel<span>Finder</span></div>
        <div class="header-badge">IT + DE · <?= L('dati ufficiali','offizielle Daten') ?></div>
        <div class="lang-switcher">
            <a href="?lang=it" class="lang-opt<?= $lang==='it'?' active':'' ?>">IT</a>
            <a href="?lang=de" class="lang-opt<?= $lang==='de'?' active':'' ?>">DE</a>
        </div>
        <button class="lp-btn ghost" type="button" onclick="ffOpenAuth('login')"><?= L('Accedi','Anmelden') ?></button>
        <button class="lp-btn primary" type="button" onclick="ffOpenAuth('register')"><?= L('Registrati','Registrieren') ?></button>
    </nav>

    <section class="hero">
        <div>
            <span class="kick rise d1"><span class="dot"></span><?= L('Italia e Germania · dati MIMIT / Tankerkönig','Italien und Deutschland · MIMIT / Tankerkönig') ?></span>
            <h1 class="rise d2"><?= L('Il distributore che ti costa <span class="gradtx">meno davvero</span>.','Die Tankstelle, die dich <span class="gradtx">wirklich weniger</span> kostet.') ?></h1>
            <p class="sub rise d3"><?= L('FuelFinder somma al prezzo del carburante il costo reale del viaggio per arrivarci. Vicino a te o lungo il tuo percorso.','FuelFinder addiert zum Spritpreis die echten Fahrtkosten. In deiner Nähe oder entlang deiner Route.') ?></p>
            <div class="actions rise d4">
                <button class="lp-btn primary lg" type="button" onclick="ffOpenAuth('register')"><?= L('Registrati gratis','Kostenlos starten') ?></button>
                <button class="lp-btn ghost lg" type="button" onclick="ffOpenAuth('login')"><?= L('Accedi','Anmelden') ?></button>
                <a class="try" href="/?guest=1"><?= L('prova senza account','ohne Konto testen') ?> →</a>
            </div>
        </div>
        <div class="glass rc rise d3">
            <div class="rc-top">
                <div><div class="rc-name">⛽ Q8 Easy</div><div class="rc-addr">Milano · 2,4 km</div></div>
                <div class="rc-badge"><?= L('migliore','beste') ?></div>
            </div>
            <div class="rc-rows">
                <div class="r"><span>Benzina · 40 L</span><b>€ 65,92</b></div>
                <div class="r"><span><?= L('Viaggio A/R · 4,8 km','Fahrt · 4,8 km') ?></span><b>€ 1,03</b></div>
            </div>
            <div class="rc-foot"><span class="lbl"><?= L('Costo reale','Echte Kosten') ?></span><span class="tot">€ 66,95</span></div>
            <div class="rc-save">▼ <?= L('risparmi € 3,20 vs il più vicino','€ 3,20 günstiger als die nächste') ?></div>
        </div>
    </section>

    <section class="sec">
        <div class="lbl"><?= L('Funzionalità','Funktionen') ?></div>
        <h2><?= L('Tutto per spendere meno al pieno','Alles, um beim Tanken zu sparen') ?></h2>
        <div class="feat">
            <div class="fc"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V6a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v14"/><path d="M3 20h13"/><path d="M16 9h2.5a1.5 1.5 0 0 1 1.5 1.5V17a2 2 0 0 0 2 2"/><path d="M7 8h4"/></svg></div><h3><?= L('Costo reale','Echte Kosten') ?></h3><p><?= L('Al prezzo al litro sommiamo il costo del tragitto in base ai consumi del tuo veicolo.','Zum Literpreis addieren wir die Fahrtkosten je nach Verbrauch deines Fahrzeugs.') ?></p></div>
            <div class="fc"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h6a4 4 0 0 0 0-8H10a4 4 0 0 1 0-8h6"/></svg></div><h3><?= L('Sul percorso','Entlang der Route') ?></h3><p><?= L('Da A a B: i distributori lungo la rotta e il break-even di ogni deviazione.','Von A nach B: Tankstellen entlang der Strecke und der Break-even jedes Umwegs.') ?></p></div>
            <div class="fc"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 17h14"/><path d="M6 17l-1-5 2-5h10l2 5-1 5"/><circle cx="8" cy="17" r="1.6"/><circle cx="16" cy="17" r="1.6"/></svg></div><h3><?= L('Garage veicoli','Fahrzeug-Garage') ?></h3><p><?= L('Salva i tuoi veicoli con i consumi: i calcoli si adattano in automatico.','Speichere deine Fahrzeuge mit Verbrauch: die Berechnung passt sich an.') ?></p></div>
            <div class="fc"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7Z"/><circle cx="12" cy="9" r="2"/></svg></div><h3><?= L('Modalità SOS','SOS-Modus') ?></h3><p><?= L('In riserva? Un tap e trovi il distributore più vicino in assoluto.','Fast leer? Ein Tipp und du findest die absolut nächste Tankstelle.') ?></p></div>
            <div class="fc"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2Z"/><path d="M9 4v14"/><path d="M15 6v14"/></svg></div><h3><?= L('Mappa e percorsi','Karte und Routen') ?></h3><p><?= L('Mappa interattiva con rotta e distributori, distanze stradali reali.','Interaktive Karte mit Route und Tankstellen, echte Straßendistanzen.') ?></p></div>
            <div class="fc"><div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a14 14 0 0 1 0 18 14 14 0 0 1 0-18Z"/></svg></div><h3><?= L('Italia e Germania','Italien und Deutschland') ?></h3><p><?= L('Prezzi ufficiali MIMIT e Tankerkönig, anche nelle zone di confine.','Offizielle Preise MIMIT und Tankerkönig, auch in Grenzregionen.') ?></p></div>
        </div>
    </section>

    <section class="sec">
        <div class="lbl"><?= L('Come funziona','So funktioniert es') ?></div>
        <h2><?= L('Tre passaggi','In drei Schritten') ?></h2>
        <div class="steps">
            <div class="stp"><div class="n">01</div><h4><?= L('Posizione o percorso','Standort oder Route') ?></h4><p><?= L('GPS o un indirizzo. Anche da A a B.','GPS oder Adresse. Auch von A nach B.') ?></p></div>
            <div class="stp"><div class="n">02</div><h4><?= L('Veicolo e quantità','Fahrzeug und Menge') ?></h4><p><?= L('Carburante, consumi, litri o budget.','Kraftstoff, Verbrauch, Liter oder Budget.') ?></p></div>
            <div class="stp"><div class="n">03</div><h4><?= L('Costo reale','Echte Kosten') ?></h4><p><?= L('Ti diciamo dove conviene fare il pieno davvero.','Wir sagen dir, wo sich Tanken wirklich lohnt.') ?></p></div>
        </div>
    </section>

    <section class="final">
        <h2><?= L('Smetti di pagare il pieno di troppo','Hör auf, zu viel zu tanken') ?></h2>
        <p><?= L('Gratis · Italia e Germania · dati ufficiali','Kostenlos · Italien und Deutschland · offizielle Daten') ?></p>
        <div class="actions">
            <button class="lp-btn primary lg" type="button" onclick="ffOpenAuth('register')"><?= L('Crea un account gratis','Kostenloses Konto erstellen') ?></button>
            <a class="lp-btn ghost lg" href="/?guest=1"><?= L('Prova senza account','Ohne Konto testen') ?></a>
        </div>
    </section>

    <footer class="lp-foot">
        <span>© FuelFinder</span>
        <a href="/privacy">Privacy</a>
        <a href="/tos"><?= L('Termini','AGB') ?></a>
        <span class="sp"></span>
        <span><?= L('Dati','Daten') ?>: MIMIT · <a href="https://creativecommons.tankerkoenig.de" target="_blank" rel="noopener">Tankerkönig</a> · © <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a></span>
    </footer>
</div>
<script>function ffOpenAuth(t){location.href='/account?tab='+(t||'login');}</script>
</body>
</html>
