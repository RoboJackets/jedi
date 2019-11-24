<?php

declare(strict_types=1);

return [
    'enabled' => env('GITHUB_ENABLED', false),
    'app_id' => env('GITHUB_APP_ID'),
    'organization' => env('GITHUB_ORGANIZATION'),
    'private_key' => env('GITHUB_PRIVATE_KEY'),
    'installation_id' => env('GITHUB_INSTALLATION_ID'),
    'admin_token' => env('GITHUB_ADMIN_TOKEN'),
];
