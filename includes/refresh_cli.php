<?php
// Refresh CLI dell'anagrafica MIMIT, eseguito dal cron giornaliero.
// Scarica il CSV (se >24h) e pre-genera la cache JSON, così le richieste web
// non pagano mai il costo di download/parsing.
// Non raggiungibile da web: includes/ è bloccata da .htaccess.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api.php'; // il require esegue aggiornaAnagrafica()
caricaAnagrafica();                // pre-warm della cache JSON
fwrite(STDOUT, "anagrafica refresh ok\n");
