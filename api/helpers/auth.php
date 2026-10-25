<?php

const LOGIN_WINDOW_SECONDS = 900;
const LOGIN_BLOCK_SECONDS = 900;
const LOGIN_MAX_ATTEMPTS_PER_IP_USERNAME = 5;
const LOGIN_MAX_ATTEMPTS_PER_IP = 30;
const LOGIN_MAX_ATTEMPTS_PER_USERNAME = 50;
const LOGIN_MAX_ATTEMPTS_PER_DEVICE = 10;
const PASSWORD_CHANGE_MAX_ATTEMPTS = 5;

const SESSION_IDLE_SECONDS = 7200;
const SESSION_ABSOLUTE_SECONDS = 43200;
const TRUSTED_DEVICE_SECONDS = 7776000;

// Salt and hash of a random password. loginDummyPasswordHash() prefixes the
// runtime's default bcrypt cost, so unknown users cost as much as existing
// ones (which are rehashed to that cost on login): only password_verify().
const LOGIN_DUMMY_PASSWORD_HASH_BODY = 'ENlVjhW77QzZlX9WCr8HmegfiRwBVAJyncnZTrzwaBlKpyZMupUau';

/**
 * With HTTPS the __Host- prefix makes browsers reject the cookie unless it is
 * Secure, has path / and no Domain, so a subdomain cannot plant or override it.
 */
function authCookieName(string $name): string {
    return SESSION_COOKIE_SECURE ? '__Host-' . $name : $name;
}

function authCookieOptions(int $expires): array {
    return [
        'expires' => $expires,
        'path' => '/',
        'secure' => SESSION_COOKIE_SECURE,
        'httponly' => true,
        'samesite' => 'Strict',
    ];
}

function hasSessionCookie(): bool {
    $value = $_COOKIE[authCookieName('boilerplate_session')] ?? '';
    return is_string($value) && $value !== '';
}

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', (string) SESSION_IDLE_SECONDS);

    session_name(authCookieName('boilerplate_session'));
    $options = authCookieOptions(0);
    unset($options['expires']);
    session_set_cookie_params(['lifetime' => 0] + $options);
    session_start();
}

function csrfToken(): string {
    startSecureSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function requireCsrfToken(): void {
    if (session_status() !== PHP_SESSION_ACTIVE && !hasSessionCookie()) {
        errorResponse('invalid_csrf_token', 403);
    }
    startSecureSession();
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $expected = $_SESSION['csrf_token'] ?? '';

    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        errorResponse('invalid_csrf_token', 403);
    }
}

function findUserByUsername(PDO $db, string $username): ?array {
    $stmt = $db->prepare(
        'SELECT id, username, password_hash, role, must_change_password,
                session_version, created_at
         FROM users
         WHERE username = :username'
    );
    $stmt->execute(['username' => normalizeUsername($username)]);
    $user = $stmt->fetch();
    return $user === false ? null : $user;
}

function findUserById(PDO $db, string $id): ?array {
    $stmt = $db->prepare(
        'SELECT id, username, password_hash, role, must_change_password,
                session_version, created_at
         FROM users
         WHERE id = :id'
    );
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    return $user === false ? null : $user;
}

function publicUser(array $user): array {
    return [
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'mustChangePassword' => (bool) $user['must_change_password'],
        'createdAt' => $user['created_at'],
    ];
}

function currentUser(PDO $db, ?int $now = null): ?array {
    // Anonymous visitors get no session, so they cannot fill session storage.
    if (session_status() !== PHP_SESSION_ACTIVE && !hasSessionCookie()) {
        return null;
    }
    startSecureSession();
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_string($userId) || $userId === '') {
        logoutCurrentUser();
        return null;
    }

    $now ??= time();
    $authenticatedAt = (int) ($_SESSION['authenticated_at'] ?? 0);
    $lastSeen = (int) ($_SESSION['last_seen'] ?? 0);
    if ($now - $authenticatedAt > SESSION_ABSOLUTE_SECONDS || $now - $lastSeen > SESSION_IDLE_SECONDS) {
        logoutCurrentUser();
        return null;
    }

    $user = findUserById($db, $userId);
    if ($user === null) {
        logoutCurrentUser();
        return null;
    }
    if ((int) ($_SESSION['session_version'] ?? 0) !== (int) $user['session_version']) {
        logoutCurrentUser();
        return null;
    }
    $_SESSION['last_seen'] = $now;
    return $user;
}

