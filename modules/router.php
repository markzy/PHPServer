<?php

/**
 * Author: Mark
 * Date: 16/8/3
 */
class Router {

    public static function fallback($path) {
        if (!preg_match('/\/$/', $path))
            $path = $path . "/";

        if (file_exists(Config::$docroot . $path . "index.php")) {
            return $path . "index.php";
        } elseif (file_exists(Config::$docroot . $path . "index.html")) {
            return $path . "index.html";
        } else {
            return false;
        }
    }

    public static function route($request) {
        $path = $request->path;

        if (Config::$yaf) {
            $check_yaf = YAFSupport::check($path, $request);
            if ($check_yaf != -1) {
                return $check_yaf;
            }
        }

        return self::no_htaccess($request);
    }

    public static function no_htaccess($request) {
        $path = $request->path;

        $full_path = Config::$docroot . $path;

        if (is_dir($full_path)) {
            $result = self::fallback($path);
            if ($result) {
                if (!preg_match('/\/$/', $full_path)) {
                    $route_result = self::get_route_result(307, $path . "/", "pre");
                } else {
                    $route_result = self::get_route_result(200, $result);
                }
            } else {
                $route_result = self::get_route_result(200, $result);
            }
        } else {
            $route_result = self::get_route_result(200, $path);
        }
        return $route_result;
    }

    public static function get_route_result($status, $path, $function = '') {
        $result['status'] = $status;
        $result['uri'] = Config::$docroot . $path;
        $result['path'] = $path;
        if ($function == '') {
            if (preg_match('/\.php$/', $path)) {
                $result['function'] = 'php';
            } else {
                $result['function'] = 'static';
            }
        } else {
            $result['function'] = $function;
        }
        return $result;
    }
}