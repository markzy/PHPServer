<?php
/**
 * Author: Mark
 * Date: 8/14/15
 */
# temprarily NO BUFFER support for CGIStream
class CGIStream {
    public $context;

    const BUFFERING = 0;
    const EOF = 1;
    public $cur_state;

    private $buffer = '';
    private $process;
    private $output_stream;
    private $err_stream;

    public function stream_open($path, $mode, $options, &$opened_path) {
        $options = stream_context_get_options($this->context);
        $php_cgi = substr($path, 6);// suggest path starts with 'cgi://'

        $cgi_options = $options['cgi'];
        $descriptorspec = array(
            0 => $cgi_options['stdin'],
            1 => array('pipe', 'w'),
            2 => STDERR
        );

        $process = proc_open($php_cgi, $descriptorspec, $pipes, __DIR__, $cgi_options['env']);
        if (!is_resource($process))
            return false;

        $this->output_stream = $pipes[1];// the ouput stream
        $this->process = $process;
        return true;
    }

    public function stream_read($count) {
        return fread($this->output_stream, $count);
    }

    public function stream_eof() {
        return feof($this->output_stream);
    }

    public function stream_close() {
        proc_close($this->process);
    }
}
