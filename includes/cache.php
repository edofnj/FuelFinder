<?php
// Cache file-based semplice con TTL. Sharding per evitare dir troppo affollate.
// Serializzazione JSON (non unserialize) per evitare PHP object injection se
// la cache dir diventa scrivibile da un attacker.

define('CACHE_DIR', __DIR__ . '/../cache');

function cachePath($namespace, $key) {
    $hash = md5($key);
    $dir  = CACHE_DIR . '/' . $namespace . '/' . substr($hash, 0, 2);
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    return $dir . '/' . $hash . '.json';
}

function cacheGet($namespace, $key, $ttlSeconds) {
    $path = cachePath($namespace, $key);
    if (!file_exists($path)) return null;
    if (time() - filemtime($path) > $ttlSeconds) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $data = json_decode($raw, true);
    if ($data === null && json_last_error() !== JSON_ERROR_NONE) return null;
    return $data;
}

// Scrittura atomica: scrive su file temporaneo univoco e poi rename() (atomico
// sullo stesso filesystem). Evita che un lettore concorrente legga un file
// troncato a metà scrittura (che verrebbe trattato come miss → fetch ridondante).
function cacheSet($namespace, $key, $value) {
    $path = cachePath($namespace, $key);
    $tmp  = $path . '.' . uniqid('', true) . '.tmp';
    if (@file_put_contents($tmp, json_encode($value)) === false) {
        @unlink($tmp);
        return;
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $path)) @unlink($tmp);
}
