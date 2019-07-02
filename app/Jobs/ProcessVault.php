<?php declare(strict_types = 1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use RoboJackets\Vault\Client;

class ProcessVault implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user's username
     *
     * @var string
     */
    protected $uid;

    /**
     * Whether the user should have access
     *
     * @var bool
     */
    protected $has_access;

    /**
     * The user's teams
     *
     * @var array<string>
     */
    protected $teams;

    /**
     * The user's first name
     *
     * @var string
     */
    protected $first_name;

    /**
     * The user's last name
     *
     * @var string
     */
    protected $last_name;
    /**
     * Create a new job instance.
     */
    public function __construct(Request $request)
    {
        $this->uid = $request->uid;
        $this->has_access = $request->is_access_active;
        $this->teams = $request->teams;
        $this->first_name = $request->first_name;
        $this->last_name = $request->last_name;
    }
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        if ($this->uid === config('vault.username')) {
            return;
        }
        $vault = Client::makeWithCredentials(
            'asdf',
            'jkl',
            'semicolon'
        );
        $userId = $vault->getUserIdByUsername($this->uid);
        if ($userId <= 0) {
            return;
        }

        $vault->updateUserInfo($userId, $this->has_access, $this->uid, '', $this->first_name, $this->last_name);
        if (true !== $this->has_access) {
            return;
        }
    }
}
