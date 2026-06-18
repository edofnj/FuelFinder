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

// Carica idImpianto → [nome, addr] dal CSV anagrafica.
// Cache JSON (non unserialize → no PHP object injection).
// Scrittura atomica (tmp+rename); se un altro processo sta già rigenerando il
// JSON (lock non acquisito) si parsa in memoria senza riscrivere (no write herd).
function caricaAnagrafica() {
    $cacheFile = FILE_ANAGRAFICA . '.json';
    $legacyCacheFile = FILE_ANAGRAFICA . '.ser';

    if (file_exists($cacheFile) && file_exists(FILE_ANAGRAFICA)
        && filemtime($cacheFile) >= filemtime(FILE_ANAGRAFICA)) {
        $map = json_decode(file_get_contents($cacheFile), true);
        if (is_array($map)) return $map;
    }

    $map = [];
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
        $map[$id] = [
            'nome' => trim($d[4]),
            'addr' => trim($d[5]) . ', ' . trim($d[6]) . ' (' . trim($d[7]) . ')',
        ];
    }
    fclose($h);

    // Scrittura atomica del JSON, solo se nessun altro processo sta già scrivendo.
    $lock = @fopen($cacheFile . '.lock', 'c');
    if ($lock) {
        if (flock($lock, LOCK_EX | LOCK_NB)) {
            $tmp = $cacheFile . '.' . uniqid('', true) . '.tmp';
            if (@file_put_contents($tmp, json_encode($map)) !== false) {
                @chmod($tmp, 0640);
                if (!@rename($tmp, $cacheFile)) @unlink($tmp);
            }
            @unlink($legacyCacheFile);
            flock($lock, LOCK_UN);
        }
        fclose($lock);
    }

    return $map;
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
