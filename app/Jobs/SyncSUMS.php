<?php

declare(strict_types=1);

// phpcs:disable SlevomatCodingStandard.ControlStructures.RequireSingleLineCondition.RequiredSingleLineCondition
// phpcs:disable SlevomatCodingStandard.Functions.RequireSingleLineCall.RequiredSingleLineCall

namespace App\Jobs;

use App\EmailEvent;
use App\Services\SUMS;
use Exception;
use Illuminate\Support\Facades\Log;

class SyncSUMS extends SyncJob
{
    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'sums';

    /**
     * Whether this job should trigger an email
     *
     * @var bool
     */
    private $should_send_email;

    /**
     * Last seen attendance event ID
     *
     * @var ?int
     */
    private $last_attendance_id;

    /**
     * Whether the user exists in SUMS according to Apiary
     *
     * @var bool
     */
    private $exists_in_sums;

    /**
     * Create a new job instance
     *
     * @param string $uid             The user's GT username
     * @param bool $is_access_active  Whether the user should have access to systems
     * @param bool $should_send_email Whether this job should trigger an email
     * @param ?int $last_attendance_id The last seen attendance event ID for this user
     * @param bool $exists_in_sums    Whether the user exists in SUMS according to Apiary
     */
    protected function __construct(
        string $uid,
        bool $is_access_active,
        bool $should_send_email,
        ?int $last_attendance_id,
        bool $exists_in_sums
    ) {
        parent::__construct($uid, '', '', $is_access_active, []);

        $this->should_send_email = $should_send_email;
        $this->last_attendance_id = $last_attendance_id;
        $this->exists_in_sums = $exists_in_sums;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
        if (in_array($this->uid, config('sums.whitelisted_accounts'), true) && (false === $this->is_access_active)) {
            Log::info('Attempted to disable ' . $this->uid . ' but that user is whitelisted');
            return;
        }

        if ($this->is_access_active) {
            Log::info(self::class . ': Enabling ' . $this->uid);

            $responseBody = SUMS::addUserToBillingGroup($this->uid);

            if (SUMS::SUCCESS === $responseBody) {
                Log::info(self::class . ': Enabled ' . $this->uid);
                if (!$this->exists_in_sums) {
                    UpdateExistsInSUMSFlag::dispatch($this->uid);
                }
            } elseif (SUMS::MEMBER_EXISTS === $responseBody) {
                Log::info(self::class . ': ' . $this->uid . ' was already enabled');
                if (!$this->exists_in_sums) {
                    UpdateExistsInSUMSFlag::dispatch($this->uid);
                }
            } elseif (SUMS::USER_NOT_FOUND === $responseBody) {
                Log::info(self::class . ': ' . $this->uid . ' does not exist in SUMS, attempting to create');

                $createResponse = SUMS::createUser($this->uid);

                if (SUMS::SUCCESS === $createResponse) {
                    Log::info(self::class . ': '. 'Created ' . $this->uid . ' in SUMS, dispatching new job to add to billing group');

                    self::dispatch($this->uid, $this->is_access_active, $this->should_send_email, $this->last_attendance_id, $this->exists_in_sums);
                } else {
                    throw new Exception(
                        'SUMS returned an unexpected response ' . $createResponse . ' while creating user, expected "' . SUMS::SUCCESS '"';
                    )
                }
            } else {
                throw new Exception(
                    'SUMS returned an unexpected response ' . $responseBody . ' while adding user to billing group, expected "' . SUMS::SUCCESS . '", "'
                        . SUMS::MEMBER_EXISTS . '", "' . SUMS::USER_NOT_FOUND . '"'
                );
            }
        } else {
            Log::info(self::class . ': Disabling ' . $this->uid);

            $responseBody = SUMS::removeUser($this->uid);

            if (SUMS::SUCCESS === $responseBody) {
                Log::info(self::class . ': Disabled ' . $this->uid);
                if (!$this->exists_in_sums) {
                    UpdateExistsInSUMSFlag::dispatch($this->uid);
                }
            } elseif (SUMS::MEMBER_NOT_EXISTS === $responseBody) {
                Log::info(self::class . ': ' . $this->uid . ' was already disabled');
                if (!$this->exists_in_sums) {
                    UpdateExistsInSUMSFlag::dispatch($this->uid);
                }
            } elseif (SUMS::USER_NOT_FOUND === $responseBody) {
                Log::info(self::class . ': ' . $this->uid . ' does not exist in SUMS');
            } else {
                throw new Exception(
                    'SUMS returned an unexpected response ' . $responseBody . ' while removing user from billing group, expected "' . SUMS::SUCCESS . '", "'
                        . SUMS::MEMBER_NOT_EXISTS . '", "' . SUMS::USER_NOT_FOUND . '"'
                );
            }

            if ($this->should_send_email) {
                if (
                    0 === EmailEvent::where(
                        'last_attendance_id',
                        $this->last_attendance_id
                    )->where(
                        'uid',
                        $this->uid
                    )->count()
                ) {
                    $email = new EmailEvent();
                    $email->last_attendance_id = $this->last_attendance_id;
                    $email->uid = $this->uid;
                    $email->save();
                    if (SUMS::USER_NOT_FOUND === $responseBody) {
                        SendTimeoutEmail::dispatch($this->uid, false);
                    } else {
                        SendTimeoutEmail::dispatch($this->uid, true);
                    }
                }
            }
        }
    }
}
