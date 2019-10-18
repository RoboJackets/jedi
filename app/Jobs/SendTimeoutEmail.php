<?php declare(strict_types = 1);

namespace App\Jobs;

use App\EmailEvent;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class SendTimeoutEmail extends AbstractSyncJob
{
    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'apiary';

    private $exists_in_sums = false;

    /**
     * Create a new job instance
     *
     * @param string $uid The user's GT username
     * @param bool $exists_in_sums Whether this user exists in SUMS
     */
    public function __construct(
        string $uid,
        bool $exists_in_sums
    ) {
        parent::__construct($uid, '', '', false, []);
        $this->$exists_in_sums = $exists_in_sums;
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
                    'Authorization' => 'Bearer ' . config('apiary.token'),
                ],
                'allow_redirects' => false,
            ]
        );

        $response = $client->post(
            '/api/v1/notification/manual',
            [
                'json' => [
                    'template_type' => 'database',
                    'template_id' => $this->existsInSUMS
                        ? config('apiary.sums_timeout_email_template_id')
                        : config('apiary.non_sums_timeout_email_template_id'),
                    'emails' => [
                        $this->uid . '@gatech.edu',
                    ],
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

        Log::info(self::class . ': Successfully queued for ' . $this->uid);
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return ['user:' . $this->uid, 'exists_in_sums:' . ($this->exists_in_sums ? 'true' : 'false')];
    }
}
