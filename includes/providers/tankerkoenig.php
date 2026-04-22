<?php
// Provider Tankerkoenig (Germania).
// API: https://creativecommons.tankerkoenig.de/
// Carburanti: e5 (benzina 95), e10, diesel. No GPL/metano.

function tkFuelMap($fuelType) {
    switch (strtolower($fuelType)) {
        case 'benzina': return 'e5';
        case 'gasolio': return 'diesel';
        default:        return null;
    }
}

function tankerkoenigSearch($lat, $lon, $radiusKm, $fuelType) {
    if (!defined('TANKERKOENIG_KEY') || TANKERKOENIG_KEY === '') return [];
    $tkType = tkFuelMap($fuelType);
    if ($tkType === null) return [];

    $rad = min(25, max(1, (float)$radiusKm));
    $url = 'https://creativecommons.tankerkoenig.de/json/list.php'
         . '?lat=' . rawurlencode($lat)
         . '&lng=' . rawurlencode($lon)
         . '&rad=' . rawurlencode($rad)
         . '&sort=dist&type=' . $tkType
         . '&apikey=' . TANKERKOENIG_KEY;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'FuelFinder/1.0',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!$resp || $code !== 200) return [];

    $data = json_decode($resp, true);
    if (empty($data['ok']) || empty($data['stations'])) return [];

    $out = [];
    foreach ($data['stations'] as $st) {
        $price = isset($st['price']) ? (float)$st['price'] : 0;
        if ($price <= 0) continue;

        $brand = trim($st['brand'] ?? '');
        if ($brand === '') $brand = 'Indipendente';
        $name  = trim($st['name'] ?? $brand);
        $addr  = trim(($st['street'] ?? '') . ' ' . ($st['houseNumber'] ?? ''))
               . ', ' . ($st['postCode'] ?? '') . ' ' . ($st['place'] ?? '');

        $out[] = [
            'id'          => $st['id'] ?? '',
            'brand'       => $brand,
            'name'        => $name,
            'addr'        => trim($addr, ', '),
            'lat'         => (float)($st['lat'] ?? 0),
            'lon'         => (float)($st['lng'] ?? 0),
            'price'       => $price,
            'fuelType'    => $fuelType,
            'insertDate'  => '',
            'country'     => 'DE',
            'isSelf'      => true,
            'distanceApi' => isset($st['dist']) ? (float)$st['dist'] : null,
        ];
    }
    return $out;
}
