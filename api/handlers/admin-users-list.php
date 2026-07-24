<?php

requireAdmin($db);
$rows = $db->query(
    'SELECT id, username, role, must_change_password, created_at
     FROM users
     ORDER BY created_at ASC, username ASC'
)->fetchAll();

jsonResponse(array_map('publicUser', $rows));
