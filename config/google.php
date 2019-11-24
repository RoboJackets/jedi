<?php

declare(strict_types=1);

return [
    'enabled' => env('GOOGLE_ENABLED', false),
    'credentials' => env('GOOGLE_CREDENTIALS'),
    'admin' => env('GOOGLE_ADMIN', 'admin@robojackets.org'),
];
