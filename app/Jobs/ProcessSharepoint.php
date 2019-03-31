<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;apc_exists
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class ProcessSharepoint implements ShouldQueue
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

        $auth = $this->makeSoapClient($server."/AutodeskDM/Services/Filestore/v22/AuthService.svc?wsdl");
        $auth->__soapCall(
            "SignIn",
            array(array(
                "userName" => $username,
                "dataServer" => $server,
                "userPassword" => $password,
                "knowledgeVault" => $vault
            )),
            null,
            null,
            $response_headers
        );
        $auth->__soapCall(
            "SignIn",
            array(array(
                "userName" => $username,
                "dataServer" => $server,
                "userPassword" => $password,
                "knowledgeVault" => $vault
            )),
            null,
            null,
            $response_headers
        );
        $this->AdminService = $this->makeSoapClient(
            $server."/AutodeskDM/Services/v22/AdminService.svc?wsdl",
            $header
        );
        $this->KnowledgeVaultService = $this->makeSoapClient(
            $server."/AutodeskDM/Services/v22/KnowledgeVaultService.svc?wsdl",
            $header
        );
        if (200 !== $response->getStatusCode()) {
            throw new \Exception(
               'Sending data to SUMS failed with HTTP response code '.$response->getStatusCode()
            );
        }
    }
}
