<?php declare(strict_types = 1);

namespace App\Http\Controllers;

use App\Service\Apiary;
use App\Service\GitHub;
use App\Service\SUMS;

class SelfServiceController extends Controller
{
    public function github(Request $request)
    {
        $username = $request->user()->uid;

        $apiary_user = Apiary::getUser($username);

        if (! $apiary_user->user->is_access_active) {
            return view(
                'selfservice.layout',
                [
                    'title' => 'Unauthorized',
                    'message' => 'You have not paid dues, so you do not have access to GitHub right now. If you need a '
                    . 'temporary exception, please ask in <a href="https://slack.com/'
                    . 'app_redirect?team=T033JPZLT&channel=C29Q3D8K0">#it-helpdesk</a> on Slack.'
                ]
            );
        }

        if (0 === count($apiary_user->user->teams)) {
            return view(
                'selfservice.layout',
                [
                    'title' => 'Unauthorized',
                    'message' => 'You are not a member of any teams yet. <a href="https://my.robojackets.org/teams">'
                    . 'Join a team in MyRoboJackets</a>, then try again.'
                ]
            );
        }

        if (null === $apiary_user->user->github_username) {
            return view(
                'selfservice.layout',
                [
                    'title' => 'Link your GitHub account',
                    'message' => 'You have not linked your GitHub account yet. <a href="https://my.robojackets.org/'
                    . 'github">Click here</a> to get started.'
                ]
            );
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
                return view(
                    'selfservice.layout',
                    [
                        'title' => 'Something went wrong',
                        'message' => 'Please post in <a href="https://slack.com/'
                        . 'app_redirect?team=T033JPZLT&channel=C29Q3D8K0">#it-helpdesk</a> in Slack so an admin can '
                        . 'help you.'
                    ]
                );
            }
        }

        if ('pending' === $github_membership->state) {
            return redirect('https://github.com/orgs/' . config('github.organization') . '/invitation');
        }

        if ('active' === $github_membership->state) {
            return view(
                'selfservice.layout',
                [
                    'title' => 'You are already a member',
                    'message' => 'You already have access to GitHub.'
                ]
            );
        }

        return view(
            'selfservice.layout',
            [
                'title' => 'Something went wrong',
                'message' => 'Please post in <a href="https://slack.com/'
                . 'app_redirect?team=T033JPZLT&channel=C29Q3D8K0">#it-helpdesk</a> in Slack so an admin can '
                . 'help you.'
            ]
        );
    }

    public function sums(Request $request)
    {
        $username = $request->user()->uid;

        $apiary_user = Apiary::getUser($username);

        if (! $apiary_user->user->is_access_active) {
            return view(
                'selfservice.layout',
                [
                    'title' => 'Unauthorized',
                    'message' => 'You have not paid dues, so you do not have access to GitHub right now. If you need a '
                    . 'temporary exception, please ask in <a href="https://slack.com/'
                    . 'app_redirect?team=T033JPZLT&channel=C29Q3D8K0">#it-helpdesk</a> on Slack.'
                ]
            );
        }

        if (0 === count($apiary_user->user->teams)) {
            return view(
                'selfservice.layout',
                [
                    'title' => 'Unauthorized',
                    'message' => 'You are not a member of any teams yet. <a href="https://my.robojackets.org/teams">'
                    . 'Join a team in MyRoboJackets</a>, then try again.'
                ]
            );
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
            return view(
                'selfservice.layout',
                [
                    'title' => 'Unauthorized',
                    'message' => 'You have not swiped in at the shop recently, so you do not have access to SUMS right '
                    . 'now.'
                ]
            );
        }

        $response = SUMS::addUser($username);

        if (SUMS::SUCCESS === $response) {
            return view(
                'selfservice.layout',
                [
                    'title' => 'Success!',
                    'message' => 'You have been added as a member of the RoboJackets group in SUMS.'
                ]
            );
        } elseif (SUMS::MEMBER_EXISTS === $response) {
            return view(
                'selfservice.layout',
                [
                    'title' => 'You are already a member',
                    'message' => 'You already have access to SUMS.'
                ]
            );
        } elseif (SUMS::USER_NOT_FOUND === $response) {
            return view(
                'selfservice.layout',
                [
                    'title' => 'You don\'t have a SUMS account',
                    'message' => 'You need to log in once to create an account before you can be added to the '
                    . 'RoboJackets group. <a href="https://sums.gatech.edu/Login2.aspx?LP=Users">Click here</a> to log '
                    . 'in, then try again.'
                ]
            );
        }

        return view(
            'selfservice.layout',
            [
                'title' => 'Something went wrong',
                'message' => 'Please post in <a href="https://slack.com/'
                . 'app_redirect?team=T033JPZLT&channel=C29Q3D8K0">#it-helpdesk</a> in Slack so an admin can '
                . 'help you.'
            ]
        );
    }
}
