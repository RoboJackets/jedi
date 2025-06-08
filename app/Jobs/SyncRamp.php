<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.Functions.DisallowNamedArguments.DisallowedNamedArgument

namespace App\Jobs;

use App\Services\Ramp;
use Illuminate\Support\Facades\Log;

class SyncRamp extends SyncJob
{
    /**
     * The queue this job will run on.
     *
     * @var string
     */
    public $queue = 'ramp';

    /**
     * Create a new job instance.
     *
     * @param  string  $username  The user's GT username
     * @param  bool  $is_access_active  Whether the user should have access to systems
     * @param  array<string>  $teams  The names of the teams the user is in
     */
    protected function __construct(
        protected readonly string $username,
        protected readonly bool $is_access_active,
        private readonly ?string $ramp_user_id,
        private readonly ?string $google_workspace_account
    ) {
        parent::__construct($username, '', '', $is_access_active, []);
    }

    public function handle(): void
    {
        if ($this->is_access_active) {
            return;
        }

        if ($this->ramp_user_id !== null) {
            $ramp_user = Ramp::getUser($this->ramp_user_id);
        } elseif ($this->google_workspace_account !== null) {
            $ramp_user = Ramp::getUserByEmail($this->google_workspace_account);
        } else {
            throw new \Exception('Job was invoked without a Ramp user ID or email address');
        }

        if ($ramp_user !== null) {
            Log::info(self::class.': Found Ramp user ID: '.$ramp_user->id.' for username '.$this->username);

            if (in_array($ramp_user->role, ['BUSINESS_ADMIN', 'BUSINESS_OWNER', 'IT_ADMIN'], strict: true)) {
                $this->fail(
                    'Ramp user is not access active but has exempt role '.$ramp_user->role
                    .', manual intervention required'
                );
            } else {
                Log::info(self::class.': Deactivating Ramp user '.$ramp_user->id.'because they are not access active');
                Ramp::deactivateUser($ramp_user->id);
            }
        }
    }
}
