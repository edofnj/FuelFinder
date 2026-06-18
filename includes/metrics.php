<?php
require_once __DIR__ . '/db.php';

// IP reale dietro nginx-proxy-manager (X-Forwarded-For).
function clientIp() {
    $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($xff !== '') { $p = explode(',', $xff); return trim($p[0]); }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Hash visitatore ANONIMO: salt giornaliero (data UTC + segreto) || ip || ua.
// Nessun IP grezzo persistito; il salt cambia ogni giorno => non correlabile
// tra giorni diversi (approccio "privacy-friendly" stile Plausible).
function visitorHash() {
    $secret = getenv('METRICS_SALT') ?: 'ff-default-salt';
    $salt   = hash('sha256', gmdate('Y-m-d') . '|' . $secret);
    $ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return substr(hash('sha256', $salt . '|' . clientIp() . '|' . $ua), 0, 32);
}

// Hash IP stabile (con segreto) solo per il rate-limit login. Non è PII leggibile.
function ipHashForRate() {
    $secret = getenv('METRICS_SALT') ?: 'ff-default-salt';
    return substr(hash('sha256', 'rate|' . $secret . '|' . clientIp()), 0, 32);
}

function refererHost() {
    $r = $_SERVER['HTTP_REFERER'] ?? '';
    if ($r === '') return null;
    $h = parse_url($r, PHP_URL_HOST);
    return $h ?: null;
}

// Parsing minimale UA → [device, browser, os]
function parseUA($ua = null) {
    $ua = $ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternal|preview/i', $ua)) {
        $device = 'Bot';
    } elseif (preg_match('/iPad|Tablet/i', $ua)) {
        $device = 'Tablet';
    } elseif (preg_match('/Mobi|Android|iPhone|iPod/i', $ua)) {
        $device = 'Mobile';
    } else {
        $device = 'Desktop';
    }
    $browser = 'Other';
    foreach (['Edg'=>'Edge','OPR'=>'Opera','SamsungBrowser'=>'Samsung','Chrome'=>'Chrome','Firefox'=>'Firefox','Safari'=>'Safari'] as $k=>$v) {
        if (stripos($ua, $k) !== false) { $browser = $v; break; }
    }
    $os = 'Other';
    foreach (['Android'=>'Android','iPhone'=>'iOS','iPad'=>'iOS','Windows'=>'Windows','Mac OS'=>'macOS','CrOS'=>'ChromeOS','Linux'=>'Linux'] as $k=>$v) {
        if (stripos($ua, $k) !== false) { $os = $v; break; }
    }
    return [$device, $browser, $os];
}

// Inserisce un evento metrica. NON BLOCCANTE: qualunque errore (DB giù, driver
// assente, ecc.) viene ignorato per non rompere mai la pagina.
function track($type, array $fields = []) {
    try {
        [$device, $browser, $os] = parseUA();
        $uid = function_exists('currentUserId') ? currentUserId() : null;
        $st = pdo()->prepare(
            'INSERT INTO events (visitor_hash, user_id, type, page, country, fuel, radius, mode, results, ua_device, ua_browser, ua_os, referrer_host, lang, meta)
             VALUES (:vh,:uid,:type,:page,:country,:fuel,:radius,:mode,:results,:dev,:br,:os,:ref,:lang,:meta)'
        );
        $st->execute([
            ':vh'      => visitorHash(),
            ':uid'     => $uid,
            ':type'    => $type,
            ':page'    => $fields['page'] ?? basename($_SERVER['SCRIPT_NAME'] ?? ''),
            ':country' => $fields['country'] ?? null,
            ':fuel'    => $fields['fuel'] ?? null,
            ':radius'  => isset($fields['radius']) ? (int)$fields['radius'] : null,
            ':mode'    => $fields['mode'] ?? null,
            ':results' => isset($fields['results']) ? (int)$fields['results'] : null,
            ':dev'     => $device,
            ':br'      => $browser,
            ':os'      => $os,
            ':ref'     => refererHost(),
            ':lang'    => function_exists('currentLang') ? currentLang() : null,
            ':meta'    => isset($fields['meta']) ? json_encode($fields['meta']) : null,
        ]);
    } catch (Throwable $e) {
        // silenzioso di proposito: le metriche non devono mai impattare l'utente
    }
}
