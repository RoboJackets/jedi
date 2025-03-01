<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingTraversableReturnTypeHintSpecification
// phpcs:disable Generic.NamingConventions.CamelCapsFunctionName.ScopeNotCamelCaps

namespace App\Services;

use GuzzleHttp\Client;

class Grouper extends Service
{
    /**
     * A Guzzle client configured for Grouper.
     */
    private static ?Client $client = null;

    public static function addUserToGroup(string $group_name, string $username): void
    {
        $response = self::client()->post(
            'groups/'.config('grouper.folder_base_path').':'.$group_name.'/members',
            [
                'json' => [
                    'WsRestAddMemberLiteRequest' => [
                        'subjectId' => $username,
                        'groupName' => config('grouper.folder_base_path').':'.$group_name,
                    ],
                ],
            ]
        );
        self::expectStatusCodes($response, 200, 201);
    }

    public static function removeUserFromGroup(string $group_name, string $username): void
    {
        $response = self::client()->post(
            'groups/'.config(
                'grouper.folder_base_path'
            ).':'.$group_name.'/members/sources/gted-accounts/subjectId/'.$username,
            [
                'json' => ['WsRestDeleteMemberLiteRequest' => new \stdClass()],
            ]
        );
        self::expectStatusCodes($response, 200);
    }

    public static function getGroupMembershipsForUser(string $username): object
    {
        $response = self::client()->get('subjects/'.$username.'/memberships');

        self::expectStatusCodes($response, 200);

        return self::decodeToObject($response);
    }

    /**
     * Returns all groups in the RoboJackets Grouper hierarchy.
     */
    public static function getGroups(): object
    {
        $response = self::client()->post(
            'groups',
            [
                'json' => [
                    'WsRestFindGroupsLiteRequest' => [
                        'stemName' => config('grouper.folder_base_path'),
                        'queryFilterType' => 'FIND_BY_STEM_NAME',
                    ],
                ],
            ]
        );

        self::expectStatusCodes($response, 200);

        return self::decodeToObject($response);
    }

    /**
     * Return a client configured for Grouper.
     *
     * The load balancer in front of Grouper isn't sending a complete (or correct) certificate chain
     * as of 2024/09/15, so we have to manually specify the intermediate certificate from InCommon.
     */
    #[\Override]
    public static function client(): Client
    {
        self::$client = new Client(
            [
                'base_uri' => 'https://'.config('grouper.server').'/grouper-ws/servicesRest/v4_0_000/',
                'headers' => [
                    'User-Agent' => 'RoboJacketsJEDI/'.config('sentry.release'),
                ],
                'auth' => [config('grouper.username'), config('grouper.password')],
                'allow_redirects' => false,
                'http_errors' => true,
                'verify' => '/app/storage/certs/InCommon_RSA_Server_CA_2.cer',
            ]
        );

        return self::$client;
    }
}
