<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Apiary;
use Illuminate\Support\Facades\Log;

class UpdateClickUpAttributes extends AbstractApiaryJob
{
    /**
     * The numeric ID for this user within ClickUp
     *
     * @var int
     */
    private $clickup_id;

    /**
     * Whether this user has a pending ClickUp invitation
     *
     * @var bool
     */
    private $clickup_invite_pending = false;

    /**
     * Create a new job instance
     */
    protected function __construct(
        string $uid,
        int $clickup_id,
        bool $clickup_invite_pending
    ) {
        parent::__construct($uid);
        $this->clickup_id = $clickup_id;
        $this->clickup_invite_pending = $clickup_invite_pending;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Apiary::setAttributes(
            $this->uid,
            [
                'clickup_id' => $this->clickup_id,
                'clickup_invite_pending' => $this->clickup_invite_pending,
            ]
        );

        Log::info(self::class . ': Successfully updated ClickUp attributes for ' . $this->uid);
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
            'clickup_id:' . $this->clickup_id,
            'clickup_invite_pending:' . ($this->clickup_invite_pending ? 'true' : 'false'),
        ];
    }
}
