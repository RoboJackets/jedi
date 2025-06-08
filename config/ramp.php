<?php

declare(strict_types=1);

return [
    'enabled' => env('RAMP_ENABLED', false),
    'server' => env('RAMP_SERVER'),
    'client' => [
        'id' => env('RAMP_CLIENT_ID'),
        'secret' => env('RAMP_CLIENT_SECRET'),
    ],
];
