<?php
/**
 * Author: Mark
 * Date: 8/12/15
 */
// define some status for httprequest
define("READ_HEADER", 0);
define("READ_CONTENT", 1);
define("READ_COMPLETE", 2);

class Http_Request {
    public $method;
    public $uri;
    public $path;
    public $query;

    public $has_content = 0;
    public $content = '';
    public $content_stream;

    public $data;
    public $headers = array();

    public $socket;

    public function __construct($socket) {
        $this->content_stream = fopen("data://text/plain,", "r+b");
        $this->socket = $socket;
    }

    public function get_data() {
        $ret = socket_recv($this->socket, $buf, 1024, MSG_DONTWAIT);
        $this->data = $buf;
        return strlen($this->data);
    }

    public function read_data() {
        $data = trim($this->data);

        // explode HTTP Request into Headers and Data
        $request = $this->ex_trim($data, "\r\n\r\n");

        // explode Headers
        $header = $this->ex_trim($request[0], "\r\n");

        //check if there is Content
        if (isset($request[1])) {
            $this->content = $request[1];
            fwrite($this->content_stream, $this->content);
            $this->has_content = 1;
        } else {
            $this->content = '';
            fwrite($this->content_stream, $this->content);
            $this->has_content = 0;
        }

        // the first request line
        $request_line = $header[0];

        // parse the first request line(method, uri and HTTP Version)
        $req_arr = $this->ex_trim($request_line, " ");
        $this->method = $req_arr[0];
        $this->uri = $req_arr[1];
        $this->parse_uri();

        // format headers into associative array
        foreach ($header as $key => $value) {
            if ($key > 0) {
                $parsed_req = $this->ex_trim($value, ":");
                $this->headers[$parsed_req[0]] = $parsed_req[1];
            }
        }
    }

    //explode the string and trim all blankspace
    public function ex_trim($data, $delimeter) {
        $data = explode($delimeter, $data);
        $newdata = array();
        foreach ($data as $key => $value) {
            $newvalue = trim($value);
            if ($newvalue != "") {
                $newdata[] = $newvalue;
            }
        }
        return (array)$newdata;
    }

    public function parse_uri() {
        // this is irrelevant, just comleting it for parse_url function
        $url = "http://localhost" . $this->uri;
        $parsed_url = parse_url($url);
        $this->path = $parsed_url['path'];
        @$this->query = $parsed_url['query'];
    }

    public function process() {
        $datalength = $this->get_data();
        if ($datalength > 5) {
            $this->read_data();
            return 0;
        }
        return 1;
    }

}
