<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Providers;

use App\Jobs\SyncGitHub;
use App\Jobs\SyncGoogleGroups;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // nothing to do here
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        RateLimiter::for('google-groups', static fn (SyncGoogleGroups $job): Limit => Limit::perSecond(10, 5));
        RateLimiter::for('github', static fn (SyncGitHub $job): Limit => Limit::perSecond(10, 5));
    }
}
