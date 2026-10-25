<?php

$user = currentUser($db);
jsonResponse([
    'user' => $user === null ? null : publicUser($user),
    'csrfToken' => $user === null ? null : csrfToken(),
]);
