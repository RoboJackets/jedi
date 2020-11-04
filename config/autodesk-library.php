<?php

declare(strict_types=1);

return [
    'enabled' => env('AUTODESK_LIBRARY_ENABLED', false),
    'email' => env('AUTODESK_LIBRARY_EMAIL'),
    'password' => env('AUTODESK_LIBRARY_PASSWORD'),
    'hub_id' => env('AUTODESK_LIBRARY_HUB_ID'),
];
