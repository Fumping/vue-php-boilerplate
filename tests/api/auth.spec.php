<?php
declare(strict_types=1);

define('DB_DRIVER', 'sqlite');
define('APP_ENV', 'test');
define('BOOTSTRAP_ADMIN_USERNAME', 'admin');
define('BOOTSTRAP_ADMIN_PASSWORD', 'admin');
define('SESSION_COOKIE_SECURE', false);

require __DIR__ . '/../../api/helpers/database.php';
require __DIR__ . '/../../api/helpers/auth.php';
require __DIR__ . '/../../api/helpers/request.php';

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
// ─── Rate limiting ────────────────────────────────────────────────

function attemptRows(PDO $db): int {
    return (int) $db->query('SELECT COUNT(*) FROM auth_login_attempts')->fetchColumn();
}

/** Simulates assertLoginAllowed without exiting: true = allowed. */
function tryLogin(PDO $db, string $ip, string $username, int $now, ?string $deviceKey = null): bool {
    pruneLoginAttempts($db, $now);
    foreach (loginRateLimitBuckets($ip, $username, $deviceKey) as $key => $limit) {
        if (consumeLoginAttempt($db, $key, $limit, $now) > 0) {
            return false;
        }
    }
    return true;
}

$now = 1_000_000;
$db->exec('DELETE FROM auth_login_attempts');

$buckets = array_keys(loginRateLimitBuckets('10.0.0.1', 'Admin '));
assertTest(count(array_unique($buckets)) === 3, 'There must be three separate buckets.');
assertTest(
    $buckets[1] === array_keys(loginRateLimitBuckets('10.0.0.1', 'other'))[1],
    'IP bucket must not depend on the username.'
);
assertTest(
    $buckets[2] === array_keys(loginRateLimitBuckets('10.0.0.2', 'admin'))[2],
    'Username bucket must not depend on the IP.'
);

// IP + username: blocked after 5 attempts.
for ($i = 0; $i < LOGIN_MAX_ATTEMPTS_PER_IP_USERNAME; $i++) {
    assertTest(tryLogin($db, '10.0.0.1', 'admin', $now), "Attempt {$i} should be allowed.");
}
assertTest(!tryLogin($db, '10.0.0.1', 'admin', $now), 'Sixth attempt on the same account must be blocked.');
assertTest(
    consumeLoginAttempt($db, $buckets[0], LOGIN_MAX_ATTEMPTS_PER_IP_USERNAME, $now + 10)
        === LOGIN_BLOCK_SECONDS - 10,
    'retryAfter must be the remaining block time.'
);
assertTest(
    tryLogin($db, '10.0.0.1', 'admin', $now + LOGIN_BLOCK_SECONDS),
    'Login must be allowed again after the block expires.'
);

// Password spraying: one IP, a different username each time.
$db->exec('DELETE FROM auth_login_attempts');
$allowed = 0;
for ($i = 0; $i < 100; $i++) {
    if (tryLogin($db, '10.0.0.2', "user{$i}", $now)) {
        $allowed++;
    }
}
assertTest(
    $allowed === LOGIN_MAX_ATTEMPTS_PER_IP,
    "Password spraying from one IP must stop at the IP limit ({$allowed} allowed)."
);

// Distributed guessing: a different IP each time, same account.
$db->exec('DELETE FROM auth_login_attempts');
$allowed = 0;
for ($i = 0; $i < 100; $i++) {
    if (tryLogin($db, "10.1.0.{$i}", 'victim', $now)) {
        $allowed++;
    }
}
assertTest(
    $allowed === LOGIN_MAX_ATTEMPTS_PER_USERNAME,
    "Distributed guessing on one account must stop ({$allowed} allowed)."
);

// Successful logins do not count toward the shared IP/username buckets.
$db->exec('DELETE FROM auth_login_attempts');
for ($i = 0; $i < LOGIN_MAX_ATTEMPTS_PER_IP * 2; $i++) {
    assertTest(tryLogin($db, '10.0.0.3', "office{$i}", $now), 'Legitimate login behind NAT was rejected.');
    recordSuccessfulLogin($db, '10.0.0.3', "office{$i}", null, $now);
}
[$ipUserKey, $ipKey] = array_keys(loginRateLimitBuckets('10.0.0.3', 'office0'));
$stmt = $db->prepare('SELECT COUNT(*) FROM auth_login_attempts WHERE ip = :ip');
$stmt->execute(['ip' => $ipUserKey]);
assertTest((int) $stmt->fetchColumn() === 0, 'Successful login must clear the IP+username bucket.');
$stmt = $db->prepare('SELECT attempts FROM auth_login_attempts WHERE ip = :ip');
$stmt->execute(['ip' => $ipKey]);
assertTest((int) $stmt->fetchColumn() === 0, 'Successful login must refund the IP attempt.');

