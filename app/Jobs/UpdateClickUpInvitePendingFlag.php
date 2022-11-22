<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Apiary;
use Illuminate\Support\Facades\Log;

class UpdateClickUpInvitePendingFlag extends ApiaryJob
{
    /**
     * Create a new job instance.
     */
    protected function __construct(string $uid, private readonly bool $clickup_invite_pending)
    {
        parent::__construct($uid);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Apiary::setFlag($this->uid, 'clickup_invite_pending', $this->clickup_invite_pending);

        Log::info(self::class.': Successfully updated clickup_invite_pending flag for '.$this->uid);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'user:'.$this->uid,
            'clickup_invite_pending:'.($this->clickup_invite_pending ? 'true' : 'false'),
        ];
    }
}
