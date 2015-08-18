<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 8/12/15
 * Time: 2:32 PM
 */
    // define some status for httprequest
define("READ_HEADER",0);
define("READ_CONTENT",1);
define("READ_COMPLETE",2);

class Http_Request{
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

    public function __construct($socket){
        $this->content_stream = fopen("data://text/plain,","r+b");
        $this->socket = $socket;
    }

    public function get_data(){
        $this->data = socket_read($this->socket,1024);
        return strlen($this->data);
    }

    public function read_data(){
        $data = trim($this->data);
        $request = $this->ex_trim($data,"\r\n\r\n");
        $header = $this->ex_trim($request[0],"\r\n");
        if(isset($request[1])){
            $this->content = $request[1];
            fwrite($this->content_stream,$this->content);
            $this->has_content = 1;
        }
        else {
            $this->content = '';
            fwrite($this->content_stream,$this->content);
            $this->has_content = 0;
        }
        $request_line = $header[0];

        // parse the first request line
        $req_arr = $this->ex_trim($request_line," ");
        $this->method = $req_arr[0];
        $this->uri = $req_arr[1];
        $this->parse_uri();

        foreach($header as $key=>$value){
            if($key>0) {
                $parsed_req = $this->ex_trim($value, ":");
                $this->headers[$parsed_req[0]] = $parsed_req[1];
            }
        }
    }

    //explode the string and trim all blankspace
    public function ex_trim($data,$delimeter){
        $data = explode($delimeter,$data);
        $newdata = array();
        foreach($data as $key => $value) {
            $newvalue = trim($value);
            if ($newvalue != "") {
                $newdata[] = $newvalue;
            }
        }
        return (array)$newdata;
    }

    public function parse_uri(){
        $url = "http://localhost".$this->uri;
        $parsed_url = parse_url($url);
        $this->path = $parsed_url['path'];
        @$this->query = $parsed_url['query'];
    }

    public function process(){
        $datalength = $this->get_data();
        if($datalength > 5)
        {
            $this->read_data();
            return 0;
        }
        return 1;
    }

}

//    $a = new Http_Request("1");
//    $a->data = "GET /form.html?a=a HTTP/1.1\r\nHost: localhost:13000\r\nConnection: keep-alive\r\n".
//                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\n".
//                "Upgrade-Insecure-Requests: 1";
//    $a->data = "POST / HTTP/1.1\r\nHost: localhost:13000\r\nConnection: keep-alive\r\nContent-Length: 36\r\n". "Cache-Control: max-age=0\r\n
//Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8\r\nOrigin: null\r\nUpgrade-Insecure-Requests: 1\r\n".
//"User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/44.0.2403.155 Safari/537.36\r\n".
//"Content-Type: application/x-www-form-urlencoded\r\nAccept-Encoding: gzip, deflate\r\nAccept-Language: zh-CN,zh;q=0.8,en;q=0.6\r\n".
//"\r\nname=fdsfa&gender=fdsa&submit=submit";
//    $a->read_data();
//    var_dump($a);
//    fseek($a->content_stream,0);
//   echo  fread($a->content_stream,20);