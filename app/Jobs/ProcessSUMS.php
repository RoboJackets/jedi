<?php declare(strict_types = 1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class ProcessSUMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $uid;
    protected $has_access;

    /**
     * Create a new job instance.
     */
    public function __construct(Request $request)
    {
        $this->uid = $request->uid;
        $this->has_access = $request->is_access_active;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        if ($this->uid === config('sums.username')) {
            return;
        }
        $send = [];
        $send['UserName'] = $this->uid;
        $send['BillingGroupId'] = config('sums.billingid');
        $send['isRemove'] = !$this->has_access ? 'true' : 'false';
        $send['isListMembers'] = 'false';
        $send['Key'] = config('sums.token');
        $client = new Client(
            [
                'headers' => [
                    'User-Agent' => 'JEDI on ' . config('app.url'),
                ],
            ]
        );
        $response = $client->request('GET', config('sums.endpoint'), ['query' => $send]);
        if (200 !== $response->getStatusCode() && 204 !== $response->getStatusCode()) {
            throw new \Exception(
                'Sending data to SUMS failed with HTTP response code ' . $response->getStatusCode()
            );
        }
    }
}
