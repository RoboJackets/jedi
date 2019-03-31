<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class ProcessGithub implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $uid;
    protected $has_access;
    protected $teams;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->uid= $request->uid;
        $this->has_access= $request->is_access_active;
        $this->teams= $request->teams;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $send = [];
        $send['account'] = $this->uid;
        $send['access'] = ($this->has_access)? 'true' : 'false';
        $send['teams'] = $this->teams;
        $client = new Client(
            [
             'headers' => [
                 'User-Agent' => 'JEDI on '.config('app.url'),
                 'Accept' => 'application/json',
                 'Authorization' => 'Bearer '.config('github.token'),
             ],
            ]
        );
        $response = $client->request('POST', config('github.endpoint'), ['json' => $send]);
        if (200 !== $response->getStatusCode()) {
            throw new \Exception(
                'Sending data to Github failed with HTTP response code '.$response->getStatusCode()
            );
        }
    }
}
