<?php

/**
 * Author: Mark
 * Date: 8/12/15
 */
class Http_Response {

    public $status;                 // HTTP status code
    public $status_msg;             // HTTP status message
    public $headers;                // associative array of HTTP headers (name => list of values)

    public $content = '';           // response body, as string (optional)
    public $stream;                 // response as stream
    public $output_type;

    function __construct($status = 200, $content = '', $headers = null, $status_msg = null) {
        $this->status = $status;
        $this->status_msg = $status_msg;

        if (is_resource($content)) {
            $this->stream = $content;
            $this->output_type = 1;
        } else {
            $this->content = $content;
            $this->output_type = 0;
        }
        $this->headers = $headers ?: array();
    }

    static function render_status($status, $status_msg = null) {
        if (empty($status_msg)) {
            $status_msg = static::$status_messages[$status];
        }
        return "HTTP/1.1 $status $status_msg\r\n";
    }

    static function render_headers($headers) {
        //buffer
        ob_start();
        foreach ($headers as $name => $values) {
            if (is_array($values)) {
                foreach ($values as $value) {
                    echo "$name: $value\r\n";
                }
            } else {
                echo "$name: $values\r\n";
            }
        }
        echo "\r\n";
        //clean buffer
        return ob_get_clean();
    }

    function render() {
        $headers = $this->headers;

        if (!isset($headers['Content-Length'])) {
            $headers['Content-Length'] = array($this->get_content_length());
        }

        return static::render_status($this->status, $this->status_msg) .
        static::render_headers($headers);
    }

    function get_content_length() {
        return strlen($this->content);
    }

    static $status_messages = array(
        100 => "Continue",
        101 => "Switching Protocols",
        200 => "OK",
        201 => "Created",
        202 => "Accepted",
        203 => "Non-Authoritative Information",
        204 => "No Content",
        205 => "Reset Content",
        206 => "Partial Content",
        300 => "Multiple Choices",
        301 => "Moved Permanently",
        302 => "Found",
        303 => "See Other",
        304 => "Not Modified",
        305 => "Use Proxy",
        307 => "Temporary Redirect",
        400 => "Bad Request",
        401 => "Unauthorized",
        402 => "Payment Required",
        403 => "Forbidden",
        404 => "Not Found",
        405 => "Method Not Allowed",
        406 => "Not Acceptable",
        407 => "Proxy Authentication Required",
        408 => "Request Timeout",
        409 => "Conflict",
        410 => "Gone",
        411 => "Length Required",
        412 => "Precondition Failed",
        413 => "Request Entity Too Large",
        414 => "Request-URI Too Long",
        415 => "Unsupported Media Type",
        416 => "Requested Range Not Satisfiable",
        417 => "Expectation Failed",
        500 => "Internal Server Error",
        501 => "Not Implemented",
        502 => "Bad Gateway",
        503 => "Service Unavailable",
        504 => "Gateway Timeout",
        505 => "HTTP Version Not Supported",
    );
}
