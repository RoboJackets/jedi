<?php declare(strict_types = 1);

namespace App\Jobs;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RoboJackets\Vault\Client;
use RoboJackets\Vault\Group;
use SoapFault;
use Throwable;

class SyncVault extends AbstractSyncJob
{
    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'vault';

    /**
     * The number of times the job may be attempted.
     *
     * Overridden to 2 here because we want to retry if the issue was with the security header.
     *
     * @var int
     */
    public $tries = 2;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $server = config('vault.server');
        $username = config('vault.username');
        $password = config('vault.password');

        if (!is_string($server)) {
            throw new Exception('server is not a string');
        }

        if (!is_string($username)) {
            throw new Exception('username is not a string');
        }

        if (!is_string($password)) {
            throw new Exception('password is not a string');
        }

        $header = Cache::get('vault_security_header');

        if (null === $header) {
            $header = Client::makeSecurityHeader($server, $username, $password);
            Cache::forever('vault_security_header', $header);
        }

        $vault = Client::makeWithSecurityHeader($server, $header);

        $userId = Cache::get('vault_user_id_' . $this->uid);

        if (null === $userId) {
            $userId = $vault->getUserIdByUsername($this->uid);

            if (-1 !== $userId) {
                Cache::forever('vault_user_id_' . $this->uid, $userId);
            }
        }

        if (-1 === $userId) {
            Log::info(self::class . ': User ' . $this->uid . ' does not exist in Vault');

            return;
        }

        $user = $vault->getUserByUserId($userId);

        if ($user->Name !== $this->uid) {
            throw new Exception('Cached ID has wrong username');
        }

        if ($this->is_access_active) {
            Log::info(self::class . ': Enabling user ' . $this->uid);
            $need_to_save = false;

            if (true !== $user->IsActive) {
                $user->IsActive = true;
                $need_to_save = true;
            }

            if ($user->FirstName !== $this->first_name) {
                $user->FirstName = $this->first_name;
                $need_to_save = true;
            }

            if ($user->LastName !== $this->last_name) {
                $user->LastName = $this->last_name;
                $need_to_save = true;
            }

            if ('' !== $user->Email) {
                $user->Email = '';
                $need_to_save = true;
            }

            if ($need_to_save) {
                Log::debug(self::class . ': Updating name/email for user ' . $this->uid);
                $user->save();
            } else {
                Log::debug(self::class . ': User ' . $this->uid . ' attributes are up to date');
            }

            $groups = Cache::get('vault_groups');

            if (null === $groups) {
                $groups = $vault->getAllGroups();
                Cache::forever('vault_groups', $groups);
            }

            foreach ($this->teams as $team) {
                $filteredgroups = array_filter(
                    $groups,
                    static function (Group $group) use ($team): bool {
                        return $group->Name === $team;
                    }
                );

                if (0 === count($filteredgroups)) {
                    continue;
                }

                if (1 === count($filteredgroups)) {
                    // checking group membership is ass so we're just going to try to add them
                    // as far as i can tell this doesn't throw an exception or anything if they're already in the group
                    $key = array_keys($filteredgroups)[0];
                    Log::debug(self::class . ': Adding group ' . $filteredgroups[$key]->Name . ' to ' . $this->uid);
                    $vault->addUserToGroup($userId, $filteredgroups[$key]->Id);
                }

                if (count($filteredgroups) > 1) {
                    throw new Exception('Two groups with the same name, y u do dis');
                }
            }
        } else {
            if ($user->IsActive) {
                Log::info(self::class . ': Disabling user ' . $this->uid);

                $user->IsActive = false;
                $user->save();

                Log::info(self::class . ': Successfully disabled ' . $this->uid);
            } else {
                Log::info(self::class . ': User ' . $this->uid . ' already disabled, don\'t need to change anything');
            }
        }
    }

    // phpcs:disable SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed

    /**
     * The job failed to process.
     *
     * @param Throwable  $throwable
     *
     * @return void
     */
    public function failed(Throwable $throwable): void
    {
        if ($throwable instanceof SoapFault && 300 === intval($throwable->getMessage())) {
            Cache::forget('vault_security_header');
        }
    }
}
