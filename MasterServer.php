<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 8/19/15
 * Time: 10:34 AM
 */

require_once("HttpWorker.php");

stream_wrapper_register("cgi", "CGIStream");

class MasterServer {
    public $listener;
    public $pre_fork_num = 3;

    public $continue_work = 1; // the flag indicate whether the child process should continue;

    public function __construct() {
        # add parse-option feature when possible +_+
    }

    public function run() {
        $this->init_listener();
        $this->pre_fork();
        pcntl_wait($status);
    }


    public function init_listener() {
        $socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);
        socket_set_option($socket,SOL_SOCKET,SO_REUSEADDR,1);
        socket_bind($socket,"127.0.0.1",12000);
        socket_listen($socket,10);
        $this->listener = $socket;
    }

    public function pre_fork() {
        foreach(range(1,$this->pre_fork_num) as $key) {
            $pid = pcntl_fork();
            if($pid > 0) {
                // parent process
                continue;
            } elseif($pid == 0) {
                //child process
                $this->child_run();
                exit;
            }
        }
    }

    public function child_run() {
        $socket = $this->listener;
        while($this->continue_work) {
            $client = socket_accept($socket);
            $server = new HttpWorker($client);
            $server->run();
        }
    }


}


$a = new MasterServer();
$a->run();


