<?php declare(strict_types = 1);

namespace App\Jobs;

use Exception;
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
        $client = self::client();

        $response = $client->put(
            '/api/v1/users/' . $this->uid,
            [
                'json' => [
                    'exists_in_sums' => true,
                ],
            ]
        );

        if (200 !== $response->getStatusCode()) {
            throw new Exception(
                'Apiary returned an unexpected HTTP response code ' . $response->getStatusCode() . ', expected 200'
            );
        }

        $responseBody = $response->getBody()->getContents();

        $json = json_decode($responseBody);

        if ('success' !== $json->status) {
            throw new Exception(
                'Apiary returned an unexpected response ' . $responseBody . ', expected status: success'
            );
        }

        Log::info(self::class . ': Successfully updated exists_in_sums flag for ' . $this->uid);
    }
}
