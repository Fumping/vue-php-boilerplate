<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$configuredPath = getenv('APP_CONFIG_PATH');
$configPath = is_string($configuredPath) && $configuredPath !== ''
    ? $configuredPath
    : dirname(__DIR__) . '/config.php';

if (!is_file($configPath)) {
    fwrite(STDERR, "Configuratie ontbreekt. Stel APP_CONFIG_PATH in.\n");
    exit(1);
}

require $configPath;
require dirname(__DIR__) . '/helpers/database.php';

try {
    validateAppConfig($configPath);
    $db = getDb();
    assertDatabaseSchema($db);

    $username = normalizeUsername($argv[1] ?? (getenv('ADMIN_USERNAME') ?: 'admin'));
    $password = getenv('ADMIN_PASSWORD');

    if (!isValidUsername($username)) {
        throw new RuntimeException('Invalid admin username.');
    }
    if (!is_string($password) || !isStrongPassword($password, $username)) {
        throw new RuntimeException(
            'Temporarily set ADMIN_PASSWORD to a unique password of 12 to 200 characters.'
        );
    }

    $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
    $stmt->execute(['username' => $username]);
    if ((int) $stmt->fetchColumn() !== 0) {
        throw new RuntimeException('This username already exists.');
    }

    createUser($db, $username, $password, 'admin');
    fwrite(STDOUT, "Admin '{$username}' created; a password change is required.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Bootstrap failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
