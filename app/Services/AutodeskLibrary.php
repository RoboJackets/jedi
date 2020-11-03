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

        $jar = new \GuzzleHttp\Cookie\CookieJar;

        $autodesk_client = new Client(
            [
                'base_uri' => 'https://accounts.autodesk.com/' ,
                'headers' => [
                    'User-Agent' => 'JEDI on ' . config('app.url'),
                    'Accept' => 'application/json',
                ],
                'allow_redirects' => true,
                'cookies' => $jar,
            ]
        );

        $autodesk_client->post(
            'Authentication/LogOn',
            [
                'json' => [
                    'UserName' => config('autodesk.email'),
                    'Password' => config('autodesk.password'),
                    'RememberMe' => 'true',
                ],
            ]
        );

        # Above should expect 200

        self::$client = new Client(
            [
                'base_uri' => 'https://contapi.circuits.io/123D-Circuits/' ,
                'headers' => [
                    'User-Agent' => 'JEDI on ' . config('app.url'),
                    'Accept' => 'application/json',
                ],
                'allow_redirects' => true,
                'cookies' => $jar,
            ]
        );

        self::$client->get(
            'actions/login?tenant=circuits&redirect=https://library.io/id-username/libraries',
        );


        return self::$client;
    }
}
