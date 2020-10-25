<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Functions.RequireSingleLineCall.RequiredSingleLineCall

namespace App\Jobs;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class SyncWordPress extends SyncJob
{
    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'wordpress';

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $client = new Client(
            [
                'base_uri' => config('wordpress.server') . '/wp-json/wp/v2/',
                'headers' => [
                    'User-Agent' => 'JEDI on ' . config('app.url'),
                ],
                'auth' => [
                    config('wordpress.username'),
                    config('wordpress.password'),
                ],
                'allow_redirects' => false,
            ]
        );

        $response = $client->get(
            'users',
            [
                'query' => [
                    'slug' => $this->uid,
                    'context' => 'edit',
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new Exception(
                'WordPress returned an unexpected HTTP response code ' . $response->getStatusCode() . ', expected 200'
            );
        }

        $json = json_decode($response->getBody()->getContents());

        if (!is_array($json)) {
            throw new Exception(
                'WordPress did not return an array for query by slug - should not happen'
            );
        }

        if (0 === count($json)) {
            Log::info(self::class . ': User ' . $this->uid . ' does not exist in WordPress');

            return;
        }

        if (1 !== count($json)) {
            throw new Exception(
                'WordPress returned more than one user for query by slug - should not happen'
            );
        }

        $wp_user = $json[0];

        if ($wp_user->username !== $this->uid) {
            throw new Exception(
                'WordPress returned a user with mismatched username - searched for '
                . $this->uid . ', got ' . $wp_user->username
            );
        }

        if ($this->is_access_active && in_array(config('wordpress.team'), $this->teams, true)) {
            Log::info(self::class . ': Enabling user ' . $this->uid);

            if (in_array('administrator', $wp_user->roles, true)) {
                Log::debug(self::class . ': User ' . $this->uid . ' is admin');
                if (
                    $wp_user->first_name === $this->first_name
                    && $wp_user->last_name === $this->last_name
                    && $wp_user->name === $this->first_name . ' ' . $this->last_name
                    && $wp_user->email === $this->uid . '@gatech.edu'
                ) {
                    Log::debug(self::class . ': User ' . $this->uid . ' attributes are up to date');
                    return;
                }

                Log::debug(self::class . ': Updating name/email for user ' . $this->uid);

                $client->post(
                    'users/' . $wp_user->id,
                    [
                        'query' => [
                            'first_name' => $this->first_name,
                            'last_name' => $this->last_name,
                            'name' => $this->first_name . ' ' . $this->last_name,
                            'email' => $this->uid . '@gatech.edu',
                        ],
                    ]
                );

                if (200 !== $response->getStatusCode()) {
                    throw new Exception(
                        'WordPress returned an unexpected HTTP response code ' . $response->getStatusCode()
                        . ', expected 200'
                    );
                }
            } else {
                if (
                    $wp_user->first_name === $this->first_name
                    && $wp_user->last_name === $this->last_name
                    && $wp_user->name === $this->first_name . ' ' . $this->last_name
                    && $wp_user->email === $this->uid . '@gatech.edu'
                    && ['editor'] === $wp_user->roles
                ) {
                    Log::debug(self::class . ': User ' . $this->uid . ' attributes are up to date');
                    return;
                }

                $client->post(
                    'users/' . $wp_user->id,
                    [
                        'query' => [
                            'first_name' => $this->first_name,
                            'last_name' => $this->last_name,
                            'name' => $this->first_name . ' ' . $this->last_name,
                            'email' => $this->uid . '@gatech.edu',
                            'roles' => 'editor',
                        ],
                    ]
                );

                if (200 !== $response->getStatusCode()) {
                    throw new Exception(
                        'WordPress returned an unexpected HTTP response code ' . $response->getStatusCode()
                        . ', expected 200'
                    );
                }
            }

            Log::debug(self::class . ': Successfully updated ' . $this->uid);
        } else {
            if ([] === $wp_user->roles) {
                Log::info(self::class . ': User ' . $this->uid . ' already disabled, don\'t need to change anything');
                return;
            }

            Log::info(self::class . ': Disabling user ' . $this->uid);

            $client->post(
                'users/' . $wp_user->id,
                [
                    'query' => [
                        'roles' => '',
                    ],
                ]
            );

            if (200 !== $response->getStatusCode()) {
                throw new Exception(
                    'WordPress returned an unexpected HTTP response code ' . $response->getStatusCode()
                    . ', expected 200'
                );
            }

            Log::info(self::class . ': Successfully disabled ' . $this->uid);
        }
    }
}
