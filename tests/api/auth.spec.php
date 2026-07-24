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
assertTest(
    loginRateLimitKey('127.0.0.1', 'admin') !== loginRateLimitKey('127.0.0.1', 'other'),
    'Rate limiting moet per IP en gebruikersnaam gelden.'
);

echo "PHP auth tests passed\n";