function requireUser(PDO $db, bool $allowPasswordChange = false): array {
    $user = currentUser($db);
    if ($user === null) {
        errorResponse('unauthorized', 401);
    }
    if (!$allowPasswordChange && (bool) $user['must_change_password']) {
        errorResponse('password_change_required', 403);
    }
    return $user;
}

function requireAdmin(PDO $db): array {
    $user = requireUser($db);
    if ($user['role'] !== 'admin') {
        errorResponse('forbidden', 403);
    }
    return $user;
}

function loginUser(array $user): void {
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => $user['id'],
        'session_version' => (int) $user['session_version'],
        'csrf_token' => bin2hex(random_bytes(32)),
        'authenticated_at' => time(),
        'last_seen' => time(),
    ];
}

function logoutCurrentUser(): void {
    startSecureSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Strict',
        ]);
    }

    session_destroy();
}

function loginDummyPasswordHash(): string {
    return sprintf('$2y$%02d$', PASSWORD_BCRYPT_DEFAULT_COST) . LOGIN_DUMMY_PASSWORD_HASH_BODY;
}

function loginPasswordHash(?array $user): string {
    return $user['password_hash'] ?? loginDummyPasswordHash();
}

function trustedDeviceCookieName(): string {
    return authCookieName('boilerplate_device');
}

/**
 * Keyed with the user's password hash: secret to the server, unique per user,
 * and it changes with the password, which revokes every trusted device.
 */
function trustedDeviceMac(array $user, int $issuedAt): string {
    return hash_hmac(
        'sha256',
        "trusted-device\0" . $user['id'] . "\0" . $issuedAt,
        $user['password_hash']
    );
}

/**
 * Marks this browser as one that logged in to $user before (an OWASP device
 * cookie). It only changes which rate-limit bucket applies; the password is
 * still required.
 */
function issueTrustedDeviceCookie(array $user, ?int $now = null): void {
    $now ??= time();
    setcookie(
        trustedDeviceCookieName(),
        $user['id'] . '.' . $now . '.' . trustedDeviceMac($user, $now),
        authCookieOptions($now + TRUSTED_DEVICE_SECONDS)
    );
}

/**
 * Returns the rate-limit key of this device when $cookie proves an earlier
 * login as $user, otherwise null.
 */
function trustedDeviceKey(?array $user, mixed $cookie, ?int $now = null): ?string {
    $now ??= time();
    if ($user === null || !is_string($cookie)) {
        return null;
    }

    $parts = explode('.', $cookie);
    if (count($parts) !== 3 || !ctype_digit($parts[1])) {
        return null;
    }
    [$userId, $issuedAt, $mac] = $parts;
    $issuedAt = (int) $issuedAt;
    if (
        !hash_equals((string) $user['id'], $userId)
        || $issuedAt > $now
        || $now - $issuedAt > TRUSTED_DEVICE_SECONDS
        || !hash_equals(trustedDeviceMac($user, $issuedAt), $mac)
    ) {
        return null;
    }
    return hash('sha256', "device\0" . $mac);
}

/**
 * Rate-limit buckets for one login attempt. IP+username blocks quickly, the
 * IP bucket stops password spraying, and the username bucket stops
 * distributed guessing on one account. A trusted device gets its own bucket
 * instead of the username bucket, so an attacker who fills the username
 * bucket cannot lock the real user out of devices they used before.
 *
 * @return array<string, int> bucket key => maximum number of attempts
 */
function loginRateLimitBuckets(string $ip, string $username, ?string $deviceKey = null): array {
    $username = normalizeUsername($username);
    $buckets = [
        hash('sha256', "ip-user\0" . $ip . "\0" . $username) => LOGIN_MAX_ATTEMPTS_PER_IP_USERNAME,
        hash('sha256', "ip\0" . $ip) => LOGIN_MAX_ATTEMPTS_PER_IP,
    ];
    if ($deviceKey === null) {
        $buckets[hash('sha256', "user\0" . $username)] = LOGIN_MAX_ATTEMPTS_PER_USERNAME;
    } else {
        $buckets[$deviceKey] = LOGIN_MAX_ATTEMPTS_PER_DEVICE;
    }
    return $buckets;
}

