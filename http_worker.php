<?php
/**
 * Author: Mark
 * Date: 8/12/15
 */

require_once("config.php");
require_once("modules/http_request.php");
require_once("modules/http_response.php");
require_once("modules/cgi_stream.php");
require_once("modules/router.php");
require_once("modules/yaf_support.php");
require_once("modules/lru_cache.php");


class HttpWorker {
    static $cache = null;

    public static function parse_request($client) {
        $request = new Http_Request($client);
        $wrong_request = $request->process();
        if ($wrong_request)
            return false;
        else
            return $request;
    }

    // only for simple GET method, range is not supported
    public static function get_static_response($request, $route_result) {
        $path = $route_result['uri'];
        if (is_file($path)) {
            $file_size = filesize($path);
            $headers = array(
                'Server' => 'PHPServer',
                'Content-Type' => self::get_mime_type($path),
                'Content-Length' => $file_size,
                'Connection' => 'keep-alive'
            );

            $file = fopen($path, 'rb');
            return new Http_Response("200", $file, $headers);
        } elseif (is_dir($path)) {
            return new Http_Response("403", "Directory listing is temporarily not supported");
        } else {
            return new Http_Response("404", "404 File Not Found");
        }
    }

    // process PHP files for this server, GET & POST is supported
    public static function get_php_response($request, $route_result) {
        # to be modified
        $cgi_env = array(
            'QUERY_STRING' => $request->query,
            'REQUEST_METHOD' => $request->method,
            'REQUEST_URI' => $request->uri,
            'REDIRECT_STATUS' => 200,
            'SCRIPT_FILENAME' => $route_result['uri'],
            'SCRIPT_NAME' => $route_result['path'],
            'SERVER_NAME' => $request->headers['Host'],
            'SERVER_PORT' => 12000,
            'SERVER_PROTOCOL' => 'HTTP/1.1',
            'SERVER_SOFTWARE' => 'PHPServer/0.2',
        );

        if ($request->has_content == 1) {
            $cgi_env['CONTENT_TYPE'] = $request->headers['Content-Type'];
            $cgi_env['CONTENT_LENGTH'] = $request->headers['Content-Length'];
        }

        foreach ($request->headers as $name => $values) {
            $name = str_replace('-', '_', $name);
            $name = strtoupper($name);
            $cgi_env["HTTP_$name"] = $values;
        }

        fseek($request->content_stream, 0);

        $context = stream_context_create(array(
            'cgi' => array(
                'env' => array_merge($_ENV, $cgi_env),
                'stdin' => $request->content_stream,
            )
        ));
        $cgi_stream = fopen("cgi://php-cgi", 'rb', false, $context);
        $buffer = '';

        while ($content = fread($cgi_stream, 4096)) {
            $buffer = $buffer . $content;
        };

        $start = strpos($buffer, 'Content-type:') + 13;
        $length = strpos($buffer, '\r\n\r\n') - $start;
        $headers = array(
            'Content-Type' => substr($buffer, $start, $length)
        );

        return new Http_Response("200", $buffer, $headers);
    }

    public static function get_pre_response($status, $content, $headers, $status_msg) {
        return new Http_Response($status, $content, $headers, $status_msg);
    }


    public static function get_mime_type($path) {
        $path_info = pathinfo($path);
        $extension = strtolower($path_info['extension']);
        if (isset(Config::$mime_types[$extension])) {
            return Config::$mime_types[$extension];
        } else {
            return "application/octet-stream";
        }
    }

    public static function get_special_result($request, $route_result) {
        $result = '';
        switch ($route_result['status']) {
            case 307:
                $headers['Location'] = $route_result['path'];
                $response = HttpWorker::get_pre_response($route_result['status'], '', $headers, 'Moved Temporarily');
                $result = $response->render();
                break;
            default:
                break;
        }
        return $result;
    }

    public static function no_cache($request, $route_result) {
        switch ($route_result['function']) {
            case 'php':
                return HttpWorker::get_php_response($request, $route_result);
            case 'static':
                return HttpWorker::get_static_response($request, $route_result);
            default:
                return false;
        }
    }

    public static function keep_alive($request) {
        if (isset($request->headers['Connection']) && $request->headers['Connection'] == 'keep-alive')
            return true;
        else
            return false;
    }

    public static function process($client) {
        $request = HttpWorker::parse_request($client);

        if (!$request) {
            return false;
        }

        $route_result = Router::route($request);

        if ($route_result['status'] != 200) {
            self::static_output($client, HttpWorker::get_special_result($request, $route_result));
        } else {
            $response = HttpWorker::no_cache($request, $route_result);
            $header = $response->render();
            socket_write($client, $header);

            if ($response->output_type) {
                self::stream_output($client, $response->stream);
            } else {
                self::static_output($client, $response->content);
            }
        }

        return [HttpWorker::keep_alive($request), $route_result['uri']];
    }

    public static function stream_output($client, $resource) {
        while ($resource != NULL && !feof($resource)) {
            $buf = fread($resource, 4096);
            self::full_socket_write($client, $buf);
        }
        @fclose($resource);
    }

    public static function static_output($client, $content) {
        $total_length = strlen($content);
        $bytes_written = 0;
        $buf_size = Config::$buf_size;
        while ($total_length - $bytes_written > $buf_size) {
            self::full_socket_write($client, substr($content, $bytes_written, $buf_size));
            $bytes_written += $buf_size;
        }
        self::full_socket_write($client, substr($content, $bytes_written));
    }

    public static function full_socket_write($client, $buf) {
        $length = strlen($buf);
        while (true) {
            $sent = socket_write($client, $buf);
            if ($sent === false) {
                break;
            }
            // Check if the entire message has been sented
            if ($sent < $length) {
                // If not sent the entire message.
                // Get the part of the message that has not yet been sented as message
                $buf = substr($buf, $sent);

                // Get the length of the not sented part
                $length -= $sent;
            } else {
                break;
            }
        }
    }
}

