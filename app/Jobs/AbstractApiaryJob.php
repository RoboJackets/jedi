<?php declare(strict_types = 1);

namespace App\Jobs;

use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

abstract class AbstractApiaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * The user's GT username
     *
     * @var string
     */
    protected $uid;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    protected static function client(): Client
    {
        return new Client(
            [
                'base_uri' => config('apiary.server'),
                'headers' => [
                    'User-Agent' => 'JEDI on ' . config('app.url'),
                    'Authorization' => 'Bearer ' . config('apiary.token'),
                ],
                'allow_redirects' => false,
            ]
        );
    }

    /**
     * Create a new job instance
     *
     * @param string $uid The user's GT username
     */
    protected function __construct(string $uid) {
        $this->queue = 'apiary';
        $this->uid = $uid;
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
    abstract public function tags(): array;
}
