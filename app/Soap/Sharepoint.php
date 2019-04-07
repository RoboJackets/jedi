<?php
namespace App\Jobs;

/**
 * Created by PhpStorm.
 * User: Kristaps
 * Date: 1/15/2017
 * Time: 8:18 PM
 */
class Sharepoint
{
    private $client;
    public function __construct(String $wsdl, String $email, String $password)
    {

        $context = stream_context_create([
            'ssl' => [
                // set some SSL/TLS specific options
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ]);
        // curl_setopt( $curl_handle, CURLOPT_COOKIESESSION, true );
        // curl_setopt( $curl_handle, CURLOPT_COOKIEJAR, uniquefilename );
        // curl_setopt( $curl_handle, CURLOPT_COOKIEFILE, uniquefilename );

        // $client  = new SoapClient(null, [
        //     'location' => ,
        //     'uri' => '...',
        //     'stream_context' => $context
        // ]);
        $wsdl = __DIR__."/../../resources/wsdls/sympa-wsdl.xml";
        $auth = new \SoapClient(
            $wsdl,
            array("trace" => 1, "location" => "https://lists.gatech.edu/sympa/wsdl", "stream_context" => $context)
        );
        $auth->__soapCall(
            "login",
            array(
                "email" => $email,
                "password" => $password
            ),
            null,
            null,
            $response_headers
        );
        $header = new \SoapHeader(
            "https://lists.gatech.edu/",
            "SecurityHeader",
            array(
                "Ticket" => $response_headers['SecurityHeader']->Ticket,
                "UserId" => $response_headers['SecurityHeader']->UserId
            )
        );

        $this->client = new \SoapClient($wsdl, array("trace" => 1, "location" => $wsdl));
        $this->client->__setSoapHeaders($header);
    }
    public function addUser(String $list, String $email)
    {
        return $this->client->add(
            array(
                "list" => $list,
                "email" => $email
            )
        )->AddUserResult;
    }
}
