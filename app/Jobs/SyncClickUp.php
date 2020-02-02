<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ClickUp;
use Illuminate\Support\Facades\Log;

class SyncClickUp extends SyncJob
{
    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'clickup';

    /**
     * The email associated with this user in Apiary
     *
     * @var string
     */
    private $clickup_email;

    /**
     * The numeric ID of this user within ClickUp
     *
     * @var ?int
     */
    private $clickup_id;

    /**
     * Whether Apiary thinks this user has a pending invitation in ClickUp
     *
     * @var bool
     */
    private $clickup_invite_pending;

    /**
     * Create a new job instance
     */
    protected function __construct(
        string $uid,
        bool $is_access_active,
        array $teams,
        string $clickup_email,
        ?int $clickup_id,
        bool $clickup_invite_pending
    ) {
        parent::__construct($uid, '', '', $is_access_active, $teams);

        $this->clickup_email = $clickup_email;
        $this->clickup_id = $clickup_id;
        $this->clickup_invite_pending = $clickup_invite_pending;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        if ($this->is_access_active) {
            Log::info(self::class . ': Enabling ' . $this->uid);

            $response = ClickUp::addUser($this->clickup_email);

            if (null === $this->clickup_id) {
                UpdateClickUpAttributes::dispatch($this->uid, $response->user->id, $response->invite);
            } else {
                if ($this->clickup_invite_pending !== $response->invite) {
                    UpdateClickUpInvitePendingFlag::dispatch($this->uid, $response->invite);
                }
            }

            foreach ($this->teams as $team) {
                if (array_key_exists($team, config('clickup.teams_to_spaces'))) {
                    foreach (config('clickup.teams_to_spaces')[$team] as $space_id) {
                        ClickUp::addUserToSpace($this->clickup_id, $space_id);
                    }
                }
            }
        } else {
            Log::info(self::class . ': Disabling ' . $this->uid);

            if (null === $this->clickup_id) {
                Log::warning(self::class . ': Asked to disable ' . $this->uid . ' but no clickup_id available');
                return;
            }

            ClickUp::removeUser($this->clickup_id);

            if (true === $this->clickup_invite_pending) {
                UpdateClickUpInvitePendingFlag::dispatch($this->uid, false);
            }
        }
    }
}
