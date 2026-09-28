<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/api.php';

if (!function_exists('getDistance')) {
    function getDistance($lat1, $lon1, $lat2, $lon2) {
        $rad = pi() / 180; $theta = $lon1 - $lon2;
        $dist = sin($lat1*$rad)*sin($lat2*$rad) + cos($lat1*$rad)*cos($lat2*$rad)*cos($theta*$rad);
        return acos(min(1, $dist)) * 6371;
    }
}

function decodePolyline6(string $encoded): array {
    $coords = []; $index = 0; $len = strlen($encoded); $lat = 0; $lng = 0;
    while ($index < $len) {
        $shift = 0; $result = 0;
        do { $b = ord($encoded[$index++]) - 63; $result |= ($b & 0x1f) << $shift; $shift += 5; } while ($b >= 0x20);
        $lat += ($result & 1) ? ~($result >> 1) : ($result >> 1);
        $shift = 0; $result = 0;
        do { $b = ord($encoded[$index++]) - 63; $result |= ($b & 0x1f) << $shift; $shift += 5; } while ($b >= 0x20);
        $lng += ($result & 1) ? ~($result >> 1) : ($result >> 1);
        $coords[] = [$lng / 1e6, $lat / 1e6]; // GeoJSON [lon, lat]
    }
    return $coords;
}

function routeDetectCountry($lat, $lon) {
    if ($lat >= 35.4 && $lat <= 47.2 && $lon >= 6.5 && $lon <= 18.6) return 'IT';
    if ($lat >= 47.2 && $lat <= 55.1 && $lon >= 5.8 && $lon <= 15.1) return 'DE';
    return null;
}

function getOsrmRoute($fromLat, $fromLon, $toLat, $toLon): ?array {
    $cacheKey = sprintf('osrm_%.4f,%.4f|%.4f,%.4f',
        round($fromLat, 4), round($fromLon, 4),
        round($toLat,   4), round($toLon,   4));
    $cached = cacheGet('route', $cacheKey, 2592000);
    if ($cached !== null) return $cached;

    $url = 'https://router.project-osrm.org/route/v1/driving/'
         . $fromLon . ',' . $fromLat . ';'
         . $toLon   . ',' . $toLat
         . '?overview=full&geometries=geojson&alternatives=false&steps=false';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'FuelFinder/1.0',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$resp || $code !== 200) return null;
    $data = json_decode($resp, true);
    if (empty($data['routes'][0])) return null;

    $route  = $data['routes'][0];
    $coords = $route['geometry']['coordinates'] ?? [];
    if (empty($coords)) return null;

    $result = [
        'coords'       => $coords,
        'distance_km'  => round($route['distance'] / 1000, 2),
        'duration_min' => (int)round($route['duration'] / 60),
    ];
    cacheSet('route', $cacheKey, $result);
    return $result;
}

function getValhallaRoute($fromLat, $fromLon, $toLat, $toLon, array $exclude = []): ?array {
    $noTolls    = in_array('toll',     $exclude) ? 0 : 1;
    $noHighways = in_array('motorway', $exclude) ? 0 : 1;

    $cacheKey = sprintf('vh_%.4f,%.4f|%.4f,%.4f|t%d_h%d',
        round($fromLat, 4), round($fromLon, 4),
        round($toLat,   4), round($toLon,   4),
        $noTolls, $noHighways);
    $cached = cacheGet('route', $cacheKey, 2592000);
    if ($cached !== null) return $cached;

    $payload = json_encode([
        'locations' => [
            ['lon' => $fromLon, 'lat' => $fromLat],
            ['lon' => $toLon,   'lat' => $toLat],
        ],
        'costing'         => 'auto',
        'costing_options' => ['auto' => [
            'use_tolls'    => $noTolls,
            'use_highways' => $noHighways,
        ]],
    ]);

    $ch = curl_init(VALHALLA_URL . '/route');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => 'FuelFinder/1.0',
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!$resp || $code !== 200) return null;
    $data = json_decode($resp, true);
    if (!is_array($data)) return null;

    $shapeStr = $data['trip']['legs'][0]['shape'] ?? '';
    if (!is_string($shapeStr) || $shapeStr === '') return null;
    $coords = decodePolyline6($shapeStr);
    if (empty($coords)) return null;

    $result = [
        'coords'       => $coords,  // GeoJSON [lon, lat] — same convention as OSRM
        'distance_km'  => round((float)($data['trip']['summary']['length'] ?? 0), 2),
        'duration_min' => (int)round(($data['trip']['summary']['time'] ?? 0) / 60),
    ];
    cacheSet('route', $cacheKey, $result);
    return $result;
}

