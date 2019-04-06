<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

use App\Soap\Vault;

class ProcessVault implements ShouldQueue
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
        $vault = new Vault(config('vault.host'), config('vault.username'), config('vault.password'));
        $id = $vault->getUserId($this->uid);
        if($id > 0) {
            $response = $vault->updateUser($id, $this->has_access);
            if (true !== $response) {
                throw new \Exception(
                   'Sending data to Vault failed with HTTP response code '.$response
                );
            }
        }

    }
}
