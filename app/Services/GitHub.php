<?php declare(strict_types = 1);

// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingTraversableReturnTypeHintSpecification
// phpcs:disable Generic.NamingConventions.CamelCapsFunctionName.ScopeNotCamelCaps

namespace App\Services;

use App\Exceptions\DownstreamServiceException;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use SimpleJWT\JWT;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\Keys\RSAKey;

class GitHub extends Service
{
    private const FIFTY_NINE_MINUTES = 59 * 60;

    /**
     * A Guzzle client configured for GitHub
     *
     * @var \GuzzleHttp\Client
     */
    private static $client = null;

    public static function removeUserFromOrganization(string $username): void
    {
        $response = self::client()->delete(
            '/orgs/' . config('github.organization') . '/memberships/' . $username
        );
        self::expectStatusCodes($response, 204);
    }

    public static function addUserToTeam(int $team_id, string $username): void
    {
        $response = self::client()->put('/teams/' . $team_id . '/memberships/' . $username);
        self::expectStatusCodes($response, 200);
    }

    public static function getTeamMembership(int $team_id, string $username): ?object
    {
        $cache_key = 'github_team_' . $team_id . '_member_' . $username;
        $etag_key = 'github_team_' . $team_id . '_member_' . $username . '_etag';

        $membership = Cache::get($cache_key);
        $etag = Cache::get($etag_key);

        if (null === $membership) {
            $response = self::client()->get('/teams/' . $team_id . '/memberships/' . $username);

            self::expectStatusCodes($response, 200, 404);
            $membership = self::decodeToObject($response);

            if (200 === $response->getStatusCode()) {
                $etag = $response->getHeader('ETag')[0];

                Cache::forever($cache_key, $membership);
                Cache::forever($etag_key, $etag);
                return $membership;
            }

            return null;
        }

        $response = self::client()->request(
            'GET',
            '/teams/' . $team_id . '/memberships/' . $username,
            [
                'headers' => [
                    'If-None-Match' => $etag,
                ],
            ]
        );

        self::expectStatusCodes($response, 200, 304, 404);

        if (200 === $response->getStatusCode()) {
            $membership = self::decodeToObject($response);
            $etag = $response->getHeader('ETag')[0];

            Cache::forever($cache_key, $membership);
            Cache::forever($etag_key, $etag);
            return $membership;
        }
        if (304 === $response->getStatusCode()) {
            return $membership;
        }
        if (404 === $response->getStatusCode()) {
            Cache::forget($cache_key);
            Cache::forget($etag_key);
            return null;
        }
    }

    /**
     * Invite a user to the organization
     *
     * @param int $invitee_id the GitHub user's numeric ID
     * @param array<int>  $team_ids   The teams to add the user to
     *
     * @return void
     */
    public static function inviteUserToOrganization(int $invitee_id, array $team_ids): void
    {
        $response = self::client()->request(
            'POST',
            '/orgs/' . config('github.organization') . '/invitations',
            [
                'json' => [
                    'invitee_id' => $invitee_id,
                    'team_ids' => $team_ids,
                ],
                'headers' => [
                    'Accept' => 'application/vnd.github.dazzler-preview+json',
                    'Authorization' => 'Bearer ' . config('github.admin_token'),
                ],
            ]
        );

        self::expectStatusCodes($response, 201);
    }

    public static function getTeams(): array
    {
        $cache_key = 'github_teams';
        $etag_key = $cache_key . '_etag';

        $teams = Cache::get($cache_key);
        $etag = Cache::get($etag_key);

        if (null === $teams) {
            $response = self::client()->get('/orgs/' . config('github.organization') . '/teams');

            self::expectStatusCodes($response, 200);
            $teams = self::decodeToArray($response);

            $etag = $response->getHeader('ETag')[0];

            Cache::forever($cache_key, $teams);
            Cache::forever($etag_key, $etag);
        } else {
            $response = self::client()->request(
                'GET',
                '/orgs/' . config('github.organization') . '/teams',
                [
                    'headers' => [
                        'If-None-Match' => $etag,
                    ],
                ]
            );

            self::expectStatusCodes($response, 200, 304);

            if (200 === $response->getStatusCode()) {
                $teams = self::decodeToArray($response);

                $etag = $response->getHeader('ETag')[0];

                Cache::forever($cache_key, $teams);
                Cache::forever($etag_key, $etag);
            }
        }

        return $teams;
    }

