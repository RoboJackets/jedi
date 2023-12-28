<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\SyncGitHub;
use App\Jobs\UpdateClickUpAttributes;
use App\Jobs\UpdateClickUpInvitePendingFlag;
use App\Jobs\UpdateExistsInSUMSFlag;
use App\Services\Apiary;
use App\Services\ClickUp;
use App\Services\GitHub;
use App\Services\SUMS;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SelfServiceController extends Controller
{
    /**
     * Sync the currently logged in user with GitHub.
     */
    public function github(Request $request)
    {
        $username = $request->user()->username;

        $apiary_user = Apiary::getUser($username);

        if (! $apiary_user->user->is_access_active) {
            return view('selfservice.unpaiddues');
        }

        if (count($apiary_user->user->teams) === 0) {
            return view('selfservice.noteams');
        }

        if ($apiary_user->user->github_username === null) {
            return redirect(config('apiary.server').'/github');
        }

        $github_membership = GitHub::getOrganizationMembership($apiary_user->user->github_username);

        if ($github_membership === null) {
            SyncGitHub::dispatchSync(
                $username,
                true,
                array_map(
                    static fn (object $team): string => $team->name,
                    $apiary_user->user->teams
                ),
                [],
                $apiary_user->user->github_username
            );

            $github_membership = GitHub::getOrganizationMembership($apiary_user->user->github_username);

            if ($github_membership === null) {
                return view('selfservice.error');
            }
        }

        if ($github_membership->state === 'pending') {
            return redirect('https://github.com/orgs/'.config('github.organization').'/invitation');
        }

        if ($github_membership->state === 'active') {
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
     */
    public function sums(Request $request)
    {
        $username = $request->user()->username;

        $apiary_user = Apiary::getUser($username);

        if (! $apiary_user->user->is_access_active) {
            return view('selfservice.unpaiddues');
        }

        if (count($apiary_user->user->teams) === 0) {
            return view('selfservice.noteams');
        }

        $recent_attendance = array_filter(
            $apiary_user->user->attendance,
            static fn (object $attendance): bool => $attendance->created_at > new Carbon(
                // @phan-suppress-next-line PhanPartialTypeMismatchArgument
                config('sums.attendance_timeout_limit'),
                'America/New_York'
            )
        );

        if (count($recent_attendance) === 0) {
            return view('selfservice.norecentattendance');
        }

        $response = SUMS::addUserToBillingGroup($username);

        if ($response === SUMS::SUCCESS) {
            UpdateExistsInSUMSFlag::dispatch($username);

            return view(
                'selfservice.success',
                [
                    'group_name_in_service' => 'group in SUMS',
                ]
            );
        }
        if ($response === SUMS::MEMBER_EXISTS) {
            UpdateExistsInSUMSFlag::dispatch($username);

            return view(
                'selfservice.alreadymember',
                [
                    'service' => 'SUMS',
                ]
            );
        }
        if ($response === SUMS::USER_NOT_FOUND) {
            return view('selfservice.needsumsaccount');
        }

        return view('selfservice.error');
    }

    /**
     * Resend an invitation to ClickUp for the currently logged in user.
     */
    public function clickup(Request $request)
    {
        $username = $request->user()->username;

        $apiary_user = Apiary::getUser($username);

        if (! $apiary_user->user->is_access_active) {
            return view('selfservice.unpaiddues');
        }

        if (count($apiary_user->user->teams) === 0) {
            return view('selfservice.noteams');
        }

        if ($apiary_user->user->clickup_email === null) {
            return redirect(config('apiary.server').'/profile');
        }

        $id_in_apiary_is_wrong = false;

        // if ($apiary_user->user->clickup_id !== null) {
        //     $clickup_membership = ClickUp::getUserById($apiary_user->user->clickup_id);

        //     if ($clickup_membership === null) {
        //         $id_in_apiary_is_wrong = true;
        //     }
        // }

        if ($apiary_user->user->clickup_id === null || $id_in_apiary_is_wrong) {
            $clickup_membership = ClickUp::addUser($apiary_user->user->clickup_email);
            UpdateClickUpAttributes::dispatch($username, $clickup_membership->user->id, $clickup_membership->invite);
            if ($clickup_membership->invite === true) {
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

        if ($clickup_membership === null) {
            // This should be unreachable, but just in case
            return view('selfservice.error');
        }

        if ($clickup_membership->memberInfo->invite === false) {
            if ($apiary_user->user->clickup_invite_pending === true) {
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

        if ($clickup_membership === null) {
            // This should be unreachable, but just in case
            return view('selfservice.error');
        }

        if ($clickup_membership->memberInfo->invite === true) {
            UpdateClickUpInvitePendingFlag::dispatch($username, true);
        }

        return view('selfservice.checkemailforclickup');
    }
}
