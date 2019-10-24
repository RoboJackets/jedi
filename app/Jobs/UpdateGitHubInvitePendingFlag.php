<?php declare(strict_types = 1);

namespace App\Jobs;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class UpdateGitHubInvitePendingFlag extends AbstractApiaryJob
{
    /**
     * Whether this user has a pending GitHub invitation
     *
     * @var bool
     */
    private $github_invite_pending = false;

    /**
     * Create a new job instance
     *
     * @param string $uid
     * @param bool $github_invite_pending
     */
    public function __construct(
        string $uid,
        bool $github_invite_pending,
    ) {
        $this->uid = $uid;
        $this->github_invite_pending = $github_invite_pending;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $client = self::client();

        $response = $client->post(
            '/api/v1/users/' . $this->uid,
            [
                'json' => [
                    'github_invite_pending' => $this->github_invite_pending,
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new Exception(
                'Apiary returned an unexpected HTTP response code ' . $response->getStatusCode() . ', expected 200'
            );
        }

        $responseBody = $response->getBody()->getContents();

        $json = json_decode($responseBody);

        if ('success' !== $json->status) {
            throw new Exception(
                'Apiary returned an unexpected response ' . $responseBody . ', expected status: success'
            );
        }

        Log::info(self::class . ': Successfully updated github_invite_pending flag for ' . $this->uid);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'user:' . $this->uid,
            'invite_pending:' . ($this->invite_pending ? 'true' : 'false'),
        ];
    }
}
