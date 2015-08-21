<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 8/19/15
 * Time: 10:34 AM
 */

require_once("HttpWorker.php");
require_once("LRUCache.php");

stream_wrapper_register("cgi", "CGIStream");


class MasterServer {
    public $listener;
    public $pre_fork_num = 3;

    public $continue_work = 1; // the flag indicate whether the child process should continue;

    public $cache_size = 10;
    public $cache;
    public $cache_strategy = 'LRU';

    public function __construct() {
        $this->cache = new LRUCache($this->cache_size);
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
//                 parent process
                continue;
            } elseif($pid == 0) {
//                child process
                $this->child_run();
                exit;
            }
        }
    }

    public function child_run() {
        $socket = $this->listener;
        while($this->continue_work) {
            $client = socket_accept($socket);
            while($this->process($client));
            socket_close($client);
        }
    }

    public function process($client){
        $cache = $this->cache;
        $request = HttpWorker::parse_request($client);
        if(!$request) {
            return false;
        }
        $path = HttpWorker::route($request);
        $cache_node = $cache->get($path);
        if($cache_node == null ){
//            echo "not valid\n";
            $response = $this->no_cache($request,$path);
            $result = $response->render();
            socket_write($client,$result);
            $cache_node = new Node($path,$result,filectime($path));
            $cache->put($cache_node);
            return $this->keep_alive($request);
        }
        elseif($cache_node->isoutofdate()) {
//            echo "out of date\n";
            $response = $this->no_cache($request,$path);
            $result = $response->render();
            socket_write($client,$result);
            //store in Cache
            $cache_node->setData($result);
            $cache->put($cache_node);
            return $this->keep_alive($request);
        }
        else {
            // valid cache
//            echo "valid\n";
            $result = $cache_node->getData();
            socket_write($client,$result);
            return $this->keep_alive($request);
        }
    }

    public function no_cache($request,$path){
        if(preg_match('#\.php$#',$path))
            $response = HttpWorker::get_php_response($request,$path);
        else
            $response = HttpWorker::get_static_response($request,$path);

        return $response;
    }

    public function keep_alive($request){
        if(isset($request->headers['Connection']) && $request->headers['Connection']=='keep-alive')
            return true;
        else
            return false;
    }


}


$a = new MasterServer();
$a->run();


