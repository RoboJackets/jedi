<?php declare(strict_types = 1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use GuzzleHttp\Client;

class ProcessWordPress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user's username
     *
     * @var string
     */
    private $uid;

    /**
     * Whether the user should have access
     *
     * @var bool
     */
    private $is_access_active;

    /**
     * The user's teams
     *
     * @var array<string>
     */
    private $teams;

    /**
     * The user's first name
     *
     * @var string
     */
    private $first_name;

    /**
     * The user's last name
     *
     * @var string
     */
    private $last_name;

    /**
     * Create a new job instance
     *
     * @param string $uid              The user's username
     * @param bool   $is_access_active Whether the user should have access
     * @param array<string>  $teams    The user's teams
     * @param string $first_name       The user's first name
     * @param string $last_name        The user's last name
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
                'query' => 'slug=' . $this->uid . '&context=edit',
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new \Exception(
                'WordPress returned an unexpected HTTP response code ' . $response->getStatusCode() . ', expected 200'
            );
        }

        $json = json_decode($response->getBody()->getContents());

        if (!is_array($json)) {
            throw new \Exception(
                'WordPress did not return an array for query by slug - should not happen'
            );
        }

        if (0 === count($json)) {
            // user does not exist in wordpress
            return;
        }

        if (1 !== count($json)) {
            throw new \Exception(
                'WordPress returned more than one user for query by slug - should not happen'
            );
        }

        $wp_user = $json[0];

        if ($wp_user->username !== $this->uid) {
            throw new \Exception(
                'WordPress returned a user with mismatched username - searched for '
                . $this->uid . ', got ' . $wp_user->username
            );
        }

        if ($this->is_access_active && in_array(config('wordpress.team'), $this->teams)) {
            if (in_array('administrator', $wp_user->roles)) {
                if ($wp_user->first_name === $this->first_name
                    && $wp_user->last_name === $this->last_name
                    && $wp_user->name === $this->first_name . ' ' . $this->last_name
                    && $wp_user->email === $this->uid . '@gatech.edu'
                ) {
                    return;
                }

                $client->post(
                    'users/' . $wp_user->id,
                    [
                        'query' => 'first_name=' . $this->first_name
                                    . '&last_name=' . $this->last_name
                                    . '&name=' . $this->first_name . ' ' . $this->last_name
                                    . '&email=' . $this->uid . '@gatech.edu',
                    ]
                );

                if (200 !== $response->getStatusCode()) {
                    throw new \Exception(
                        'WordPress returned an unexpected HTTP response code ' . $response->getStatusCode()
                        . ', expected 200'
                    );
                }
            } else {
                if ($wp_user->first_name === $this->first_name
                    && $wp_user->last_name === $this->last_name
                    && $wp_user->name === $this->first_name . ' ' . $this->last_name
                    && $wp_user->email === $this->uid . '@gatech.edu'
                    && ['editor'] === $wp_user->roles
                ) {
                    return;
                }

                $client->post(
                    'users/' . $wp_user->id,
                    [
                        'query' => 'first_name=' . $this->first_name
                                    . '&last_name=' . $this->last_name
                                    . '&name=' . $this->first_name . ' ' . $this->last_name
                                    . '&email=' . $this->uid . '@gatech.edu' . '&roles=editor',
                    ]
                );

                if (200 !== $response->getStatusCode()) {
                    throw new \Exception(
                        'WordPress returned an unexpected HTTP response code ' . $response->getStatusCode()
                        . ', expected 200'
                    );
                }
            }
        } else {
            if ([] === $wp_user->roles) {
                return;
            }

            $client->post(
                'users/' . $wp_user->id,
                [
                    'query' => 'roles=',
                ]
            );

            if (200 !== $response->getStatusCode()) {
                throw new \Exception(
                    'WordPress returned an unexpected HTTP response code ' . $response->getStatusCode()
                    . ', expected 200'
                );
            }
        }
    }
}
