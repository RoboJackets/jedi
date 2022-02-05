<?php

declare(strict_types=1);

return [
    'enabled' => env('SUMS_ENABLED', false),
    'server' => env('SUMS_SERVER'),
    'whitelisted_accounts' => explode(',', env('SUMS_WHITELISTED_ACCOUNTS', '')),
    'token' => env('SUMS_TOKEN'),
    'token_owner' => env('SUMS_TOKEN_OWNER'),
    'billinggroupid' => env('SUMS_BILLING_GROUP_ID'),
    'attendance_timeout_enabled' => env('SUMS_ATTENDANCE_TIMEOUT_ENABLED', false),
    'attendance_timeout_emails' => env('SUMS_ATTENDANCE_TIMEOUT_EMAILS', false),
    'attendance_timeout_limit' => env('SUMS_ATTENDANCE_TIMEOUT_LIMIT', '4 weeks ago'),
];
