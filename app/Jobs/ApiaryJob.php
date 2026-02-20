<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

abstract class ApiaryJob implements ShouldQueue
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
     * @psalm-mutation-free
     */
    protected function __construct(protected string $username)
    {
        $this->queue = 'apiary';
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
        return [
            'user:'.$this->username,
        ];
    }
}
