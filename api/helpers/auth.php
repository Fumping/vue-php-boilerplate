<?php

const LOGIN_WINDOW_SECONDS = 900;
const LOGIN_MAX_ATTEMPTS = 5;
const LOGIN_BLOCK_SECONDS = 900;

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

function assertLoginAllowed(PDO $db, string $ip): void {
    $stmt = $db->prepare(
        'SELECT attempts, window_started, blocked_until
         FROM auth_login_attempts
         WHERE ip = :ip'
    );
    $stmt->execute(['ip' => $ip]);
    $attempt = $stmt->fetch();

    if ($attempt !== false && (int) $attempt['blocked_until'] > time()) {
        errorResponse('too_many_login_attempts', 429, [
            'retryAfter' => (int) $attempt['blocked_until'] - time(),
        ]);
    }
}

function recordFailedLogin(PDO $db, string $ip): void {
    $now = time();
    $stmt = $db->prepare(
        'SELECT attempts, window_started
         FROM auth_login_attempts
         WHERE ip = :ip'
    );
    $stmt->execute(['ip' => $ip]);
    $attempt = $stmt->fetch();

    if ($attempt === false) {
        $insert = $db->prepare(
            'INSERT INTO auth_login_attempts (
                ip, attempts, window_started, blocked_until
            ) VALUES (
                :ip, 1, :window_started, 0
            )'
        );
        $insert->execute(['ip' => $ip, 'window_started' => $now]);
        return;
    }

    $windowStarted = (int) $attempt['window_started'];
    $attempts = (int) $attempt['attempts'];
    if ($windowStarted <= $now - LOGIN_WINDOW_SECONDS) {
        $windowStarted = $now;
        $attempts = 1;
    } else {
        $attempts++;
    }
    $blockedUntil = $attempts >= LOGIN_MAX_ATTEMPTS ? $now + LOGIN_BLOCK_SECONDS : 0;

    $update = $db->prepare(
        'UPDATE auth_login_attempts
         SET attempts = :attempts,
             window_started = :window_started,
             blocked_until = :blocked_until
         WHERE ip = :ip'
    );
    $update->execute([
        'attempts' => $attempts,
        'window_started' => $windowStarted,
        'blocked_until' => $blockedUntil,
        'ip' => $ip,
    ]);
}

function clearFailedLogins(PDO $db, string $ip): void {
    $stmt = $db->prepare('DELETE FROM auth_login_attempts WHERE ip = :ip');
    $stmt->execute(['ip' => $ip]);
}

function loginRateLimitKey(string $ip, string $username): string {
    return hash('sha256', $ip . "\0" . normalizeUsername($username));
}