    public static function getUser(string $username): object
    {
        $cache_key = 'github_user_' . $username;
        $etag_key = $cache_key . '_etag';

        $user = Cache::get($cache_key);
        $etag = Cache::get($etag_key);

        if (null === $user) {
            $response = self::client()->get('/users/' . $username);

            self::expectStatusCodes($response, 200, 404);

            if (404 === $response->getStatusCode()) {
                throw new DownstreamServiceException(
                    'Linked GitHub user '
                    . $username
                    . ' does not exist, it may have been renamed. Admin intervention required! '
                    . $response->getBody()->getContents()
                );
            }

            $user = self::decodeToObject($response);

            $etag = $response->getHeader('ETag')[0];

            Cache::forever($cache_key, $user);
            Cache::forever($etag_key, $etag);
        } else {
            $response = self::client()->request(
                'GET',
                '/users/' . $username,
                [
                    'headers' => [
                        'If-None-Match' => $etag,
                    ],
                ]
            );

            self::expectStatusCodes($response, 200, 304);

            if (200 === $response->getStatusCode()) {
                $user = self::decodeToObject($response);

                $etag = $response->getHeader('ETag')[0];

                Cache::forever($cache_key, $user);
                Cache::forever($etag_key, $etag);
            }
        }

        return $user;
    }

    public static function getOrganizationMembership(string $username): ?object
    {
        $cache_key = 'github_organization_member_' . $username;
        $etag_key = $cache_key . '_etag';

        $membership = Cache::get($cache_key);
        $etag = Cache::get($etag_key);

        if (null === $etag) {
            $response = self::client()->get(
                '/orgs/' . config('github.organization') . '/memberships/' . $username
            );

            self::expectStatusCodes($response, 200, 404);

            $membership = self::decodeToObject($response);

            if (200 === $response->getStatusCode()) {
                $etag = $response->getHeader('ETag')[0];

                Cache::forever($cache_key, $membership);
                Cache::forever($etag_key, $etag);

                return $membership;
            }

            return null;
        }

        $response = self::client()->request(
            'GET',
            '/users/' . $username,
            [
                'headers' => [
                    'If-None-Match' => $etag,
                ],
            ]
        );

        self::expectStatusCodes($response, 200, 304, 404);

        if (200 === $response->getStatusCode()) {
            $membership = self::decodeToObject($response);

            $etag = $response->getHeader('ETag')[0];

            Cache::forever($cache_key, $membership);
            Cache::forever($etag_key, $etag);

            return $membership;
        }
        if (304 === $response->getStatusCode()) {
            return $membership;
        }
        if (404 === $response->getStatusCode()) {
            Cache::forget($cache_key);
            Cache::forget($etag_key);

            return null;
        }
    }

    public static function getRateLimitRemaining(): int
    {
        $response = self::client()->get('/rate_limit');

        self::expectStatusCodes($response, 200);

        return intval($response->getHeader('X-RateLimit-Remaining')[0]);
    }

    public static function client(): Client
    {
        if (null !== self::$client) {
            return self::$client;
        }

        $token = Cache::get('github_installation_token');

        if (null === $token) {
            $token = self::getInstallationToken();
            Cache::put('github_installation_token', $token, self::FIFTY_NINE_MINUTES);
        }

        self::$client = new Client(
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

        return self::$client;
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

        self::expectStatusCodes($response, 201);
        return self::decodeToObject($response)->token;
    }

    /**
     * Generate a new JWT to authenticate to the API as the GitHub App
     *
     * @return string
     */
    private static function generateJWT(): string
    {
        $set = new KeySet();

        $filename = config('github.private_key');

        if (!is_string($filename)) {
            throw new Exception('Private key path is not string');
        }

        $pem = file_get_contents($filename);

        if (false === $pem) {
            throw new Exception('Could not read private key');
        }

        $set->add(new RSAKey($pem, 'pem'));

        $headers = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = ['iss' => config('github.app_id'), 'exp' => time() + 5];
        $jwt = new JWT($headers, $claims);

        return $jwt->encode($set);
    }
}
