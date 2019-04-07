<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Soap\Vault;

class ProcessVault implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    protected $uid;
    protected $has_access;
    protected $teams;
    protected $first_name;
    protected $last_name;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Request $request)
    {
        $this->uid= $request->uid;
        $this->has_access= $request->is_access_active;
        $this->teams= $request->teams;
        $this->first_name= $request->first_name;
        $this->last_name= $request->last_name;
    }
    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $vault = new Vault(config('vault.host'), config('vault.username'), config('vault.password'));
        $id = $vault->getUserId($this->uid);
        if ($id > 0) {
            $response = $vault->updateUser($id, $this->uid, $this->first_name, $this->last_name, $this->has_access);
            if ($this->has_access == true) { //update teams
                $teamIds = $vault->getGroupsByName($this->teams);
                $currentGroups=$vault->getUsersGroups($id);
                //diff the two groups
                $toAdd=array_diff($teamIds, $currentGroups);
                $toRemove=array_diff($currentGroups, $teamIds);
                $vault->addUserToGroups($id, $toAdd);
                $vault->removeUserFromGroups($id, $toRemove);
            }
        }
    }
}
