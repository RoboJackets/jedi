<?php

declare(strict_types=1);

return [
    'enabled' => env('GOOGLE_ENABLED', false),
    'credentials' => json_decode(env('GOOGLE_CREDENTIALS'), true),
    'admin' => env('GOOGLE_ADMIN'),
    'manual_groups' => explode(',', env('GOOGLE_MANUAL_GROUPS', '')),
];
