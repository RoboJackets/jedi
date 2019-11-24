<?php declare(strict_types = 1);

namespace App\Jobs;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Services\Apiary;

class UpdateExistsInSUMSFlag extends AbstractApiaryJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        Apiary::client()->setFlag($this->uid, 'exists_in_sums', true);

        Log::info(self::class . ': Successfully updated exists_in_sums flag for ' . $this->uid);
    }
}
