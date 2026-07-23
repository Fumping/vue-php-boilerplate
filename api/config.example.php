<?php
// ─── API ──────────────────────────────────────────────────────────
define('API_KEY', 'change-me');

// ─── Database ─────────────────────────────────────────────────────
// Zet DB_DRIVER op 'sqlite' (lokaal) of 'mysql' (productie).
define('DB_DRIVER', 'sqlite');

// SQLite (lokaal) — bestand wordt automatisch aangemaakt
define('DB_SQLITE_PATH', __DIR__ . '/storage/db.sqlite');

// MySQL (productie) — vul in bij deploy
define('DB_HOST', 'localhost');
define('DB_NAME', 'my_database');
define('DB_USER', 'my_user');
define('DB_PASS', 'my_password');

function getDb(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    if (DB_DRIVER === 'sqlite') {
        $dir = dirname(DB_SQLITE_PATH);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } elseif (DB_DRIVER === 'mysql') {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
    } else {
        throw new RuntimeException('Onbekende DB_DRIVER: ' . DB_DRIVER);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}
