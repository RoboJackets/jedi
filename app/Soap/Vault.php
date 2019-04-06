<?php

namespace App\Soap;
/**
 * Created by PhpStorm.
 * User: Kristaps
 * Date: 1/15/2017
 * Time: 8:18 PM
 */
class Vault
{
    private $AdminService;
    private $KnowledgeVaultService;
    public function __construct(String $server, String $username, String $password)
    {
        $auth = $this->makeSoapClient($server.'/AutodeskDM/Services/Filestore/v22/AuthService.svc?wsdl');
        $auth->__soapCall(
            "SignIn",
            array(array(
                "userName" => $username,
                "dataServer" => $server,
                "userPassword" => $password,
                "knowledgeVault" => config('vault.vault')
            )),
            null,
            null,
            $response_headers
        );
        $header = new \SoapHeader(
            "http://AutodeskDM/Services",
            "SecurityHeader",
            array(
                "Ticket" => $response_headers['SecurityHeader']->Ticket,
                "UserId" => $response_headers['SecurityHeader']->UserId
            )
        );
        $this->AdminService = $this->makeSoapClient(
            $server."/AutodeskDM/Services/v22/AdminService.svc?wsdl",
            $header
        );
    }
    private function makeSoapClient(String $wsdl, \SoapHeader $header = null)
    {
        $client = new \SoapClient($wsdl, array("trace" => 1, "sslmethod" => SOAP_SSL_METHOD_TLS, "location" => $wsdl));
        if ($header != null) {
            $client->__setSoapHeaders($header);
        }
        return $client;
    }
    public function updateUser(int $userid, bool $has_access)
    {
        //$isActive= $has_access? 'true' : 'false';
        return $this->AdminService->UpdateUserInfo(
            array("userId" => $userid,
                  "atype" => "Vault",
                  "isActive" => "true"
            )
        );
    }

    public function getUserId(String $username)
    {
        $users = $this->AdminService->GetAllUsers()->GetAllUsersResult->User;
        if (!is_array($users)) {
            $users = array($users);
        }
        foreach ($users as $user) {
            if ($user->Name === $username) {
                return $user->Id;
            }
        }
        return -1;
    }
}
