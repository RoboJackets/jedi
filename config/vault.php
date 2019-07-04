<?php declare(strict_types = 1);

return [
    'enabled' => env('VAULT_ENABLED', false),
    'server' => env('VAULT_SERVER'),
    'username' => env('VAULT_USERNAME'),
    'password' => env('VAULT_PASSWORD'),
];
