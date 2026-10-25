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

assertTest(count($users) === 1, 'Bootstrap moet exact één gebruiker maken.');
assertTest($users[0]['username'] === 'admin', 'Bootstrap-gebruikersnaam klopt niet.');
assertTest($users[0]['role'] === 'admin', 'Bootstrap-gebruiker moet admin zijn.');
assertTest((int) $users[0]['must_change_password'] === 1, 'Eerste wijziging moet verplicht zijn.');
assertTest(password_verify('admin', $users[0]['password_hash']), 'Bootstrap-wachtwoord is niet gehasht.');
assertTest((int) $users[0]['session_version'] === 1, 'Sessieversie moet geïnitialiseerd zijn.');
assertTest(
    (int) $db->query('SELECT version FROM schema_version WHERE id = 1')->fetchColumn()
        === DATABASE_SCHEMA_VERSION,
    'Schemaversie klopt niet.'
);
assertTest(isStrongPassword('Long-Unique-Password!', 'tester'), 'Sterk wachtwoord wordt geweigerd.');
assertTest(!isStrongPassword('adminadminadmin', 'admin'), 'Bekend zwak wachtwoord wordt geaccepteerd.');
// ─── Rate limiting ────────────────────────────────────────────────

function attemptRows(PDO $db): int {
    return (int) $db->query('SELECT COUNT(*) FROM auth_login_attempts')->fetchColumn();
}

/** Simuleert assertLoginAllowed zonder exit: true = toegestaan. */
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
assertTest(count(array_unique($buckets)) === 3, 'Er moeten drie aparte buckets zijn.');
assertTest(
    $buckets[1] === array_keys(loginRateLimitBuckets('10.0.0.1', 'other'))[1],
    'IP-bucket mag niet van de gebruikersnaam afhangen.'
);
assertTest(
    $buckets[2] === array_keys(loginRateLimitBuckets('10.0.0.2', 'admin'))[2],
    'Gebruikersnaam-bucket mag niet van het IP afhangen.'
);

// IP + gebruikersnaam: na 5 pogingen geblokkeerd.
for ($i = 0; $i < LOGIN_MAX_ATTEMPTS_PER_IP_USERNAME; $i++) {
    assertTest(tryLogin($db, '10.0.0.1', 'admin', $now), "Poging {$i} hoort toegestaan te zijn.");
}
assertTest(!tryLogin($db, '10.0.0.1', 'admin', $now), 'Zesde poging op hetzelfde account moet blokkeren.');
assertTest(
    consumeLoginAttempt($db, $buckets[0], LOGIN_MAX_ATTEMPTS_PER_IP_USERNAME, $now + 10)
        === LOGIN_BLOCK_SECONDS - 10,
    'retryAfter moet de resterende blokkadetijd zijn.'
);
assertTest(
    tryLogin($db, '10.0.0.1', 'admin', $now + LOGIN_BLOCK_SECONDS),
    'Na afloop van de blokkade moet inloggen weer mogen.'
);

// Password spraying: één IP, steeds een andere gebruikersnaam.
$db->exec('DELETE FROM auth_login_attempts');
$allowed = 0;
for ($i = 0; $i < 100; $i++) {
    if (tryLogin($db, '10.0.0.2', "user{$i}", $now)) {
        $allowed++;
    }
}
assertTest(
    $allowed === LOGIN_MAX_ATTEMPTS_PER_IP,
    "Password spraying vanaf één IP moet na het IP-limiet stoppen ({$allowed} toegestaan)."
);

// Gedistribueerd raden: steeds een ander IP, hetzelfde account.
$db->exec('DELETE FROM auth_login_attempts');
$allowed = 0;
for ($i = 0; $i < 100; $i++) {
    if (tryLogin($db, "10.1.0.{$i}", 'victim', $now)) {
        $allowed++;
    }
}
assertTest(
    $allowed === LOGIN_MAX_ATTEMPTS_PER_USERNAME,
    "Gedistribueerd raden op één account moet stoppen ({$allowed} toegestaan)."
);

// Geslaagde logins tellen niet mee voor gedeelde IP-/gebruikersnaam-buckets.
$db->exec('DELETE FROM auth_login_attempts');
for ($i = 0; $i < LOGIN_MAX_ATTEMPTS_PER_IP * 2; $i++) {
    assertTest(tryLogin($db, '10.0.0.3', "office{$i}", $now), 'Legitieme login achter NAT geweigerd.');
    recordSuccessfulLogin($db, '10.0.0.3', "office{$i}", $now);
}
[$ipUserKey, $ipKey] = array_keys(loginRateLimitBuckets('10.0.0.3', 'office0'));
$stmt = $db->prepare('SELECT COUNT(*) FROM auth_login_attempts WHERE ip = :ip');
$stmt->execute(['ip' => $ipUserKey]);
assertTest((int) $stmt->fetchColumn() === 0, 'Geslaagde login moet IP+gebruikersnaam-bucket wissen.');
$stmt = $db->prepare('SELECT attempts FROM auth_login_attempts WHERE ip = :ip');
$stmt->execute(['ip' => $ipKey]);
assertTest((int) $stmt->fetchColumn() === 0, 'Geslaagde login moet de IP-poging teruggeven.');

// Verlopen rijen worden opgeruimd; actieve blokkades blijven staan.
$db->exec('DELETE FROM auth_login_attempts');
for ($i = 0; $i < 20; $i++) {
    tryLogin($db, "10.2.0.{$i}", "junk{$i}", $now);
}
assertTest(attemptRows($db) > 0, 'Pogingen moeten worden vastgelegd.');
for ($i = 0; $i <= LOGIN_MAX_ATTEMPTS_PER_IP_USERNAME; $i++) {
    tryLogin($db, '10.3.0.1', 'blocked', $now + LOGIN_WINDOW_SECONDS - 1);
}
pruneLoginAttempts($db, $now + LOGIN_WINDOW_SECONDS);
assertTest(
    attemptRows($db) === 3,
    'Alleen de buckets uit het actieve venster mogen overblijven (' . attemptRows($db) . ').'
);
pruneLoginAttempts($db, $now + LOGIN_WINDOW_SECONDS * 3);
assertTest(attemptRows($db) === 0, 'Verlopen pogingen moeten worden opgeruimd.');

// ─── Timing: geen extra hash voor onbekende gebruikers ──────────

$dummy = loginPasswordHash(null);
assertTest(password_get_info($dummy)['algo'] === PASSWORD_DEFAULT, 'Dummy-hash moet het standaardalgoritme gebruiken.');
assertTest(
    !password_needs_rehash($dummy, PASSWORD_DEFAULT),
    'Dummy-hash moet dezelfde kosten hebben als echte hashes.'
);
assertTest(!password_verify('', $dummy), 'Dummy-hash mag geen leeg wachtwoord accepteren.');
assertTest(
    loginPasswordHash(['password_hash' => $users[0]['password_hash']]) === $users[0]['password_hash'],
    'Bestaande gebruiker moet de eigen hash gebruiken.'
);
assertTest(
    !str_contains((string) file_get_contents(__DIR__ . '/../../api/handlers/auth-login.php'), 'password_hash(\'dummy'),
    'Login mag per request geen dummy-hash meer berekenen.'
);

echo "PHP auth tests passed\n";
