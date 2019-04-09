<?php
namespace App\Soap;

/**
 * Created by PhpStorm.
 * User: Kristaps
 * Date: 1/15/2017
 * Time: 8:18 PM
 */
class Vault
{
    private $AdminService;
    public function __construct(String $server, String $username, String $password)
    {
        $auth = $this->makeSoapClient($server.'/AutodeskDM/Services/Filestore/v22/AuthService.svc?wsdl');

        // for static analyzers
        $response_headers = ['SecurityHeader' => new \stdClass];
        $response_headers['SecurityHeader']->Ticket = null;
        $response_headers['SecurityHeader']->UserId = null;

        $auth->__soapCall(
            "SignIn",
            array(array(
                "userName" => $username,
                "dataServer" => $server,
                "userPassword" => $password,
                "knowledgeVault" => config('vault.vault')
            )),
            null,
            null,
            $response_headers
        );
        $header = new \SoapHeader(
            "http://AutodeskDM/Services",
            "SecurityHeader",
            array(
                "Ticket" => $response_headers['SecurityHeader']->Ticket,
                "UserId" => $response_headers['SecurityHeader']->UserId
            )
        );
        $this->AdminService = $this->makeSoapClient(
            $server."/AutodeskDM/Services/v22/AdminService.svc?wsdl",
            $header
        );
    }
    private function makeSoapClient(String $wsdl, \SoapHeader $header = null)
    {
        $client = new \SoapClient($wsdl, array("trace" => 1, "sslmethod" => SOAP_SSL_METHOD_TLS, "location" => $wsdl));
        if ($header != null) {
            $client->__setSoapHeaders($header);
        }
        return $client;
    }
    public function updateUser(int $userid, String $uid, String $firstName, String $lastName, bool $has_access)
    {
        //$isActive= $has_access? 'true' : 'false';
        return $this->AdminService->UpdateUserInfo(
            array("userId" => $userid,
                  "userName"=> $uid,
                  "firstName" => $firstName,
                  "lastName" => $lastName,
                  "email" => $uid.'@gatech.edu',
                  "atype" => "Vault",
                  "isActive" => $has_access
            )
        );
    }
    public function getUserId(String $username)
    {
        $users = $this->AdminService->GetAllUsers()->GetAllUsersResult->User;
        if (!is_array($users)) {
            $users = array($users);
        }
        foreach ($users as $user) {
            if ($user->Name === $username) {
                return $user->Id;
            }
        }
        return -1;
    }
    public function getGroupsByName(Array $teams)
    {
        $groups = $this->AdminService->GetAllGroups()->GetAllGroupsResult->Group;
        $teamIds = [];
        foreach ($groups as $group) {
            foreach ($teams as $team) {
                if ($group->Name == $team) {
                    array_push($teamIds, $group->Id);
                }
            }
        }
        return $teamIds;
    }
    public function getUsersGroups(int $uid)
    {
        $groups = $this->AdminService->GetAllGroups()->GetAllGroupsResult->Group;
        $user_groups = [];
        foreach ($groups as $group) {
            $result = $this->AdminService->GetGroupInfoByGroupId(
                array(
                    'groupId'=>$group->Id
                )
            )->GetGroupInfoByGroupIdResult;
            if (property_exists($result, 'Users')) {
                $users= $result->Users;
                if (!is_array($users)) {
                    $users = array($users);
                }
                foreach ($users as $user) {
                    if (property_exists($user, 'CreateUserId') && $user->CreateUserId == $uid) {
                        array_push($user_groups, $group->Id);
                    }
                }
            }
        }
        return $user_groups;
    }
    public function addUserToGroups(int $uid, Array $gids)
    {
        foreach ($gids as $gid) {
            $this->AdminService->addUserToGroup(
                array(
                    "userId" => $uid,
                    "parentGroupId" => $gid
                )
            );
        }
    }
    public function removeUserFromGroups(int $uid, Array $gids)
    {
        foreach ($gids as $gid) {
            $this->AdminService->DeleteUserFromGroup(
                array(
                    "userId" => $uid,
                    "parentGroupId" => $gid
                )
            );
        }
    }
}
