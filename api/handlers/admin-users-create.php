<?php

requireAdmin($db);
requireCsrfToken();
$body = requireJsonBody();
$username = normalizeUsername(is_string($body['username'] ?? null) ? $body['username'] : '');
$password = is_string($body['password'] ?? null) ? $body['password'] : '';
$role = is_string($body['role'] ?? null) ? $body['role'] : 'user';

if (!isValidUsername($username)) {
    errorResponse('invalid_username', 422);
}
if (!isStrongPassword($password, $username)) {
    errorResponse('password_too_weak', 422);
}
if (!in_array($role, ['user', 'admin'], true)) {
    errorResponse('invalid_role', 422);
}
if (findUserByUsername($db, $username) !== null) {
    errorResponse('username_taken', 409);
}

$id = bin2hex(random_bytes(16));
$stmt = $db->prepare(
    'INSERT INTO users (
        id, username, password_hash, role, must_change_password
    ) VALUES (
        :id, :username, :password_hash, :role, 1
    )'
);
try {
    $stmt->execute([
        'id' => $id,
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => $role,
    ]);
} catch (PDOException $exception) {
    if ((string) $exception->getCode() === '23000') {
        errorResponse('username_taken', 409);
    }
    throw $exception;
}

$createdUser = findUserById($db, $id);
jsonResponse(publicUser($createdUser), 201);