// Expired rows are pruned; active blocks remain.
$db->exec('DELETE FROM auth_login_attempts');
for ($i = 0; $i < 20; $i++) {
    tryLogin($db, "10.2.0.{$i}", "junk{$i}", $now);
}
assertTest(attemptRows($db) > 0, 'Attempts must be recorded.');
for ($i = 0; $i <= LOGIN_MAX_ATTEMPTS_PER_IP_USERNAME; $i++) {
    tryLogin($db, '10.3.0.1', 'blocked', $now + LOGIN_WINDOW_SECONDS - 1);
}
pruneLoginAttempts($db, $now + LOGIN_WINDOW_SECONDS);
assertTest(
    attemptRows($db) === 3,
    'Only buckets from the active window may remain (' . attemptRows($db) . ').'
);
pruneLoginAttempts($db, $now + LOGIN_WINDOW_SECONDS * 3);
assertTest(attemptRows($db) === 0, 'Expired attempts must be pruned.');

// ─── Timing: no extra hash for unknown users ─────────────────────

$dummy = loginPasswordHash(null);
assertTest(password_get_info($dummy)['algo'] === PASSWORD_DEFAULT, 'Dummy hash must use the default algorithm.');
assertTest(
    !password_needs_rehash($dummy, PASSWORD_DEFAULT),
    'Dummy hash must have the same cost as real hashes.'
);
assertTest(!password_verify('', $dummy), 'Dummy hash must not accept an empty password.');
assertTest(
    loginPasswordHash(['password_hash' => $users[0]['password_hash']]) === $users[0]['password_hash'],
    'Existing user must use their own hash.'
);
assertTest(
    !str_contains((string) file_get_contents(__DIR__ . '/../../api/handlers/auth-login.php'), 'password_hash(\'dummy'),
    'Login must not compute a dummy hash per request.'
);
assertTest(
    !isStrongPassword(str_repeat('x', 60) . 'Long-Suffix-1', 'tester'),
    'Passwords longer than 72 bytes must be rejected (bcrypt truncates them).'
);

assertTest(
    rateLimitIp('2001:db8:1:2:aaaa::1') === rateLimitIp('2001:db8:1:2:ffff::9'),
    'IPv6 addresses in one /64 must share a rate-limit bucket.'
);
assertTest(
    rateLimitIp('2001:db8:1:2::1') !== rateLimitIp('2001:db8:1:3::1'),
    'Different IPv6 /64 networks must get separate buckets.'
);
assertTest(rateLimitIp('203.0.113.9') === '203.0.113.9', 'IPv4 addresses must stay unchanged.');
assertTest(
    rateLimitIp('::ffff:203.0.113.9') !== rateLimitIp('::ffff:203.0.113.10'),
    'IPv4-mapped IPv6 addresses must not be grouped into one bucket.'
);

// ─── Trusted devices ─────────────────────────────────────────────

$victim = [
    'id' => str_repeat('a', 32),
    'password_hash' => password_hash('Victim-Password-1', PASSWORD_DEFAULT),
];
$deviceCookie = $victim['id'] . '.' . $now . '.' . trustedDeviceMac($victim, $now);
$deviceKey = trustedDeviceKey($victim, $deviceCookie, $now + 60);
assertTest(is_string($deviceKey), 'A valid device cookie must be accepted.');
assertTest(trustedDeviceKey(null, $deviceCookie, $now) === null, 'Unknown users have no trusted devices.');
assertTest(trustedDeviceKey($victim, null, $now) === null, 'A missing cookie is not trusted.');
assertTest(
    trustedDeviceKey($victim, $victim['id'] . '.' . $now . '.' . str_repeat('0', 64), $now) === null,
    'A forged MAC must be rejected.'
);
assertTest(
    trustedDeviceKey(['id' => str_repeat('b', 32)] + $victim, $deviceCookie, $now) === null,
    'A device cookie must only count for its own user.'
);
assertTest(
    trustedDeviceKey($victim, $deviceCookie, $now + TRUSTED_DEVICE_SECONDS + 1) === null,
    'An expired device cookie must be rejected.'
);
assertTest(
    trustedDeviceKey(['password_hash' => password_hash('Changed-Password-1', PASSWORD_DEFAULT)] + $victim, $deviceCookie, $now) === null,
    'Changing the password must revoke trusted devices.'
);