function getRoute($fromLat, $fromLon, $toLat, $toLon, array $exclude = []): ?array {
    // Valhalla self-hostato gestisce sia il percorso base sia evita pedaggi/autostrade.
    // (getOsrmRoute resta definita ma non più usata: niente dipendenza dal demo OSRM.)
    return getValhallaRoute($fromLat, $fromLon, $toLat, $toLon, $exclude);
}

function sampleRouteWaypoints(array $coords, float $stepKm = 8.0): array {
    if (empty($coords)) return [];

    $waypoints   = [];
    $accumulated = 0.0;
    $prev        = $coords[0];
    // coords are [lon, lat] — GeoJSON order
    $waypoints[] = ['lat' => (float)$prev[1], 'lon' => (float)$prev[0]];

    $n = count($coords);
    for ($i = 1; $i < $n; $i++) {
        $cur  = $coords[$i];
        $seg  = getDistance((float)$prev[1], (float)$prev[0], (float)$cur[1], (float)$cur[0]);
        $accumulated += $seg;

        if ($accumulated >= $stepKm) {
            $waypoints[] = ['lat' => (float)$cur[1], 'lon' => (float)$cur[0]];
            $accumulated = 0.0;
        }
        $prev = $cur;
    }

    // Always include last point if not already included
    $last    = $coords[$n - 1];
    $lastWp  = end($waypoints);
    if (abs($lastWp['lat'] - (float)$last[1]) > 0.001 || abs($lastWp['lon'] - (float)$last[0]) > 0.001) {
        $waypoints[] = ['lat' => (float)$last[1], 'lon' => (float)$last[0]];
    }

    return $waypoints;
}

// Primi $km chilometri della polilinea (coords GeoJSON [lon, lat]).
function routeHead(array $coords, float $km): array {
    $out = []; $acc = 0.0; $prev = null;
    foreach ($coords as $c) {
        if ($prev !== null) {
            $acc += getDistance((float)$prev[1], (float)$prev[0], (float)$c[1], (float)$c[0]);
            if ($acc > $km) { $out[] = $c; break; }
        }
        $out[] = $c;
        $prev  = $c;
    }
    return $out;
}

