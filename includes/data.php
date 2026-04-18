<?php
function getDistance($lat1, $lon1, $lat2, $lon2) {
    $rad = pi() / 180; $theta = $lon1 - $lon2;
    $dist = sin($lat1*$rad)*sin($lat2*$rad) + cos($lat1*$rad)*cos($lat2*$rad)*cos($theta*$rad);
    return acos(min(1, $dist)) * 6371;
}

// Sostituisce le distanze in linea d'aria con quelle reali su strada via OSRM.
// Lavora su max $limit candidati per tenere la richiesta piccola e veloce.
function applyRoadDistances($uLat, $uLon, &$results, $limit = 15) {
    if (empty($results)) return;
    $limit  = min(count($results), $limit);
    $coords = $uLon . ',' . $uLat;
    for ($i = 0; $i < $limit; $i++) {
        $coords .= ';' . $results[$i]['lon'] . ',' . $results[$i]['lat'];
    }
    $url = 'https://router.project-osrm.org/table/v1/driving/' . $coords
         . '?sources=0&annotations=distance&skip_waypoints=true';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 3,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'FuelFinder/1.0',
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if (!$resp) return;
    $data = json_decode($resp, true);
    if (!isset($data['distances'][0])) return;
    $row = $data['distances'][0];
    for ($i = 0; $i < $limit; $i++) {
        $dm = $row[$i + 1] ?? null;
        if ($dm !== null && $dm > 0) {
            $results[$i]['distanza'] = round($dm / 1000, 2);
        }
    }
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
    $fuelId   = tipoToFuelId($valTipo);

    $payload = [
        'points'        => [['lat' => $uLat, 'lng' => $uLon]],
        'radius'        => $uRaggio,
        'fuelType'      => $fuelId,
        'refuelingMode' => 'x',
        'priceOrder'    => 'asc',
    ];

    $data      = ospzPost('/search/zone', $payload);
    $anagrafica = caricaAnagrafica();

    if (empty($data['results'])) {
        $apiError = 'Nessun distributore trovato nella zona. Prova ad aumentare il raggio.';
    } else {
        foreach ($data['results'] as $item) {
            $brand = trim($item['brand'] ?? $item['name'] ?? '');

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

            // Trova il prezzo per il tipo di carburante richiesto (preferisce self)
            $prezzo   = null;
            $dataFmt  = '';
            $isSelf   = false;
            $fuelIdInt = (int)$fuelId;
            foreach ($item['fuels'] as $fuel) {
                if ((int)($fuel['fuelId'] ?? 0) !== $fuelIdInt) continue;
                if ($prezzo === null || ($fuel['isSelf'] && !$isSelf)) {
                    $prezzo  = (float)$fuel['price'];
                    $isSelf  = (bool)$fuel['isSelf'];
                }
            }
            if ($prezzo === null) continue;

            // Scarta prezzi più vecchi di 3 giorni
            $dataFmt = '';
            if (!empty($item['insertDate'])) {
                $ts = strtotime($item['insertDate']);
                if ($ts && (time() - $ts) > 86400 * 3) continue;
                if ($ts) $dataFmt = date('d/m/Y H:i', $ts);
            }

            $lat  = (float)($item['location']['lat'] ?? 0);
            $lon  = (float)($item['location']['lng'] ?? 0);
            $dist = round((float)($item['distance'] ?? getDistance($uLat, $uLon, $lat, $lon)), 2);

            $impId = (int)($item['id'] ?? 0);
            if ($impId && isset($anagrafica[$impId])) {
                $nomeDisplay = $anagrafica[$impId]['nome'] . ' (' . $brand . ')';
                $addr        = $anagrafica[$impId]['addr'];
            } else {
                $nomeDisplay = ($item['name'] ?? $brand) . ' (' . $brand . ')';
                $addr        = !empty($item['address']) ? $item['address'] : 'Vedi mappa';
            }

            $results[] = [
                'nome'     => htmlspecialchars($nomeDisplay),
                'marca'    => $brand,
                'addr'     => $addr,
                'distanza' => $dist,
                'prezzo'   => $prezzo,
                'lat'      => $lat,
                'lon'      => $lon,
                'data'     => $dataFmt,
                'is_sos'   => $isSOS,
                'modo'     => $valModo,
            ];
        }
    }

    if (!empty($results)) {
        usort($results, fn($a,$b) => $a['distanza'] <=> $b['distanza']);

        // SOS: basta il più vicino in linea d'aria, salta OSRM per massima velocità.
        // Modalità normale: corregge le distanze con quelle reali su strada (max 15 candidati).
        if (!$isSOS) {
            applyRoadDistances($uLat, $uLon, $results, 15);
        }

        $results = array_values(array_filter($results, fn($r) => $r['distanza'] <= $uRaggio));

        foreach ($results as &$r) {
            $litri_v = (($r['distanza'] * 2) / 100) * $uConsumo;
            $costo_v = $litri_v * $r['prezzo'];
            if ($valModo == 'litri') {
                $val_confronto = ($r['prezzo'] * $uQta) + $costo_v;
                $label = "Spesa Tot: EUR " . number_format($val_confronto, 2);
            } else {
                $val_confronto = ($uQta / $r['prezzo']) - $litri_v;
                $label = "Litri Netti: " . number_format($val_confronto, 2) . " L";
            }
            $spesa_carb = $valModo == 'litri' ? $r['prezzo'] * $uQta : $uQta;
            $r['litri_v']    = round($litri_v, 2);
            $r['costo_v']    = round($costo_v, 2);
            $r['spesa_carb'] = round($spesa_carb, 2);
            $r['totale']     = round($spesa_carb + $costo_v, 2);
            $r['valore']     = $val_confronto;
            $r['label']      = $isSOS ? "Prezzo: EUR " . number_format($r['prezzo'], 3) : $label;
        }
        unset($r);

        if ($isSOS) usort($results, fn($a,$b) => $a['distanza'] <=> $b['distanza']);
        elseif ($valModo == 'litri') usort($results, fn($a,$b) => $a['valore'] <=> $b['valore']);
        else usort($results, fn($a,$b) => $b['valore'] <=> $a['valore']);
    }
}