function pruneLoginAttempts(PDO $db, ?int $now = null): void {
    $now ??= time();
    $stmt = $db->prepare(
        'DELETE FROM auth_login_attempts
         WHERE window_started <= :window_expired AND blocked_until <= :now'
    );
    $stmt->execute(['window_expired' => $now - LOGIN_WINDOW_SECONDS, 'now' => $now]);
}

/**
 * Atomically consumes one attempt from a bucket. Returns 0 if the attempt
 * is allowed, otherwise the number of seconds until the block expires.
 */
function consumeLoginAttempt(PDO $db, string $key, int $limit, ?int $now = null): int {
    $now ??= time();

    try {
        $insert = $db->prepare(
            'INSERT INTO auth_login_attempts (ip, attempts, window_started, blocked_until)
             VALUES (:ip, 0, :now, 0)'
        );
        $insert->execute(['ip' => $key, 'now' => $now]);
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() !== '23000') {
            throw $exception;
        }
    }

    // A single UPDATE: parallel requests cannot bypass the check and the
    // increment.
    $increment = $db->prepare(
        'UPDATE auth_login_attempts
         SET attempts = attempts + 1
         WHERE ip = :ip AND blocked_until <= :now AND attempts < :limit'
    );
    $increment->execute(['ip' => $key, 'now' => $now, 'limit' => $limit]);
    if ($increment->rowCount() === 1) {
        return 0;
    }

    $block = $db->prepare(
        'UPDATE auth_login_attempts
         SET blocked_until = :blocked_until
         WHERE ip = :ip AND blocked_until <= :now'
    );
    $block->execute([
        'ip' => $key,
        'now' => $now,
        'blocked_until' => $now + LOGIN_BLOCK_SECONDS,
    ]);

    $stmt = $db->prepare('SELECT blocked_until FROM auth_login_attempts WHERE ip = :ip');
    $stmt->execute(['ip' => $key]);
    return max(1, (int) $stmt->fetchColumn() - $now);
}

/**
 * Consumes an attempt from every bucket and stops with 429 once one is full.
 */
function assertLoginAllowed(
    PDO $db,
    string $ip,
    string $username,
    ?string $deviceKey = null,
    ?int $now = null
): void {
    $now ??= time();
    pruneLoginAttempts($db, $now);

    foreach (loginRateLimitBuckets($ip, $username, $deviceKey) as $key => $limit) {
        $retryAfter = consumeLoginAttempt($db, $key, $limit, $now);
        if ($retryAfter > 0) {
            errorResponse('too_many_login_attempts', 429, ['retryAfter' => $retryAfter]);
        }
    }
}

/**
 * After a successful login: clear IP+username and refund the attempt to the
 * shared IP and username (or device) buckets, so legitimate traffic does not
 * count.
 */
function recordSuccessfulLogin(
    PDO $db,
    string $ip,
    string $username,
    ?string $deviceKey = null,
    ?int $now = null
): void {
    $now ??= time();
    [$ipUserKey, $ipKey, $userKey] = array_keys(loginRateLimitBuckets($ip, $username, $deviceKey));

    $delete = $db->prepare('DELETE FROM auth_login_attempts WHERE ip = :ip');
    $delete->execute(['ip' => $ipUserKey]);

    $refund = $db->prepare(
        'UPDATE auth_login_attempts
         SET attempts = attempts - 1
         WHERE ip = :ip AND attempts > 0 AND blocked_until <= :now'
    );
    $refund->execute(['ip' => $ipKey, 'now' => $now]);
    $refund->execute(['ip' => $userKey, 'now' => $now]);
}

function passwordChangeRateLimitKey(string $userId): string {
    return hash('sha256', "change-password\0" . $userId);
}

/**
 * Limits guessing the current password with a stolen session. Every attempt
 * counts; a successful change clears the bucket.
 */
function assertPasswordChangeAllowed(PDO $db, string $userId, ?int $now = null): void {
    $now ??= time();
    pruneLoginAttempts($db, $now);

    $retryAfter = consumeLoginAttempt(
        $db,
        passwordChangeRateLimitKey($userId),
        PASSWORD_CHANGE_MAX_ATTEMPTS,
        $now
    );
    if ($retryAfter > 0) {
        errorResponse('too_many_attempts', 429, ['retryAfter' => $retryAfter]);
    }
}

function clearPasswordChangeAttempts(PDO $db, string $userId): void {
    $stmt = $db->prepare('DELETE FROM auth_login_attempts WHERE ip = :ip');
    $stmt->execute(['ip' => passwordChangeRateLimitKey($userId)]);
}
