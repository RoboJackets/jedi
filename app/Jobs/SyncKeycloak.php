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
     */
    protected function __construct(
        string $username,
        bool $is_access_active,
        array $teams
    ) {
        parent::__construct($username, '', '', $is_access_active, $teams);
    }

    /**
     * Execute the job.
     *
     * @phan-suppress PhanTypeSuspiciousStringExpression
     */
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

        if (property_exists($user, 'attributes') && property_exists($user->attributes, 'googleWorkspaceAccount')) {
            SyncGoogleGroups::dispatch(
                $this->username,
                $this->is_access_active,
                $this->teams,
                $user->attributes->googleWorkspaceAccount[0]
            );
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
                        .' because the user is not enabled'
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
                    .' because they are no longer in the group in Apiary'
                );
                Keycloak::removeUserFromGroup($user->id, $group->id);
            });
    }
}
