<?php
// Provider MIMIT (Italia). Ritorna stazioni in schema unificato.
// Schema: [id, brand, name, addr, lat, lon, price, fuelType, insertDate, country, isSelf]

function mimitSearch($lat, $lon, $radiusKm, $fuelType) {
    $anagrafica = caricaAnagrafica();

    if ($radiusKm > MIMIT_MAX_RADIUS) {
        // Cerchio di raggio R coperto da 7 cerchi di raggio R/2: uno al centro
        // e sei ai vertici di un esagono a distanza R·√3/2 (copertura esatta).
        $sub    = $radiusKm / 2;
        $points = [['lat' => $lat, 'lon' => $lon]];
        $dLat   = ($radiusKm * sqrt(3) / 2) / 111.0;
        $dLon   = $dLat / max(cos(deg2rad($lat)), 0.1);
        for ($k = 0; $k < 6; $k++) {
            $a = deg2rad(60 * $k);
            $points[] = ['lat' => $lat + $dLat * sin($a), 'lon' => $lon + $dLon * cos($a)];
        }
        $data = mimitZoneQueryMulti($points, $sub, $fuelType);
        $fromPivot = true; // distanze API relative ai sotto-centri: le ricalcola data.php
    } else {
        $data = mimitZoneQuery($lat, $lon, $radiusKm, $fuelType);
        $fromPivot = false;
    }
    if ($data === null) return []; // errore upstream: ospz_last_error già valorizzato

    // In alcune zone (es. Brennero, confine austriaco) /search/zone risponde
    // vuoto anche se ci sono impianti a pochi km. Se l'anagrafica ne conosce
    // almeno uno nel raggio, ripetiamo la ricerca centrata su di esso: data.php
    // filtra comunque per distanza dall'utente.
    if (empty($data['results'])) {
        $pivot = mimitNearestStation($lat, $lon, $radiusKm, $anagrafica);
        if ($pivot) {
            $retry = mimitZoneQuery($pivot['lat'], $pivot['lon'],
                min(MIMIT_MAX_RADIUS, $radiusKm + $pivot['dist']), $fuelType);
            if ($retry !== null) { $data = $retry; $fromPivot = true; }
        }
    }
    if (empty($data['results'])) return [];

    $fuelIdInt = (int)tipoToFuelId($fuelType);
    $out       = [];

    foreach ($data['results'] as $item) {
        if (!is_array($item['fuels'] ?? null) || !is_array($item['location'] ?? null)) continue;
        $brand = trim($item['brand'] ?? $item['name'] ?? '');

        // Prezzo per fuelId richiesto, preferisce self
        $prezzo = null; $isSelf = false;
        foreach ($item['fuels'] as $fuel) {
            if ((int)($fuel['fuelId'] ?? 0) !== $fuelIdInt) continue;
            if ($prezzo === null || (!empty($fuel['isSelf']) && !$isSelf)) {
                $prezzo = (float)($fuel['price'] ?? 0);
                $isSelf = (bool)($fuel['isSelf'] ?? false);
            }
        }
        if ($prezzo === null || $prezzo <= 0) continue;

        // Scarta prezzi > 3 giorni
        $insertDate = $item['insertDate'] ?? '';
        if ($insertDate) {
            $ts = strtotime($insertDate);
            if ($ts && (time() - $ts) > 86400 * 3) continue;
        }

        $impId = (int)($item['id'] ?? 0);
        // Coordinate segnaposto (condivise da impianti di comuni diversi):
        // distanza e posizione sarebbero inventate, meglio non mostrarlo.
        if ($impId && !empty($anagrafica[$impId]['bad_coord'])) continue;

        if ($impId && isset($anagrafica[$impId])) {
            $name = $anagrafica[$impId]['nome'];
            $addr = $anagrafica[$impId]['addr'];
        } else {
            $name = $item['name'] ?? $brand;
            $addr = !empty($item['address']) ? $item['address'] : 'Vedi mappa';
        }

        $sLat = (float)($item['location']['lat'] ?? 0);
        $sLon = (float)($item['location']['lng'] ?? 0);

        $out[] = [
            'id'         => $impId,
            'brand'      => $brand,
            'name'       => $name,
            'addr'       => $addr,
            'lat'        => $sLat,
            'lon'        => $sLon,
            'price'      => $prezzo,
            'fuelType'   => $fuelType,
            'insertDate' => $insertDate,
            'country'    => 'IT',
            'isSelf'     => $isSelf,
            // La distanza API è relativa al centro della query: dopo il
            // fallback il centro non è l'utente, quindi la ricalcola data.php.
            'distanceApi'=> (!$fromPivot && isset($item['distance'])) ? (float)$item['distance'] : null,
        ];
    }
    return $out;
}

