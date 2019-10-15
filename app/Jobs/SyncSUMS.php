<?php declare(strict_types = 1);

namespace App\Jobs;

use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class SyncSUMS extends AbstractSyncJob
{
    private const MEMBER_EXISTS = 'BG member already exists';
    private const MEMBER_NOT_EXISTS = 'BG member does not exist';
    private const SUCCESS = 'Success';
    private const USER_NOT_FOUND = 'User not found';

    /**
     * The queue this job will run on
     *
     * @var string
     */
    public $queue = 'sums';

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
        if (in_array($this->uid, config('sums.whitelisted_accounts')) && !$this->is_access_active) {
            throw new Exception('Attempted to disable ' . $this->uid . ' but that user is whitelisted');
        }

        $client = new Client(
            [
                'base_uri' => config('sums.server') . '/SUMSAPI/rest/BillingGroupEdit/',
                'headers' => [
                    'User-Agent' => 'JEDI on ' . config('app.url'),
                ],
                'allow_redirects' => false,
            ]
        );

        if ($this->is_access_active) {
            Log::info(self::class . ': Enabling ' . $this->uid);

            $response = $client->get(
                'EditPeople',
                [
                    'query' => [
                        'UserName' => $this->uid,
                        'BillingGroupId' => config('sums.billinggroupid'),
                        'isRemove' => 'false',
                        'isListMembers' => 'false',
                        'Key' => config('sums.token'),
                    ],
                ]
            );

            if (200 !== $response->getStatusCode()) {
                throw new Exception(
                    'SUMS returned an unexpected HTTP response code ' . $response->getStatusCode() . ', expected 200'
                );
            }

            $responseBody = $response->getBody()->getContents();

            if (self::SUCCESS === $responseBody) {
                Log::info(self::class . ': Enabled ' . $this->uid);
            } elseif (self::MEMBER_EXISTS === $responseBody) {
                Log::info(self::class . ': ' . $this->uid . ' was already enabled');
            } elseif (self::USER_NOT_FOUND === $responseBody) {
                Log::info(self::class . ': ' . $this->uid . ' does not exist in SUMS');
            } else {
                throw new Exception(
                    'SUMS returned an unexpected response ' . $responseBody . ', expected "' . self::SUCCESS . '", "'
                        . self::MEMBER_EXISTS . '", "' . self::USER_NOT_FOUND . '"'
                );
            }
        } else {
            Log::info(self::class . ': Disabling ' . $this->uid);

            $response = $client->get(
                'EditPeople',
                [
                    'query' => [
                        'UserName' => $this->uid,
                        'BillingGroupId' => config('sums.billinggroupid'),
                        'isRemove' => 'true',
                        'isListMembers' => 'false',
                        'Key' => config('sums.token'),
                    ],
                ]
            );

            if (200 !== $response->getStatusCode()) {
                throw new Exception(
                    'SUMS returned an unexpected HTTP response code ' . $response->getStatusCode() . ', expected 200'
                );
            }

            $responseBody = $response->getBody()->getContents();

            if (self::SUCCESS === $responseBody) {
                Log::info(self::class . ': Disabled ' . $this->uid);
            } elseif (self::MEMBER_NOT_EXISTS === $responseBody) {
                Log::info(self::class . ': ' . $this->uid . ' was already disabled');
            } elseif (self::USER_NOT_FOUND === $responseBody) {
                Log::info(self::class . ': ' . $this->uid . ' does not exist in SUMS');
            } else {
                throw new Exception(
                    'SUMS returned an unexpected response ' . $responseBody . ', expected "' . self::SUCCESS . '", "'
                        . self::MEMBER_NOT_EXISTS . '", "' . self::USER_NOT_FOUND . '"'
                );
            }
        }
    }
}
