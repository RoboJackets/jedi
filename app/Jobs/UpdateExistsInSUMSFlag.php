<?php declare(strict_types = 1);

namespace App\Jobs;

use App\Services\Apiary;
use Illuminate\Support\Facades\Log;

class UpdateExistsInSUMSFlag extends AbstractApiaryJob
{
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        Apiary::setFlag($this->uid, 'exists_in_sums', true);

        Log::info(self::class . ': Successfully updated exists_in_sums flag for ' . $this->uid);
    }
}
