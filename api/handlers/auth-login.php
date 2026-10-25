<?php

$body = requireJsonBody();
$username = is_string($body['username'] ?? null) ? $body['username'] : '';
$password = is_string($body['password'] ?? null) ? $body['password'] : '';
$ip = clientIp();

$user = findUserByUsername($db, $username);
$deviceKey = trustedDeviceKey($user, $_COOKIE[trustedDeviceCookieName()] ?? null);
assertLoginAllowed($db, $ip, $username, $deviceKey);
$valid = $username !== '' && $password !== ''
    && password_verify($password, loginPasswordHash($user));

if (!$valid || $user === null) {
    errorResponse('invalid_credentials', 401);
}

recordSuccessfulLogin($db, $ip, $username, $deviceKey);
if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $user['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $stmt->execute([
        'hash' => $user['password_hash'],
        'id' => $user['id'],
    ]);
}

issueTrustedDeviceCookie($user);
loginUser($user);
jsonResponse([
    'user' => publicUser($user),
    'csrfToken' => csrfToken(),
]);
