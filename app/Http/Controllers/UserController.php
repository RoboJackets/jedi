<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Jobs\ProcessSUMS;
use App\Jobs\ProcessGithub;
use App\Jobs\ProcessNextcloud;
use App\Jobs\ProcessWordPress;

class UserController extends Controller
{
    public function editUser(Request $request)
    {
        $this->validate($request, [
          'is_access_active' => 'required',
          'teams' => 'required'
        ]);
        ProcessSUMS::dispatch($request);
        ProcessGithub::dispatch($request);
        ProcessNextcloud::dispatch($request);
        ProcessWordPress::dispatch(
            $request->uid,
            $request->is_access_active,
            $request->teams,
            $request->first_name,
            $request->last_name
        );

        return Response('good', 200);
    }
}
