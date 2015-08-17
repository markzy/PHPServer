<?php
/**
 * Created by PhpStorm.
 * User: Mark
 * Date: 8/14/15
 * Time: 10:46 AM
 */

    # temprarily NO BUFFER support CGIStream
    class CGIStream{
        public $context;

        const BUFFERING = 0;
        const EOF = 1;
        public $cur_state;

        private $buffer = '';
        private $process;
        private $output_stream;
        private $err_stream;

        public function stream_open($path,$mode,$options,&$opened_path){
            $options = stream_context_get_options($this->context);
            $php_cgi = substr($path,6);// suggest path starts with 'cgi://'

            $cgi_options = $options['cgi'];
            $descriptorspec = array(
                0 => $cgi_options['stdin'],
                1 => array('pipe','w'),
                2 => STDERR
            );

            $process = proc_open($php_cgi,$descriptorspec,$pipes,__DIR__,$cgi_options['env']);
            if(!is_resource($process))
                return false;

            $this->output_stream = $pipes[1];// the ouput stream
//            $this->err_stream = $pipes[2];
            $this->process = $process;
            return true;
        }

        public function stream_read($count){
            $this->cur_state = static::BUFFERING;
            $data = fread($this->output_stream,$count);

//            if($data !== false ) {
//                echo "enter\n";
//                $this->buffer = $this->buffer . $data;
//                echo $this->buffer;
//                if(!feof($this->output_stream))
//                    return '';
//            }
//            else throw new Exception("Fail to read CGIStream");
//            echo $this->buffer;
            //finished
            $end_response_headers = strpos($data, "\r\n\r\n");
            $content = substr($data, $end_response_headers + 4);
            $this->cur_state = static::EOF;
            return $content;
        }

        public function stream_eof(){
            return $this->cur_state == static::EOF;
        }

        public function stream_close(){
            proc_close($this->process);
        }
    }
