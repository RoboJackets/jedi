<?php declare(strict_types = 1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\ProcessSUMS;
use App\Jobs\ProcessGithub;
use App\Jobs\ProcessNextcloud;
use App\Jobs\ProcessWordPress;
use App\Jobs\ProcessVault;

class SyncController extends Controller
{
    public function sync(Request $request): JsonResponse
    {
        $this->validate(
            $request,
            [
                'uid' => 'bail|required|string|alpha_num',
                'first_name' => 'bail|required|string',
                'last_name' => 'bail|required|string',
                'is_access_active' => 'bail|required|boolean',
                'teams' => 'bail|present|array',
            ]
        );

        if (true === config('nextcloud.enabled')) {
            SyncNextcloud::dispatch(
                $request->uid,
                $request->first_name,
                $request->last_name,
                $request->is_access_active,
                $request->teams
            );
        }

        return response()->json('ok', 200);
    }
}
