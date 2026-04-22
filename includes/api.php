<?php
// Scarica il CSV anagrafica se mancante o più vecchio di 24h
function aggiornaAnagrafica() {
    if (file_exists(FILE_ANAGRAFICA) && (time() - filemtime(FILE_ANAGRAFICA) < 86400)) return;
    $ch = curl_init(URL_ANAGRAFICA);
    $fp = fopen(FILE_ANAGRAFICA, 'wb');
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'FuelFinder/1.0',
    ]);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}

// Carica idImpianto → [nome, addr] dal CSV anagrafica.
// Usa una cache serializzata per evitare di parsare 23k righe ad ogni richiesta.
function caricaAnagrafica() {
    $cacheFile = FILE_ANAGRAFICA . '.ser';

    // Cache valida: esiste ed è più recente del CSV
    if (file_exists($cacheFile) && file_exists(FILE_ANAGRAFICA)
        && filemtime($cacheFile) >= filemtime(FILE_ANAGRAFICA)) {
        $map = unserialize(file_get_contents($cacheFile));
        if (is_array($map)) return $map;
    }

    // Rigenera cache dal CSV
    $map = [];
    if (!file_exists(FILE_ANAGRAFICA)) return $map;
    $h = fopen(FILE_ANAGRAFICA, 'r');
    if (!$h) return $map;
    $riga = 0;
    while (($line = fgets($h)) !== false) {
        $riga++;
        if ($riga <= 2) continue; // salta "Estrazione del..." e header
        $d = explode('|', rtrim($line, "\r\n"));
        if (count($d) < 8 || !is_numeric(trim($d[0]))) continue;
        $id  = (int)trim($d[0]);
        $map[$id] = [
            'nome' => trim($d[4]),
            'addr' => trim($d[5]) . ', ' . trim($d[6]) . ' (' . trim($d[7]) . ')',
        ];
    }
    fclose($h);
    file_put_contents($cacheFile, serialize($map));
    return $map;
}

// Avvia aggiornamento asincrono dell'anagrafica (non blocca la request)
aggiornaAnagrafica();

// Endpoint brands: cache 24h
if (isset($_GET['get_brands'])) {
    require_once __DIR__ . '/cache.php';
    $cached = cacheGet('brands', 'all', 86400);
    if ($cached !== null) {
        header('Content-Type: application/json');
        header('X-Cache: HIT');
        echo json_encode($cached);
        exit;
    }
    $data = ospzGet('/registry/brands');
    $brands = [];
    if (!empty($data['results'])) {
        foreach ($data['results'] as $b) {
            $name = trim($b['description'] ?? '');
            if ($name !== '') $brands[] = $name;
        }
        sort($brands);
    }
    if (!empty($brands)) cacheSet('brands', 'all', array_values($brands));
    header('Content-Type: application/json');
    header('X-Cache: MISS');
    echo json_encode(array_values($brands));
    exit;
}

// Mappa tipo carburante → fuelId API MIMIT
function tipoToFuelId($tipo) {
    $map = [
        'benzina' => '1',
        'gasolio' => '2',
        'gpl'     => '4',
        'metano'  => '3',
    ];
    return $map[strtolower($tipo)] ?? '1';
}