function stationRouteInfo(float $sLat, float $sLon, array $routeCoords): array {
    $minDist     = PHP_FLOAT_MAX;
    $bestKmAlong = 0.0;
    $kmAlong     = 0.0;
    $n           = count($routeCoords);

    for ($i = 0; $i < $n - 1; $i++) {
        // GeoJSON: [lon, lat]
        $aLon = (float)$routeCoords[$i][0];     $aLat = (float)$routeCoords[$i][1];
        $bLon = (float)$routeCoords[$i + 1][0]; $bLat = (float)$routeCoords[$i + 1][1];

        $segLen = getDistance($aLat, $aLon, $bLat, $bLon);

        // Correct for latitude distortion so dot-product works in km-ish units
        $avgLat = ($aLat + $bLat) / 2.0;
        $cosLat = cos(deg2rad($avgLat));

        $dLonKm = ($bLon - $aLon) * $cosLat;
        $dLatKm = $bLat - $aLat;

        $pLonKm = ($sLon - $aLon) * $cosLat;
        $pLatKm = $sLat - $aLat;

        $len2 = $dLonKm * $dLonKm + $dLatKm * $dLatKm;
        if ($len2 > 0) {
            $t = ($pLonKm * $dLonKm + $pLatKm * $dLatKm) / $len2;
            $t = max(0.0, min(1.0, $t));
        } else {
            $t = 0.0;
        }

        $cLat = $aLat + $t * ($bLat - $aLat);
        $cLon = $aLon + $t * ($bLon - $aLon);

        $dist = getDistance($sLat, $sLon, $cLat, $cLon);

        if ($dist < $minDist) {
            $minDist     = $dist;
            $bestKmAlong = $kmAlong + $t * $segLen;
        }

        $kmAlong += $segLen;
    }

    // Also check last point
    if ($n > 0) {
        $lastLon = (float)$routeCoords[$n - 1][0];
        $lastLat = (float)$routeCoords[$n - 1][1];
        $dist    = getDistance($sLat, $sLon, $lastLat, $lastLon);
        if ($dist < $minDist) {
            $minDist     = $dist;
            $bestKmAlong = $kmAlong;
        }
    }

    return [
        'perp_dist' => round($minDist, 3),
        'km_along'  => round($bestKmAlong, 1),
    ];
}

// MIMIT applica un rate limit per IP (~20 richieste ravvicinate, poi 429).
// Le richieste partono quindi a piccoli blocchi con pausa, e le zone fallite
// vengono ritentate una volta più lentamente. $failed riporta quante zone
// restano scoperte, così la UI può avvisare di risultati parziali invece di
// mostrare in silenzio solo i primi km del percorso.
function routeSearchMimit(array $waypoints, float $radiusKm, string $fuelType, ?int &$failed = null): array {
    $failed = 0;
    if (empty($waypoints)) return [];

    $ttl       = 3600;
    $namespace = 'routepts';
    $all       = []; // sid => station
    $toFetch   = []; // index => waypoint

    // Phase 1: cache check
    foreach ($waypoints as $idx => $wp) {
        $cached = cacheGet($namespace, routePointCacheKey($wp, $radiusKm, $fuelType), $ttl);
        if ($cached !== null) {
            foreach ($cached as $sid => $station) {
                if (!isset($all[$sid])) $all[$sid] = $station;
            }
        } else {
            $toFetch[$idx] = $wp;
        }
    }

    if (empty($toFetch)) return array_values($all);

    $anagrafica = caricaAnagrafica();

    // Phase 2: finestra scorrevole (max 8 richieste attive, partenze
    // distanziate) con scadenza complessiva; le zone fallite vengono
    // ritentate una volta, più lentamente, se resta tempo.
    $deadline = microtime(true) + 30;
    $pending  = routeMimitFetch($toFetch, $radiusKm, $fuelType, $anagrafica, $all, 8, 0.2, $deadline);
    if (!empty($pending) && $deadline - microtime(true) > 5) {
        usleep(1500000);
        $pending = routeMimitFetch($pending, $radiusKm, $fuelType, $anagrafica, $all, 2, 0.6, $deadline);
    }

    $failed = count($pending);
    return array_values($all);
}

function routePointCacheKey(array $wp, float $radiusKm, string $fuelType): string {
    return sprintf('rpt_%.3f_%.3f_%.0f_%s',
        round($wp['lat'], 3), round($wp['lon'], 3), $radiusKm, $fuelType);
}

