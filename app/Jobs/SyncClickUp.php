<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\ClickUp;
use Illuminate\Support\Facades\Log;

class SyncClickUp extends SyncJob
{
    /**
     * The queue this job will run on.
     *
     * @var string
     */
    public $queue = 'clickup';

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * Create a new job instance.
     *
     * @param  string  $username  The user's GT username
     * @param  bool  $is_access_active  Whether the user should have access to systems
     * @param  array<string>  $teams  The names of the teams the user is in
     * @param  string  $clickup_email  The user's ClickUp email
     * @param  ?int  $clickup_id  the user's ClickUp ID
     * @param  bool  $clickup_invite_pending  whether Apiary thinks the ClickUp invitation is pending
     */
    protected function __construct(
        string $username,
        bool $is_access_active,
        array $teams,
        private readonly string $clickup_email,
        private readonly ?int $clickup_id,
        private readonly bool $clickup_invite_pending
    ) {
        parent::__construct($username, '', '', $is_access_active, $teams);
    }

    /**
     * Execute the job.
     */
    #[\Override]
    public function handle(): void
    {
        if ($this->is_access_active) {
            Log::info(self::class.': Enabling '.$this->username);

            $response = ClickUp::addUser($this->clickup_email);

            if ($this->clickup_id === null || $this->clickup_id !== $response->user->id) {
                UpdateClickUpAttributes::dispatch($this->username, $response->user->id, $response->invite);
            } else {
                if ($this->clickup_invite_pending !== $response->invite) {
                    UpdateClickUpInvitePendingFlag::dispatch($this->username, $response->invite);
                }
            }

            foreach ($this->teams as $team) {
                // @phan-suppress-next-line PhanTypeMismatchArgumentInternal
                if (! array_key_exists($team, config('clickup.teams_to_spaces'))) {
                    continue;
                }

                foreach (config('clickup.teams_to_spaces')[$team] as $space_id) {
                    ClickUp::addUserToSpace($response->user->id, $space_id);
                }
            }
        } else {
            Log::info(self::class.': Disabling '.$this->username);

            if ($this->clickup_id === null) {
                Log::info(self::class.': Asked to disable '.$this->username.' but no clickup_id available');

                return;
            }

            ClickUp::removeUser($this->clickup_id);

            if ($this->clickup_invite_pending === true) {
                UpdateClickUpInvitePendingFlag::dispatch($this->username, false);
            }
        }
    }
}
