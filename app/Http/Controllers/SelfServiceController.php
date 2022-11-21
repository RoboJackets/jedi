<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncGitHub;
use App\Jobs\UpdateAutodeskLibraryInvitePendingFlag;
use App\Jobs\UpdateClickUpAttributes;
use App\Jobs\UpdateClickUpInvitePendingFlag;
use App\Jobs\UpdateExistsInSUMSFlag;
use App\Services\Apiary;
use App\Services\AutodeskLibrary;
use App\Services\ClickUp;
use App\Services\GitHub;
use App\Services\SUMS;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SelfServiceController extends Controller
{
    /**
     * Sync the currently logged in user with GitHub.
     *
     * @param  Request  $request  The incoming request
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function github(Request $request)
    {
        $username = $request->user()->uid;

        $apiary_user = Apiary::getUser($username);

        if (! $apiary_user->user->is_access_active) {
            return view('selfservice.unpaiddues');
        }

        if (0 === count($apiary_user->user->teams)) {
            return view('selfservice.noteams');
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
                [],
                $apiary_user->user->github_username
            );

            $github_membership = GitHub::getOrganizationMembership($apiary_user->user->github_username);

            if (null === $github_membership) {
                return view('selfservice.error');
            }
        }

        if ('pending' === $github_membership->state) {
            return redirect('https://github.com/orgs/'.config('github.organization').'/invitation');
        }

        if ('active' === $github_membership->state) {
            return view(
                'selfservice.alreadymember',
                [
                    'service' => 'GitHub',
                ]
            );
        }

        return view('selfservice.error');
    }

    /**
     * Sync the currently logged in user with SUMS.
     *
     * @param  Request  $request  The incoming request
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory
     */
    public function sums(Request $request)
    {
        $username = $request->user()->uid;

        $apiary_user = Apiary::getUser($username);

        if (! $apiary_user->user->is_access_active) {
            return view('selfservice.unpaiddues');
        }

        if (0 === count($apiary_user->user->teams)) {
            return view('selfservice.noteams');
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
            return view('selfservice.norecentattendance');
        }

        $response = SUMS::addUserToBillingGroup($username);

        if (SUMS::SUCCESS === $response) {
            UpdateExistsInSUMSFlag::dispatch($username);

            return view(
                'selfservice.success',
                [
                    'group_name_in_service' => 'group in SUMS',
                ]
            );
        }
        if (SUMS::MEMBER_EXISTS === $response) {
            UpdateExistsInSUMSFlag::dispatch($username);

            return view(
                'selfservice.alreadymember',
                [
                    'service' => 'SUMS',
                ]
            );
        }
        if (SUMS::USER_NOT_FOUND === $response) {
            return view('selfservice.needsumsaccount');
        }

        return view('selfservice.error');
    }

    /**
     * Resend an invitation to ClickUp for the currently logged in user.
     *
     * @param  Request  $request  The incoming request
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function clickup(Request $request)
    {
        $username = $request->user()->uid;

        $apiary_user = Apiary::getUser($username);

        if (! $apiary_user->user->is_access_active) {
            return view('selfservice.unpaiddues');
        }

        if (0 === count($apiary_user->user->teams)) {
            return view('selfservice.noteams');
        }

        if (null === $apiary_user->user->clickup_email) {
            return redirect('https://my.robojackets.org/profile');
        }

        $id_in_apiary_is_wrong = false;

        if (null !== $apiary_user->user->clickup_id) {
            $clickup_membership = ClickUp::getUserById($apiary_user->user->clickup_id);

            if (null === $clickup_membership) {
                $id_in_apiary_is_wrong = true;
            }
        }

        if (null === $apiary_user->user->clickup_id || $id_in_apiary_is_wrong) {
            $clickup_membership = ClickUp::addUser($apiary_user->user->clickup_email);
            UpdateClickUpAttributes::dispatch($username, $clickup_membership->user->id, $clickup_membership->invite);
            if (true === $clickup_membership->invite) {
                return view('selfservice.checkemailforclickup');
            }

            return view(
                'selfservice.alreadymember',
                [
                    'service' => 'ClickUp',
                ]
            );
        }

        $clickup_membership = ClickUp::getUserById($apiary_user->user->clickup_id);

        if (null === $clickup_membership) {
            // This should be unreachable, but just in case
            return view('selfservice.error');
        }

        if (false === $clickup_membership->memberInfo->invite) {
            if (true === $apiary_user->user->clickup_invite_pending) {
                UpdateClickUpInvitePendingFlag::dispatch($username, false);
            }

            return view(
                'selfservice.alreadymember',
                [
                    'service' => 'ClickUp',
                ]
            );
        }

        ClickUp::resendInvitationToUser($apiary_user->user->clickup_id);

        $clickup_membership = ClickUp::getUserById($apiary_user->user->clickup_id);

        if (null === $clickup_membership) {
            // This should be unreachable, but just in case
            return view('selfservice.error');
        }

        if (true === $clickup_membership->memberInfo->invite) {
            UpdateClickUpInvitePendingFlag::dispatch($username, true);
        }

        return view('selfservice.checkemailforclickup');
    }

    /**
     * Resend an invitation to library.io for the currently logged in user.
     *
     * @param  Request  $request  The incoming request
     * @return \Illuminate\View\View|\Illuminate\Contracts\View\Factory|\Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function autodesk(Request $request)
    {
        $username = $request->user()->uid;

        $apiary_user = Apiary::getUser($username);

        if (! $apiary_user->user->is_access_active) {
            return view('selfservice.unpaiddues');
        }

        if (0 === count($apiary_user->user->teams)) {
            return view('selfservice.noteams');
        }

        if (null === $apiary_user->user->autodesk_email) {
            return redirect('https://my.robojackets.org/profile');
        }

        $member = AutodeskLibrary::isMember($apiary_user->user->autodesk_email);
        // Will return false if invite is in any state but pending including canceled
        $pending = AutodeskLibrary::isInvitePending($apiary_user->user->autodesk_email);

        if (! $member) {
            // Always just resend an invite
            AutodeskLibrary::addUser($apiary_user->user->autodesk_email);
            $pending = true;

            if ($apiary_user->user->autodesk_invite_pending !== $pending) {
                UpdateAutodeskLibraryInvitePendingFlag::dispatch($username, $pending);
            }

            return view('selfservice.checkemailforautodesk');
        } else {
            // If the invite was accepted
            if ($apiary_user->user->autodesk_invite_pending !== $pending) {
                UpdateAutodeskLibraryInvitePendingFlag::dispatch($username, $pending);
            }

            return view(
                'selfservice.alreadymember',
                [
                    'service' => 'Autodesk',
                ]
            );
        }
    }
}
