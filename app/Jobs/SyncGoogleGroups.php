<?php

declare(strict_types=1);

// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter
// phpcs:disable SlevomatCodingStandard.Functions.UnusedParameter

namespace App\Jobs;

use App\Services\Apiary;
use Exception;
use Google\Service\Exception as Google_Service_Exception;
use Google_Client;
use Google_Service_Directory;
use Google_Service_Directory_Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\RateLimitedMiddleware\RateLimited;

class SyncGoogleGroups extends SyncJob
{
    /**
     * The queue this job will run on.
     *
     * @var string
     */
    public $queue = 'google';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 5;

    /**
     * Create a new job instance.
     *
     * @param  string  $username  The user's GT username
     * @param  bool  $is_access_active  Whether the user should have access to systems
     * @param  array<string>  $teams  The names of the teams the user is in
     * @param  string  $gmail_address  The user's Gmail address
     *
     * @psalm-mutation-free
     */
    public function __construct(
        string $username,
        bool $is_access_active,
        array $teams,
        private readonly string $gmail_address
    ) {
        parent::__construct($username, '', '', $is_access_active, $teams);
    }

    /**
     * Execute the job.
     *
     * @phan-suppress PhanTypeMismatchArgument
     * @phan-suppress PhanUndeclaredClassProperty
     */
    #[\Override]
    public function handle(): void
    {
        $client = new Google_Client();
        $client->setAuthConfig(config('google.credentials'));
        $client->setApplicationName('MyRoboJackets');
        $client->setScopes(['https://www.googleapis.com/auth/admin.directory.group.member']);
        // The subject is the user that the service account "impersonates"
        $client->setSubject(config('google.admin'));

        $service = new Google_Service_Directory($client);

        $member = new Google_Service_Directory_Member();
        $member->setEmail($this->gmail_address);
        $member->setRole('MEMBER');

        // Cache for 15 minutes
        $allGroups = Cache::remember('apiary_google_groups_teams', 15, fn (): Collection => $this->getAllGroups());
        // Get the groups that the user should be in
        $user_teams = $this->teams;
        $activeGroups = $allGroups->filter(static fn ($group, $team): bool => in_array($team, $user_teams, true));

        foreach ($allGroups as $group) {
            // @phan-suppress-next-line PhanPluginNonBoolInLogicalArith
            if ($this->is_access_active && $activeGroups->contains($group)) {
                $this->debug('Adding to group '.$group);
                try {
                    $service->members->insert($group, $member);
                } catch (Google_Service_Exception $e) {
                    if ($e->getCode() === 409) {
                        $this->info('User was already a member of Google Group '.$group);

                        continue;
                    }
                    throw $e;
                }
                $this->info('Added user to Google Group '.$group);
            } else {
                $this->debug('Removing from group '.$group);
                try {
                    $service->members->delete($group, $this->gmail_address);
                } catch (Google_Service_Exception $e) {
                    if ($e->getCode() === 404) {
                        $this->info('User was already not a member of Google Group '.$group);

                        continue;
                    }
                    throw $e;
                }
                $this->info('Removed user from Google Group '.$group);
            }
        }
    }

    /**
     * Get all groups in the domain.
     *
     * @return \Illuminate\Support\Collection<string,string>
     *
     * @phan-suppress PhanTypeMismatchReturn
     */
    private function getAllGroups(): Collection
    {
        $client = Apiary::client();

        $response = $client->get('/api/v1/teams');

        if ($response->getStatusCode() !== 200) {
            throw new Exception(
                'Apiary returned an unexpected HTTP response code '.$response->getStatusCode().', expected 200'
            );
        }

        $responseBody = $response->getBody()->getContents();
        $json = json_decode($responseBody);

        if ($json->status !== 'success') {
            throw new Exception('Apiary returned an unexpected response '.$responseBody.', expected status: success');
        }

        $teams = collect($json->teams);

        return $teams->filter(
            static fn (object $team): bool => $team->google_group !== null
                // @phan-suppress-next-line PhanTypeMismatchArgumentInternal
                && ! in_array($team->google_group, config('google.manual_groups'), true)
        )->mapWithKeys(
            static fn (object $team): array => [$team->name => $team->google_group]
        );
    }

    private function debug(string $message): void
    {
        Log::debug(self::jobDetails().$message);
    }

    private function info(string $message): void
    {
        Log::info(self::jobDetails().$message);
    }

    /**
     * Build details about this job for logging.
     *
     * @psalm-mutation-free
     */
    private function jobDetails(): string
    {
        return self::class.' GT='.$this->username.' Gmail='.$this->gmail_address.' ';
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     *
     * @psalm-mutation-free
     */
    #[\Override]
    public function tags(): array
    {
        return [
            'user:'.$this->username,
            'active:'.($this->is_access_active ? 'true' : 'false'),
            'google_account:'.$this->gmail_address,
        ];
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<object>
     */
    public function middleware(): array
    {
        return [
            (new RateLimited())
                ->allow(1)
                ->everySecond()
                ->releaseAfterSeconds(30)
                ->releaseAfterBackoff($this->attempts(), 4),
        ];
    }
}
