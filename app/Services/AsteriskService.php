<?php

namespace App\Services;

use AGI_AsteriskManager;

class AsteriskService
{
    private string $server_ip;
    private string $username;
    private string $password;

    /**
     * Create a new class instance.
     */
    public function __construct(
        string $server_ip,
        string $username,
        string $password
    )
    {
        $this->setServerIp($server_ip);
        $this->setUsername($username);
        $this->setPassword($password);
    }

    public function setServerIp(string $server_ip){
        $this->server_ip=$server_ip;
    }

    public function setUsername(string $username){
        $this->username=$username;
    }

    public function setPassword(string $password){
        $this->password=$password;
    }

    public function originateCall(
        string $channel,
        string $exten,
        string $context,
        string $priority,
        string $application,
        string $data,
        int $timeout,
        string $caller_id,
        string $variables,
        string $account,
        string $async,
        string $action_id
    ){

        $manager=new AGI_AsteriskManager();

        if($manager->connect(
            $this->server_ip,
            $this->username,
            $this->password
        )){

            $originate=$manager->Originate(
                $channel,
                $exten,
                $context,
                $priority,
                $application,
                $data,
                $timeout,
                $caller_id,
                $variables,
                $account,
                $async,
                $action_id
            );

            return $originate;

            // $manager->disconnect();

        }else{
            return false;
        }
    }

    public function hangup(
        string $channel
    ){

        $manager=new AGI_AsteriskManager();

        if($manager->connect(
            $this->server_ip,
            $this->username,
            $this->password
        )){

            $response = $manager->Command('core show channels concise');
            $srt=$response['data'];
            $group_channels=explode("\nSIP/",$srt);

            foreach($group_channels as $group){
                $ch_exten=explode('-',$group)[0];
                $ch_sip=explode('!',$group)[0];
                $ch_state=explode('!',$group)[5];

                ($ch_exten==$channel | $ch_state==='BUSY' | $ch_state==='Congestion') ? $manager->Hangup($ch_sip) : "";
            }

            return [
                "status"=>200,
                "data"=>$group_channels,
                "channel"=>$channel
            ];
            
        }else{
            return false;
        }   
    }
}