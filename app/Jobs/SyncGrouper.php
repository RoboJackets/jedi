<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Grouper;
use Illuminate\Support\Collection;
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
    #[\Override]
    public function handle(): void
    {
        $membershipResponse = Grouper::getGroupMembershipsForUser($this->username);
        $memberships = property_exists($membershipResponse->WsGetMembershipsResults, 'wsMemberships')
            ? collect($membershipResponse->WsGetMembershipsResults->wsMemberships) :
            Collection::empty();
        $filteredMemberships = $memberships->where('membershipType', 'immediate');
        $userGroupFullNames = $filteredMemberships->pluck('groupName');
        $this->debug('User is a direct member of Grouper groups: '.implode(', ', $userGroupFullNames->toArray()));

        $allGroupsResponse = Cache::remember('grouper_groups', 900, static fn (): object => Grouper::getGroups());
        $allGroups = collect($allGroupsResponse->WsFindGroupsResults->groupResults)->filter(
            static fn (object $group): bool => ! in_array(
                $group->displayExtension,
                (array) config('grouper.manual_groups'),
                true
            )
        );

        $userTeams = array_map('strtolower', $this->teams);
        $desiredGroups = $allGroups->filter(
            static fn (object $group): bool => in_array(strtolower($group->displayExtension), $userTeams, true)
        );

        foreach ($allGroups as $group) {
            $this->debug('Processing '.$group->name);
            $shouldBeGroupMember = $desiredGroups->contains($group);
            $currentGroupMember = $userGroupFullNames->contains($group->name);

            if ($this->is_access_active && $shouldBeGroupMember && ! $currentGroupMember) {
                // User should be, but is not currently, in the group
                $this->debug('Adding user to group '.$group->name);
                Grouper::addUserToGroup($group->displayExtension, $this->username);
                $this->info('Added user to group '.$group->name);
            } elseif ($this->is_access_active && $shouldBeGroupMember) {
                // User should be, and is currently, in the group. No action required.
                $this->debug('User is already a direct member of group '.$group->name);
            } elseif (
                (! $this->is_access_active && $currentGroupMember) ||
                ($this->is_access_active && ! $shouldBeGroupMember && $currentGroupMember)
            ) {
                // Remove the user, either because their access is inactive, or they otherwise shouldn't be a member
                $this->debug('Removing user from group '.$group->name);
                Grouper::removeUserFromGroup($group->displayExtension, $this->username);
                $this->info('Removed user from group '.$group->name);
            } elseif ($this->is_access_active && ! $shouldBeGroupMember && ! $currentGroupMember) {
                // User is access active, but is not and should not be a member of the group
                $this->debug('User is not a direct member of group '.$group->name);
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
