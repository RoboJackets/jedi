<?php

declare(strict_types=1);

namespace App\Services;

use GuzzleHttp\Client;

class SUMS extends Service
{
    public const MEMBER_EXISTS = 'BG member already exists';

    public const MEMBER_NOT_EXISTS = 'BG member does not exist';

    public const SUCCESS = 'Success';

    public const USER_NOT_FOUND = 'User not found';

    /**
     * A Guzzle client configured for SUMS.
     */
    private static ?Client $client;

    public static function removeUser(string $username): string
    {
        $response = self::client()->get(
            'EditPeople',
            [
                'query' => [
                    'UserName' => $username,
                    'BillingGroupId' => config('sums.billinggroupid'),
                    'isRemove' => 'true',
                    'isListMembers' => 'false',
                    'Key' => config('sums.token'),
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        return $response->getBody()->getContents();
    }

    public static function addUserToBillingGroup(string $username): string
    {
        $response = self::client()->get(
            'EditPeople',
            [
                'query' => [
                    'UserName' => $username,
                    'BillingGroupId' => config('sums.billinggroupid'),
                    'isRemove' => 'false',
                    'isListMembers' => 'false',
                    'Key' => config('sums.token'),
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        return $response->getBody()->getContents();
    }

    public static function createUser(string $username): string
    {
        $response = self::client()->post(
            '/SUMSAPI/rest/API/Add_User_By_Username',
            [
                'query' => [
                    'icalID' => config('sums.token'),
                    'CreatorUsername' => config('sums.token_owner'),
                ],
                'json' => [
                    [
                        'Username' => $username,
                    ],
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        return $response->getBody()->getContents();
    }

    public static function client(): Client
    {
        if (self::$client !== null) {
            return self::$client;
        }

        self::$client = new Client(
            [
                'base_uri' => config('sums.server').'/SUMSAPI/rest/BillingGroupEdit/',
                'headers' => [
                    'User-Agent' => 'JEDI on '.config('app.url'),
                ],
                'allow_redirects' => false,
            ]
        );

        return self::$client;
    }
}
