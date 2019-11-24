<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Apiary;
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
    protected function __construct(
        string $uid,
        bool $github_invite_pending
    ) {
        parent::__construct($uid);
        $this->github_invite_pending = $github_invite_pending;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        Apiary::setFlag($this->uid, 'github_invite_pending', $this->github_invite_pending);

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
            'github_invite_pending:' . ($this->github_invite_pending ? 'true' : 'false'),
        ];
    }
}
