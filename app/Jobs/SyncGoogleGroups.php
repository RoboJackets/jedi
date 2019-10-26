<?php declare(strict_types = 1);

// phpcs:disable Generic.NamingConventions.CamelCapsFunctionName.ScopeNotCamelCaps

namespace App\Jobs;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Google_Client;
use Google_Service_Directory;
use Google_Service_Directory_Member;

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
     * @param string $github_username The user's GitHub username
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
        $client->setAuthConfig(config('google.credentials'));
        $client->setApplicationName('MyRoboJackets');
        $client->setScopes(['https://www.googleapis.com/auth/admin.directory.group.member']);
        $client->setSubject(config('google.admin'));

        $service = new Google_Service_Directory($client);

        $member = new Google_Service_Directory_Member();
        $member->setEmail($this->gmail_address);
        $member->setRole('MEMBER');

        $allGroups = $this->getAllGroups();
        $activeGroups = $allGroups->filter(function ($group, $team) {
            return in_array($team, $this->teams);
        });

        $this->debug('Got all groups: '.print_r($allGroups, true));

        foreach ($allGroups as $team => $group) {
            if ($this->is_access_active && $activeGroups->contains($group)) {
                $this->debug('Adding to group '.$group);
                $service->members->insert($group, $member);
            } else {
                $this->debug('Removing from group '.$group);
                $service->members->delete($group, $this->gmail_address);
            }
        }
    }

    private function getAllGroups()
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

        return $teams->filter(function ($team) {
            return null !== $team->google_group;
        })->mapWithKeys(function ($team) {
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
