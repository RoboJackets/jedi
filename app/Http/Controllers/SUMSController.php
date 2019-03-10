<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;

class SUMSController extends Controller
{
    public function editUser(Request $request)
    {
        $this->validate($request, [
        'is_access_active' => 'required',
        ]);
        $send = [];
        $send['UserName'] = $request->uid;
        $send['BillingGroupId'] = config('sums.billingid');
        $send['isRemove'] = (!$request->is_access_active)? 'true' : 'false';
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
        return response()->json(204);
    }
}
