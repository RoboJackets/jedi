<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DownstreamServiceException;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use SimpleJWT\JWT;

class ClickUp extends Service
{
    private const ALMOST_TWO_WEEKS = (7 * 24 * 60 * 60) - 60;

    /**
     * A Guzzle client configured for ClickUp
     *
     * @var \GuzzleHttp\Client
     */
    private static $client = null;

    public static function resendInvitationToUser(int $id): void
    {
        self::client()->put(
            'invite',
            [
                'json' => [
                    'invitee' => $id,
                ],
            ]
        );
    }

    public static function getUserById(int $id): object
    {
        $response = self::client()->get('profile/' . $id);

        self::expectStatusCodes($response, 200);

        return self::decodeToObject($response);
    }

    public static function removeUser(int $id): void
    {
        $response = self::client()->put(
            '/v1/team/' . config('clickup.workspace_id'),
            [
                'json' => [
                    'rem' => [
                        [
                            'id' => $id,
                        ],
                    ],
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        $team = self::decodeToObject($response);
    }

    public static function addUser(string $email): object
    {
        $response = self::client()->put(
            '/v1/team/' . config('clickup.workspace_id'),
            [
                'json' => [
                    'add' => [
                        [
                            'email' => $email,
                            'role' => 3,
                        ],
                    ],
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        $team = self::decodeToObject($response);

        foreach($team->members as $member) {
            if ($email === $member->user->email) {
                return $member;
            }
        }

        throw new DownstreamServiceException('Couldn\'t find newly added user');
    }

    public static function client(): Client
    {
        if (null !== self::$client && null !== Cache::get('clickup_jwt')) {
            return self::$client;
        }

        $token = Cache::remember(
            'clickup_jwt',
            self::ALMOST_TWO_WEEKS,
            static function (): string {
                return self::fetchJWT();
            }
        );

        list($headers, $claims, $signing_input, $signature) = JWT::deserialise($token);

        if ($claims['exp'] < (time() - 60)) {
            Cache::put('clickup_jwt', self::fetchJWT(), self::ALMOST_TWO_WEEKS);
            $token = Cache::get('clickup_jwt');
        }

        self::$client = new Client(
            [
                'base_uri' => 'https://app.clickup.com/v1/team/' . config('clickup.workspace_id') . '/',
                'headers' => [
                    'User-Agent' => 'RoboJacketsJEDI/' . config('bugsnag.app_version') . ' Make a real user api pls '
                    . '--kristaps@robojackets.org',
                ],
                'allow_redirects' => false,
            ]
        );

        return self::$client;
    }

    private static function fetchJWT(): string
    {
        $response = (new Client(
            [
                'base_uri' => 'https://app.clickup.com/v1/',
                'headers' => [
                    'User-Agent' => 'RoboJacketsJEDI/' . config('bugsnag.app_version') . ' Make a real user api pls '
                    . '--kristaps@robojackets.org',
                ],
                'allow_redirects' => false,
                'auth' => [
                    config('clickup.email'),
                    config('clickup.password'),
                    'basic',
                ],
            ]
        ))->post(
            'login',
            [
                'json' => [
                    'email' => config('clickup.email'),
                    'password' => config('clickup.password'),
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        $json = self::decodeToObject($response);

        return $json->token;
    }
}
