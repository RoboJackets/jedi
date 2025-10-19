<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Apiary;
use App\Services\Ramp;
use Illuminate\Support\Facades\Log;

class CopyRampPhoneNumberToApiary extends SyncJob
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
     */
    protected function __construct(
        string $username,
        private readonly ?string $ramp_user_id,
        private readonly ?string $google_workspace_account
    ) {
        parent::__construct($username, '', '', false, []);
    }

    #[\Override]
    public function handle(): void
    {
        $apiary_user = Apiary::getUser($this->username);

        if (property_exists($apiary_user, 'phone_verified') && $apiary_user->phone_verified === true) {
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

            if ($ramp_user->phone !== null) {
                Apiary::setAttributes($this->username, [
                    'phone' => $ramp_user->phone,
                    'phone_verified' => true,
                ]);
            }
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    #[\Override]
    public function tags(): array
    {
        return ['user:'.$this->username];
    }
}
