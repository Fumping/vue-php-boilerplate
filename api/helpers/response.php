<?php

function applySecurityHeaders(): void {
    header_remove('X-Powered-By');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    header('Referrer-Policy: no-referrer');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
}

function jsonResponse(mixed $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function errorResponse(string $message, int $status = 400, array $extra = []): void {
    jsonResponse(array_merge(['error' => $message], $extra), $status);
}

function registerSafeExceptionHandler(): void {
    set_exception_handler(static function (Throwable $exception): never {
        error_log(sprintf(
            '[api] %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));

        if (!headers_sent()) {
            applySecurityHeaders();
            errorResponse('server_error', 500);
        }

        exit(1);
    });
}
