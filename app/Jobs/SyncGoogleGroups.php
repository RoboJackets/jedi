<?php declare(strict_types = 1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Jobs;

use Exception;
use Google_Client;
use Google_Service_Directory;
use Google_Service_Directory_Member;
use Google_Service_Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncGoogleGroups extends AbstractSyncJob
{
    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'google';

    /**
     * The user's Gmail address
     *
     * @var string
     */
    private $gmail_address;

    /**
     * Create a new job instance
     *
     * @param string $uid             The user's GT username
     * @param string $first_name      The user's first name
     * @param string $last_name       The user's last name
     * @param bool $is_access_active  Whether the user should have access to systems
     * @param array<string>  $teams   The names of the teams the user is in
     * @param string $gmail_address   The user's Gmail address
     */
    public function __construct(
        string $uid,
        string $first_name,
        string $last_name,
        bool $is_access_active,
        array $teams,
        string $gmail_address
    ) {
        parent::__construct($uid, $first_name, $last_name, $is_access_active, $teams);

        $this->gmail_address = $gmail_address;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $client = new Google_Client();
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        $client->setAuthConfig(config('google.credentials'));
        $client->setApplicationName('MyRoboJackets');
        $client->setScopes(['https://www.googleapis.com/auth/admin.directory.group.member']);
        // The subject is the user that the service account "impersonates"
        // @phan-suppress-next-line PhanPartialTypeMismatchArgument
        $client->setSubject(config('google.admin'));

        $service = new Google_Service_Directory($client);

        $member = new Google_Service_Directory_Member();
        $member->setEmail($this->gmail_address);
        $member->setRole('MEMBER');

        // Cache for 15 minutes
        $allGroups = Cache::remember('apiary_google_groups_teams', 15, function () {
            return $this->getAllGroups();
        });
        // Get the groups that the user should be in
        $user_teams = $this->teams;
        // @phan-suppress-next-line PhanUnusedClosureParameter
        $activeGroups = $allGroups->filter(static function ($group, $team) use ($user_teams): bool {
            return in_array($team, $this->teams);
        });

        foreach ($allGroups as $group) {
            // @phan-suppress-next-line PhanPluginNonBoolInLogicalArith
            if ($this->is_access_active && $activeGroups->contains($group)) {
                $this->debug('Adding to group ' . $group);
                try {
                    $service->members->insert($group, $member);
                } catch (Google_Service_Exception $e) {
                    if (409 === $e->getCode()) {
                        $this->info('User was already a member of Google Group ' . $group);
                        continue;
                    }
                    throw $e;
                }
                $this->info('Added user to Google Group ' . $group);
            } else {
                $this->debug('Removing from group ' . $group);
                try {
                    $service->members->delete($group, $this->gmail_address);
                } catch (Google_Service_Exception $e) {
                    if (404 === $e->getCode()) {
                        $this->info('User was already not a member of Google Group ' . $group);
                        continue;
                    }
                    throw $e;
                }
                $this->info('Removed user from Google Group ' . $group);
            }
        }
    }

    private function getAllGroups(): Collection
    {
        $client = AbstractApiaryJob::client();

        $response = $client->get('/api/v1/teams');

        if (200 !== $response->getStatusCode()) {
            throw new Exception(
                'Apiary returned an unexpected HTTP response code ' . $response->getStatusCode() . ', expected 200'
            );
        }

        $responseBody = $response->getBody()->getContents();
        $json = json_decode($responseBody);

        if ('success' !== $json->status) {
            throw new Exception(
                'Apiary returned an unexpected response ' . $responseBody . ', expected status: success'
            );
        }

        $teams = collect($json->teams);

        return $teams->filter(static function ($team) {
            return null !== $team->google_group
                && 'officers@robojackets.org' !== $team->google_group
                && 'developers@robojackets.org' !== $team->google_group;
        })->mapWithKeys(static function ($team) {
            return [$team->name => $team->google_group];
        });
    }

    private function debug(string $message): void
    {
        Log::debug(self::jobDetails() . $message);
    }

    private function info(string $message): void
    {
        Log::info(self::jobDetails() . $message);
    }

    private function jobDetails(): string
    {
        return self::class . ' GT=' . $this->uid . ' Gmail=' . $this->gmail_address . ' ';
    }
}
