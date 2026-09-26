<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Issuer & Audience
    |--------------------------------------------------------------------------
    |
    | Every access token carries these claims and every verifier rejects a
    | token whose claims do not match. Any backend implementation must use
    | the same values to accept tokens issued by another one.
    |
    */

    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),

    'audience' => env('JWT_AUDIENCE', 'my-workouts'),

    /*
    |--------------------------------------------------------------------------
    | Signing Keys (Ed25519 / EdDSA)
    |--------------------------------------------------------------------------
    |
    | Base64-encoded raw keys as produced by `php artisan jwt:keys`. The
    | private key is the 64-byte libsodium secret key, the public key the
    | 32-byte verification key that is also published at /.well-known/jwks.json.
    |
    */

    'private_key' => env('JWT_PRIVATE_KEY'),

    'public_key' => env('JWT_PUBLIC_KEY'),

    'key_id' => env('JWT_KEY_ID'),

    /*
    |--------------------------------------------------------------------------
    | Lifetimes (seconds)
    |--------------------------------------------------------------------------
    */

    'access_ttl' => (int) env('JWT_ACCESS_TTL', 900),

    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 60 * 60 * 24 * 30),

    'leeway' => (int) env('JWT_LEEWAY', 30),

    /*
    |--------------------------------------------------------------------------
    | Refresh Cookie (web clients)
    |--------------------------------------------------------------------------
    |
    | Web clients never see the refresh token; it travels only in this
    | httpOnly cookie scoped to the refresh endpoint.
    |
    */

    'refresh_cookie' => [
        'name' => 'refresh_token',
        'path' => '/api/v1/auth/refresh',
        'secure' => (bool) env('JWT_COOKIE_SECURE', true),
    ],

];
