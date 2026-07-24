<?php

$user = requireUser($db, true);
requireCsrfToken();
$body = requireJsonBody();
$currentPassword = is_string($body['currentPassword'] ?? null) ? $body['currentPassword'] : '';
$newPassword = is_string($body['newPassword'] ?? null) ? $body['newPassword'] : '';

if (!password_verify($currentPassword, $user['password_hash'])) {
    errorResponse('invalid_current_password', 400);
}
if (!isStrongPassword($newPassword, $user['username'])) {
    errorResponse('password_too_weak', 422);
}
if (hash_equals($currentPassword, $newPassword)) {
    errorResponse('password_must_change', 422);
}

$stmt = $db->prepare(
    'UPDATE users
     SET password_hash = :password_hash,
         must_change_password = 0,
         session_version = session_version + 1
     WHERE id = :id'
);
$stmt->execute([
    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
    'id' => $user['id'],
]);

$updatedUser = findUserById($db, $user['id']);
loginUser($updatedUser);
jsonResponse([
    'user' => publicUser($updatedUser),
    'csrfToken' => csrfToken(),
]);
