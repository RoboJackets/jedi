<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;
use Laravel\Horizon\MasterSupervisor;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    #[\Override]
    public function boot(): void
    {
        parent::boot();

        if (config('horizon.master_supervisor_name') !== null) {
            // @phan-suppress-next-line PhanTypeMismatchReturn
            MasterSupervisor::determineNameUsing(static fn (): string => config('horizon.master_supervisor_name'));
        }
    }

    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     */
    #[\Override]
    protected function gate(): void
    {
        Gate::define('viewHorizon', static fn (User $user): bool => boolval($user->admin));
    }

    /**
     * Register any application services.
     *
     * @phan-suppress PhanTypeMismatchArgument
     */
    #[\Override]
    public function register(): void
    {
        Horizon::routeSlackNotificationsTo(config('slack.endpoint'));
    }
}
