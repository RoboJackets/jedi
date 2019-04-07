<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class ProcessWordPress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $uid;
    private $is_access_active;
    private $teams;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        string $uid,
        bool $is_access_active,
        array $teams,
        string $first_name,
        string $last_name
    ) {
        $this->uid = $uid;
        $this->is_access_active = $is_access_active;
        $this->teams = $teams;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client = new Client(
            [
                'base_uri' => config('wordpress.server').'/wp-json/wp/v2/',
                'headers' => [
                    'User-Agent' => 'JEDI on '.config('app.url'),
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
                'query' => 'slug='.$this->uid,
                'context' => 'edit',
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \Exception(
                'WordPress returned an unexpected HTTP response code '.$response->getStatusCode().', expected 200'
            );
        }

        $json = json_decode($response->getBody()->getContents());

        if (!is_array($json)) {
            throw new \Exception(
                'WordPress did not return an array for query by slug - should not happen'
            );
        }

        if (count($json) === 0) {
            // user does not exist in wordpress
            return;
        }

        if (count($json) !== 1) {
            throw new \Exception(
                'WordPress returned more than one user for query by slug - should not happen'
            );
        }

        if ($this->is_access_active && in_array(config('wordpress.team'), $this->teams)) {
            if (in_array('administrator', $json[0]->roles)) {
                // user is an admin, don't revoke that
                $client->post(
                    'users/'.$json[0]->id,
                    [
                        'query' => 'first_name='.$this->first_name
                                    .'&last_name='.$this->last_name
                                    .'&name='.$this->first_name.' '.$this->last_name
                                    .'&email='.$this->uid.'@gatech.edu'
                    ]
                );

                if (200 !== $response->getStatusCode()) {
                    throw new \Exception(
                        'WordPress returned an unexpected HTTP response code '.$response->getStatusCode()
                        .', expected 200'
                    );
                }
            } else {
                $client->post(
                    'users/'.$json[0]->id,
                    [
                        'query' => 'first_name='.$this->first_name
                                    .'&last_name='.$this->last_name
                                    .'&name='.$this->first_name.' '.$this->last_name
                                    .'&email='.$this->uid.'@gatech.edu'
                                    .'&roles=editor'
                    ]
                );

                if (200 !== $response->getStatusCode()) {
                    throw new \Exception(
                        'WordPress returned an unexpected HTTP response code '.$response->getStatusCode()
                        .', expected 200'
                    );
                }
            }
        } else {
            $client->post(
                'users/'.$json[0]->id,
                [
                    'query' => 'roles='
                ]
            );

            if (200 !== $response->getStatusCode()) {
                throw new \Exception(
                    'WordPress returned an unexpected HTTP response code '.$response->getStatusCode()
                    .', expected 200'
                );
            }
        }
    }
}
