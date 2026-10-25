<?php
// ─── Application ──────────────────────────────────────────────────
// Use 'production' on the server. Production mode rejects unsafe config and
// cookie settings and never runs schema DDL.
define('APP_ENV', 'development');
define('SESSION_COOKIE_SECURE', false);

// Only local SQLite development uses this bootstrap. Create production admins
// once with api/cli/bootstrap-admin.php.
define('BOOTSTRAP_ADMIN_USERNAME', 'admin');
define('BOOTSTRAP_ADMIN_PASSWORD', 'admin');

// ─── Database ─────────────────────────────────────────────────────
// Set DB_DRIVER to 'sqlite' (local) or 'mysql' (production).
define('DB_DRIVER', 'sqlite');

// SQLite (local): the file is created automatically
define('DB_SQLITE_PATH', __DIR__ . '/storage/db.sqlite');

// MySQL (production): fill in when deploying
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
        throw new RuntimeException('Invalid APP_ENV.');
    }

    if (APP_ENV === 'production') {
        if (pathIsInsideDocumentRoot($configPath)) {
            throw new RuntimeException('Place the production config outside the webroot.');
        }
        if (DB_DRIVER === 'sqlite' && pathIsInsideDocumentRoot(DB_SQLITE_PATH)) {
            throw new RuntimeException('Place the production SQLite database outside the webroot.');
        }
        if (!SESSION_COOKIE_SECURE) {
            throw new RuntimeException('SESSION_COOKIE_SECURE must be enabled in production.');
        }
    }
}
