<?php declare(strict_types = 1);

namespace App\Jobs;

use App\Services\GitHub;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncGitHub extends AbstractSyncJob
{
    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'github';

    /**
     * The user's GitHub username
     *
     * @var string
     */
    private $github_username;

    /**
     * Create a new job instance
     *
     * @param string $uid             The user's GT username
     * @param string $first_name      The user's first name
     * @param string $last_name       The user's last name
     * @param bool $is_access_active  Whether the user should have access to systems
     * @param array<string>  $teams   The names of the teams the user is in
     * @param string $github_username The user's GitHub username
     */
    protected function __construct(
        string $uid,
        bool $is_access_active,
        array $teams,
        string $github_username
    ) {
        parent::__construct($uid, '', '', $is_access_active, $teams);

        $this->github_username = $github_username;
    }

    /**
     * Execute the job.
     *
     * @return void
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

            if (null === $membership) {
                $this->info('Not a member, building invite');

                $team_ids = [];

                foreach ($teams as $team) {
                    if (!in_array($team->name, $this->teams, true)) {
                        continue;
                    }

                    $this->info('Team ' . $team->name . ' will be in invite');
                    $team_ids[] = $team->id;
                }

                if (0 === count($team_ids)) {
                    $this->warning(
                        'User would have access to GitHub but is not a member of any teams in Apiary, '
                        . 'not sending invitation'
                    );
                    return;
                }

                GitHub::inviteUserToOrganization($user->id, $team_ids);

                $this->info('Invite sent successfully');
            } else {
                $this->info('User is in the organization');

                foreach ($teams as $team) {
                    if (!in_array($team->name, $this->teams, true)) {
                        continue;
                    }

                    $this->debug('User should be in team ' . $team->name . ', checking membership');

                    if (GitHub::getTeamMembership($team->id, $this->github_username)) {
                        $this->debug('User already in team ' . $team->name);
                        continue;
                    } else {
                        $this->info('Adding user to team ' . $team->name);
                        GitHub::addUserToTeam($team->id, $this->github_username);
                        continue;
                    }
                }
            }

            return;
        }

        if (null !== $membership) {
            GitHub::removeUserFromOrganization($this->github_username);
            $this->info('successfully removed from organization');
        }
    }

    private function warning(string $message): void
    {
        Log::warning($this->jobDetails() . $message);
    }

    private function debug(string $message): void
    {
        Log::debug($this->jobDetails() . $message);
    }

    private function info(string $message): void
    {
        Log::info($this->jobDetails() . $message);
    }

    private function jobDetails(): string
    {
        return self::class . ' GT=' . $this->uid . ' GH=' . $this->github_username . ' ';
    }
}
