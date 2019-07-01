<?php declare(strict_types = 1);

return [
    /*
    |--------------------------------------------------------------------------
    | CAS Hostname
    |--------------------------------------------------------------------------
    | Example: 'cas.myuniv.edu'.
    */
    'host'         => env('VAULT_HOST', ''),
    'username'     => env('VAULT_USERNAME', ''),
    'password'     => env('VAULT_PASSWORD', ''),
    'vault'        => env('VAULT_VAULT', ''),
];
