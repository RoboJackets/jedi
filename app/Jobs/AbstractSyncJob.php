<?php declare(strict_types = 1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

abstract class AbstractSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The user's GT username
     *
     * @var string
     */
    protected $uid;

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
     * Whether the user should have access to systems
     *
     * @var bool
     */
    protected $is_access_active;

    /**
     * The names of the teams the user is in
     *
     * @var array<string>
     */
    protected $teams;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * Create a new job instance
     *
     * @param string $uid            The user's GT username
     * @param string $first_name     The user's first name
     * @param string $last_name      The user's last name
     * @param bool $is_access_active Whether the user should have access to systems
     * @param array<string>  $teams  The names of the teams the user is in
     */
    protected function __construct(
        string $uid,
        string $first_name,
        string $last_name,
        bool $is_access_active,
        array $teams
    ) {
        $this->uid = $uid;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->is_access_active = $is_access_active;
        $this->teams = $teams;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    abstract public function handle(): void;

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['user:' . $this->uid, 'active:' . ($this->is_access_active ? 'true' : 'false')];
    }
}
