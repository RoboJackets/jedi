<?php

declare(strict_types=1);

namespace App\Util;

use Closure;
use Illuminate\Support\Str;
use Sentry\SentrySdk;
use Sentry\Tracing\SamplingContext;
use Sentry\Tracing\SpanContext;

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

    /**
     * Methods that should be ignored for performance tracing.
     *
     * @phan-read-only
     *
     * @var array<string>
     */
    private static array $ignoreMethods = [
        'GET',
        'HEAD',
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
            array_key_exists('method', $transactionData) &&
            in_array($transactionData['method'], self::$ignoreMethods, true) &&
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
