<?php

declare(strict_types=1);

namespace App\Util;

use Illuminate\Support\Str;
use Sentry\Tracing\SamplingContext;

class Sentry
{
    /**
     * URLs that should be ignored for performance tracing.
     *
     * @phan-read-only
     *
     * @var array<string>
     */
    private static array $ignoreUrls = [
        '/health',
        '/ping',
    ];

    public static function tracesSampler(SamplingContext $context): float
    {
        if ($context->getParentSampled() === true) {
            return 1;
        }

        $transactionData = $context->getTransactionContext()?->getData();

        if (
            $transactionData !== null &&
            array_key_exists('url', $transactionData) &&
            (
                in_array($transactionData['url'], self::$ignoreUrls, true) ||
                Str::startsWith($transactionData['url'], '/horizon/')
            )
        ) {
            return 0;
        }

        return 1;
    }
}
