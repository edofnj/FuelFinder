<?php
// Scarica il CSV anagrafica se mancante o più vecchio di 24h.
// - Lock non bloccante: un solo processo scarica (no thundering herd).
// - Download su file temporaneo + rename atomico: nessun lettore vede un CSV
//   scaricato a metà, e un download fallito NON corrompe più l'anagrafica
//   esistente (il vecchio comportamento apriva il file reale in 'wb',
//   troncandolo subito anche quando il download falliva).
function aggiornaAnagrafica() {
    if (file_exists(FILE_ANAGRAFICA) && (time() - filemtime(FILE_ANAGRAFICA) < 86400)) return;

    $lock = @fopen(FILE_ANAGRAFICA . '.lock', 'c');
    if (!$lock) return;
    if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return; } // altro processo sta già scaricando

    // Ricontrolla dopo il lock: un altro processo potrebbe aver appena finito.
    if (file_exists(FILE_ANAGRAFICA) && (time() - filemtime(FILE_ANAGRAFICA) < 86400)) {
        flock($lock, LOCK_UN); fclose($lock); return;
    }

    $tmp = FILE_ANAGRAFICA . '.download';
    $fp  = @fopen($tmp, 'wb');
    if (!$fp) { flock($lock, LOCK_UN); fclose($lock); return; }

    $ch = curl_init(URL_ANAGRAFICA);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_USERAGENT      => 'FuelFinder/1.0',
    ]);
    $ok   = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    // Solo se download valido e file non vuoto: rename atomico sopra l'originale.
    if ($ok && $code === 200 && @filesize($tmp) > 0) {
        @rename($tmp, FILE_ANAGRAFICA);
    } else {
        @unlink($tmp);
    }

    flock($lock, LOCK_UN);
    fclose($lock);
}

// Carica idImpianto → [nome, addr, lat, lon, bad_coord?] dal CSV anagrafica.
// Cache JSON (non unserialize → no PHP object injection).
// Scrittura atomica (tmp+rename); se un altro processo sta già rigenerando il
// JSON (lock non acquisito) si parsa in memoria senza riscrivere (no write herd).
function caricaAnagrafica() {
    // v2: include lat/lon e flag bad_coord (vedi sotto). Nome file versionato
    // così la vecchia cache senza questi campi non viene riusata.
    $cacheFile = FILE_ANAGRAFICA . '.v2.json';
    $legacyCacheFiles = [FILE_ANAGRAFICA . '.ser', FILE_ANAGRAFICA . '.json'];

    if (file_exists($cacheFile) && file_exists(FILE_ANAGRAFICA)
        && filemtime($cacheFile) >= filemtime(FILE_ANAGRAFICA)) {
        $map = json_decode(file_get_contents($cacheFile), true);
        if (is_array($map)) return $map;
    }

    $map = [];
    $comuniByCoord = []; // "lat,lon" => comune => [idImpianto]
    $idsByComune   = []; // "COMUNE|PR" => [idImpianto]
    if (!file_exists(FILE_ANAGRAFICA)) return $map;
    $h = fopen(FILE_ANAGRAFICA, 'r');
    if (!$h) return $map;
    $riga = 0;
    while (($line = fgets($h)) !== false) {
        $riga++;
        if ($riga <= 2) continue;
        $d = explode('|', rtrim($line, "\r\n"));
        if (count($d) < 8 || !is_numeric(trim($d[0]))) continue;
        $id  = (int)trim($d[0]);
        $lat = isset($d[8]) && is_numeric(trim($d[8])) ? (float)trim($d[8]) : null;
        $lon = isset($d[9]) && is_numeric(trim($d[9])) ? (float)trim($d[9]) : null;
        $map[$id] = [
            'nome' => trim($d[4]),
            'addr' => trim($d[5]) . ', ' . trim($d[6]) . ' (' . trim($d[7]) . ')',
            'lat'  => $lat,
            'lon'  => $lon,
        ];
        if ($lat !== null && $lon !== null) {
            $comune = strtoupper(trim($d[6])) . '|' . trim($d[7]);
            $comuniByCoord[sprintf('%.4f,%.4f', $lat, $lon)][$comune][] = $id;
            $idsByComune[$comune][] = $id;
        }
    }
    fclose($h);

    // Coordinate segnaposto: il MIMIT assegna a volte la stessa coordinata a
    // impianti di comuni diversi (es. Romentino/Arluno/Busto Garolfo tutti in
    // centro a Milano). Una coordinata condivisa tra comuni diversi è
    // sicuramente sbagliata per almeno uno di essi: le marchiamo tutte, così
    // la ricerca non mostra distributori a "2 km" che sono a 30.
    foreach ($comuniByCoord as $comuni) {
        if (count($comuni) < 2) continue;
        foreach ($comuni as $ids) {
            foreach ($ids as $id) $map[$id]['bad_coord'] = true;
        }
    }

    // Stesso errore con coordinate non identiche (es. "Magenta Corso Europa" a
    // 140 m dal segnaposto di Milano): impianto molto lontano dalla mediana
    // degli altri impianti del suo comune. Soglia larga (≥20 km e 8× la
    // dispersione tipica del comune) per non colpire frazioni e statali dei
    // comuni estesi; servono almeno 3 impianti per avere una mediana sensata.
    foreach ($idsByComune as $ids) {
        if (count($ids) < 3) continue;
        $mLat = anagMedian(array_map(fn($id) => $map[$id]['lat'], $ids));
        $mLon = anagMedian(array_map(fn($id) => $map[$id]['lon'], $ids));
        $dist = [];
        foreach ($ids as $id) $dist[$id] = anagKm($mLat, $mLon, $map[$id]['lat'], $map[$id]['lon']);
        $thr = max(20.0, 8 * anagMedian(array_values($dist)));
        foreach ($dist as $id => $km) {
            if ($km > $thr) $map[$id]['bad_coord'] = true;
        }
    }

    // Scrittura atomica del JSON, solo se nessun altro processo sta già scrivendo.
    $lock = @fopen($cacheFile . '.lock', 'c');
    if ($lock) {
        if (flock($lock, LOCK_EX | LOCK_NB)) {
            $tmp = $cacheFile . '.' . uniqid('', true) . '.tmp';
            if (@file_put_contents($tmp, json_encode($map)) !== false) {
                @chmod($tmp, 0640);
                if (!@rename($tmp, $cacheFile)) @unlink($tmp);
            }
            foreach ($legacyCacheFiles as $f) @unlink($f);
            flock($lock, LOCK_UN);
        }
        fclose($lock);
    }

    return $map;
}

function anagMedian(array $v) {
    sort($v);
    $n = count($v);
    return $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2;
}

function anagKm($lat1, $lon1, $lat2, $lon2) {
    $rad = M_PI / 180;
    $a = sin(($lat2 - $lat1) * $rad / 2) ** 2
       + cos($lat1 * $rad) * cos($lat2 * $rad) * sin(($lon2 - $lon1) * $rad / 2) ** 2;
    return 6371 * 2 * asin(min(1, sqrt($a)));
}

// Avvia aggiornamento dell'anagrafica (lock non bloccante: non genera herd).
// In condizioni normali il refresh è gestito dal cron giornaliero
// (includes/refresh_cli.php); questo resta come safety-net se il cron non gira.
aggiornaAnagrafica();


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