// An attacker fills the username bucket from many IPs ...
$db->exec('DELETE FROM auth_login_attempts');
for ($i = 0; $i < 100; $i++) {
    tryLogin($db, "10.4.0.{$i}", 'victim', $now);
}
assertTest(!tryLogin($db, '10.5.0.1', 'victim', $now), 'Unknown devices stay blocked by the username bucket.');
// ... but the victim's own browser can still log in.
assertTest(
    tryLogin($db, '10.5.0.2', 'victim', $now, $deviceKey),
    'A trusted device must not be locked out by the username bucket.'
);
$allowed = 1;
for ($i = 3; $i < 50; $i++) {
    if (tryLogin($db, "10.5.0.{$i}", 'victim', $now, $deviceKey)) {
        $allowed++;
    }
}
assertTest(
    $allowed === LOGIN_MAX_ATTEMPTS_PER_DEVICE,
    "A stolen device cookie must have its own limit ({$allowed} allowed)."
);

// ─── Sessions ────────────────────────────────────────────────────

session_save_path(sys_get_temp_dir());
$_COOKIE = [];
assertTest(currentUser($db) === null, 'Anonymous requests must not get a user.');
assertTest(session_status() !== PHP_SESSION_ACTIVE, 'Anonymous requests must not start a session.');

$admin = findUserByUsername($db, 'admin');
loginUser($admin);
$loginTime = time();
assertTest(currentUser($db, $loginTime + 60) !== null, 'A fresh session must be valid.');
assertTest(
    currentUser($db, $loginTime + 60 + SESSION_IDLE_SECONDS - 1) !== null,
    'Activity must extend the idle timeout.'
);
assertTest(
    currentUser($db, $loginTime + 60 + 2 * SESSION_IDLE_SECONDS) === null,
    'An idle session must expire.'
);
loginUser($admin);
for ($t = $loginTime; $t <= $loginTime + SESSION_ABSOLUTE_SECONDS; $t += 3600) {
    currentUser($db, $t);
}
assertTest(
    currentUser($db, $loginTime + SESSION_ABSOLUTE_SECONDS + 1) === null,
    'A session must expire after the absolute timeout, even when active.'
);

// ─── Client IP ───────────────────────────────────────────────────

assertTest(ipInRange('10.1.2.3', '10.0.0.0/8'), 'IPv4 CIDR must match.');
assertTest(!ipInRange('11.1.2.3', '10.0.0.0/8'), 'IPv4 CIDR must not over-match.');
assertTest(ipInRange('192.168.1.130', '192.168.1.128/25'), 'Partial-byte CIDR must match.');
assertTest(!ipInRange('192.168.1.127', '192.168.1.128/25'), 'Partial-byte CIDR must not over-match.');
assertTest(ipInRange('2001:db8::5', '2001:db8::/32'), 'IPv6 CIDR must match.');
assertTest(!ipInRange('10.0.0.1', '10.0.0.0/99'), 'An invalid prefix must not match.');
assertTest(!ipInRange('10.0.0.1', '::/0'), 'IPv4 must not match an IPv6 range.');
assertTest(isLoopbackIp('127.0.0.1') && isLoopbackIp('::1') && isLoopbackIp('::ffff:127.0.0.1'), 'Loopback must be detected.');
assertTest(!isLoopbackIp('192.168.1.10') && !isLoopbackIp('') && !isLoopbackIp('unknown'), 'Non-loopback must be rejected.');

$proxies = ['10.0.0.0/8'];
assertTest(
    resolveClientIp('203.0.113.7', '198.51.100.1', $proxies) === '203.0.113.7',
    'X-Forwarded-For must be ignored from untrusted senders.'
);
assertTest(
    resolveClientIp('10.0.0.2', '198.51.100.9', []) === '10.0.0.2',
    'Without trusted proxies X-Forwarded-For must be ignored.'
);
assertTest(
    resolveClientIp('10.0.0.2', '1.2.3.4, 198.51.100.9', $proxies) === '198.51.100.9',
    'The client-forged left part of X-Forwarded-For must be ignored.'
);
assertTest(
    resolveClientIp('10.0.0.2', '198.51.100.9, 10.0.0.5', $proxies) === '198.51.100.9',
    'Chained trusted proxies must be skipped.'
);
assertTest(
    resolveClientIp('10.0.0.2', 'garbage', $proxies) === '10.0.0.2',
    'A malformed X-Forwarded-For must fall back to the proxy address.'
);

echo "PHP auth tests passed\n";
