<?php
define('OSPZ_API', 'https://carburanti.mise.gov.it/ospzApi');
define('URL_ANAGRAFICA', 'https://www.mimit.gov.it/images/exportCSV/anagrafica_impianti_attivi.csv');
define('FILE_ANAGRAFICA', __DIR__ . '/../anagrafica.csv');

// Prefer env var (docker-compose). Fallback to config.local.php (gitignored).
$envKey = getenv('TANKERKOENIG_KEY');
if ($envKey !== false && $envKey !== '') {
    define('TANKERKOENIG_KEY', $envKey);
} elseif (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    define('TANKERKOENIG_KEY', '');
}

// Bootstrap DB + metriche + auth (connessione DB lazy, sessione no-op in CLI).
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/metrics.php';
require_once __DIR__ . '/auth.php';
authBoot();

// Timeout OSPZ: l'API MIMIT risponde tipicamente in 6-12s, con picchi oltre 18s.
// Il vecchio TIMEOUT=10 tagliava buona parte delle richieste, e l'errore veniva
// ingoiato silenziosamente (null) diventando indistinguibile da "zona senza
// distributori". Ora: budget ampio, connect timeout corto (host giù = fail fast)
// e ultimo errore esposto in $GLOBALS['ospz_last_error'] per la UI e i log.
define('OSPZ_TIMEOUT', 30);
define('OSPZ_CONNECTTIMEOUT', 5);

function ospzExec($ch, $endpoint) {
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => OSPZ_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => OSPZ_CONNECTTIMEOUT,
        CURLOPT_ENCODING       => 'gzip, deflate',
    ]);
    $t0    = microtime(true);
    $resp  = curl_exec($ch);
    $errno = curl_errno($ch);
    $err   = curl_error($ch);
    $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ms = (int)round((microtime(true) - $t0) * 1000);

    if ($resp === false || $code < 200 || $code >= 300) {
        $reason = $errno ? "curl $errno: $err" : "http $code";
        $GLOBALS['ospz_last_error'] = $reason;
        error_log("OSPZ $endpoint FAIL ($reason) after {$ms}ms");
        return null;
    }

    $data = json_decode($resp, true);
    if (!is_array($data)) {
        $GLOBALS['ospz_last_error'] = 'json non valido';
        error_log("OSPZ $endpoint FAIL (json non valido) after {$ms}ms");
        return null;
    }

    $GLOBALS['ospz_last_error'] = null;
    return $data;
}

function ospzPost($endpoint, $payload) {
    $ch = curl_init(OSPZ_API . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST       => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: FuelFinder/1.0'],
    ]);
    return ospzExec($ch, $endpoint);
}

function ospzGet($endpoint) {
    $ch = curl_init(OSPZ_API . $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: FuelFinder/1.0']);
    return ospzExec($ch, $endpoint);
}
