<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingTraversableReturnTypeHintSpecification
// phpcs:disable Generic.NamingConventions.CamelCapsFunctionName.ScopeNotCamelCaps

namespace App\Services;

use App\Exceptions\DownstreamServiceProblem;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SimpleJWT\JWT;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\Keys\RSAKey;

class GitHub extends Service
{
    private const FIFTY_NINE_MINUTES = 59 * 60;

    /**
     * A Guzzle client configured for GitHub.
     */
    private static ?Client $client = null;

    public static function removeUserFromOrganization(string $username): void
    {
        $response = self::client()->delete(
            '/orgs/'.config('github.organization').'/memberships/'.$username
        );

        if ($response->getStatusCode() === 401) {
            Cache::forget('github_installation_token');

            throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
        }

        self::expectStatusCodes($response, 204);
    }

    public static function addUserToTeam(int $team_id, string $username): void
    {
        $response = self::client()->put(
            '/organizations/'.config('github.organization_id').'/team/'.$team_id.'/memberships/'.$username
        );

        if ($response->getStatusCode() === 401) {
            Cache::forget('github_installation_token');

            throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
        }

        self::expectStatusCodes($response, 200);
    }

    public static function promoteUserToTeamMaintainer(int $team_id, string $username): void
    {
        $response = self::client()->put(
            '/organizations/'.config('github.organization_id').'/team/'.$team_id.'/memberships/'.$username,
            [
                'json' => [
                    'role' => 'maintainer',
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.config('github.admin_token'),
                ],
            ]
        );

        if ($response->getStatusCode() === 401) {
            Cache::forget('github_installation_token');

            throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
        }

        self::expectStatusCodes($response, 200);
    }

    public static function getTeamMembership(int $team_id, string $username): ?object
    {
        $cache_key = 'github_team_'.$team_id.'_member_'.$username;
        $etag_key = 'github_team_'.$team_id.'_member_'.$username.'_etag';

        $membership = Cache::get($cache_key);
        $etag = Cache::get($etag_key);

        if ($membership === null) {
            $response = self::client()->get(
                '/organizations/'.config('github.organization_id').'/team/'.$team_id.'/memberships/'.$username
            );

            if ($response->getStatusCode() === 401) {
                Cache::forget('github_installation_token');

                throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
            }

            self::expectStatusCodes($response, 200, 404);
            $membership = self::decodeToObject($response);

            if ($response->getStatusCode() === 200) {
                $etag = $response->getHeader('ETag')[0];

                Cache::forever($cache_key, $membership);
                Cache::forever($etag_key, $etag);

                return $membership;
            }

            return null;
        }

        $response = self::client()->get(
            '/organizations/'.config('github.organization_id').'/team/'.$team_id.'/memberships/'.$username,
            [
                'headers' => [
                    'If-None-Match' => $etag,
                ],
            ]
        );

        if ($response->getStatusCode() === 401) {
            Cache::forget('github_installation_token');

            throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
        }

        self::expectStatusCodes($response, 200, 304, 404);

        if ($response->getStatusCode() === 200) {
            $membership = self::decodeToObject($response);
            $etag = $response->getHeader('ETag')[0];

            Cache::forever($cache_key, $membership);
            Cache::forever($etag_key, $etag);

            return $membership;
        }
        if ($response->getStatusCode() === 304) {
            return $membership;
        }
        if ($response->getStatusCode() === 404) {
            Cache::forget($cache_key);
            Cache::forget($etag_key);

            return null;
        }

        throw new Exception('Unreachable statement');
    }

    /**
     * Invite a user to the organization.
     *
     * @param  int  $invitee_id  the GitHub user's numeric ID
     * @param  array<int>  $team_ids  The teams to add the user to
     */
    public static function inviteUserToOrganization(int $invitee_id, array $team_ids): void
    {
        $response = self::client()->post(
            '/orgs/'.config('github.organization').'/invitations',
            [
                'json' => [
                    'invitee_id' => $invitee_id,
                    'team_ids' => $team_ids,
                ],
                'headers' => [
                    'Accept' => 'application/vnd.github.dazzler-preview+json',
                    'Authorization' => 'Bearer '.config('github.admin_token'),
                ],
            ]
        );

        if ($response->getStatusCode() === 401) {
            Cache::forget('github_installation_token');

            throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
        }

        self::expectStatusCodes($response, 201);
    }

