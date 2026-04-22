<?php
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/providers/mimit.php';
require_once __DIR__ . '/providers/tankerkoenig.php';
require_once __DIR__ . '/providers/router.php';

function getDistance($lat1, $lon1, $lat2, $lon2) {
    $rad = pi() / 180; $theta = $lon1 - $lon2;
    $dist = sin($lat1*$rad)*sin($lat2*$rad) + cos($lat1*$rad)*cos($lat2*$rad)*cos($theta*$rad);
    return acos(min(1, $dist)) * 6371;
}

// Chiave cache OSRM: arrotonda coord a ~50m per favorire hit ripetuti.
// ~50m = 4 decimali lat (0.00045° ≈ 50m), 4 decimali lon.
function osrmCacheKey($uLat, $uLon, $sLat, $sLon) {
    return sprintf('%.4f,%.4f|%.4f,%.4f',
        round($uLat, 4), round($uLon, 4),
        round($sLat, 4), round($sLon, 4));
}

// Calcola distanze stradali reali via OSRM /route in parallelo (curl_multi).
// Con cache file 30gg e pre-filtro 1.8x linea d'aria.
function applyRoadDistances($uLat, $uLon, &$results, $concurrency = 20, $uRaggio = null) {
    if (empty($results)) return;
    $total = count($results);
    foreach ($results as &$r) $r['road_ok'] = false;
    unset($r);

    $ttl = 30 * 86400; // 30 giorni
    $toFetch = []; // indice => true

    // Fase 1: cache hit + pre-filtro
    for ($i = 0; $i < $total; $i++) {
        // Pre-filtro: se linea d'aria > raggio * 1.8, salta OSRM (improbabile dentro raggio strada)
        if ($uRaggio !== null) {
            $airDist = getDistance($uLat, $uLon, $results[$i]['lat'], $results[$i]['lon']);
            if ($airDist > $uRaggio * 1.8) {
                $results[$i]['distanza'] = round($airDist, 2); // resta linea d'aria, filtro raggio la eliminerà
                continue;
            }
        }
        $key = osrmCacheKey($uLat, $uLon, $results[$i]['lat'], $results[$i]['lon']);
        $cached = cacheGet('osrm', $key, $ttl);
        if ($cached !== null && $cached > 0) {
            $results[$i]['distanza'] = $cached;
            $results[$i]['road_ok']  = true;
        } else {
            $toFetch[$i] = $key;
        }
    }
    if (empty($toFetch)) return;

    // Fase 2: chiamate OSRM parallele solo sui miss
    $mh = curl_multi_init();
    $handles = [];
    foreach ($toFetch as $i => $key) {
        $url = 'https://router.project-osrm.org/route/v1/driving/'
             . $uLon . ',' . $uLat . ';'
             . $results[$i]['lon'] . ',' . $results[$i]['lat']
             . '?overview=false&alternatives=false&steps=false';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'FuelFinder/1.0',
        ]);
        $handles[$i] = $ch;
    }

    $indices = array_keys($handles);
    $numFetch = count($indices);
    $pos = 0;
    while ($pos < $numFetch) {
        $windowEnd = min($pos + $concurrency, $numFetch);
        for ($k = $pos; $k < $windowEnd; $k++) curl_multi_add_handle($mh, $handles[$indices[$k]]);

        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh, 0.1);
        } while ($running > 0);

        $failed = [];
        for ($k = $pos; $k < $windowEnd; $k++) {
            $i = $indices[$k];
            $resp = curl_multi_getcontent($handles[$i]);
            $code = curl_getinfo($handles[$i], CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $handles[$i]);
            curl_close($handles[$i]);
            $okThis = false;
            if ($resp && $code === 200) {
                $data = json_decode($resp, true);
                $dm = $data['routes'][0]['distance'] ?? null;
                if ($dm !== null && $dm > 0) {
                    $km = round($dm / 1000, 2);
                    $results[$i]['distanza'] = $km;
                    $results[$i]['road_ok']  = true;
                    cacheSet('osrm', $toFetch[$i], $km);
                    $okThis = true;
                }
            }
            if (!$okThis) $failed[] = $i;
        }
        $pos = $windowEnd;

        // Retry una volta sui fallimenti del batch (seriale, timeout breve)
        foreach ($failed as $i) {
            $url = 'https://router.project-osrm.org/route/v1/driving/'
                 . $uLon . ',' . $uLat . ';'
                 . $results[$i]['lon'] . ',' . $results[$i]['lat']
                 . '?overview=false&alternatives=false&steps=false';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 8,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_USERAGENT      => 'FuelFinder/1.0',
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($resp && $code === 200) {
                $data = json_decode($resp, true);
                $dm = $data['routes'][0]['distance'] ?? null;
                if ($dm !== null && $dm > 0) {
                    $km = round($dm / 1000, 2);
                    $results[$i]['distanza'] = $km;
                    $results[$i]['road_ok']  = true;
                    cacheSet('osrm', $toFetch[$i], $km);
                }
            }
        }
    }
    curl_multi_close($mh);
}

// Chiave cache search: coord a ~100m + raggio + fuel + marche.
function searchCacheKey($uLat, $uLon, $raggio, $fuel, $marche) {
    $m = $marche ? implode(',', $marche) : '';
    return sprintf('%.3f,%.3f|r=%s|f=%s|m=%s',
        round($uLat, 3), round($uLon, 3), $raggio, $fuel, $m);
}

