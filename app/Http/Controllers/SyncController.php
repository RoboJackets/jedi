<?php declare(strict_types = 1);

namespace App\Http\Controllers;

use App\Jobs\SyncGitHub;
use App\Jobs\SyncNextcloud;
use App\Jobs\SyncSUMS;
use App\Jobs\SyncVault;
use App\Jobs\SyncWordPress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    private const ONE_DAY = 24 * 60 * 60;

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
                'model_class' => 'bail|required|string',
                'model_id' => 'bail|required|numeric',
                'model_event' => 'bail|required|string',
            ]
        );

        Log::info(
            self::class . ': Request to sync ' . $request->uid . ' caused by ' . $request->model_event . ' of '
            . $request->model_class . ' with id ' . $request->model_event
        );

        $lastRequest = Cache::get('last_request_for_' . $request->uid);

        if (null !== $lastRequest) {
            $same = $lastRequest->first_name === $request->first_name &&
                    $lastRequest->last_name === $request->last_name &&
                    $lastRequest->is_access_active === $request->is_access_active &&
                    [] === array_diff($lastRequest->teams, $request->teams) &&
                    [] === array_diff($request->teams, $lastRequest->teams) &&
                    $lastRequest->github_username === $request->github_username;

            if ($same) {
                if ('manual' === $request->model_event) {
                    Log::info(
                        self::class . ': ' . $request->uid
                            . ' is a duplicate request but this one is manual, continuing'
                    );
                } else {
                    Log::info(
                        self::class . ': Not syncing ' . $request->uid . ' as it is a duplicate of last seen event'
                    );
                    return response()->json('duplicate', 200);
                }
            }
        }

        Cache::put('last_request_for_' . $request->uid, $request->all(), self::ONE_DAY);

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

        return response()->json('queued', 200);
    }
}
