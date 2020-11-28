<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\AutodeskLibrary;
use Illuminate\Support\Facades\Log;

class SyncAutodeskLibrary extends SyncJob
{
    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'autodesk';

    /**
     * The email associated with this user in Apiary
     *
     * @var string
     */
    private $autodesk_email;

    /**
     * Whether Apiary thinks this user has a pending invitation in Autodesk
     *
     * @var bool
     */
    private $autodesk_invite_pending;

    /**
     * Create a new job instance
     *
     * @param string $uid            The user's GT username
     * @param bool $is_access_active Whether the user should have access to systems
     * @param array<string>  $teams  The names of the teams the user is in
     * @param string $autodesk_email    The user's Autodesk email
     * @param bool $autodesk_invite_pending whether Apiary thinks the Autodesk invitation is pending
     */
    protected function __construct(
        string $uid,
        bool $is_access_active,
        array $teams,
        string $autodesk_email,
        bool $autodesk_invite_pending
    ) {
        parent::__construct($uid, '', '', $is_access_active, $teams);

        $this->autodesk_email = $autodesk_email;
        $this->autodesk_invite_pending = $autodesk_invite_pending;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $member = AutodeskLibrary::isMember($this->$autodesk_email);
        $pending = AutodeskLibrary::isInvitePending($this->$autodesk_email);

        if ($this->is_access_active) {
            Log::info(self::class . ': Enabling ' . $this->uid);
            if (true !== $member) {
                AutodeskLibrary::addUser($this->autodesk_email);
                $pending = true;
            }
        } else {
            Log::info(self::class . ': Disabling ' . $this->uid);

            if (true === $member) {
                AutodeskLibrary::removeUser($this->autodesk_email);
            }

            if (true === $pending) {
                AutodeskLibrary::cancelInvite($this->autodesk_email);
                $pending = false;
            }
        }

        if ($this->autodesk_invite_pending === $pending) {
            return;
        }

        UpdateAutodeskLibraryInvitePendingFlag::dispatch($this->uid, $pending);
    }
}
