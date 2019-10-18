<?php declare(strict_types = 1);

return [
    'enabled' => env('APIARY_ENABLED', false),
    'server' => env('APIARY_SERVER'),
    'token' => env('APIARY_TOKEN'),
    'sums_timeout_email_template_id' => env('APIARY_SUMS_TIMEOUT_EMAIL_TEMPLATE_ID'),
    'non_sums_timeout_email_template_id' => env('APIARY_NON_SUMS_TIMEOUT_EMAIL_TEMPLATE_ID'),
];
