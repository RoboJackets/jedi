<?php

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
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->uid= $request->uid;
        $this->has_access= $request->is_access_active;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $send = [];
        $send['UserName'] = $this->uid;
        $send['BillingGroupId'] = config('sums.billingid');
        $send['isRemove'] = (!$this->has_access)? 'true' : 'false';
        $send['isListMembers'] = 'false';
        $send['Key'] = config('sums.token');
        $client = new Client(
            [
             'headers' => [
                 'User-Agent' => 'JEDI on '.config('app.url'),
                 'Accept' => 'application/json',
                 'Authorization' => 'Bearer '.config('sums.token'),
             ],
            ]
        );
      $response = $client->request('GET', config('sums.endpoint'), ['query' => $send]);
      if (200 !== $response->getStatusCode()) {
           throw new \Exception(
               'Sending data to SUMS failed with HTTP response code '.$response->getStatusCode()
           );
      }
    }
}