$results     = [];
$apiError    = null;
$valTipo     = $_POST['tipo']        ?? 'benzina';
$valConsumo  = $_POST['consumo']     ?? '';
$valQuantita = $_POST['quantita']    ?? '';
$valModo     = $_POST['modo']        ?? 'litri';
$valRaggio   = $_POST['raggio']      ?? '5';
$valMarche   = (!empty($_POST['marche_json'])) ? json_decode($_POST['marche_json'], true) : [];
$isSOS       = isset($_POST['sos_mode']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['calc']) || $isSOS)) {
    $uLat     = (float)$_POST['lat'];
    $uLon     = (float)$_POST['lon'];
    $uConsumo = empty($valConsumo)  ? 7.0  : (float)$valConsumo;
    $uQta     = empty($valQuantita) ? 20.0 : (float)$valQuantita;
    $uRaggio  = (float)$valRaggio;

    // Cache search completo (1h TTL): se hit, bypassa tutto (MIMIT + OSRM).
    // Non attivo in SOS (vogliamo sempre distanze fresche) e quando ci sono marche filtrate (troppe chiavi).
    $searchKey = searchCacheKey($uLat, $uLon, $uRaggio, $valTipo, $valMarche);
    $searchCacheTTL = 3600;
    $stations = null;
    $cachedStations = $isSOS ? null : cacheGet('search', $searchKey, $searchCacheTTL);
    if ($cachedStations !== null) {
        $stations = $cachedStations;
    } else {
        // Interroga tutti i provider rilevanti (paese principale + cross-border)
        $countries = countriesInRange($uLat, $uLon, $uRaggio);
        $stations  = [];
        foreach ($countries as $cc) {
            $part = searchByCountry($cc, $uLat, $uLon, $uRaggio, $valTipo);
            if ($part) $stations = array_merge($stations, $part);
        }
    }

    if (empty($stations)) {
        $apiError = t('err_no_stations');
    } else {
        foreach ($stations as $s) {
            $brand = $s['brand'];

            // Filtro marche
            if (!empty($valMarche)) {
                $match = false;
                foreach ($valMarche as $m) {
                    if (stripos($brand, $m) !== false || stripos($m, $brand) !== false) {
                        $match = true; break;
                    }
                }
                if (!$match) continue;
            }

            $dataFmt = '';
            if (!empty($s['insertDate'])) {
                $ts = strtotime($s['insertDate']);
                if ($ts) $dataFmt = date('d/m/Y H:i', $ts);
            }

            $lat = $s['lat']; $lon = $s['lon'];
            $dist = $s['distanceApi'] !== null
                ? round($s['distanceApi'], 2)
                : round(getDistance($uLat, $uLon, $lat, $lon), 2);

            $nomeDisplay = $s['name'] . ' (' . $brand . ')';

            $results[] = [
                'nome'     => htmlspecialchars($nomeDisplay),
                'marca'    => $brand,
                'addr'     => $s['addr'],
                'distanza' => $dist,
                'prezzo'   => $s['price'],
                'lat'      => $lat,
                'lon'      => $lon,
                'data'     => $dataFmt,
                'country'  => $s['country'],
                'is_sos'   => $isSOS,
                'modo'     => $valModo,
            ];
        }
    }

    if (!empty($results)) {
        usort($results, fn($a,$b) => $a['distanza'] <=> $b['distanza']);

        // Cap: max 40 stazioni passate a OSRM (le più vicine in linea d'aria).
        // Evita chiamate inutili quando la zona è super densa (Monaco, Milano).
        if (count($results) > 40) $results = array_slice($results, 0, 40);

        // Distanze reali su strada su tutti i risultati (anche in SOS).
        applyRoadDistances($uLat, $uLon, $results, 10, $uRaggio);

        // Salva cache search (solo se non SOS e c'erano risultati)
        if (!$isSOS && $cachedStations === null && !empty($stations)) {
            cacheSet('search', $searchKey, $stations);
        }

        $results = array_values(array_filter($results, fn($r) => $r['distanza'] <= $uRaggio));

        foreach ($results as &$r) {
            $litri_v = (($r['distanza'] * 2) / 100) * $uConsumo;
            $costo_v = $litri_v * $r['prezzo'];
            if ($valModo == 'litri') {
                $val_confronto = ($r['prezzo'] * $uQta) + $costo_v;
                $label = t('total') . ": EUR " . number_format($val_confronto, 2);
            } else {
                $val_confronto = ($uQta / $r['prezzo']) - $litri_v;
                $label = t('net_liters') . ": " . number_format($val_confronto, 2) . " L";
            }
            $spesa_carb = $valModo == 'litri' ? $r['prezzo'] * $uQta : $uQta;
            $r['litri_v']    = round($litri_v, 2);
            $r['costo_v']    = round($costo_v, 2);
            $r['spesa_carb'] = round($spesa_carb, 2);
            $r['totale']     = round($spesa_carb + $costo_v, 2);
            $r['valore']     = $val_confronto;
            $r['label']      = $isSOS ? "EUR " . number_format($r['prezzo'], 3) : $label;
        }
        unset($r);

        if ($isSOS) usort($results, fn($a,$b) => $a['distanza'] <=> $b['distanza']);
        elseif ($valModo == 'litri') usort($results, fn($a,$b) => $a['valore'] <=> $b['valore']);
        else usort($results, fn($a,$b) => $b['valore'] <=> $a['valore']);
    }
}
