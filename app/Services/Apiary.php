<?php declare(strict_types = 1);

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use SimpleJWT\JWT;
use SimpleJWT\Keys\KeySet;
use SimpleJWT\Keys\RSAKey;
use Psr\Http\Message\ResponseInterface;

class Apiary extends Service
{
    private $client = null;

    public static function getUser(string $username): object
    {
        $response = self::client()->get(
            '/api/v1/users/' . $username,
            [
                'query' => [
                    'include' => 'teams,attendance',
                ]
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
