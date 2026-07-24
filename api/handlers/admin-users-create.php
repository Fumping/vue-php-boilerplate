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

try {
    $createdUser = createUser($db, $username, $password, $role);
} catch (PDOException $exception) {
    if ((string) $exception->getCode() === '23000') {
        errorResponse('username_taken', 409);
    }
    throw $exception;
}

jsonResponse(publicUser($createdUser), 201);
