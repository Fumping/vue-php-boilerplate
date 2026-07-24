<?php

function initializeDatabase(PDO $db): void {
    $db->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id CHAR(32) PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL,
            must_change_password INTEGER NOT NULL DEFAULT 1,
            session_version INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
    ensureUserSessionVersionColumn($db);

    $db->exec(
        'CREATE TABLE IF NOT EXISTS auth_login_attempts (
            ip VARCHAR(64) PRIMARY KEY,
            attempts INTEGER NOT NULL,
            window_started INTEGER NOT NULL,
            blocked_until INTEGER NOT NULL DEFAULT 0
        )'
    );

    $count = (int) $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count !== 0) {
        return;
    }

    $username = normalizeUsername(BOOTSTRAP_ADMIN_USERNAME);
    if (!isValidUsername($username)) {
        throw new RuntimeException('Ongeldige BOOTSTRAP_ADMIN_USERNAME.');
    }

    $stmt = $db->prepare(
        'INSERT INTO users (
            id, username, password_hash, role, must_change_password
        ) VALUES (
            :id, :username, :password_hash, :role, :must_change_password
        )'
    );
    $stmt->execute([
        'id' => bin2hex(random_bytes(16)),
        'username' => $username,
        'password_hash' => password_hash(BOOTSTRAP_ADMIN_PASSWORD, PASSWORD_DEFAULT),
        'role' => 'admin',
        'must_change_password' => 1,
    ]);
}

function ensureUserSessionVersionColumn(PDO $db): void {
    if (DB_DRIVER === 'sqlite') {
        $columns = $db->query('PRAGMA table_info(users)')->fetchAll();
        $exists = array_filter(
            $columns,
            static fn (array $column): bool => $column['name'] === 'session_version'
        );
    } else {
        $exists = $db->query("SHOW COLUMNS FROM users LIKE 'session_version'")->fetchAll();
    }

    if ($exists === []) {
        $db->exec(
            'ALTER TABLE users
             ADD COLUMN session_version INTEGER NOT NULL DEFAULT 1'
        );
    }
}

function normalizeUsername(string $username): string {
    return strtolower(trim($username));
}

function isValidUsername(string $username): bool {
    return preg_match('/^[a-z0-9._-]{3,50}$/', $username) === 1;
}
