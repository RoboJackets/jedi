<?php

declare(strict_types=1);

return [
    'enabled' => env('GROUPER_ENABLED', false),
    'server' => env('GROUPER_SERVER'),
    'username' => env('GROUPER_USERNAME'),
    'password' => env('GROUPER_PASSWORD'),
    'folder_base_path' => env('GROUPER_FOLDER_BASE_PATH'),
];
