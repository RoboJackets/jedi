<?php declare(strict_types = 1);

namespace App\Jobs;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class SendSUMSTimeoutEmail extends AbstractSyncJob
{
    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'apiary';

    /**
     * Create a new job instance
     *
     * @param string $uid The user's GT username
     */
    public function __construct(
        string $uid
    ) {
        parent::__construct($uid, '', '', false, []);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $client = new Client(
            [
                'base_uri' => config('apiary.server'),
                'headers' => [
                    'User-Agent' => 'JEDI on ' . config('app.url'),
                    'Authorization' => 'Bearer ' . config('apiary.token')
                ],
                'allow_redirects' => false,
            ]
        );

        $client->put(
            '/api/v1/notification/manual',
            [
                'json' => [
                    'template_type' => 'database',
                    'template_id' => config('apiary.sums_timeout_email_template_id'),
                    'emails' => [
                        $this->uid . '@gatech.edu',
                    ]
                ]
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

        Log::info(self::class . ': Successfully queued for ' . $this->uid);
    }
}
