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

function ospzPost($endpoint, $payload) {
    $ch = curl_init(OSPZ_API . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'User-Agent: FuelFinder/1.0'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp ? json_decode($resp, true) : null;
}

function ospzGet($endpoint) {
    $ch = curl_init(OSPZ_API . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['User-Agent: FuelFinder/1.0'],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp ? json_decode($resp, true) : null;
}
