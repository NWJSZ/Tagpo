<?php
namespace PHPMailer\PHPMailer;
require_once __DIR__ . '/Exception.php';
require_once __DIR__ . '/SMTP.php';

class PHPMailer {
    public $Priority = null;
    public $CharSet = 'UTF-8';
    public $ContentType = 'text/plain';
    public $Encoding = '8bit';
    public $ErrorInfo = '';
    public $From = 'root@localhost';
    public $FromName = 'Root User';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    protected $MIMEBody = '';
    protected $MIMEHeader = '';
    protected $mailHeader = '';
    public $WordWrap = 0;
    public $Mailer = 'smtp';
    public $Sendmail = '/usr/sbin/sendmail';
    public $UseSendmailOptions = true;
    public $ConfirmReadingTo = '';
    public $Hostname = '';
    public $MessageID = '';
    public $MessageDate = '';
    public $Host = 'localhost';
    public $Port = 25;
    public $Helo = '';
    public $SMTPSecure = '';
    public $SMTPAutoTLS = true;
    public $SMTPAuth = false;
    public $SMTPOptions = array();
    public $Username = '';
    public $Password = '';
    public $AuthType = '';
    public $Timeout = 5;
    public $SMTPDebug = 0;
    public $Debugoutput = 'echo';
    public $SMTPKeepAlive = false;
    public $SingleTo = false;
    protected $SingleToArray = array();
    public $LE = "\n";
    public $DKIM_selector = '';
    public $DKIM_identity = '';
    public $DKIM_passphrase = '';
    public $DKIM_domain = '';
    public $DKIM_private = '';
    public $DKIM_private_string = '';
    public $action_function = '';
    public $XMailer = '';
    protected $smtp = null;
    protected $to = array();
    protected $cc = array();
    protected $bcc = array();
    protected $ReplyTo = array();
    protected $all_recipients = array();
    protected $attachment = array();
    protected $CustomHeader = array();
    protected $lastMessageID = '';
    protected $message_type = '';
    protected $boundary = array();
    protected $language = array();
    protected $error_count = 0;
    protected $sign_cert_file = '';
    protected $sign_key_file = '';
    protected $sign_extracerts_file = '';
    protected $sign_key_passphrase = '';
    protected $exceptions = true;
    const VERSION = '6.8.0';

    public function __construct($exceptions = null) {
        if ($exceptions !== null) {
            $this->exceptions = (bool) $exceptions;
        }
    }
    public function isSMTP() {
        $this->Mailer = 'smtp';
    }
    public function setFrom($address, $name = '', $auto = true) {
        $this->From = trim((string)$address);
        $this->FromName = trim((string)$name);
        return true;
    }
    public function addAddress($address, $name = '') {
        $this->to[] = array(trim((string)$address), trim((string)$name));
        return true;
    }
    public function isHTML($ishtml = true) {
        if ($ishtml) {
            $this->ContentType = 'text/html';
        } else {
            $this->ContentType = 'text/plain';
        }
    }
    public function send() {
        try {
            $this->smtp = new SMTP();
            if ($this->SMTPDebug > 0) {
                $this->smtp->Debugoutput = $this->Debugoutput;
            }
            $this->smtp->do_debug = $this->SMTPDebug;
            $this->smtp->Timeout = $this->Timeout;
            
            $host = $this->Host;
            $port = $this->Port;
            $tls = ($this->SMTPSecure === 'tls' || ($this->SMTPSecure === '' && $this->SMTPAutoTLS));
            
            if (!$this->smtp->connect($host, $port, $this->Timeout)) {
                throw new Exception('SMTP connect() failed.');
            }
            if ($tls && !$this->smtp->startTLS()) {
                throw new Exception('SMTP TLS failed.');
            }
            if ($this->SMTPAuth && !$this->smtp->authenticate($this->Username, $this->Password)) {
                throw new Exception('SMTP Authentication failed.');
            }
            
            $this->smtp->mail($this->From);
            foreach ($this->to as $to) {
                $this->smtp->recipient($to[0]);
            }
            
            $header = "Date: " . date('r') . "\r\n";
            $header .= "To: " . $this->to[0][0] . "\r\n";
            $header .= "From: " . $this->FromName . " <" . $this->From . ">\r\n";
            $header .= "Subject: " . $this->Subject . "\r\n";
            $header .= "MIME-Version: 1.0\r\n";
            $header .= "Content-Type: " . $this->ContentType . "; charset=" . $this->CharSet . "\r\n\r\n";
            
            $this->smtp->data($header . $this->Body);
            $this->smtp->quit();
            return true;
        } catch (\Exception $e) {
            $this->ErrorInfo = $e->getMessage();
            if ($this->exceptions) {
                throw new Exception($e->getMessage());
            }
            return false;
        }
    }
}