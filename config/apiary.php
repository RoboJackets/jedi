<?php

declare(strict_types=1);

return [
    'enabled' => env('APIARY_ENABLED', false),
    'server' => env('APIARY_SERVER'),
    'token' => env('APIARY_TOKEN'),
    'whitelisted_events' => [
        'manual',
        'sums-self-service-ux',
        'duplicate-attendance',
    ],
];
