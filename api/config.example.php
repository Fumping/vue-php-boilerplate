<?php
// ─── Applicatie ───────────────────────────────────────────────────
// Gebruik 'production' op de server. Productiemodus weigert onveilige
// config- en cookie-instellingen en voert nooit schema-DDL uit.
define('APP_ENV', 'development');
define('SESSION_COOKIE_SECURE', false);

// Alleen lokale SQLite-development gebruikt deze bootstrap. Productieadmins
// maak je eenmalig aan met api/cli/bootstrap-admin.php.
define('BOOTSTRAP_ADMIN_USERNAME', 'admin');
define('BOOTSTRAP_ADMIN_PASSWORD', 'admin');

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
            mkdir($dir, 0700, true);
        }
        chmod($dir, 0700);
        $pdo = new PDO('sqlite:' . DB_SQLITE_PATH);
        if (file_exists(DB_SQLITE_PATH)) {
            chmod(DB_SQLITE_PATH, 0600);
        }
        $pdo->exec('PRAGMA foreign_keys = ON');
    } elseif (DB_DRIVER === 'mysql') {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } else {
        throw new RuntimeException('Onbekende DB_DRIVER: ' . DB_DRIVER);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
}

function pathIsInsideDocumentRoot(string $path): bool {
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $targetDirectory = realpath(dirname($path));
    if ($documentRoot === false || $targetDirectory === false) {
        return false;
    }

    return $targetDirectory === $documentRoot
        || str_starts_with($targetDirectory, $documentRoot . DIRECTORY_SEPARATOR);
}

function validateAppConfig(string $configPath): void {
    if (!in_array(APP_ENV, ['development', 'test', 'production'], true)) {
        throw new RuntimeException('Ongeldige APP_ENV.');
    }

    if (APP_ENV === 'production') {
        if (pathIsInsideDocumentRoot($configPath)) {
            throw new RuntimeException('Plaats de productieconfig buiten de webroot.');
        }
        if (DB_DRIVER === 'sqlite' && pathIsInsideDocumentRoot(DB_SQLITE_PATH)) {
            throw new RuntimeException('Plaats de productie-SQLite-database buiten de webroot.');
        }
        if (!SESSION_COOKIE_SECURE) {
            throw new RuntimeException('SESSION_COOKIE_SECURE moet aan staan in productie.');
        }
    }
}