function mimitZoneQuery($lat, $lon, $radiusKm, $fuelType) {
    $data = ospzPost('/search/zone', [
        'points'        => [['lat' => $lat, 'lng' => $lon]],
        'radius'        => $radiusKm,
        'fuelType'      => tipoToFuelId($fuelType),
        'refuelingMode' => 'x',
        'priceOrder'    => 'asc',
    ]);
    return is_array($data) ? $data : null;
}

// Più query /search/zone in parallelo (raggi > 10 km). Risultati uniti e
// deduplicati per id; null solo se falliscono tutte.
function mimitZoneQueryMulti(array $points, $radiusKm, $fuelType) {
    $mh = curl_multi_init();
    $handles = [];
    foreach ($points as $p) {
        $ch = curl_init(OSPZ_API . '/search/zone');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'points'        => [['lat' => $p['lat'], 'lng' => $p['lon']]],
                'radius'        => $radiusKm,
                'fuelType'      => tipoToFuelId($fuelType),
                'refuelingMode' => 'x',
                'priceOrder'    => 'asc',
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'User-Agent: FuelFinder/1.0'],
            CURLOPT_TIMEOUT        => OSPZ_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => OSPZ_CONNECTTIMEOUT,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[] = $ch;
    }
    do {
        curl_multi_exec($mh, $running);
        if ($running) curl_multi_select($mh, 0.05);
    } while ($running > 0);

    $byId = []; $ok = 0;
    foreach ($handles as $ch) {
        $resp = curl_multi_getcontent($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        $d = ($resp && $code === 200) ? json_decode($resp, true) : null;
        if (!is_array($d)) continue;
        $ok++;
        foreach (($d['results'] ?? []) as $item) {
            $key = $item['id'] ?? md5(json_encode($item['location'] ?? []));
            $byId[$key] = $item;
        }
    }
    curl_multi_close($mh);

    if ($ok === 0) {
        $GLOBALS['ospz_last_error'] = 'search/zone multi: nessuna risposta valida';
        error_log('OSPZ /search/zone multi FAIL (0/' . count($points) . ')');
        return null;
    }
    $GLOBALS['ospz_last_error'] = null;
    return ['results' => array_values($byId)];
}

// Impianto dell'anagrafica (con coordinate affidabili) più vicino al punto,
// entro il raggio. Usato solo nel fallback, quindi la scansione lineare
// (~22k righe) non pesa sul percorso normale.
function mimitNearestStation($lat, $lon, $radiusKm, array $anagrafica) {
    $best = null;
    foreach ($anagrafica as $a) {
        if (!isset($a['lat'], $a['lon']) || !empty($a['bad_coord'])) continue;
        if (abs($a['lat'] - $lat) > $radiusKm / 111.0) continue; // pre-filtro economico
        $d = mimitKm($lat, $lon, $a['lat'], $a['lon']);
        if ($d <= $radiusKm && ($best === null || $d < $best['dist'])) {
            $best = ['lat' => $a['lat'], 'lon' => $a['lon'], 'dist' => $d];
        }
    }
    return $best;
}

function mimitKm($lat1, $lon1, $lat2, $lon2) {
    $rad = M_PI / 180;
    $a = sin(($lat2 - $lat1) * $rad / 2) ** 2
       + cos($lat1 * $rad) * cos($lat2 * $rad) * sin(($lon2 - $lon1) * $rad / 2) ** 2;
    return 6371 * 2 * asin(min(1, sqrt($a)));
}
