<?php
declare(strict_types=1);

define('DB_DRIVER', 'sqlite');
define('APP_ENV', 'production');

require __DIR__ . '/../../api/helpers/database.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$failedClosed = false;
try {
    initializeDatabase($db);
} catch (RuntimeException $exception) {
    $failedClosed = str_contains($exception->getMessage(), 'schema');
}

if (!$failedClosed) {
    throw new RuntimeException('Productie zonder geïmporteerd schema moet falen.');
}

$createdTables = (int) $db->query(
    "SELECT COUNT(*) FROM sqlite_master
     WHERE type = 'table' AND name IN ('users', 'auth_login_attempts')"
)->fetchColumn();

if ($createdTables !== 0) {
    throw new RuntimeException('Productieruntime mag geen tabellen aanmaken.');
}

echo "Production schema guard tests passed\n";
