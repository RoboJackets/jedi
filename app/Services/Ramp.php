<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Ramp extends Service
{
    /**
     * A Guzzle client configured for Keycloak.
     */
    private static ?Client $client = null;

    public static function getUser(string $user_id): object
    {
        $response = self::client()->get('/developer/v1/users/'.$user_id);

        self::expectStatusCodes($response, 200);

        return self::decodeToObject($response);
    }

    public static function getUserByEmail(string $email): ?object
    {
        $response = self::client()->get(
            '/developer/v1/users/',
            [
                'query' => [
                    'email' => $email,
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        $response = self::decodeToObject($response);

        if (property_exists($response, 'data') && count($response->data) > 0) {
            return $response->data[0];
        }

        return null;
    }

    public static function deactivateUser(string $user_id): void
    {
        $response = self::client()->patch('/developer/v1/users/'.$user_id.'/deactivate');

        self::expectStatusCodes($response, 200);
    }

    #[\Override]
    public static function client(): Client
    {
        if (self::$client !== null && Cache::get('ramp_access_token') !== null) {
            return self::$client;
        }

        $token = Cache::remember(
            'ramp_access_token',
            59, // seconds
            static function (): string {
                Log::debug('Generating new Ramp access token');

                return self::getAccessToken();
            }
        );

        self::$client = new Client(
            [
                'base_uri' => config('ramp.server'),
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
                'base_uri' => config('ramp.server'),
                'headers' => [
                    'User-Agent' => 'RoboJacketsJEDI/'.config('sentry.release'),
                ],
                'allow_redirects' => false,
            ]
        );

        $response = $client->post(
            '/developer/v1/token',
            [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'scope' => 'users:read users:write',
                ],
                'auth' => [
                    config('ramp.client.id'),
                    config('ramp.client.secret'),
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        return self::decodeToObject($response)->access_token;
    }
}
