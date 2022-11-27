<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DownstreamServiceProblem;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Keycloak extends Service
{
    /**
     * A Guzzle client configured for Keycloak.
     */
    private static ?Client $client = null;

    /**
     * Return a client configured for GitHub.
     *
     * @phan-suppress PhanTypeMismatchReturnNullable
     */
    public static function client(): Client
    {
        if (self::$client !== null && Cache::get('keycloak_access_token') !== null) {
            return self::$client;
        }

        $token = Cache::remember(
            'keycloak_access_token',
            59, // seconds
            static function (): string {
                Log::debug('Generating new Keycloak access token');

                return self::getAccessToken();
            }
        );

        self::$client = new Client(
            [
                'base_uri' => config('keycloak.server').'/admin/realms/'.config('keycloak.user_realm'),
                'headers' => [
                    'User-Agent' => 'RoboJacketsJEDI/'.config('sentry.release'),
                    'Authorization' => 'Bearer '.$token,
                ],
                'allow_redirects' => false,
            ]
        );

        return self::$client;
    }

    private static function getAccessToken(): string
    {
        $client = new Client(
            [
                'base_uri' => config('keycloak.server'),
                'headers' => [
                    'User-Agent' => 'RoboJacketsJEDI/'.config('sentry.release'),
                ],
                'allow_redirects' => false,
            ]
        );

        $response = $client->post(
            '/realms/'.config('keycloak.client.realm').'/protocol/openid-connect/token',
            [
                'form_params' => [
                    'client_id' => config('keycloak.client.id'),
                    'client_secret' => config('keycloak.client.secret'),
                    'grant_type' => 'client_credentials',
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        return self::decodeToObject($response)->access_token;
    }

    public static function searchForUsername(string $username): ?object
    {
        $response = self::client()->get('users', [
            'query' => [
                'username' => $username,
                'exact' => true,
            ],
        ]);

        self::expectStatusCodes($response, 200);

        $user_list = self::decodeToArray($response);

        if (count($user_list) === 0) {
            return null;
        } elseif (count($user_list) === 1) {
            return $user_list[0];
        } else {
            throw new DownstreamServiceProblem(
                'Keycloak returned a list of '.count($user_list).' users for username search, expected <2'
            );
        }
    }

    public static function getUser(string $user_id): object
    {
        $response = self::client()->get('users/'.$user_id);

        self::expectStatusCodes($response, 200);

        return self::decodeToObject($response);
    }

    public static function setUserEnabled(string $user_id, bool $enabled): void
    {
        $response = self::client()->put(
            'users/'.$user_id,
            [
                'json' => [
                    'enabled' => $enabled,
                ],
            ]
        );

        self::expectStatusCodes($response, 204);
    }

    public static function getGroups(): array
    {
        $response = self::client()->get('groups');

        self::expectStatusCodes($response, 200);

        return self::decodeToArray($response);
    }

    public static function getGroupsForUser(string $user_id): array
    {
        $response = self::client()->get('users/'.$user_id.'/groups');

        self::expectStatusCodes($response, 200);

        return self::decodeToArray($response);
    }

    public static function addUserToGroup(string $user_id, string $group_id): void
    {
        $response = self::client()->put('users/'.$user_id.'/groups/'.$group_id);

        self::expectStatusCodes($response, 204);
    }

    public static function removeUserFromGroup(string $user_id, string $group_id): void
    {
        $response = self::client()->delete('users/'.$user_id.'/groups/'.$group_id);

        self::expectStatusCodes($response, 204);
    }
}
