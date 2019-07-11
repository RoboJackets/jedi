<?php declare(strict_types = 1);

namespace App\Http\Controllers;

use App\Jobs\SyncGitHub;
use App\Jobs\SyncNextcloud;
use App\Jobs\SyncSUMS;
use App\Jobs\SyncVault;
use App\Jobs\SyncWordPress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
                'github_username' => 'bail|present|string|nullable',
            ]
        );

        Log::info(self::class . ': Request to sync ' . $request->uid);

        if (true === config('github.enabled') && $request->filled('github_username')) {
            SyncGitHub::dispatch(
                $request->uid,
                $request->first_name,
                $request->last_name,
                $request->is_access_active,
                $request->teams,
                $request->github_username
            );
        }

        if (true === config('nextcloud.enabled')) {
            SyncNextcloud::dispatch(
                $request->uid,
                $request->first_name,
                $request->last_name,
                $request->is_access_active,
                $request->teams
            );
        }

        if (true === config('sums.enabled')) {
            SyncSUMS::dispatch(
                $request->uid,
                $request->first_name,
                $request->last_name,
                $request->is_access_active,
                $request->teams
            );
        }

        if (true === config('vault.enabled')) {
            SyncVault::dispatch(
                $request->uid,
                $request->first_name,
                $request->last_name,
                $request->is_access_active,
                $request->teams
            );
        }

        if (true === config('wordpress.enabled')) {
            SyncWordPress::dispatch(
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