// Interroga MIMIT per i waypoint dati con al massimo $concurrency richieste
// attive e almeno $spacing secondi tra una partenza e l'altra (rate limit).
// Aggiunge le stazioni trovate a $all, mette in cache le risposte valide e
// ritorna i waypoint falliti (429, timeout, non partiti entro $deadline).
function routeMimitFetch(array $toFetch, float $radiusKm, string $fuelType, array $anagrafica,
                         array &$all, int $concurrency, float $spacing, float $deadline): array {
    $fuelId    = tipoToFuelId($fuelType);
    $fuelIdInt = (int)$fuelId;
    $failed    = [];
    $queue     = $toFetch;   // idx => waypoint ancora da avviare
    $active    = [];         // (int)handle => idx
    $handles   = [];         // (int)handle => handle
    $lastStart = 0.0;
    $mh        = curl_multi_init();

    while ($queue || $active) {
        $now = microtime(true);

        // Avvia nuove richieste se c'è posto, è passato $spacing e c'è tempo
        // per completarle prima della scadenza.
        if ($queue && count($active) < $concurrency && $now - $lastStart >= $spacing) {
            if ($deadline - $now < 3) {
                $failed += $queue; // tempo finito: non partono più
                $queue = [];
            } else {
                $idx = array_key_first($queue);
                $wp  = $queue[$idx];
                unset($queue[$idx]);
                $ch = curl_init(OSPZ_API . '/search/zone');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => json_encode([
                        'points'        => [['lat' => $wp['lat'], 'lng' => $wp['lon']]],
                        'radius'        => $radiusKm,
                        'fuelType'      => $fuelId,
                        'refuelingMode' => 'x',
                        'priceOrder'    => 'asc',
                    ]),
                    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'User-Agent: FuelFinder/1.0'],
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT        => (int)max(3, min(20, $deadline - $now)),
                ]);
                curl_multi_add_handle($mh, $ch);
                $active[(int)$ch]  = $idx;
                $handles[(int)$ch] = $ch;
                $lastStart = $now;
                continue;
            }
        }

        if (!$active) { usleep(20000); continue; } // in attesa dello spacing
        curl_multi_exec($mh, $running);
        curl_multi_select($mh, 0.05);

        while ($info = curl_multi_info_read($mh)) {
            $ch   = $info['handle'];
            $idx  = $active[(int)$ch];
            unset($active[(int)$ch], $handles[(int)$ch]);
            $resp = curl_multi_getcontent($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $secs = curl_getinfo($ch, CURLINFO_TOTAL_TIME);
            $cerr = curl_error($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            $data = ($resp && $code === 200) ? json_decode($resp, true) : null;
            if (!is_array($data)) {
                error_log(sprintf('OSPZ route zone FAIL code=%d t=%.1fs err=%s', $code, $secs, $cerr ?: '-'));
                // Niente cache su errore: un 429 non deve avvelenare la cache
                // con risultati vuoti per tutto il TTL.
                $failed[$idx] = $toFetch[$idx];
                continue;
            }

            $wpResult = [];
            foreach (($data['results'] ?? []) as $item) {
                if (!is_array($item['fuels'] ?? null) || !is_array($item['location'] ?? null)) continue;
                $brand = trim($item['brand'] ?? $item['name'] ?? '');

                $prezzo = null; $isSelf = false;
                foreach ($item['fuels'] as $fuel) {
                    if ((int)($fuel['fuelId'] ?? 0) !== $fuelIdInt) continue;
                    if ($prezzo === null || (!$isSelf && (bool)($fuel['isSelf'] ?? false))) {
                        $prezzo = (float)$fuel['price'];
                        $isSelf = (bool)($fuel['isSelf'] ?? false);
                    }
                }
                if ($prezzo === null || $prezzo <= 0) continue;

                $insertDate = $item['insertDate'] ?? '';
                if ($insertDate) {
                    $ts = strtotime($insertDate);
                    if ($ts && (time() - $ts) > 86400 * 3) continue;
                }

                $impId = (int)($item['id'] ?? 0);
                // Coordinate segnaposto: km lungo percorso e deviazione inventati.
                if ($impId && !empty($anagrafica[$impId]['bad_coord'])) continue;

                if ($impId && isset($anagrafica[$impId])) {
                    $name = $anagrafica[$impId]['nome'];
                    $addr = $anagrafica[$impId]['addr'];
                } else {
                    $name = $item['name'] ?? $brand;
                    $addr = !empty($item['address']) ? $item['address'] : 'Vedi mappa';
                }

                $sid = $impId ? (string)$impId
                    : ((float)($item['location']['lat'] ?? 0)) . '_' . ((float)($item['location']['lng'] ?? 0));

                $station = [
                    'id'      => $sid,
                    'brand'   => $brand,
                    'name'    => $name,
                    'addr'    => $addr,
                    'lat'     => (float)($item['location']['lat'] ?? 0),
                    'lon'     => (float)($item['location']['lng'] ?? 0),
                    'price'   => $prezzo,
                    'fuelType'=> $fuelType,
                    'insDate' => $insertDate,
                    'country' => 'IT',
                ];

                $wpResult[$sid] = $station;
                if (!isset($all[$sid])) $all[$sid] = $station;
            }
            cacheSet('routepts', routePointCacheKey($toFetch[$idx], $radiusKm, $fuelType), $wpResult);
        }
    }

    curl_multi_close($mh);
    return $failed;
}

