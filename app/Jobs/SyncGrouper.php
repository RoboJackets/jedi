<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Grouper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncGrouper extends SyncJob
{
    /**
     * The queue this job will run on.
     *
     * @var string
     */
    public $queue = 'grouper';

    /**
     * Create a new job instance.
     *
     * @param  string  $username  The user's GT username
     * @param  bool  $is_access_active  Whether the user should have access to systems
     * @param  array<string>  $teams  The names of the teams the user is in
     */
    protected function __construct(string $username, bool $is_access_active, array $teams)
    {
        parent::__construct($username, '', '', $is_access_active, $teams);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $membershipResponse = Grouper::getGroupMembershipsForUser($this->username);
        $memberships = property_exists($membershipResponse->WsGetMembershipsResults, 'wsMemberships')
            ? collect($membershipResponse->WsGetMembershipsResults->wsMemberships) :
            Collection::empty();
        $filteredMemberships = $memberships->where('membershipType', 'immediate');
        $userGroupFullNames = $filteredMemberships->pluck('groupName');

        $allGroupsResponse = Cache::remember('grouper_groups', 900, static fn (): object => Grouper::getGroups());
        $allGroups = collect($allGroupsResponse->WsFindGroupsResults->groupResults);

        $userTeams = array_map('strtolower', $this->teams);
        $desiredGroups = $allGroups->filter(
            static fn (object $group): bool => in_array(strtolower($group->displayExtension), $userTeams, true)
        );

        foreach ($allGroups as $group) {
            if (
                $this->is_access_active
                && $desiredGroups->contains($group)
                && ! in_array($group->name, (array) $userGroupFullNames, true)
            ) {
                // User should be, but is not currently, in the group
                $this->debug('Adding user to group '.$group->name);
                Grouper::addUserToGroup($group->displayExtension, $this->username);
                $this->info('Added user to group '.$group->name);
            } elseif ($this->is_access_active && $desiredGroups->contains($group)) {
                // User should be, and is currently, in the group. No action required.
                $this->debug('User is already a member of group '.$group->name);
            } else {
                // User should not be in the group
                $this->debug('Removing user from group '.$group->name);
                Grouper::removeUserFromGroup($group->displayExtension, $this->username);
                $this->info('Removed user from group '.$group->name);
            }
        }
    }

    private function debug(string $message): void
    {
        Log::debug($this->jobDetails().$message);
    }

    private function info(string $message): void
    {
        Log::info($this->jobDetails().$message);
    }

    private function jobDetails(): string
    {
        return self::class.' GT='.$this->username.' ';
    }
}
