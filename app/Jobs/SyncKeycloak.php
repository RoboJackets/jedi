<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Jobs;

use App\Services\Keycloak;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncKeycloak extends SyncJob
{
    /**
     * The queue this job will run on.
     *
     * @var string
     */
    public $queue = 'keycloak';

    /**
     * Create a new job instance.
     *
     * @param  string  $username  The user's GT username
     * @param  bool  $is_access_active  Whether the user should have access to systems
     * @param  array<string>  $teams  The names of the teams the user is in
     *
     * @psalm-mutation-free
     */
    protected function __construct(
        string $username,
        bool $is_access_active,
        array $teams,
        private readonly ?string $google_account
    ) {
        parent::__construct($username, '', '', $is_access_active, $teams);
    }

    /**
     * Execute the job.
     */
    #[\Override]
    public function handle(): void
    {
        $cached_user_id = Cache::get('keycloak_user_id_'.$this->username);

        if ($cached_user_id === null) {
            $user = Keycloak::searchForUsername($this->username);

            if ($user === null) {
                Log::info(self::class.': User '.$this->username.' does not exist in Keycloak');

                return;
            } else {
                Cache::add('keycloak_user_id_'.$this->username, $user->id);
            }
        } else {
            $user = Keycloak::getUser($cached_user_id);
        }

        $google_workspace_account = null;
        $ramp_user_id = null;

        if (
            property_exists($user, 'attributes') &&
            property_exists($user->attributes, 'googleWorkspaceAccount') &&
            count($user->attributes->googleWorkspaceAccount) > 0
        ) {
            $google_workspace_account = $user->attributes->googleWorkspaceAccount[0];
        }

        if (
            property_exists($user, 'attributes') &&
            property_exists($user->attributes, 'rampUserId') &&
            count($user->attributes->rampUserId) > 0
        ) {
            $ramp_user_id = $user->attributes->rampUserId[0];
        }

        if ($google_workspace_account !== null && $this->google_account !== $google_workspace_account) {
            SyncGoogleGroups::dispatch(
                $this->username,
                $this->is_access_active,
                $this->teams,
                $google_workspace_account
            );
        }

        if ($google_workspace_account !== null || $ramp_user_id !== null) {
            SyncRamp::dispatch($this->username, $this->is_access_active, $ramp_user_id, $google_workspace_account);
            CopyRampPhoneNumberToApiary::dispatch($this->username, $ramp_user_id, $google_workspace_account);
        }

        if ($this->is_access_active !== $user->enabled) {
            Log::info(self::class.': Updating user '.$this->username.' to enabled='.$this->is_access_active);

            Keycloak::setUserEnabled($user->id, $this->is_access_active);
        }

        if (! $this->is_access_active) {
            collect(Keycloak::getGroupsForUser($user->id))
                ->each(static function (object $group) use ($user): void {
                    Log::info(
                        self::class.': Removing user '.$user->username.' from group '.$group->name
                        .' because the user is not access active in Apiary'
                    );
                    Keycloak::removeUserFromGroup($user->id, $group->id);
                });

            return;
        }

        collect(Cache::rememberForever('keycloak_groups', static fn (): array => Keycloak::getGroups()))
            ->filter(fn (object $group): bool => in_array($group->name, $this->teams, true))
            // @phan-suppress-next-line PhanUnusedClosureParameter
            ->each(static function (object $group, int $key) use ($user): void {
                Log::info(self::class.': Adding user '.$user->username.' to group '.$group->name);
                Keycloak::addUserToGroup($user->id, $group->id);
            });

        collect(Keycloak::getGroupsForUser($user->id))
            ->filter(fn (object $group): bool => ! in_array($group->name, $this->teams, true))
            // @phan-suppress-next-line PhanUnusedClosureParameter
            ->each(static function (object $group, int $key) use ($user): void {
                Log::info(
                    self::class.': Removing user '.$user->username.' from group '.$group->name
                    .' because they are not in the team in Apiary'
                );
                Keycloak::removeUserFromGroup($user->id, $group->id);
            });
    }
}
