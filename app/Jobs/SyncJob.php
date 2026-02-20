<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

abstract class SyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param  string  $username  The user's GT username
     * @param  string  $first_name  The user's first name
     * @param  string  $last_name  The user's last name
     * @param  bool  $is_access_active  Whether the user should have access to systems
     * @param  array<string>  $teams  The names of the teams the user is in
     *
     * @psalm-mutation-free
     */
    protected function __construct(
        protected readonly string $username,
        protected readonly string $first_name,
        protected readonly string $last_name,
        protected readonly bool $is_access_active,
        protected readonly array $teams
    ) {
    }

    /**
     * Execute the job.
     *
     * @psalm-impure
     */
    abstract public function handle(): void;

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     *
     * @psalm-mutation-free
     */
    public function tags(): array
    {
        return ['user:'.$this->username, 'active:'.($this->is_access_active ? 'true' : 'false')];
    }
}
