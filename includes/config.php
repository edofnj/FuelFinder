<?php
define('OSPZ_API', 'https://carburanti.mise.gov.it/ospzApi');
define('URL_ANAGRAFICA', 'https://www.mimit.gov.it/images/exportCSV/anagrafica_impianti_attivi.csv');
define('FILE_ANAGRAFICA', __DIR__ . '/../anagrafica.csv');

// Carica secrets locali (gitignorati). Se mancano, chiave vuota → provider DE disattivato.
if (is_file(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    define('TANKERKOENIG_KEY', '');
}

function ospzPost($endpoint, $payload) {
    $ch = curl_init(OSPZ_API . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'User-Agent: FuelFinder/1.0'],
        CURLOPT_SSL_VERIFYPEER => false,
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
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp ? json_decode($resp, true) : null;
}
