<?php
/**
 * Author: Mark
 * Date: 8/19/15
 */

require_once("http_worker.php");
stream_wrapper_register("cgi", "CGIStream");

class MasterServer {
    public $listener;
    public $cache;

    public function __construct() {
        HttpWorker::$cache = new LRUCache(Config::$cache_size);
        # add parse-option() when totally finished+_+
    }

    public function run() {
        $this->init_listener();
        $this->pre_fork();
        pcntl_wait($status);
    }

    public function init_listener() {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($socket, SOL_SOCKET, SO_SNDBUF, Config::$buf_size);
        socket_bind($socket, "127.0.0.1", 12000);
        socket_listen($socket, 128);
        socket_set_nonblock($socket);
        $this->listener = $socket;
    }

    public function pre_fork() {
        $lock = sem_get(ftok(__FILE__, 'p'));
        foreach (range(1, Config::$pre_fork_num) as $child_id) {
            $pid = pcntl_fork();
            if ($pid > 0) {
                continue;
            } elseif ($pid == 0) {
                $this->child_run($child_id, $lock);
                exit;
            }
        }
    }

    public function child_run($child_id, $lock) {
        $socket = $this->listener;
        socket_set_nonblock($socket);
        $accept_watcher = new EvIo($socket, Ev::READ, function ($watcher, $revent) use ($child_id, $lock) {

//            if (!sem_acquire($lock, true)) return;
            @$client = socket_accept($watcher->data);

            if ($client === FALSE) return;

            socket_set_block($client);
//            sem_release($lock);


            $evio = new EvIo($client, Ev::READ, function ($watcher, $revent) use ($child_id) {
                $result = HttpWorker::process($watcher->data[0]);
                $route_result = $result[1];
                if (trim($route_result) && Config::$print_result) {
                    echo "Worker " . $child_id . " successfully processed " . $route_result . "\n";
                }
                if (!$result[0]) {
                    // check if request asks for keep-alive
                    EvIoManager::remove($watcher);
                }
                return;
            }, array(0 => $client));

            EvIoManager::add($evio);
        }, $socket);

        Ev::run();
    }
}

class EvIoManager {
    private static $_i = 0;
    private static $_watchers = array();

    public static function add(EvIo $watcher) {
        $watcher->data[1] = self::$_i;
        self::$_watchers[self::$_i] = $watcher;
        self::$_i++;
        return self::$_i;
    }

    public static function remove(EvIo $watcher) {
        $watcher->stop();
        if (is_null($watcher) || !isset($watcher->data[1])) {
            return;
        }

        $idx = $watcher->data[1];
        if (isset(self::$_watchers[$idx])
            && ($watcher == self::$_watchers[$idx])
        ) {
            self::$_watchers[$idx] = null;
            unset(self::$_watchers[$idx]);
            return $idx;
        }

        return -1;
    }

    public static function count() {
        return count(self::$_watchers);
    }
}

if (Config::$docroot == '') {
    Config::$docroot = __DIR__ . "/sites";
}

$a = new MasterServer();
$a->run();


