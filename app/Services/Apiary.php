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
    private static ?Client $client;

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

        return self::decodeToObject($response);
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

        if ('success' !== self::decodeToObject($response)->status) {
            throw new DownstreamServiceProblem(
                'Apiary returned an unexpected response '.$response->getBody()->getContents()
                .', expected status: success'
            );
        }
    }

    /**
     * Sets some attributes on a user.
     *
     * @param  string  $username  The user's uid
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

        if ('success' !== self::decodeToObject($response)->status) {
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
    public static function client(): Client
    {
        if (self::$client !== null) {
            return self::$client;
        }

        self::$client = new Client(
            [
                'base_uri' => config('apiary.server'),
                'headers' => [
                    'User-Agent' => 'JEDI on '.config('app.url'),
                    'Authorization' => 'Bearer '.config('apiary.token'),
                    'Accept' => 'application/json',
                ],
                'allow_redirects' => false,
            ]
        );

        return self::$client;
    }
}