    /**
     * Returns all teams in the organization.
     *
     * @return array<object>
     */
    public static function getTeams(): array
    {
        $cache_key = 'github_teams';
        $etag_key = $cache_key.'_etag';

        $teams = Cache::get($cache_key);
        $etag = Cache::get($etag_key);

        if ($teams === null) {
            $response = self::client()->get('/orgs/'.config('github.organization').'/teams');

            if ($response->getStatusCode() === 401) {
                Cache::forget('github_installation_token');

                throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
            }

            self::expectStatusCodes($response, 200);
            $teams = self::decodeToArray($response);

            $etag = $response->getHeader('ETag')[0];

            Cache::forever($cache_key, $teams);
            Cache::forever($etag_key, $etag);
        } else {
            $response = self::client()->get(
                '/orgs/'.config('github.organization').'/teams',
                [
                    'headers' => [
                        'If-None-Match' => $etag,
                    ],
                ]
            );

            if ($response->getStatusCode() === 401) {
                Cache::forget('github_installation_token');

                throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
            }

            self::expectStatusCodes($response, 200, 304);

            if ($response->getStatusCode() === 200) {
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
        $cache_key = 'github_user_'.$username;
        $etag_key = $cache_key.'_etag';

        $user = Cache::get($cache_key);
        $etag = Cache::get($etag_key);

        if ($user === null) {
            $response = self::client()->get('/users/'.$username);

            if ($response->getStatusCode() === 401) {
                Cache::forget('github_installation_token');

                throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
            }

            self::expectStatusCodes($response, 200, 404);

            if ($response->getStatusCode() === 404) {
                throw new DownstreamServiceProblem(
                    'Linked GitHub user '
                    .$username
                    .' does not exist, it may have been renamed. Admin intervention required! '
                    .$response->getBody()->getContents()
                );
            }

            $user = self::decodeToObject($response);

            $etag = $response->getHeader('ETag')[0];

            Cache::forever($cache_key, $user);
            Cache::forever($etag_key, $etag);
        } else {
            $response = self::client()->get(
                '/users/'.$username,
                [
                    'headers' => [
                        'If-None-Match' => $etag,
                    ],
                ]
            );

            if ($response->getStatusCode() === 401) {
                Cache::forget('github_installation_token');

                throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
            }

            self::expectStatusCodes($response, 200, 304);

            if ($response->getStatusCode() === 200) {
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
        $cache_key = 'github_organization_member_'.$username;
        $etag_key = $cache_key.'_etag';

        $membership = Cache::get($cache_key);
        $etag = Cache::get($etag_key);

        if ($etag === null) {
            $response = self::client()->get(
                '/orgs/'.config('github.organization').'/memberships/'.$username
            );

            if ($response->getStatusCode() === 401) {
                Cache::forget('github_installation_token');

                throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
            }

            self::expectStatusCodes($response, 200, 404);

            $membership = self::decodeToObject($response);

            if ($response->getStatusCode() === 200) {
                $etag = $response->getHeader('ETag')[0];

                Cache::forever($cache_key, $membership);
                Cache::forever($etag_key, $etag);

                return $membership;
            }

            return null;
        }

        $response = self::client()->get(
            '/orgs/'.config('github.organization').'/memberships/'.$username,
            [
                'headers' => [
                    'If-None-Match' => $etag,
                ],
            ]
        );

        if ($response->getStatusCode() === 401) {
            Cache::forget('github_installation_token');

            throw new DownstreamServiceProblem('GitHub returned 401, flushing token from cache');
        }

        self::expectStatusCodes($response, 200, 304, 404);

        if ($response->getStatusCode() === 200) {
            $membership = self::decodeToObject($response);

            $etag = $response->getHeader('ETag')[0];

            Cache::forever($cache_key, $membership);
            Cache::forever($etag_key, $etag);

            return $membership;
        }
        if ($response->getStatusCode() === 304) {
            return $membership;
        }
        if ($response->getStatusCode() === 404) {
            Cache::forget($cache_key);
            Cache::forget($etag_key);

            return null;
        }

        throw new Exception('Unreachable statement');
    }

    public static function getRateLimitRemaining(): int
    {
        $response = self::client()->get('/rate_limit');

        self::expectStatusCodes($response, 200);

        return intval($response->getHeader('X-RateLimit-Remaining')[0]);
    }

    /**
     * Return a client configured for GitHub.
     *
     * @phan-suppress PhanTypeMismatchReturnNullable
     */
    public static function client(): Client
    {
        if (self::$client !== null && Cache::get('github_installation_token') !== null) {
            return self::$client;
        }

        $token = Cache::remember(
            'github_installation_token',
            self::FIFTY_NINE_MINUTES,
            static function (): string {
                Log::debug('Generating new GitHub installation token');

                return self::getInstallationToken();
            }
        );

        self::$client = new Client(
            [
                'base_uri' => 'https://api.github.com',
                'headers' => [
                    'User-Agent' => 'GitHub App ID '.config('github.app_id'),
                    'Authorization' => 'Bearer '.$token,
                ],
                'allow_redirects' => false,
                'http_errors' => false,
            ]
        );

        return self::$client;
    }

    /**
     * Fetches a new installation token to authenticate to the API as the GitHub App.
     */
    private static function getInstallationToken(): string
    {
        $jwt = self::generateJWT();

        $client = new Client(
            [
                'base_uri' => 'https://api.github.com',
                'headers' => [
                    'User-Agent' => 'GitHub App ID '.config('github.app_id'),
                    'Authorization' => 'Bearer '.$jwt,
                    'Accept' => 'application/vnd.github.machine-man-preview+json',
                ],
                'allow_redirects' => false,
            ]
        );

        $response = $client->post('/app/installations/'.config('github.installation_id').'/access_tokens');

        self::expectStatusCodes($response, 201);

        return self::decodeToObject($response)->token;
    }

    /**
     * Generate a new JWT to authenticate to the API as the GitHub App.
     *
     * @phan-suppress PhanPartialTypeMismatchArgument
     */
    private static function generateJWT(): string
    {
        $set = new KeySet();

        $set->add(new RSAKey(config('github.private_key'), 'pem'));

        $headers = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = ['iss' => config('github.app_id'), 'exp' => time() + 5];
        $jwt = new JWT($headers, $claims);

        return $jwt->encode($set);
    }
}
