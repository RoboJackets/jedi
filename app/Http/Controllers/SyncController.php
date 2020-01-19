<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncClickUp;
use App\Jobs\SyncGitHub;
use App\Jobs\SyncGoogleGroups;
use App\Jobs\SyncNextcloud;
use App\Jobs\SyncSUMS;
use App\Jobs\SyncWordPress;
use Carbon\Carbon;
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
                'teams.*' => 'string',
                'project_manager_of_teams' => 'bail|present|array',
                'project_manager_of_teams.*' => 'string',
                'github_username' => 'bail|present|string|nullable',
                'google_accounts' => 'bail|present|array',
                'google_accounts.*' => 'string',
                'model_class' => 'bail|required|string',
                'model_id' => 'bail|required|numeric',
                'model_event' => 'bail|required|string',
                'last_attendance_time' => 'bail|present|date|nullable',
                'last_attendance_id' => 'bail|present|numeric|nullable',
                'exists_in_sums' => 'bail|required|boolean',
                'clickup_email' => 'bail|present|string|nullable',
                'clickup_id' => 'bail|present|integer|nullable',
                'clickup_invite_pending' => 'bail|required|boolean',
            ]
        );

        Log::info(
            self::class . ': Request to sync ' . $request->uid . ' caused by ' . $request->model_event . ' of '
            . $request->model_class . ' with id ' . $request->model_id
        );

        $lastRequest = Cache::get('last_request_for_' . $request->uid);

        if (null !== $lastRequest) {
            $same = $lastRequest['first_name'] === $request->first_name &&
                    $lastRequest['last_name'] === $request->last_name &&
                    $lastRequest['is_access_active'] === $request->is_access_active &&
                    [] === array_diff($lastRequest['teams'], $request->teams) &&
                    [] === array_diff($request->teams, $lastRequest['teams']) &&
                    [] === array_diff($lastRequest['project_manager_of_teams'], $request->project_manager_of_teams) &&
                    [] === array_diff($request->project_manager_of_teams, $lastRequest['project_manager_of_teams']) &&
                    $lastRequest['github_username'] === $request->github_username &&
                    [] === array_diff($lastRequest['google_accounts'], $request->google_accounts) &&
                    [] === array_diff($request->google_accounts, $lastRequest['google_accounts']) &&
                    $lastRequest['last_attendance_time'] === $request->last_attendance_time &&
                    $lastRequest['last_attendance_id'] === $request->last_attendance_id &&
                    $lastRequest['clickup_email'] === $request->clickup_email &&
                    $lastRequest['clickup_id'] === $request->clickup_id &&
                    $lastRequest['clickup_invite_pending'] === $request->clickup_invite_pending;

            if ($same) {
                // @phan-suppress-next-line PhanPartialTypeMismatchArgumentInternal
                if (!in_array($request->model_event, config('apiary.whitelisted_events'), true)) {
                    Log::info(
                        self::class . ': Not syncing ' . $request->uid . ' as it is a duplicate of last seen event'
                    );
                    Cache::put('last_request_for_' . $request->uid, $request->all(), self::ONE_DAY); // update exp
                    return response()->json('duplicate', 200);
                }
                Log::info(
                    self::class . ': ' . $request->uid
                        . ' is a duplicate request but this one is ' . $request->model_event . ', continuing'
                );
            }
        }

        Cache::put('last_request_for_' . $request->uid, $request->all(), self::ONE_DAY);

        if (true === config('github.enabled') && $request->filled('github_username')) {
            SyncGitHub::dispatch(
                $request->uid,
                $request->is_access_active,
                $request->teams,
                $request->project_manager_of_teams,
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
            if (
                true === config('sums.attendance_timeout_enabled')
                && (true === $request->is_access_active)
                && (null !== $request->last_attendance_time
                && ($request->last_attendance_time < new Carbon(
                    // @phan-suppress-next-line PhanPartialTypeMismatchArgument
                    config('sums.attendance_timeout_limit'),
                    'America/New_York'
                ))
                || null === $request->last_attendance_id)
            ) {
                SyncSUMS::dispatch(
                    $request->uid,
                    false,
                    true === config('sums.attendance_timeout_emails') && null !== $request->last_attendance_id,
                    $request->last_attendance_id,
                    $request->exists_in_sums
                );
            } else {
                SyncSUMS::dispatch(
                    $request->uid,
                    $request->is_access_active,
                    false,
                    $request->last_attendance_id,
                    $request->exists_in_sums
                );
            }
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

        if (true === config('google.enabled')) {
            foreach($request->google_accounts as $google_account) {
                SyncGoogleGroups::dispatch(
                    $request->uid,
                    $request->first_name,
                    $request->last_name,
                    $request->is_access_active,
                    $request->teams,
                    $google_account
                );
            }
        }

        if (true === config('clickup.enabled') && $request->filled('clickup_email')) {
            SyncClickUp::dispatch(
                $request->uid,
                $request->is_access_active,
                $request->clickup_email,
                $request->clickup_id,
                $request->clickup_invite_pending
            );
        }

        return response()->json('queued', 200);
    }
}
