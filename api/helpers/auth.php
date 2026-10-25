<?php

const LOGIN_WINDOW_SECONDS = 900;
const LOGIN_BLOCK_SECONDS = 900;
const LOGIN_MAX_ATTEMPTS_PER_IP_USERNAME = 5;
const LOGIN_MAX_ATTEMPTS_PER_IP = 30;
const LOGIN_MAX_ATTEMPTS_PER_USERNAME = 50;

// Fixed bcrypt hash (random password) so unknown users cost as much as
// existing ones: only password_verify(), no extra hash.
const LOGIN_DUMMY_PASSWORD_HASH = '$2y$12$ENlVjhW77QzZlX9WCr8HmegfiRwBVAJyncnZTrzwaBlKpyZMupUau';

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Strict');

    session_name('boilerplate_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => SESSION_COOKIE_SECURE,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
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

function currentUser(PDO $db): ?array {
    startSecureSession();
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_string($userId) || $userId === '') {
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

function loginPasswordHash(?array $user): string {
    return $user['password_hash'] ?? LOGIN_DUMMY_PASSWORD_HASH;
}

/**
 * Rate-limit buckets for one login attempt. IP+username blocks quickly,
 * the IP bucket stops password spraying, the username bucket stops
 * distributed guessing on one account.
 *
 * @return array<string, int> bucket key => maximum number of attempts
 */
function loginRateLimitBuckets(string $ip, string $username): array {
    $username = normalizeUsername($username);
    return [
        hash('sha256', "ip-user\0" . $ip . "\0" . $username) => LOGIN_MAX_ATTEMPTS_PER_IP_USERNAME,
        hash('sha256', "ip\0" . $ip) => LOGIN_MAX_ATTEMPTS_PER_IP,
        hash('sha256', "user\0" . $username) => LOGIN_MAX_ATTEMPTS_PER_USERNAME,
    ];
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
function assertLoginAllowed(PDO $db, string $ip, string $username, ?int $now = null): void {
    $now ??= time();
    pruneLoginAttempts($db, $now);

    foreach (loginRateLimitBuckets($ip, $username) as $key => $limit) {
        $retryAfter = consumeLoginAttempt($db, $key, $limit, $now);
        if ($retryAfter > 0) {
            errorResponse('too_many_login_attempts', 429, ['retryAfter' => $retryAfter]);
        }
    }
}

/**
 * After a successful login: clear IP+username and refund the attempt to the
 * shared IP and username buckets, so legitimate traffic does not count.
 */
function recordSuccessfulLogin(PDO $db, string $ip, string $username, ?int $now = null): void {
    $now ??= time();
    [$ipUserKey, $ipKey, $userKey] = array_keys(loginRateLimitBuckets($ip, $username));

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
