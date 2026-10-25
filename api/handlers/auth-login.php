<?php

$body = requireJsonBody();
$username = is_string($body['username'] ?? null) ? $body['username'] : '';
$password = is_string($body['password'] ?? null) ? $body['password'] : '';
$rateLimitKey = loginRateLimitKey(clientIp(), $username);
$ipRateLimitKey = loginIpRateLimitKey(clientIp());

assertLoginAllowed($db, $ipRateLimitKey);
assertLoginAllowed($db, $rateLimitKey);
$user = findUserByUsername($db, $username);
$hash = $user['password_hash'] ?? password_hash('dummy-password-value', PASSWORD_DEFAULT);
$valid = $username !== '' && $password !== '' && password_verify($password, $hash);

if (!$valid || $user === null) {
    recordFailedLogin($db, $ipRateLimitKey, LOGIN_MAX_ATTEMPTS_PER_IP);
    recordFailedLogin($db, $rateLimitKey);
    errorResponse('invalid_credentials', 401);
}

// Only the per-account counter resets; a valid login must not reset the IP budget.
clearFailedLogins($db, $rateLimitKey);
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
