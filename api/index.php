<?php
declare(strict_types=1);

// ─── Bootstrap ────────────────────────────────────────────────────
$configPath = __DIR__ . '/config.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error'  => 'missing_config',
        'detail' => 'Kopieer api/config.example.php naar api/config.php.',
    ]);
    exit;
}
require $configPath;
require __DIR__ . '/helpers/response.php';
require __DIR__ . '/helpers/auth.php';

// ─── CORS ─────────────────────────────────────────────────────────
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Api-Key');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ─── Path & method ────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Strip leading /api zodat /api/health en /health beide werken
// (Vite proxy stuurt met prefix door; Apache rewrite ook)
$path = preg_replace('#^/api#', '', $path) ?: '/';

// ─── Stage 1: publieke endpoints (voor requireApiKey) ─────────────
if ($method === 'GET' && $path === '/') {
    require __DIR__ . '/handlers/health.php';
    exit;
}

// ─── Stage 2: API-key gate ────────────────────────────────────────
requireApiKey();

// ─── Stage 3: beschermde endpoints ───────────────────────────────
// Voeg hier je handlers toe. Voorbeeld:
// if ($method === 'GET' && $path === '/items') {
//     require __DIR__ . '/handlers/items-list.php';
//     exit;
// }

// ─── 404 ──────────────────────────────────────────────────────────
errorResponse('not_found', 404, ['path' => $path, 'method' => $method]);
