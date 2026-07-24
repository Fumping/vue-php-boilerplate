<?php

const DATABASE_SCHEMA_VERSION = 1;

function initializeDatabase(PDO $db): void {
    if (APP_ENV !== 'production' && DB_DRIVER === 'sqlite') {
        installDevelopmentSqliteSchema($db);
        migrateDevelopmentSqliteSchema($db);
        bootstrapDevelopmentAdmin($db);
        return;
    }

    assertDatabaseSchema($db);
}

function installDevelopmentSqliteSchema(PDO $db): void {
    $schemaPath = dirname(__DIR__, 2) . '/db/schema.sqlite.sql';
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException('Het SQLite-schema kon niet worden gelezen.');
    }
    $db->exec($schema);
}

function migrateDevelopmentSqliteSchema(PDO $db): void {
    $columns = $db->query('PRAGMA table_info(users)')->fetchAll();
    $hasSessionVersion = array_filter(
        $columns,
        static fn (array $column): bool => $column['name'] === 'session_version'
    );

    if ($hasSessionVersion === []) {
        $db->exec(
            'ALTER TABLE users
             ADD COLUMN session_version INTEGER NOT NULL DEFAULT 1'
        );
    }

    $stmt = $db->prepare(
        'UPDATE schema_version
         SET version = :version
         WHERE id = 1 AND version < :version'
    );
    $stmt->execute(['version' => DATABASE_SCHEMA_VERSION]);
}

function assertDatabaseSchema(PDO $db): void {
    try {
        $version = $db->query(
            'SELECT version FROM schema_version WHERE id = 1'
        )->fetchColumn();
        $db->query('SELECT 1 FROM users LIMIT 1');
        $db->query('SELECT 1 FROM auth_login_attempts LIMIT 1');
    } catch (PDOException $exception) {
        throw new RuntimeException(
            'Database-schema ontbreekt. Importeer db/schema.mysql.sql vóór deployment.',
            0,
            $exception
        );
    }

    if ((int) $version !== DATABASE_SCHEMA_VERSION) {
        throw new RuntimeException(sprintf(
            'Database-schema versie %s verwacht; versie %s gevonden.',
            DATABASE_SCHEMA_VERSION,
            $version === false ? 'geen' : (string) $version
        ));
    }
}

function bootstrapDevelopmentAdmin(PDO $db): void {
    $count = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count !== 0) {
        return;
    }

    $username = normalizeUsername(BOOTSTRAP_ADMIN_USERNAME);
    if (!isValidUsername($username)) {
        throw new RuntimeException('Ongeldige BOOTSTRAP_ADMIN_USERNAME.');
    }

    createUser($db, $username, BOOTSTRAP_ADMIN_PASSWORD, 'admin');
}

function createUser(PDO $db, string $username, string $password, string $role): array {
    $id = bin2hex(random_bytes(16));
    $stmt = $db->prepare(
        'INSERT INTO users (
            id, username, password_hash, role, must_change_password
        ) VALUES (
            :id, :username, :password_hash, :role, 1
        )'
    );
    $stmt->execute([
        'id' => $id,
        'username' => normalizeUsername($username),
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);

    $created = findDatabaseUserById($db, $id);
    if ($created === null) {
        throw new RuntimeException('Aangemaakte gebruiker kon niet worden geladen.');
    }
    return $created;
}

function findDatabaseUserById(PDO $db, string $id): ?array {
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

function normalizeUsername(string $username): string {
    return strtolower(trim($username));
}

function isValidUsername(string $username): bool {
    return preg_match('/^[a-z0-9._-]{3,50}$/', $username) === 1;
}

function isStrongPassword(string $password, string $username = ''): bool {
    if (strlen($password) < 12 || strlen($password) > 200) {
        return false;
    }

    $normalized = strtolower($password);
    $common = ['passwordpassword', 'adminadminadmin', 'welkomwelkom', '123456789012'];
    if (in_array($normalized, $common, true)) {
        return false;
    }

    return $username === '' || $normalized !== str_repeat(normalizeUsername($username), 2);
}
