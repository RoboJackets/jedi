<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;

class AutodeskLibrary extends Service
{
    /**
     * A Guzzle client configured for Autodesk Library
     *
     * @var \GuzzleHttp\Client
     */
    private static $client;

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
