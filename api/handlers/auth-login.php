<?php

$body = requireJsonBody();
$username = is_string($body['username'] ?? null) ? $body['username'] : '';
$password = is_string($body['password'] ?? null) ? $body['password'] : '';
$ip = clientIp();

assertLoginAllowed($db, $ip, $username);
$user = findUserByUsername($db, $username);
$valid = $username !== '' && $password !== ''
    && password_verify($password, loginPasswordHash($user));

if (!$valid || $user === null) {
    errorResponse('invalid_credentials', 401);
}

recordSuccessfulLogin($db, $ip, $username);
if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $stmt = $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $stmt->execute([
        'hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $user['id'],
    ]);
}

loginUser($user);
jsonResponse([
    'user' => publicUser($user),
    'csrfToken' => csrfToken(),
]);
