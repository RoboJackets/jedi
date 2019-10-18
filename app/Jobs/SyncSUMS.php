<?php declare(strict_types = 1);

namespace App\Jobs;

use App\EmailEvent;
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
     * Whether this job should trigger an email
     *
     * @var bool
     */
    private $should_send_email;

    /**
     * Last seen attendance event ID
     *
     * @var ?int
     */
    private $last_attendance_id;

    /**
     * Create a new job instance
     *
     * @param string $uid             The user's GT username
     * @param bool $is_access_active  Whether the user should have access to systems
     * @param bool $should_send_email Whether this job should trigger an email
     * @param ?int $last_attendance_id The last seen attendance event ID for this user
     */
    public function __construct(
        string $uid,
        bool $is_access_active,
        bool $should_send_email,
        ?int $last_attendance_id
    ) {
        parent::__construct($uid, '', '', $is_access_active, []);

        $this->should_send_email = $should_send_email;
        $this->last_attendance_id = $last_attendance_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
        if (in_array($this->uid, config('sums.whitelisted_accounts')) && (false === $this->is_access_active)) {
            Log::warning('Attempted to disable ' . $this->uid . ' but that user is whitelisted');
            return;
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

            if ($this->should_send_email) {
                if (0 === EmailEvent::where(
                    'last_attendance_id',
                    $this->last_attendance_id
                )->where(
                    'uid',
                    $this->uid
                )->count()
                ) {
                    $email = new EmailEvent();
                    $email->last_attendance_id = $this->last_attendance_id;
                    $email->uid = $this->uid;
                    $email->save();
                    if (self::USER_NOT_FOUND === $responseBody) {
                        SendTimeoutEmail::dispatch($this->uid, false);
                    } else {
                        SendTimeoutEmail::dispatch($this->uid, true);
                    }
                }
            }
        }
    }
}
