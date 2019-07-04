<?php declare(strict_types = 1);

return [
    'enabled' => env('SUMS_ENABLED', false),
    'server' => env('SUMS_SERVER'),
    'username' => env('SUMS_USERNAME'),
    'token' => env('SUMS_TOKEN'),
    'billinggroupid' => env('SUMS_BILLING_GROUP_ID'),
];
