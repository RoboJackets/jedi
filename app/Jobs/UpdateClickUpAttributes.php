<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Apiary;
use Illuminate\Support\Facades\Log;

class UpdateClickUpAttributes extends ApiaryJob
{
    /**
     * Create a new job instance.
     */
    protected function __construct(
        string $username,
        private readonly int $clickup_id,
        private readonly bool $clickup_invite_pending
    ) {
        parent::__construct($username);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Apiary::setAttributes(
            $this->username,
            [
                'clickup_id' => $this->clickup_id,
                'clickup_invite_pending' => $this->clickup_invite_pending,
            ]
        );

        Log::info(self::class.': Successfully updated ClickUp attributes for '.$this->username);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'user:'.$this->username,
            'clickup_id:'.$this->clickup_id,
            'clickup_invite_pending:'.($this->clickup_invite_pending ? 'true' : 'false'),
        ];
    }
}
