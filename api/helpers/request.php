<?php

function requireJsonBody(int $maxBytes = 16384): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (!str_starts_with(strtolower($contentType), 'application/json')) {
        errorResponse('content_type_must_be_json', 415);
    }

    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maxBytes) {
        errorResponse('request_too_large', 413);
    }

    $raw = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($raw === false || strlen($raw) > $maxBytes) {
        errorResponse('request_too_large', 413);
    }

    try {
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        errorResponse('invalid_json', 400);
    }

    if (!is_array($data)) {
        errorResponse('json_object_required', 400);
    }

    return $data;
}

function clientIp(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return substr($ip, 0, 64);
}
