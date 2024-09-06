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
            'groups/'.config('grouper.folder_base_path').":$group_name/members",
            [
                'json' => [
                    'WsRestAddMemberLiteRequest' => [
                        'subjectId' => $username,
                        'groupName' => config('grouper.folder_base_path').":$group_name",
                    ],
                ],
            ]
        );
        self::expectStatusCodes($response, 200, 201);
    }

    public static function removeUserFromGroup(string $group_name, string $username): void
    {
        $response = self::client()->post(
            'groups/'.config('grouper.folder_base_path').":$group_name/members/sources/gted-accounts/subjectId/$username",
            [
                'json' => [
                    'WsRestDeleteMemberLiteRequest' => [],
                ],
            ]
        );
        self::expectStatusCodes($response, 200);
    }

    public static function getGroupMembershipsForUser(string $username): array
    {
        $response = self::client()->get("subjects/$username/memberships");

        self::expectStatusCodes($response, 200);

        return self::decodeToArray($response);
    }

    /**
     * Returns all groups in the RoboJackets Grouper hierarchy.
     *
     * @return array<object>
     */
    public static function getGroups(): array
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

        return self::decodeToArray($response);
    }

    /**
     * Return a client configured for Grouper.
     *
     * @phan-suppress PhanTypeMismatchReturnNullable
     */
    public static function client(): Client
    {
        self::$client = new Client(
            [
                'base_uri' => 'https://'.config('grouper.server').'/grouper-ws/servicesRest/v4_0_000/',
                'headers' => [
                    'User-Agent' => 'RoboJacketsJEDI/'.config('sentry.release'),
                ],
                'auth' => [
                    'user' => config('grouper.username'),
                    'pass' => config('grouper.password'),
                ],
                'allow_redirects' => false,
                'http_errors' => false,
            ]
        );

        return self::$client;
    }
}
