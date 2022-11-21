<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Apiary;
use Illuminate\Support\Facades\Log;

class UpdateAutodeskLibraryInvitePendingFlag extends ApiaryJob
{
    /**
     * Whether this user has a pending Autodesk invitation.
     *
     * @var bool
     */
    private $autodesk_invite_pending = false;

    /**
     * Create a new job instance.
     */
    protected function __construct(string $uid, bool $autodesk_invite_pending)
    {
        parent::__construct($uid);
        $this->autodesk_invite_pending = $autodesk_invite_pending;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Apiary::setFlag($this->uid, 'autodesk_invite_pending', $this->autodesk_invite_pending);
        Log::info(self::class.': Successfully updated autodesk_invite_pending flag for '.$this->uid);
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
            'autodesk_invite_pending:'.($this->autodesk_invite_pending ? 'true' : 'false'),
        ];
    }
}
