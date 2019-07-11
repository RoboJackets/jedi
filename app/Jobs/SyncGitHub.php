<?php declare(strict_types = 1);

// phpcs:disable Generic.NamingConventions.CamelCapsFunctionName.ScopeNotCamelCaps

namespace App\Jobs;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SimpleJWT\JWT;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\Keys\RSAKey;

class SyncGitHub extends AbstractSyncJob
{
    private const FIFTY_NINE_MINUTES = 59 * 60;

    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'github';

    /**
     * The number of times the job may be attempted.
     *
     * Overridden to 2 here because we want to retry if the issue was with a token expiring (although that should not
     * happen as the token should expire from the cache before it is unusable)
     *
     * @var int
     */
    public $tries = 2;

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
    public function __construct(
        string $uid,
        string $first_name,
        string $last_name,
        bool $is_access_active,
        array $teams,
        string $github_username
    ) {
        parent::__construct($uid, $first_name, $last_name, $is_access_active, $teams);

        $this->$github_username = $github_username;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $token = Cache::get('github_installation_token');

        if (null === $token) {
            $token = self::getInstallationToken();
            Cache::put('github_installation_token', $token, self::FIFTY_NINE_MINUTES);
        }

        $client = new Client(
            [
                'base_uri' => 'https://api.github.com',
                'headers' => [
                    'User-Agent' => 'GitHub App ID ' . config('github.app_id'),
                    'Authorization' => 'Bearer ' . $token,
                ],
                'allow_redirects' => false,
                'http_errors' => false,
            ]
        );

        $response = $client->get('/rate_limit');

        if (intval($response->getHeader('X-RateLimit-Remaining')[0]) < 10) {
            throw new Exception('Aborting job as we are near the rate limit');
        }

        $this->debug('Getting membership status');

        $response = $client->get(
            '/orgs/' . config('github.organization') . '/memberships/' . $this->github_username
        );

        $json = json_decode($response->getBody()->getAllContents());

        if (!is_object($json)) {
            throw new Exception('GitHub did not return an object');
        }

        if ($this->is_access_active) {
            $this->debug('Getting all teams in organization');

            $teams = Cache::get('github_teams');
            $etag = Cache::get('github_teams_etag');

            if (null === $teams) {
                $response = $client->get('/orgs/' . config('github.organization') . '/teams');
                if (200 !== $response->getStatusCode()) {
                    throw new Exception(
                        'GitHub returned an unexpected HTTP response code ' . $response->getStatusCode()
                            . ', expected 200'
                    );
                }

                $teams = json_decode($response->getBody()->getAllContents());

                if (!is_array($teams)) {
                    throw new Exception('GitHub did not return an array');
                }

                $etag = $response->getHeader('ETag')[0];

                Cache::forever('github_teams', $teams);
                Cache::forever('github_teams_etag', $etag);
            } else {
                $response = $client->request(
                    'GET',
                    '/orgs/' . config('github.organization') . '/teams',
                    [
                        'headers' => [
                            'If-None-Match' => $etag,
                        ],
                    ]
                );

                if (200 === $response->getStatusCode()) {
                    $teams = json_decode($response->getBody()->getAllContents());

                    if (!is_array($teams)) {
                        throw new Exception('GitHub did not return an array');
                    }

                    $etag = $response->getHeader('ETag')[0];

                    Cache::forever('github_teams', $teams);
                    Cache::forever('github_teams_etag', $etag);
                } elseif (304 !== $response->getStatusCode()) {
                    throw new Exception(
                        'GitHub returned an unexpected HTTP response code ' . $response->getStatusCode()
                            . ', expected 200 or 304'
                    );
                }
            }

            if (404 === $response->getStatusCode()) {
                $this->info('Not a member, building invite');
                $response = $client->get('/users/' . $this->github_username);

                if (200 !== $response->getStatusCode()) {
                    throw new Exception(
                        'GitHub returned an unexpected HTTP response code ' . $response->getStatusCode()
                            . ', expected 200'
                    );
                }

                $user = json_decode($response->getBody()->getAllContents());

                if (!is_object($teams)) {
                    throw new Exception('GitHub did not return an object');
                }

                $invitee_id = $user->id;

                $team_ids = [];

                foreach ($teams as $team) {
                    if (!in_array($team->name, $this->teams, true)) {
                        continue;
                    }

                    $this->info('Team ' . $team->name . ' will be in invite');
                    $team_ids[] = $team->id;
                }

                $response = $client->request(
                    'POST',
                    '/orgs/' . config('github.organization') . 'invitations',
                    [
                        'json' => [
                            'invitee_id' => $invitee_id,
                            'team_ids' => $team_ids,
                        ],
                    ]
                );

                $this->info('Invite sent successfully');

                if (201 !== $response->getStatusCode()) {
                    throw new Exception(
                        'GitHub returned an unexpected HTTP response code ' . $response->getStatusCode()
                            . ', expected 201'
                    );
                }
            } elseif (200 === $response->getStatusCode()) {
                foreach ($teams as $team) {
                    if (!in_array($team->name, $this->teams, true)) {
                        continue;
                    }

                    $this->debug('User should be in team ' . $team->name . ', checking membership');
                    $response = $client->get('/teams/' . $team->id . '/memberships/' . $this->github_username);

                    if (200 === $response->getStatusCode()) {
                        $this->debug('User already in team ' . $team->name);
                        continue;
                    }

                    if (404 === $response->getStatusCode()) {
                        $this->info('Adding user to team ' . $team->name);
                        $client->put('/teams/' . $team->id . '/memberships/' . $this->github_username);

                        if (200 !== $response->getStatusCode()) {
                            throw new Exception(
                                'GitHub returned an unexpected HTTP response code ' . $response->getStatusCode()
                                    . ', expected 200'
                            );
                        }

                        continue;
                    }

                    throw new Exception(
                        'GitHub returned an unexpected HTTP response code ' . $response->getStatusCode()
                            . ', expected 200 or 404'
                    );
                }
            } else {
                throw new Exception(
                    'GitHub returned an unexpected HTTP response code ' . $response->getStatusCode()
                        . ', expected 200 or 404'
                );
            }

            return;
        }

        if (404 === $response->getStatusCode()) {
            $this->info('not a member and shouldn\'t be - nothing to do');
            return;
        }

        if (200 === $response->getStatusCode()) {
            $response = $client->delete(
                '/orgs/' . config('github.organization') . '/memberships/' . $this->github_username
            );

            if (204 === $response->getStatusCode()) {
                $this->info('successfully removed from organization');
                return;
            }

            throw new Exception(
                'GitHub returned an unexpected HTTP response code ' . $response->getStatusCode()
                    . ', expected 204'
            );
        }

        throw new Exception(
            'GitHub returned an unexpected HTTP response code ' . $response->getStatusCode()
                . ', expected 200 or 404'
        );
    }

