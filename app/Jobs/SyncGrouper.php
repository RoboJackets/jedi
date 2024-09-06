<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Grouper;
use Exception;
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
    protected function __construct(
        string $username,
        bool $is_access_active,
        array $teams
    ) {
        parent::__construct($username, '', '', $is_access_active, $teams);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $membershipResponse = Grouper::getGroupMembershipsForUser($this->username);
        $memberships = collect($membershipResponse['WsGetMembershipsResults']['wsMemberships']);
        $filteredMemberships = $memberships->where('membershipType', 'immediate');
        $userGroupFullNames = $filteredMemberships->pluck('groupName');

        $allGroupsResponse = Grouper::getGroups();
        $allGroups = collect($allGroupsResponse['WsFindGroupsResults']['groupResults']);

        $userTeams = $this->teams;
        $desiredGroups = $allGroups->filter(static fn (object $group): bool => in_array($group->displayExtension, $userTeams, true));

        foreach ($allGroups as $group) {
            if ($this->is_access_active && $desiredGroups->contains($group) && !in_array($group->name, $userGroupFullNames)) {
                // User should be, but is not currently, in the group
                $this->debug('Adding user to group '.$group->name);
                Grouper::addUserToGroup($group->displayExtension, $this->username);
                $this->info('Added user to group '.$group->name);
            } else if ($this->is_access_active && $desiredGroups->contains($group)) {
                // User should be, and is currently, in the group. No action required.
                $this->debug('User is already a member of group '.$group->name);
                continue;
            } else {
                // User should not be in the group
                $this->debug('Removing user from group '.$group->name);
                Grouper::removeUserFromGroup($group->displayExtension, $this->username);
                $this->info('Removed user from group '.$group->name);
            }
        }
    }

    private function warning(string $message): void
    {
        Log::warning($this->jobDetails().$message);
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

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        $tags = parent::tags();
        $tags[] = 'grouper:'.$this->username;

        return $tags;
    }
}
