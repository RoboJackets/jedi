<?php

declare(strict_types=1);

return [
    'enabled' => env('APIARY_ENABLED', false),
    'server' => env('APIARY_SERVER'),
    'client_id' => env('APIARY_CLIENT_ID'),
    'client_secret' => env('APIARY_CLIENT_SECRET'),
    'whitelisted_events' => [
        'manual',
        'sums-self-service-ux',
        'duplicate-attendance',
    ],
];
