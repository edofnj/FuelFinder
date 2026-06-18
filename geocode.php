<?php
// Proxy geocoding/autocomplete verso Geoapify. La chiave resta server-side (.env).
// Restituisce un array in formato Nominatim-like così il frontend cambia pochissimo.
header('Content-Type: application/json');
header('Cache-Control: private, max-age=60');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 3) { echo '[]'; exit; }

$key = getenv('GEOAPIFY_KEY') ?: '';
if ($key === '') { echo '[]'; exit; }

$url = 'https://api.geoapify.com/v1/geocode/autocomplete'
     . '?text='   . rawurlencode($q)
     . '&filter=' . rawurlencode('countrycode:it,de')
     . '&limit=6&format=geojson&lang=it&apiKey=' . $key;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 6,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_USERAGENT      => 'FuelFinder/1.0',
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$out = [];
if ($resp && $code === 200) {
    $data = json_decode($resp, true);
    foreach (($data['features'] ?? []) as $f) {
        $p = $f['properties'] ?? [];
        if (!isset($p['lat'], $p['lon'])) continue;
        $city = $p['city'] ?? ($p['town'] ?? ($p['village'] ?? ($p['municipality'] ?? ($p['county'] ?? null))));
        $disp = $p['formatted'] ?? trim(($p['address_line1'] ?? '') . ', ' . ($p['address_line2'] ?? ''), ', ');
        $out[] = [
            'lat'          => (string)$p['lat'],
            'lon'          => (string)$p['lon'],
            'display_name' => $disp,
            'address'      => [
                'road'         => $p['street'] ?? null,
                'house_number' => isset($p['housenumber']) ? (string)$p['housenumber'] : null,
                'city'         => $city,
            ],
        ];
    }
}
echo json_encode($out);
