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
    return rateLimitIp(resolveClientIp(
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
        defined('TRUSTED_PROXIES') ? TRUSTED_PROXIES : []
    ));
}

/**
 * Behind a reverse proxy REMOTE_ADDR is the proxy, so every client would share
 * one rate-limit bucket. X-Forwarded-For is only read when the request comes
 * from a trusted proxy, and from the right: entries left of the first
 * untrusted address can be forged by the client.
 */
function resolveClientIp(string $remoteAddr, string $forwardedFor, array $trustedProxies): string {
    if ($trustedProxies === [] || !ipMatchesAny($remoteAddr, $trustedProxies)) {
        return $remoteAddr;
    }

    foreach (array_reverse(explode(',', $forwardedFor)) as $hop) {
        $hop = trim($hop);
        if (@inet_pton($hop) === false) {
            return $remoteAddr;
        }
        if (!ipMatchesAny($hop, $trustedProxies)) {
            return $hop;
        }
    }
    return $remoteAddr;
}

function ipMatchesAny(string $ip, array $ranges): bool {
    foreach ($ranges as $range) {
        if (is_string($range) && ipInRange($ip, $range)) {
            return true;
        }
    }
    return false;
}

/** Matches an IPv4/IPv6 address against a single address or a CIDR range. */
function ipInRange(string $ip, string $range): bool {
    [$subnet, $bits] = array_pad(explode('/', $range, 2), 2, null);
    $ipBinary = @inet_pton($ip);
    $subnetBinary = @inet_pton($subnet);
    if ($ipBinary === false || $subnetBinary === false || strlen($ipBinary) !== strlen($subnetBinary)) {
        return false;
    }

    $maxBits = strlen($ipBinary) * 8;
    if ($bits === null) {
        $bits = $maxBits;
    } elseif (ctype_digit($bits) && (int) $bits <= $maxBits) {
        $bits = (int) $bits;
    } else {
        return false;
    }

    $bytes = intdiv($bits, 8);
    if (substr($ipBinary, 0, $bytes) !== substr($subnetBinary, 0, $bytes)) {
        return false;
    }
    $remainder = $bits % 8;
    if ($remainder === 0) {
        return true;
    }
    $mask = (0xff << (8 - $remainder)) & 0xff;
    return (ord($ipBinary[$bytes]) & $mask) === (ord($subnetBinary[$bytes]) & $mask);
}

function isLoopbackIp(string $ip): bool {
    return ipInRange($ip, '127.0.0.0/8')
        || ipInRange($ip, '::1')
        || ipInRange($ip, '::ffff:127.0.0.0/104');
}

/**
 * Development mode bootstraps admin/admin and skips the production checks. If
 * a server is ever deployed with the example config, it must not be usable
 * from the network.
 */
function assertDevelopmentClientIsLocal(): void {
    if (APP_ENV === 'production' || PHP_SAPI === 'cli') {
        return;
    }
    if (defined('DEV_ALLOW_REMOTE_CLIENTS') && DEV_ALLOW_REMOTE_CLIENTS === true) {
        return;
    }
    if (!isLoopbackIp($_SERVER['REMOTE_ADDR'] ?? '')) {
        errorResponse('development_mode_is_local_only', 403);
    }
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
