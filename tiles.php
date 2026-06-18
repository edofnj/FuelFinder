<?php
// Proxy + cache dei tile mappa Geoapify. La chiave resta server-side (.env).
// La cache locale (TTL 30gg) riduce drasticamente le chiamate a Geoapify
// (ogni tile viene scaricato una volta sola) → resta nel free tier.
$z = isset($_GET['z']) ? (int)$_GET['z'] : -1;
$x = isset($_GET['x']) ? (int)$_GET['x'] : -1;
$y = isset($_GET['y']) ? (int)$_GET['y'] : -1;
if ($z < 0 || $z > 20 || $x < 0 || $y < 0 || $x >= (1 << $z) || $y >= (1 << $z)) {
    http_response_code(400); exit;
}

$style     = 'dark-matter'; // dark neutro coerente con la palette slate
$cacheDir  = __DIR__ . '/cache/tiles/' . $style . '/' . $z . '/' . $x;
$cacheFile = $cacheDir . '/' . $y . '.png';

if (is_file($cacheFile) && (time() - filemtime($cacheFile) < 30 * 86400)) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    readfile($cacheFile);
    exit;
}

$key = getenv('GEOAPIFY_KEY') ?: '';
if ($key === '') { http_response_code(502); exit; }

$url = "https://maps.geoapify.com/v1/tile/{$style}/{$z}/{$x}/{$y}.png?apiKey={$key}";
$ch  = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_USERAGENT      => 'FuelFinder/1.0',
]);
$png  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($png && $code === 200) {
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0750, true);
    $tmp = $cacheFile . '.' . uniqid('', true) . '.tmp';
    if (@file_put_contents($tmp, $png) !== false) {
        @chmod($tmp, 0640);
        if (!@rename($tmp, $cacheFile)) @unlink($tmp);
    }
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800');
    echo $png;
} else {
    http_response_code(502);
}
