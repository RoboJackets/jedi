<?php declare(strict_types = 1);

namespace App\Providers;

use App\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * Register the Horizon gate.
     *
     * This gate determines who can access Horizon in non-local environments.
     *
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     *
     * @return void
     */
    protected function gate(): void
    {
        Gate::define('viewHorizon', static function (User $user): bool {
            return boolval($user->active);
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        Horizon::routeSlackNotificationsTo(config('slack.endpoint'));
    }
}
