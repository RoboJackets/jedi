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
     *
     * @param string $uid            The user's GT username
     * @param bool $is_access_active Whether the user should have access to systems
     * @param array<string>  $teams  The names of the teams the user is in
     * @param string $clickup_email     The user's ClickUp email
     * @param ?int $clickup_id the user's ClickUp ID
     * @param bool $clickup_invite_pending whether Apiary thinks the ClickUp invitation is pending
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

            if (null === $this->clickup_id || $this->clickup_id !== $response->user->id) {
                UpdateClickUpAttributes::dispatch($this->uid, $response->user->id, $response->invite);
            } else {
                if ($this->clickup_invite_pending !== $response->invite) {
                    UpdateClickUpInvitePendingFlag::dispatch($this->uid, $response->invite);
                }
            }

            foreach ($this->teams as $team) {
                // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
                if (!array_key_exists($team, config('clickup.teams_to_spaces'))) {
                    continue;
                }

                foreach (config('clickup.teams_to_spaces')[$team] as $space_id) {
                    ClickUp::addUserToSpace($response->user->id, $space_id);
                }
            }
        } else {
            Log::info(self::class . ': Disabling ' . $this->uid);

            if (null === $this->clickup_id) {
                Log::info(self::class . ': Asked to disable ' . $this->uid . ' but no clickup_id available');
                return;
            }

            ClickUp::removeUser($this->clickup_id);

            if (true === $this->clickup_invite_pending) {
                UpdateClickUpInvitePendingFlag::dispatch($this->uid, false);
            }
        }
    }
}
