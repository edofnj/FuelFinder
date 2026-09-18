<?php
// Pagina di errore condivisa e AUTOSUFFICIENTE: nessuna dipendenza da DB/config/
// auth, così resta renderizzabile anche quando l'app è rotta (es. 500). La lingua
// è dedotta da ?lang= o dal cookie ff_lang (default it), senza includere i18n.php.
// CSS inline (compatibile con la CSP severa del sito) e asset con path assoluti,
// così funziona qualunque sia la URL che ha generato l'errore.
function renderErrorPage(int $code, array $msg): void {
    $supported = ['it', 'de'];
    $lang = 'it';
    if (isset($_GET['lang']) && in_array($_GET['lang'], $supported, true)) {
        $lang = $_GET['lang'];
    } elseif (isset($_COOKIE['ff_lang']) && in_array($_COOKIE['ff_lang'], $supported, true)) {
        $lang = $_COOKIE['ff_lang'];
    }
    $de = ($lang === 'de');
    $m   = $msg[$lang] ?? $msg['it'];

    $big   = htmlspecialchars((string)$code);
    $title = htmlspecialchars($m['title']);
    $sub   = htmlspecialchars($m['sub']);
    $cta   = $de ? 'Zur Startseite' : 'Torna alla home';

    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="{$lang}">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>FuelFinder — {$big} {$title}</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="/img/logo.svg">
<link rel="stylesheet" href="/fonts/fonts.css">
<style>
  :root{--bg:#0b1220;--text:#f1f5f9;--muted:#94a3b8;--accent:#10b981;--accent2:#34d399;
    --glass:rgba(148,163,184,.05);--glass-border:rgba(148,163,184,.13);--on-accent:#04211a}
  *{box-sizing:border-box;margin:0;padding:0}
  html,body{height:100%}
  body{font-family:'Inter',system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text);
    min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
    text-align:center;padding:32px 20px;line-height:1.6;-webkit-font-smoothing:antialiased;
    position:relative;overflow:hidden}
  body::before{content:'';position:fixed;inset:0;pointer-events:none;z-index:0;background:
    radial-gradient(ellipse 80% 60% at 10% 20%, rgba(37,99,235,.06) 0%, transparent 60%),
    radial-gradient(ellipse 60% 50% at 90% 80%, rgba(16,185,129,.05) 0%, transparent 60%)}
  .wrap{position:relative;z-index:1;max-width:560px;display:flex;flex-direction:column;align-items:center}
  .brand{display:inline-flex;align-items:center;gap:12px;text-decoration:none;margin-bottom:30px}
  .brand img{width:44px;height:44px;border-radius:11px;display:block}
  .brand .wordmark{font-size:1.5rem;font-weight:700;letter-spacing:-.03em;color:var(--text)}
  .brand .wordmark span{background:linear-gradient(135deg,#34d399,#38bdf8);-webkit-background-clip:text;
    background-clip:text;-webkit-text-fill-color:transparent}
  .code{font-family:'JetBrains Mono',ui-monospace,monospace;font-weight:700;
    font-size:clamp(76px,19vw,150px);line-height:1;letter-spacing:-.04em;
    background:linear-gradient(135deg,#34d399,#38bdf8);-webkit-background-clip:text;background-clip:text;
    -webkit-text-fill-color:transparent;filter:drop-shadow(0 10px 44px rgba(16,185,129,.28))}
  h1{font-size:clamp(1.25rem,4vw,1.6rem);font-weight:700;margin-top:16px;letter-spacing:-.01em}
  p.sub{color:var(--muted);font-size:.95rem;margin-top:12px;max-width:44ch}
  .actions{display:flex;gap:12px;flex-wrap:wrap;justify-content:center;margin-top:32px}
  .btn{display:inline-flex;align-items:center;gap:8px;font-family:'JetBrains Mono',ui-monospace,monospace;
    font-size:.8rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;text-decoration:none;
    padding:14px 24px;border-radius:10px;transition:background .2s,box-shadow .2s,transform .2s,border-color .2s}
  .btn-primary{background:var(--accent);color:var(--on-accent);box-shadow:0 1px 2px rgba(0,0,0,.3)}
  .btn-primary:hover{background:var(--accent2);box-shadow:0 4px 16px rgba(16,185,129,.22);transform:translateY(-1px)}
</style>
</head>
<body>
<div class="wrap">
  <a class="brand" href="/"><img src="/img/logo.svg" width="44" height="44" alt="">
    <span class="wordmark">Fuel<span>Finder</span></span></a>
  <div class="code">{$big}</div>
  <h1>{$title}</h1>
  <p class="sub">{$sub}</p>
  <div class="actions">
    <a class="btn btn-primary" href="/">&larr; {$cta}</a>
  </div>
</div>
</body>
</html>
HTML;
}
