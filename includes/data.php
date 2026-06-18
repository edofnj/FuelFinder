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

    // Fase 2: UNICA chiamata matrice a Valhalla self-hostato (/sources_to_targets)
    // sui soli miss. 1 sorgente (utente) -> N destinazioni (distributori).
    $targets  = [];
    $idxByPos = []; // posizione nella matrice => indice in $results
    foreach ($toFetch as $i => $key) {
        $idxByPos[] = $i;
        $targets[]  = ['lat' => $results[$i]['lat'], 'lon' => $results[$i]['lon']];
    }

    $payload = json_encode([
        'sources' => [['lat' => $uLat, 'lon' => $uLon]],
        'targets' => $targets,
        'costing' => 'auto',
        'units'   => 'kilometers',
    ]);
    $ch = curl_init('http://valhalla:8002/sources_to_targets');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp && $code === 200) {
        $data = json_decode($resp, true);
        $row  = is_array($data) ? ($data['sources_to_targets'][0] ?? []) : [];
        if (!is_array($row)) $row = [];
        foreach ($row as $pos => $cell) {
            if (!isset($idxByPos[$pos])) continue;
            $i    = $idxByPos[$pos];
            $dist = $cell['distance'] ?? null; // km (units=kilometers)
            if ($dist !== null && $dist > 0) {
                $km = round((float)$dist, 2);
                $results[$i]['distanza'] = $km;
                $results[$i]['road_ok']  = true;
                cacheSet('osrm', $toFetch[$i], $km);
            }
        }
    }
}

function searchCacheKey($uLat, $uLon, $raggio, $fuel) {
    return sprintf('%.3f,%.3f|r=%s|f=%s',
        round($uLat, 3), round($uLon, 3), $raggio, $fuel);
}

$results     = [];
$apiError    = null;
$valTipo     = $_POST['tipo']        ?? 'benzina';
$valConsumo  = $_POST['consumo']     ?? '';
$valQuantita = $_POST['quantita']    ?? '';
$valModo     = $_POST['modo']        ?? 'litri';
$valRaggio   = $_POST['raggio']      ?? '5';
$isSOS       = isset($_POST['sos_mode']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['calc']) || $isSOS)) {
    $uLat     = (float)$_POST['lat'];
    $uLon     = (float)$_POST['lon'];
    $uConsumo = empty($valConsumo)  ? 7.0  : (float)$valConsumo;
    $uQta     = empty($valQuantita) ? 20.0 : (float)$valQuantita;
    $uRaggio  = (float)$valRaggio;

    // Bounds check — coords devono essere valide; raggio 1..200 km; consumo+qta sensati.
    if ($uLat < -90 || $uLat > 90 || $uLon < -180 || $uLon > 180
        || $uRaggio < 1 || $uRaggio > 200
        || $uConsumo <= 0 || $uConsumo > 50
        || $uQta <= 0 || $uQta > 200) {
        http_response_code(400);
        die('Parametri non validi');
    }

    // Cache search completo (1h TTL): se hit, bypassa tutto (MIMIT + OSRM).
    // Non attivo in SOS (vogliamo sempre distanze fresche).
    $searchKey = searchCacheKey($uLat, $uLon, $uRaggio, $valTipo);
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
        applyRoadDistances($uLat, $uLon, $results, 20, $uRaggio);

        // Salva cache search (solo se non SOS e c'erano risultati)
        if (!$isSOS && $cachedStations === null && !empty($stations)) {
            cacheSet('search', $searchKey, $stations);
        }

        // prezzo > 0 difensivo: evita divisione per zero nel calcolo "litri netti"
        $results = array_values(array_filter($results, fn($r) => $r['distanza'] <= $uRaggio && $r['prezzo'] > 0));

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

    // Metrica: ricerca eseguita (search o SOS)
    $ffCC = ($uLat >= 47.2 && $uLon >= 5.8 && $uLon <= 15.1) ? 'DE' : 'IT';
    track($isSOS ? 'sos' : 'search', [
        'fuel'    => $valTipo,
        'radius'  => (int)$uRaggio,
        'mode'    => $valModo,
        'country' => $ffCC,
        'results' => count($results),
        'meta'    => ['cache_hit' => ($cachedStations !== null)],
    ]);
}
