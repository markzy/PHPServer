<?php
/**
 * Author: Mark
 * Date: 8/12/15
 */

require_once("http_request.php");
require_once("http_response.php");
require_once("cgi_stream.php");
require_once("yaf_support.php");


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
                'Content-Type' => self::get_mime_type($path)
            );

            $file = fopen($path, 'rb');
            $content = fread($file, $file_size);
            return new Http_Response("200", $content, $headers);
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
        }

        $headers = array(
            'Content-Type' => "text/html"
        );

        return new Http_Response("200", $buffer, $headers);
    }

    public static function get_pre_response($status, $content, $headers, $status_msg) {
        return new Http_Response($status, $content, $headers, $status_msg);
    }

    // simple fallback
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

    public static function get_mime_type($path) {
        $path_info = pathinfo($path);
        $extension = strtolower($path_info['extension']);
        return @static::$mime_types[$extension];
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

        $route_result = HttpWorker::route($request);
        $result = '';

        if ($route_result['status'] != 200) {
            $result = HttpWorker::get_special_result($request, $route_result);
        } else {
            if ($route_result['function'] == 'php' || Config::$cache != 'LRU') {
                $response = HttpWorker::no_cache($request, $route_result);
                $result = $response->render();
            } else {
                $cache = HttpWorker::$cache;
                $path = $route_result['uri'];
                $cache_node = $cache->get($path);
            }
        }
        socket_write($client, $result, strlen($result));
        return HttpWorker::keep_alive($request);
    }


    static $mime_types = array("323" => "text/h323", "acx" => "application/internet-property-stream", "ai" => "application/postscript", "aif" => "audio/x-aiff", "aifc" => "audio/x-aiff", "aiff" => "audio/x-aiff", 'apk' => "application/vnd.android.package-archive",
        "asf" => "video/x-ms-asf", "asr" => "video/x-ms-asf", "asx" => "video/x-ms-asf", "au" => "audio/basic", "avi" => "video/quicktime", "axs" => "application/olescript", "bas" => "text/plain", "bcpio" => "application/x-bcpio", "bin" => "application/octet-stream", "bmp" => "image/bmp",
        "c" => "text/plain", "cat" => "application/vnd.ms-pkiseccat", "cdf" => "application/x-cdf", "cer" => "application/x-x509-ca-cert", "class" => "application/octet-stream", "clp" => "application/x-msclip", "cmx" => "image/x-cmx", "cod" => "image/cis-cod", "cpio" => "application/x-cpio", "crd" => "application/x-mscardfile",
        "crl" => "application/pkix-crl", "crt" => "application/x-x509-ca-cert", "csh" => "application/x-csh", "css" => "text/css", "dcr" => "application/x-director", "der" => "application/x-x509-ca-cert", "dir" => "application/x-director", "dll" => "application/x-msdownload", "dms" => "application/octet-stream", "doc" => "application/msword",
        "dot" => "application/msword", "dvi" => "application/x-dvi", "dxr" => "application/x-director", "eps" => "application/postscript", "etx" => "text/x-setext", "evy" => "application/envoy", "exe" => "application/octet-stream", "fif" => "application/fractals", "flr" => "x-world/x-vrml", "gif" => "image/gif",
        "gtar" => "application/x-gtar", "gz" => "application/x-gzip", "h" => "text/plain", "hdf" => "application/x-hdf", "hlp" => "application/winhlp", "hqx" => "application/mac-binhex40", "hta" => "application/hta", "htc" => "text/x-component", "htm" => "text/html", "html" => "text/html",
        "htt" => "text/webviewhtml", "ico" => "image/x-icon", "ief" => "image/ief", "iii" => "application/x-iphone", "ins" => "application/x-internet-signup", "isp" => "application/x-internet-signup", "jfif" => "image/pipeg", "jpe" => "image/jpeg", "jpeg" => "image/jpeg", "jpg" => "image/jpeg",
        "js" => "application/x-javascript", "latex" => "application/x-latex", "lha" => "application/octet-stream", "lsf" => "video/x-la-asf", "lsx" => "video/x-la-asf", "lzh" => "application/octet-stream", "m13" => "application/x-msmediaview", "m14" => "application/x-msmediaview", "m3u" => "audio/x-mpegurl", "man" => "application/x-troff-man",
        "mdb" => "application/x-msaccess", "me" => "application/x-troff-me", "mht" => "message/rfc822", "mhtml" => "message/rfc822", "mid" => "audio/mid", "mny" => "application/x-msmoney", "mov" => "video/quicktime", "movie" => "video/x-sgi-movie", "mp2" => "video/mpeg", "mp3" => "audio/mpeg",
        'mp4' => 'video/mp4', "mpa" => "video/mpeg", "mpe" => "video/mpeg", "mpeg" => "video/mpeg", "mpg" => "video/mpeg", "mpp" => "application/vnd.ms-project", "mpv2" => "video/mpeg", "ms" => "application/x-troff-ms", "mvb" => "application/x-msmediaview", "nws" => "message/rfc822", "oda" => "application/oda",
        'ogg' => 'video/ogg', 'ogv' => 'video/ogg', "p10" => "application/pkcs10", "p12" => "application/x-pkcs12", "p7b" => "application/x-pkcs7-certificates", "p7c" => "application/x-pkcs7-mime", "p7m" => "application/x-pkcs7-mime", "p7r" => "application/x-pkcs7-certreqresp", "p7s" => "application/x-pkcs7-signature", "pbm" => "image/x-portable-bitmap", "pdf" => "application/pdf", "pfx" => "application/x-pkcs12",
        "pgm" => "image/x-portable-graymap", "pko" => "application/ynd.ms-pkipko", "pma" => "application/x-perfmon", "pmc" => "application/x-perfmon", "pml" => "application/x-perfmon", "pmr" => "application/x-perfmon", "pmw" => "application/x-perfmon", "png" => "image/png", "pnm" => "image/x-portable-anymap", "pot" => "application/vnd.ms-powerpoint", "ppm" => "image/x-portable-pixmap",
        "pps" => "application/vnd.ms-powerpoint", "ppt" => "application/vnd.ms-powerpoint", "prf" => "application/pics-rules", "ps" => "application/postscript", "pub" => "application/x-mspublisher", "qt" => "video/quicktime", "ra" => "audio/x-pn-realaudio", "ram" => "audio/x-pn-realaudio", "ras" => "image/x-cmu-raster", "rgb" => "image/x-rgb",
        "rmi" => "audio/mid", "roff" => "application/x-troff", "rtf" => "application/rtf", "rtx" => "text/richtext", "scd" => "application/x-msschedule", "sct" => "text/scriptlet", "setpay" => "application/set-payment-initiation", "setreg" => "application/set-registration-initiation", "sh" => "application/x-sh", "shar" => "application/x-shar",
        "sit" => "application/x-stuffit", "snd" => "audio/basic", "spc" => "application/x-pkcs7-certificates", "spl" => "application/futuresplash", "src" => "application/x-wais-source", "sst" => "application/vnd.ms-pkicertstore", "stl" => "application/vnd.ms-pkistl", "stm" => "text/html", "svg" => "image/svg+xml", "sv4cpio" => "application/x-sv4cpio",
        "sv4crc" => "application/x-sv4crc", "t" => "application/x-troff", "tar" => "application/x-tar", "tcl" => "application/x-tcl", "tex" => "application/x-tex", "texi" => "application/x-texinfo", "texinfo" => "application/x-texinfo", "tgz" => "application/x-compressed", "tif" => "image/tiff", "tiff" => "image/tiff",
        "tr" => "application/x-troff", "trm" => "application/x-msterminal", "tsv" => "text/tab-separated-values", "txt" => "text/plain", "uls" => "text/iuls", "ustar" => "application/x-ustar", "vcf" => "text/x-vcard", "vrml" => "x-world/x-vrml", "wav" => "audio/x-wav", "wcm" => "application/vnd.ms-works",
        "wdb" => "application/vnd.ms-works", 'webm' => 'video/webm', "wks" => "application/vnd.ms-works", "wmf" => "application/x-msmetafile", "wps" => "application/vnd.ms-works", "wri" => "application/x-mswrite", "wrl" => "x-world/x-vrml", "wrz" => "x-world/x-vrml", "xaf" => "x-world/x-vrml", "xbm" => "image/x-xbitmap", "xla" => "application/vnd.ms-excel",
        "xlc" => "application/vnd.ms-excel", "xlm" => "application/vnd.ms-excel", "xls" => "application/vnd.ms-excel", "xlt" => "application/vnd.ms-excel", "xlw" => "application/vnd.ms-excel", "xof" => "x-world/x-vrml", "xpm" => "image/x-xpixmap", "xwd" => "image/x-xwindowdump", "z" => "application/x-compress", "zip" => "application/zip");

}

class Config {
    static $docroot = "";
    static $yaf = false;
    static $cache = false;
}