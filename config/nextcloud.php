<?php

declare(strict_types=1);

return [
    'enabled' => env('NEXTCLOUD_ENABLED', false),
    'server'  => env('NEXTCLOUD_SERVER'),
    'username' => env('NEXTCLOUD_USERNAME'),
    'password' => env('NEXTCLOUD_PASSWORD'),
];
