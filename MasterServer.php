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
    public $pre_fork_num = 2;

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
        socket_set_option($socket,SOL_SOCKET,SO_SNDBUF,1048576);
        socket_bind($socket,"127.0.0.1",12000);
        socket_listen($socket,10);
        socket_set_nonblock($socket);
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
            if($client>0) {
                while ($this->process($client)) ;
                socket_close($client);
            }else
                usleep(200000);
        }
    }

    # old codes
    public function process($client){
        $cache = $this->cache;
        $request = HttpWorker::parse_request($client);
        if(!$request) {
            return false;
        }

        $route_result = HttpWorker::route($request);

        $result = '';

        if($route_result['status'] != 200){
            $result = $this->get_special_result($request,$route_result);
        }
        else {
            $path = $route_result['uri'];
            $cache_node = $cache->get($path);
            if($route_result['function'] == 'php'){
                $response = $this->no_cache($request, $route_result);
                $result = $response->render();
            }
            elseif($cache_node == null ){
                $response = $this->no_cache($request, $route_result);
                $result = $response->render();
                $cache_node = new Node($route_result['uri'],$result,filectime($path));
                $cache->put($cache_node);
            } elseif ($cache_node->isoutofdate()) {
                $response = $this->no_cache($request, $path);
                $result = $response->render();
                $cache_node->setData($result);
                $cache->put($cache_node);
            } else {
                $result = $cache_node->getData();
            }
        }

        socket_write($client,$result,strlen($result));
        return $this->keep_alive($request);
    }

    public function get_special_result($request,$route_result){
        $result = '';
        switch($route_result['status']){
            case 307:
                $headers['Location'] = $route_result['path'];
                $response = HttpWorker::get_pre_response($route_result['status'],'',$headers,'Moved Temporarily');
                $result = $response->render();
                break;
            default:
                break;
        }
        return $result;
    }

    public function no_cache($request,$route_result){
        switch($route_result['function']){
            case 'php':
                return HttpWorker::get_php_response($request,$route_result);
            case 'static':
                return HttpWorker::get_static_response($request,$route_result);
            default:
                return false;
        }
    }

    public function keep_alive($request){
        if(isset($request->headers['Connection']) && $request->headers['Connection']=='keep-alive')
            return true;
        else
            return false;
    }


}

if(Config::$docroot == ''){
    Config::$docroot = __DIR__ . "/sites";
}

$a = new MasterServer();
$a->run();


