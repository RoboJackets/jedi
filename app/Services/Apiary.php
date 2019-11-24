<?php declare(strict_types = 1);

namespace App\Services;

use GuzzleHttp\Client;

class Apiary extends Service
{
    /**
     * A Guzzle client configured for Apiary
     *
     * @var \GuzzleHttp\Client
     */
    private $client = null;

    public static function getUser(string $username): object
    {
        $response = self::client()->get(
            '/api/v1/users/' . $username,
            [
                'query' => [
                    'include' => 'teams,attendance',
                ],
            ]
        );

        self::expectResponseCodes($response, 200);

        return self::decodeToObject($response);
    }

    public static function setFlag(string $username, string $flag, bool $value): void
    {
        $response = self::client()->put(
            '/api/v1/users/' . $username,
            [
                'json' => [
                    $flag => $value,
                ],
            ]
        );

        self::expectResponseCodes($response, 200);

        if ('success' !== self::decodeToObject($response)->status) {
            throw new DownstreamServiceException(
                'Apiary returned an unexpected response ' . $response->getBody()->getContents()
                . ', expected status: success'
            );
        }
    }

    public static function client(): Client
    {
        if (null !== self::$client) {
            return self::$client;
        }

        self::$client = new Client(
            [
                'base_uri' => config('apiary.server'),
                'headers' => [
                    'User-Agent' => 'JEDI on ' . config('app.url'),
                    'Authorization' => 'Bearer ' . config('apiary.token'),
                    'Accept' => 'application/json',
                ],
                'allow_redirects' => false,
            ]
        );

        return self::$client;
    }
}