    /**
     * Fetches a new installation token to authenticate to the API as the GitHub App
     *
     * @return string
     */
    private static function getInstallationToken(): string
    {
        $jwt = self::generateJWT();

        $client = new Client(
            [
                'base_uri' => 'https://api.github.com',
                'headers' => [
                    'User-Agent' => 'GitHub App ID ' . config('github.app_id'),
                    'Authorization' => 'Bearer ' . $jwt,
                    'Accept' => 'application/vnd.github.machine-man-preview+json',
                ],
                'allow_redirects' => false,
            ]
        );

        $response = $client->post('/app/installations/' . config('github.installation_id') . '/access_tokens');

        if (201 !== $response->getStatusCode()) {
            throw new Exception(
                'GitHub returned an unexpected HTTP response code ' . $response->getStatusCode() . ', expected 201'
            );
        }

        $json = json_decode($response->getBody()->getContents());

        if (!is_object($json)) {
            throw new Exception('GitHub did not return an object');
        }

        return $json->token;
    }

    /**
     * Generate a new JWT to authenticate to the API as the GitHub App
     *
     * @return string
     */
    private static function generateJWT(): string
    {
        $set = new KeySet();
        $pemfile = file_get_contents(config('github.private_key'));

        if (false === $pemfile) {
            throw new Exception('Could not read private key');
        }

        $set->add(new RSAKey($pemfile, 'pem'));

        $headers = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = ['iss' => config('github.app_id'), 'exp' => time() + 5];
        $jwt = new JWT($headers, $claims);

        return $jwt->encode($set);
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
        return self::class . ' GT=' . $this->uid . ' GH=' . $this->github_username . ' ';
    }
}
