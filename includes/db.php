<?php
// Connessione PDO lazy al Postgres condiviso (rete db_network).
// Singleton: la connessione si apre solo al primo uso reale, così pageview/
// metriche/auth non aggiungono latenza se non servono.
function pdo() {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $host = getenv('DB_HOST') ?: 'postgres';
    $port = getenv('DB_PORT') ?: '5432';
    $name = getenv('DB_NAME') ?: 'fuelfinder';
    $user = getenv('DB_USER') ?: 'fuelfinder_user';
    $pass = getenv('DB_PASSWORD') ?: '';
    $dsn  = "pgsql:host={$host};port={$port};dbname={$name}";
    $pdo  = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 3,
    ]);
    return $pdo;
}
