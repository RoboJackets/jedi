<?php

declare(strict_types=1);

return [
    'enabled' => env('CLICKUP_ENABLED', false),
    'workspace_id' => env('CLICKUP_WORKSPACE_ID'),
    'email' => env('CLICKUP_EMAIL'),
    'password' => env('CLICKUP_PASSWORD'),
];
