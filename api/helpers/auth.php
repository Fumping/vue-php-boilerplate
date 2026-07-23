<?php

function requireApiKey(): void {
    $key = $_SERVER['HTTP_X_API_KEY'] ?? '';

    // Fallback: sommige servers strippen X-Api-Key uit $_SERVER.
    if ($key === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'X-Api-Key') === 0) {
                $key = $value;
                break;
            }
        }
    }

    if ($key !== API_KEY) {
        errorResponse('invalid_api_key', 401);
    }
}

// AUTH HOOK-POINT.
// Om user-level auth toe te voegen (Supabase, Firebase, session, ...):
//   1. Implementeer requireUser() hieronder.
//   2. Roep 'm aan in handlers die per-user auth vereisen.
// Voorbeeld voor Supabase:
//
// function requireUser(): array {
//     $token = extractBearerToken();
//     $user = validateSupabaseToken($token); // HTTP call naar /auth/v1/user
//     if (!$user) errorResponse('unauthorized', 401);
//     return $user;
// }
