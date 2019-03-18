<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Jobs\ProcessSUMS;

class UserController extends Controller
{
    public function editUser(Request $request)
    {
        $this->validate($request, [
          'is_access_active' => 'required',
        ]);
        ProcessSUMS::dispatch($request);

        return response()->json(204);
    }
}
