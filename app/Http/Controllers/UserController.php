<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Jobs\ProcessSUMS;
use App\Jobs\ProcessGithub;

use Illuminate\Http\Request;

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
        return response(204);
    }
}
