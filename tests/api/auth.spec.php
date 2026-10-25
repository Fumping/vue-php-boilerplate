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
// ─── Rate limiting ────────────────────────────────────────────────

function attemptRows(PDO $db): int {
    return (int) $db->query('SELECT COUNT(*) FROM auth_login_attempts')->fetchColumn();
}

/** Simulates assertLoginAllowed without exiting: true = allowed. */
function tryLogin(PDO $db, string $ip, string $username, int $now): bool {
    pruneLoginAttempts($db, $now);
    foreach (loginRateLimitBuckets($ip, $username) as $key => $limit) {
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
    recordSuccessfulLogin($db, '10.0.0.3', "office{$i}", $now);
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

echo "PHP auth tests passed\n";
