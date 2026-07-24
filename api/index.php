<?php
declare(strict_types=1);

require __DIR__ . '/helpers/response.php';
applySecurityHeaders();
registerSafeExceptionHandler();

$configuredPath = getenv('APP_CONFIG_PATH');
$configPath = is_string($configuredPath) && $configuredPath !== ''
    ? $configuredPath
    : __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    errorResponse('server_not_configured', 500);
}

require $configPath;
require __DIR__ . '/helpers/request.php';
require __DIR__ . '/helpers/database.php';
require __DIR__ . '/helpers/auth.php';

validateAppConfig($configPath);
$db = getDb();
initializeDatabase($db);

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (!is_string($path)) {
    errorResponse('invalid_request_path', 400);
}
$path = preg_replace('#^/api(?=/|$)#', '', $path) ?: '/';

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Publiek
if ($method === 'GET' && $path === '/') {
    require __DIR__ . '/handlers/health.php';
    exit;
}
if ($method === 'POST' && $path === '/auth/login') {
    require __DIR__ . '/handlers/auth-login.php';
    exit;
}
if ($method === 'GET' && $path === '/auth/me') {
    require __DIR__ . '/handlers/auth-me.php';
    exit;
}

// Ingelogd
if ($method === 'POST' && $path === '/auth/logout') {
    require __DIR__ . '/handlers/auth-logout.php';
    exit;
}
if ($method === 'POST' && $path === '/auth/change-password') {
    require __DIR__ . '/handlers/auth-change-password.php';
    exit;
}

// Alleen admins
if ($method === 'GET' && $path === '/admin/users') {
    require __DIR__ . '/handlers/admin-users-list.php';
    exit;
}
if ($method === 'POST' && $path === '/admin/users') {
    require __DIR__ . '/handlers/admin-users-create.php';
    exit;
}

errorResponse('not_found', 404);
