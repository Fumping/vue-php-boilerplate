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
    return rateLimitIp($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

/**
 * One client usually owns a whole IPv6 /64, so rate limiting per full IPv6
 * address would give an attacker billions of fresh buckets. Group by /64.
 */
function rateLimitIp(string $ip): string {
    $packed = @inet_pton($ip);
    if ($packed !== false && strlen($packed) === 16 && !str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
        return inet_ntop(substr($packed, 0, 8) . str_repeat("\0", 8)) . '/64';
    }
    return substr($ip, 0, 64);
}
