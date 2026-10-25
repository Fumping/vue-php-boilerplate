<?php
declare(strict_types=1);

define('DB_DRIVER', 'sqlite');
define('APP_ENV', 'test');
define('BOOTSTRAP_ADMIN_USERNAME', 'admin');
define('BOOTSTRAP_ADMIN_PASSWORD', 'admin');

require __DIR__ . '/../../api/helpers/database.php';
require __DIR__ . '/../../api/helpers/auth.php';

function assertTest(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

initializeDatabase($db);
$users = $db->query(
    'SELECT username, password_hash, role, must_change_password, session_version
     FROM users'
)->fetchAll();

assertTest(count($users) === 1, 'Bootstrap must create exactly one user.');
assertTest($users[0]['username'] === 'admin', 'Bootstrap username is wrong.');
assertTest($users[0]['role'] === 'admin', 'Bootstrap user must be an admin.');
assertTest((int) $users[0]['must_change_password'] === 1, 'First password change must be required.');
assertTest(password_verify('admin', $users[0]['password_hash']), 'Bootstrap password is not hashed.');
assertTest((int) $users[0]['session_version'] === 1, 'Session version must be initialized.');
assertTest(
    (int) $db->query('SELECT version FROM schema_version WHERE id = 1')->fetchColumn()
        === DATABASE_SCHEMA_VERSION,
    'Schema version is wrong.'
);
assertTest(isStrongPassword('Long-Unique-Password!', 'tester'), 'Strong password is rejected.');
assertTest(!isStrongPassword('adminadminadmin', 'admin'), 'Known weak password is accepted.');
assertTest(
    loginRateLimitKey('127.0.0.1', 'admin') !== loginRateLimitKey('127.0.0.1', 'other'),
    'Rate limiting must apply per IP and username.'
);
assertTest(
    !isStrongPassword(str_repeat('x', 60) . 'Long-Suffix-1', 'tester'),
    'Passwords longer than 72 bytes must be rejected (bcrypt truncates them).'
);

$ipKey = loginIpRateLimitKey('203.0.113.9');
for ($i = 1; $i < LOGIN_MAX_ATTEMPTS_PER_IP; $i++) {
    recordFailedLogin($db, $ipKey, LOGIN_MAX_ATTEMPTS_PER_IP);
}
$blocked = (int) $db->query(
    "SELECT blocked_until FROM auth_login_attempts WHERE ip = '$ipKey'"
)->fetchColumn();
assertTest($blocked === 0, 'The per-IP limit must not block below its threshold.');
recordFailedLogin($db, $ipKey, LOGIN_MAX_ATTEMPTS_PER_IP);
$blocked = (int) $db->query(
    "SELECT blocked_until FROM auth_login_attempts WHERE ip = '$ipKey'"
)->fetchColumn();
assertTest($blocked > time(), 'The per-IP limit must block password spraying across usernames.');

echo "PHP auth tests passed\n";
