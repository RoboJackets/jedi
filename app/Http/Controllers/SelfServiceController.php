<?php declare(strict_types = 1);

namespace App\Http\Controllers;

use App\Jobs\SyncGitHub;
use App\Jobs\UpdateExistsInSUMSFlag;
use App\Services\Apiary;
use App\Services\GitHub;
use App\Services\SUMS;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SelfServiceController extends Controller
{
    /**
     * Sync the currently logged in user with GitHub.
     *
     * @param Request $request The incoming request
     *
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function github(Request $request)
    {
        $username = $request->user()->uid;

        $apiary_user = Apiary::getUser($username);

        if (! $apiary_user->user->is_access_active) {
            return view('self-service.unpaid-dues');
        }

        if (0 === count($apiary_user->user->teams)) {
            return view('self-service.no-teams');
        }

        if (null === $apiary_user->user->github_username) {
            return redirect('https://my.robojackets.org/github');
        }

        $github_membership = GitHub::getOrganizationMembership($apiary_user->user->github_username);

        if (null === $github_membership) {
            SyncGitHub::dispatchNow(
                $username,
                true,
                array_map(
                    static function (object $team): string {
                        return $team->name;
                    },
                    $apiary_user->user->teams
                ),
                $apiary_user->user->github_username
            );

            $github_membership = GitHub::getOrganizationMembership($apiary_user->user->github_username);

            if (null === $github_membership) {
                return view('self-service.error');
            }
        }

        if ('pending' === $github_membership->state) {
            return redirect('https://github.com/orgs/' . config('github.organization') . '/invitation');
        }

        if ('active' === $github_membership->state) {
            return view(
                'self-service.already-member',
                [
                    'service' => 'GitHub',
                ]
            );
        }

        return view('self-service.error');
    }

    /**
     * Sync the currently logged in user with SUMS.
     *
     * @param Request $request The incoming request
     *
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory
     */
    public function sums(Request $request)
    {
        $username = $request->user()->uid;

        $apiary_user = Apiary::getUser($username);

        if (! $apiary_user->user->is_access_active) {
            return view('self-service.unpaid-dues');
        }

        if (0 === count($apiary_user->user->teams)) {
            return view('self-service.no-teams');
        }

        $recent_attendance = array_filter(
            $apiary_user->user->attendance,
            static function (object $attendance): bool {
                return $attendance->created_at > new Carbon(
                    // @phan-suppress-next-line PhanPartialTypeMismatchArgument
                    config('sums.attendance_timeout_limit'),
                    'America/New_York'
                );
            }
        );

        if (0 === count($recent_attendance)) {
            return view('self-service.no-recent-attendance');
        }

        $response = SUMS::addUser($username);

        if (SUMS::SUCCESS === $response) {
            UpdateExistsInSUMSFlag::dispatch($username);
            return view(
                'self-service.success',
                [
                    'group-name-in-service' => 'group in SUMS',
                ]
            );
        }
        if (SUMS::MEMBER_EXISTS === $response) {
            UpdateExistsInSUMSFlag::dispatch($username);
            return view(
                'self-service.already-member',
                [
                    'service' => 'SUMS',
                ]
            );
        }
        if (SUMS::USER_NOT_FOUND === $response) {
            return view('self-service.need-sums-account');
        }

        return view('self-service.error');
    }
}
