<?php

declare(strict_types=1);

return [
    'enabled' => env('KEYCLOAK_ENABLED', false),
    'server' => env('KEYCLOAK_SERVER'),
    'client' => [
        'id' => env('KEYCLOAK_CLIENT_ID'),
        'secret' => env('KEYCLOAK_CLIENT_SECRET'),
        'realm' => env('KEYCLOAK_CLIENT_REALM'),
    ],
    'user_realm' => env('KEYCLOAK_USER_REALM'),
];
