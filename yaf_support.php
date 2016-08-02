<?php

/**
 * Author: Mark
 * Date: 16/8/2
 */
class YAFSupport {
    public static function check($path, $request) {
        if ($htaccess_path = YAFSupport::check_htaccess($path)) {
            if ($array = YAFSupport::parse_htaccess($htaccess_path))
                if ($array['RewriteCond'][0] == "%{REQUEST_FILENAME}" && $array['RewriteCond'][1] == "!-f")
                    return YAFSupport::yaf_route($request, $htaccess_path, $array['RewriteRule']);
        }
        return -1;
    }

    public static function check_htaccess($path) {
        $array = explode("/", $path);
        $check = array();
        while (array_pop($array) != NULL) {
            $check[] = implode("/", $array);
        }
        foreach ($check as $key => $uri) {
            if (file_exists(Config::$docroot . $uri . "/.htaccess"))
                return $uri;
        }
        return false;
    }

    public static function parse_htaccess($path) {
        $full_path = Config::$docroot . $path . "/.htaccess";
        $file = fopen($full_path, 'r');

        $array = array();
        while (!feof($file)) {
            $line = fgets($file);
            $line = trim($line);
            if ($line != NULL) {
                $tokens = explode(" ", $line);
                $key = array_shift($tokens);
                $array[$key] = $tokens;
            }
        }
        if (strtolower($array['RewriteEngine'][0]) == 'on') {
            array_shift($array);
            return $array;
        } else
            return false;
    }

    public static function yaf_route($request, $htaccess_path, $rule) {
        $path = $request->path;

        $full_path = Config::$docroot . $path;
        $rewrite_path = Config::$docroot . $htaccess_path;
        $to_rewrite = substr($full_path, strlen($rewrite_path));

        if (file_exists($full_path)) {
            return HttpWorker::no_htaccess($request);
        } else {
            $to_rewrite = preg_replace("/{$rule[0]}/", $rule[1], $to_rewrite, 1);
            $path = $htaccess_path . "/" . $to_rewrite;
            return HttpWorker::get_route_result(200, $path);
        }
    }
}