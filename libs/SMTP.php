<?php
namespace PHPMailer\PHPMailer;

class SMTP {
    const VERSION = '6.8.0';
    const LE = "\r\n";
    public $do_debug = 0;
    public $Debugoutput = 'echo';
    public $Timeout = 5;
    protected $smtp_conn = null;
    protected $error = array();
    protected $helo_rply = null;

    public function connect($host, $port = null, $timeout = 30, $options = array()) {
        $this->error = array();
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$socket) {
            return false;
        }
        $this->smtp_conn = $socket;
        $this->get_lines();
        $this->ehlo($host);
        return true;
    }
    public function startTLS() {
        if (!stream_socket_enable_crypto($this->smtp_conn, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            return false;
        }
        return true;
    }
    public function authenticate($username, $password) {
        fputs($this->smtp_conn, "AUTH LOGIN" . self::LE);
        $this->get_lines();
        fputs($this->smtp_conn, base64_encode($username) . self::LE);
        $this->get_lines();
        fputs($this->smtp_conn, base64_encode($password) . self::LE);
        $reply = $this->get_lines();
        if (strpos($reply, '235') !== 0) {
            return false;
        }
        return true;
    }
    public function mail($from) {
        fputs($this->smtp_conn, "MAIL FROM:<" . $from . ">" . self::LE);
        return $this->get_lines();
    }
    public function recipient($to) {
        fputs($this->smtp_conn, "RCPT TO:<" . $to . ">" . self::LE);
        return $this->get_lines();
    }
    public function data($msg_data) {
        fputs($this->smtp_conn, "DATA" . self::LE);
        $this->get_lines();
        fputs($this->smtp_conn, $msg_data . self::LE . "." . self::LE);
        return $this->get_lines();
    }
    public function quit() {
        fputs($this->smtp_conn, "QUIT" . self::LE);
        fclose($this->smtp_conn);
    }
    protected function ehlo($host) {
        fputs($this->smtp_conn, "EHLO " . $host . self::LE);
        $this->get_lines();
    }
    protected function get_lines() {
        $data = "";
        while ($str = fgets($this->smtp_conn, 515)) {
            $data .= $str;
            if (substr($str, 3, 1) == " ") {
                break;
            }
        }
        return $data;
    }
}