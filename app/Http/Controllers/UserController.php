<?php declare(strict_types = 1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\ProcessSUMS;
use App\Jobs\ProcessGithub;
use App\Jobs\ProcessNextcloud;
use App\Jobs\ProcessWordPress;
use App\Jobs\ProcessVault;

class UserController extends Controller
{
    public function editUser(Request $request): JsonResponse
    {
        $this->validate($request, [
            'uid' => 'required',
            'first_name' => 'required',
            'last_name' => 'required',
            'is_access_active' => 'required',
            'teams' => 'required',
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
        ProcessVault::dispatch($request);
        return response()->json('good', 200);
    }
}
