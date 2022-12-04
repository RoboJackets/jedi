<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\GitHub;
use Exception;
use Illuminate\Support\Facades\Log;

class SyncGitHub extends SyncJob
{
    /**
     * The queue this job will run on.
     *
     * @var string
     */
    public $queue = 'github';

    /**
     * Create a new job instance.
     *
     * @param  string  $username  The user's GT username
     * @param  bool  $is_access_active  Whether the user should have access to systems
     * @param  array<string>  $teams  The names of the teams the user is in
     * @param  string  $github_username  The user's GitHub username
     */
    protected function __construct(
        string $username,
        bool $is_access_active,
        array $teams,
        private readonly string $github_username
    ) {
        parent::__construct($username, '', '', $is_access_active, $teams);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (GitHub::getRateLimitRemaining() < 10) {
            throw new Exception('Aborting job as we are near the rate limit');
        }

        $this->debug('Getting user');

        $user = GitHub::getUser($this->github_username);

        $this->debug('Getting membership status');

        $membership = GitHub::getOrganizationMembership($this->github_username);

        if ($this->is_access_active) {
            $this->debug('Getting all teams in organization');

            $teams = GitHub::getTeams();

            if ($membership === null) {
                $this->info('Not a member, building invite');

                $team_ids = [];

                foreach ($teams as $team) {
                    if (! in_array($team->name, $this->teams, true)) {
                        continue;
                    }

                    if (! is_int($team->id)) {
                        throw new Exception('Expected team id to be an integer');
                    }

                    $this->info('Team '.$team->name.' will be in invite');
                    $team_ids[] = $team->id;
                }

                if (count($team_ids) === 0) {
                    $this->warning('User is not a member of any teams in Apiary, not sending invitation');

                    return;
                }

                GitHub::inviteUserToOrganization($user->id, $team_ids);

                $this->info('Invite sent successfully');
            } else {
                $this->info('User is in the organization');
            }

            return;
        }

        if ($membership === null) {
            return;
        }

        GitHub::removeUserFromOrganization($this->github_username);
        $this->info('successfully removed from organization');
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
        return self::class.' GT='.$this->username.' GH='.$this->github_username.' ';
    }
}
