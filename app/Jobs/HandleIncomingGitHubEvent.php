<?php declare(strict_types = 1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed

namespace App\Jobs;

use Exception;
use Illuminate\Support\Facades\Log;
use Spatie\WebhookClient\ProcessWebhookJob;
use App\Services\Apiary;

class HandleIncomingGitHubEvent extends ProcessWebhookJob
{
    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'apiary';

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        # Parse webhook body
        $action = $this->webhookCall->payload['action'];

        if ('member_added' === $action || 'member_removed' === $action) {
            $github_invite_pending = false;
            $github_username = $this->webhookCall->payload['membership']['user']['login'];
        } elseif ('member_invited' === $action) {
            $github_invite_pending = true;
            $github_username = $this->webhookCall->payload['invitation']['login'];
        } else {
            throw new Exception('Unexpected action ' . $action);
        }

        Log::info(self::class . ' Received ' . $action . ' event regarding GitHub user ' . $github_username);

        # Get GT username from Apiary
        $response = Apiary::client()->get(
            '/api/v1/users/search',
            [
                'query' => [
                    'keyword' => $github_username,
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

        foreach ($json->users as $user) {
            if ($user->github_username === $github_username) {
                UpdateGitHubInvitePendingFlag::dispatch($user->uid, $github_invite_pending);
                return;
            }
        }

        throw new Exception('Could not find a corresponding GT user for GitHub user ' . $github_username);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        $action = $this->webhookCall->payload['action'];

        if ('member_added' === $action || 'member_removed' === $action) {
            $github_username = $this->webhookCall->payload['membership']['user']['login'];
        } elseif ('member_invited' === $action) {
            $github_username = $this->webhookCall->payload['invitation']['login'];
        } else {
            throw new Exception('Unexpected action ' . $action);
        }

        return [
            'action:' . $action,
            'github_user:' . $github_username,
        ];
    }
}
