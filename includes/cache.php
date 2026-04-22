<?php
// Cache file-based semplice con TTL. Sharding per evitare dir troppo affollate.

define('CACHE_DIR', __DIR__ . '/../cache');

function cachePath($namespace, $key) {
    $hash = md5($key);
    $dir  = CACHE_DIR . '/' . $namespace . '/' . substr($hash, 0, 2);
    if (!is_dir($dir)) @mkdir($dir, 0777, true);
    return $dir . '/' . $hash . '.cache';
}

function cacheGet($namespace, $key, $ttlSeconds) {
    $path = cachePath($namespace, $key);
    if (!file_exists($path)) return null;
    if (time() - filemtime($path) > $ttlSeconds) return null;
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    $data = @unserialize($raw);
    return $data === false ? null : $data;
}

function cacheSet($namespace, $key, $value) {
    $path = cachePath($namespace, $key);
    @file_put_contents($path, serialize($value), LOCK_EX);
}

// Batch helper per OSRM: accetta array di chiavi, ritorna [hit=>distance, miss=>[keys]]
function cacheGetBatch($namespace, array $keys, $ttlSeconds) {
    $hits = []; $misses = [];
    foreach ($keys as $k) {
        $v = cacheGet($namespace, $k, $ttlSeconds);
        if ($v === null) $misses[] = $k;
        else $hits[$k] = $v;
    }
    return [$hits, $misses];
}
