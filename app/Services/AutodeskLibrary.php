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

    public static function addUser(string $email): void
    {
        $response = self::client()->post(
            'hubs/' . config('autodesk-library.hub_id') . '/invite',
            [
                'json' => [
                    'inviteEmail' => $email,
                ],
            ]
        );

        self::expectStatusCodes($response, 201);
    }


    // Needs to make a get request to the invite url
    //
    public static function removeUser(string $email): void
    {
        $response = self::client()->post(
            'hubs/' . config('autodesk-library.hub_id'),
            [
                'json' => [
                    'rem' => [
                        [
                            'id' => $clickup_id,
                        ],
                    ],
                ],
            ]
        );

        self::expectStatusCodes($response, 200);
    }

    public static function client(): Client
    {
        if (null !== self::$client) {
            return self::$client;
        }

        $jar = new \GuzzleHttp\Cookie\CookieJar;

        $autodesk_client = new Client(
            [
                'base_uri' => 'https://accounts.autodesk.com' ,
                'headers' => [
                    'User-Agent' => 'JEDI on ' . config('app.url'),
                    'Accept' => 'application/json',
                ],
                'allow_redirects' => [
                    'max'             => 10,        // allow at most 10 redirects.
                    'referer'         => true,      // add a Referer header
                    'protocols'       => ['https'], // only allow https URLs
                    'track_redirects' => true
                ],
                'cookies' => $jar,
            ]
        );
        $response = $autodesk_client->post(
            '/Authentication/LogOn',
            [
                'json' => [
                    'UserName' => config('autodesk-library.email'),
                    'Password' => config('autodesk-library.password'),
                    'RememberMe' => 'true',
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        self::$client = new Client(
            [
                'base_uri' => 'https://contapi.circuits.io/123D-Circuits/' ,
                'headers' => [
                    'User-Agent' => 'JEDI on ' . config('app.url'),
                    'Accept' => 'application/json',
                ],
                'allow_redirects' => [
                    'max'             => 10,        // allow at most 10 redirects.
                    'referer'         => true,      // add a Referer header
                    'protocols'       => ['https'], // only allow https URLs
                    'track_redirects' => true
                ],
                'cookies' => $jar,
            ]
        );

        $response = self::$client->get(
            'actions/login?tenant=circuits&redirect=https://library.io/id-username/libraries',
        );

        self::expectStatusCodes($response, 200);

        return self::$client;
    }
}