function routeSearchTK(array $waypoints, float $radiusKm, string $fuelType): array {
    if (!defined('TANKERKOENIG_KEY') || TANKERKOENIG_KEY === '') return [];

    $tkType = null;
    if (strtolower($fuelType) === 'benzina') $tkType = 'e5';
    elseif (strtolower($fuelType) === 'gasolio') $tkType = 'diesel';
    else return [];

    $rad       = min(25, max(1, $radiusKm));
    $ttl       = 3600;
    $namespace = 'routepts';
    $all       = []; // sid => station

    foreach ($waypoints as $wp) {
        $key    = sprintf('rpt_de_%.3f_%.3f_%.0f_%s',
            round($wp['lat'], 3), round($wp['lon'], 3), $rad, $fuelType);
        $cached = cacheGet($namespace, $key, $ttl);
        if ($cached !== null) {
            foreach ($cached as $sid => $station) {
                if (!isset($all[$sid])) $all[$sid] = $station;
            }
            continue;
        }

        $url = 'https://creativecommons.tankerkoenig.de/json/list.php'
             . '?lat=' . rawurlencode($wp['lat'])
             . '&lng=' . rawurlencode($wp['lon'])
             . '&rad=' . rawurlencode($rad)
             . '&sort=dist&type=' . $tkType
             . '&apikey=' . TANKERKOENIG_KEY;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT      => 'FuelFinder/1.0',
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $wpResult = [];
        $okResp   = false;

        if ($resp && $code === 200) {
            $data = json_decode($resp, true);
            if (is_array($data) && !empty($data['ok'])) {
                $okResp = true;
                foreach (($data['stations'] ?? []) as $st) {
                    $price = isset($st['price']) ? (float)$st['price'] : 0;
                    if ($price <= 0) continue;

                    $sid   = (string)($st['id'] ?? '');
                    $brand = trim($st['brand'] ?? '');
                    if ($brand === '') $brand = 'Indipendente';
                    $name  = trim($st['name'] ?? $brand);
                    $addr  = trim(($st['street'] ?? '') . ' ' . ($st['houseNumber'] ?? ''))
                           . ', ' . ($st['postCode'] ?? '') . ' ' . ($st['place'] ?? '');

                    $station = [
                        'id'      => $sid,
                        'brand'   => $brand,
                        'name'    => $name,
                        'addr'    => trim($addr, ', '),
                        'lat'     => (float)($st['lat'] ?? 0),
                        'lon'     => (float)($st['lng'] ?? 0),
                        'price'   => $price,
                        'fuelType'=> $fuelType,
                        'insDate' => '',
                        'country' => 'DE',
                    ];

                    $wpResult[$sid] = $station;
                    if (!isset($all[$sid])) $all[$sid] = $station;
                }
            }
        }

        if ($okResp) cacheSet($namespace, $key, $wpResult);
    }

    return array_values($all);
}
