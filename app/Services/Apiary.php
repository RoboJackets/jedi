<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DownstreamServiceProblem;
use GuzzleHttp\Client;

class Apiary extends Service
{
    /**
     * A Guzzle client configured for Apiary.
     */
    private static ?Client $client = null;

    /**
     * Unix timestamp when the current token expires.
     */
    private static ?int $tokenExpiresAt = null;

    public static function getUser(string $username): object
    {
        $response = self::client()->get(
            '/api/v1/users/'.$username,
            [
                'query' => [
                    'include' => 'teams,attendance',
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        return self::decodeToObject($response)->user;
    }

    public static function setFlag(string $username, string $flag, bool $value): void
    {
        $response = self::client()->put(
            '/api/v1/users/'.$username,
            [
                'json' => [
                    $flag => $value,
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        if (self::decodeToObject($response)->status !== 'success') {
            throw new DownstreamServiceProblem(
                'Apiary returned an unexpected response '.$response->getBody()->getContents()
                .', expected status: success'
            );
        }
    }

    /**
     * Sets some attributes on a user.
     *
     * @param  string  $username  The user's GT username
     * @param  array<string,int|bool>  $attributes  The attributes to update
     */
    public static function setAttributes(string $username, array $attributes): void
    {
        $response = self::client()->put(
            '/api/v1/users/'.$username,
            [
                'json' => $attributes,
            ]
        );

        self::expectStatusCodes($response, 200);

        if (self::decodeToObject($response)->status !== 'success') {
            throw new DownstreamServiceProblem(
                'Apiary returned an unexpected response '.$response->getBody()->getContents()
                .', expected status: success'
            );
        }
    }

    /**
     * Return a client configured for Apiary.
     *
     * @phan-suppress PhanTypeMismatchReturnNullable
     */
    #[\Override]
    public static function client(): Client
    {
        if (self::$client !== null && self::$tokenExpiresAt !== null && time() < self::$tokenExpiresAt) {
            return self::$client;
        }

        $tokenResponse = self::getAccessToken();

        self::$tokenExpiresAt = time() + $tokenResponse->expires_in - 60;

        self::$client = new Client(
            [
                'base_uri' => config('apiary.server'),
                'headers' => [
                    'User-Agent' => 'JEDI on '.config('app.url'),
                    'Authorization' => 'Bearer '.$tokenResponse->access_token,
                    'Accept' => 'application/json',
                ],
                'allow_redirects' => false,
            ]
        );

        return self::$client;
    }

    private static function getAccessToken(): object
    {
        $client = new Client(
            [
                'base_uri' => config('apiary.server'),
                'headers' => [
                    'User-Agent' => 'JEDI on '.config('app.url'),
                ],
                'allow_redirects' => false,
            ]
        );

        $response = $client->post(
            '/oauth/token',
            [
                'form_params' => [
                    'client_id' => config('apiary.client_id'),
                    'client_secret' => config('apiary.client_secret'),
                    'grant_type' => 'client_credentials',
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        return self::decodeToObject($response);
    }
}
