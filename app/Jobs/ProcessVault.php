<?php declare(strict_types = 1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use RoboJackets\Vault\Client;

class ProcessVault implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The user's username
     *
     * @var string
     */
    protected $uid;

    /**
     * Whether the user should have access
     *
     * @var bool
     */
    protected $has_access;

    /**
     * The user's teams
     *
     * @var array<string>
     */
    protected $teams;

    /**
     * The user's first name
     *
     * @var string
     */
    protected $first_name;

    /**
     * The user's last name
     *
     * @var string
     */
    protected $last_name;
    /**
     * Create a new job instance.
     */
    public function __construct(Request $request)
    {
        $this->uid = $request->uid;
        $this->has_access = $request->is_access_active;
        $this->teams = $request->teams;
        $this->first_name = $request->first_name;
        $this->last_name = $request->last_name;
    }
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        if ($this->uid === config('vault.username')) {
            return;
        }
        $vault = Client::makeWithCredentials(
            config('vault.host'),
            config('vault.username'),
            config('vault.password')
        );
        $userId = $vault->getUserIdByUsername($this->uid);
        if ($userId <= 0) {
            return;
        }

        $vault->updateUserInfo($userId, $this->has_access, $this->uid, '', $this->first_name, $this->last_name);
        if (true !== $this->has_access) {
            return;
        }
        //update teams
        $groups = $vault->getAllGroups();
        $currentGroups = [];
        $teamIds = [];
        foreach ($groups as $group) {
            $users = [];
            foreach ($users as $user) {  //get the groupIds the user currently belongs to
                if (!property_exists($user, 'CreateUserId') || $user->CreateUserId !== $this->uid) {
                    continue;
                }

                array_push($currentGroups, $group->Id);
            }
            foreach ($this->teams as $team) { //get the groupIds of the teams user should belong to
                if ($group->Name !== $team) {
                    continue;
                }

                array_push($teamIds, $group->Id);
            }
        }
        //diff the two groups
        $toAdd = array_diff($teamIds, $currentGroups);
        $toRemove = array_diff($currentGroups, $teamIds);
        foreach ($toAdd as $gid) {
            $vault->addUserToGroup($userId, $gid);
        }
        foreach ($toRemove as $gid) {
            $vault->deleteUserFromGroup($userId, $gid);
        }
    }
}
