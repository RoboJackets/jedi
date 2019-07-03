<?php declare(strict_types = 1);

return [
    'enabled' => env('WORDPRESS_ENABLED', false),
    'server' => env('WORDPRESS_SERVER'),
    'username' => env('WORDPRESS_USERNAME'),
    'password' => env('WORDPRESS_PASSWORD'),
    'team' => env('WORDPRESS_TEAM'),
];
