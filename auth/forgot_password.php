<?php
namespace PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

// ==========================================
// DIRECT EMBEDDED PHPMAILER ENGINE 
// ==========================================
class Exception extends \Exception {
    public function errorMessage() { return $this->getMessage(); }
}

class SMTP {
    const LE = "\r\n";
    protected $smtp_conn = null;
    public function connect($host, $port = null, $timeout = 30) {
        $this->smtp_conn = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$this->smtp_conn) return false;
        fgets($this->smtp_conn, 515);
        fputs($this->smtp_conn, "EHLO " . $host . self::LE);
        fgets($this->smtp_conn, 515);
        return true;
    }
    public function startTLS() {
        if (!stream_socket_enable_crypto($this->smtp_conn, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) return false;
        return true;
    }
    public function authenticate($username, $password) {
        fputs($this->smtp_conn, "AUTH LOGIN" . self::LE); fgets($this->smtp_conn, 515);
        fputs($this->smtp_conn, base64_encode($username) . self::LE); fgets($this->smtp_conn, 515);
        fputs($this->smtp_conn, base64_encode($password) . self::LE);
        $r = fgets($this->smtp_conn, 515);
        return (strpos($r, '235') === 0);
    }
    public function mail($from) { fputs($this->smtp_conn, "MAIL FROM:<" . $from . ">" . self::LE); return fgets($this->smtp_conn, 515); }
    public function recipient($to) { fputs($this->smtp_conn, "RCPT TO:<" . $to . ">" . self::LE); return fgets($this->smtp_conn, 515); }
    public function data($msg_data) {
        fputs($this->smtp_conn, "DATA" . self::LE); fgets($this->smtp_conn, 515);
        fputs($this->smtp_conn, $msg_data . self::LE . "." . self::LE); return fgets($this->smtp_conn, 515);
    }
    public function quit() { fputs($this->smtp_conn, "QUIT" . self::LE); fclose($this->smtp_conn); }
}

class PHPMailer {
    public $Host = 'smtp.gmail.com';
    public $Port = 587;
    public $Username = '';
    public $Password = '';
    public $From = '';
    public $FromName = 'TAGPO Luxury Venues';
    public $Subject = '';
    public $Body = '';
    public $ErrorInfo = '';
    protected $to = '';

    public function addAddress($address) { $this->to = $address; }
    public function send() {
        try {
            $smtp = new SMTP();
            if (!$smtp->connect($this->Host, $this->Port)) throw new Exception('Connect failed');
            if (!$smtp->startTLS()) throw new Exception('TLS failed');
            if (!$smtp->authenticate($this->Username, $this->Password)) throw new Exception('Auth failed');
            $smtp->mail($this->From);
            $smtp->recipient($this->to);
            $header = "Date: " . date('r') . "\r\nTo: " . $this->to . "\r\nFrom: " . $this->FromName . " <" . $this->From . ">\r\nSubject: " . $this->Subject . "\r\nMIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n";
            $smtp->data($header . $this->Body);
            $smtp->quit();
            return true;
        } catch (\Exception $e) {
            $this->ErrorInfo = $e->getMessage();
            return false;
        }
    }
}
// ==========================================
// END OF PHPMailer ENGINE
// ==========================================

global $conn; 

$baseUrl = getBaseUrl();
if (isLoggedIn()) { header('Location: ' . $baseUrl . 'index.php'); exit(); }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $userCheck = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$userCheck) {
            $error = 'Email address not found in our records.';
        } else {
            $otpCode = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $stmt = $conn->prepare("INSERT INTO password_resets (email, otp_code, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $email, $otpCode, $expiresAt);
            $stmt->execute();
            $stmt->close();

            $mail = new PHPMailer();
            
            stream_context_set_default([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ]);
            
            // SENDER MACHINE
            $mail->From     = 'nataliepaduhilao@gmail.com'; 
            $mail->Username = 'nataliepaduhilao@gmail.com'; 
            $mail->Password = 'abcd efgh ijkl mnop';

            $mail->addAddress($email);
            $mail->Subject = 'TAGPO - Password Reset OTP';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 8px; max-width: 500px;'>
                    <h2 style='color: #111;'>TAGPO<span style='color: #a3704c;'>.</span></h2>
                    <p>Hi there,</p>
                    <p>We received a request to reset your password. Use the verification code below to proceed:</p>
                    <div style='background: #f4f4f5; padding: 15px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #111; border-radius: 6px; margin: 20px 0;'>
                        $otpCode
                    </div>
                    <p style='font-size: 12px; color: #666;'>This OTP is valid for 15 minutes only.</p>
                </div>
            ";

            if ($mail->send()) {
                $_SESSION['reset_email'] = $email;
                header('Location: ' . $baseUrl . 'auth/verify_otp.php');
                exit();
            } else {
                $error = "Mail error: " . $mail->ErrorInfo;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | TAGPO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/styles.css">
  <link rel="stylesheet" href="../assets/css/loginsignup.css">
</head>
<body>
<div class="auth-wrap">
  <div class="auth-panel-left">
    <div class="brand">TAGPO<span>.</span></div>
    <div class="auth-tagline">
      <h2>Capturing Moments,<br>Creating Memories</h2>
      <p>From coast-side weddings to rooftop galas, find your perfect venue and bring your celebration to life.</p>
      <div class="auth-dots"><span></span><span></span><span></span></div>
    </div>
    <div style="font-size:.78rem;color:rgba(255,255,255,.3);">&copy; 2026 TAGPO Luxury Venues</div>
  </div>

  <div class="auth-panel-right">
    <div class="auth-form">
      <p class="auth-heading">Forgot Password</p>
      <p class="auth-subheading">Enter your email and we'll send you an OTP code to reset your password.</p>

      <?php if ($error): ?>
        <div class="auth-alert">
          <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-4">
          <label class="auth-label">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
            <input type="email" name="email" class="form-control" placeholder="name@example.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>
        </div>
        
        <button type="submit" class="btn-auth-submit mb-3">
          Send OTP Code <i class="fa-solid fa-paper-plane ms-2"></i>
        </button>
      </form>
      <p class="auth-switch"><i class="fa-solid fa-arrow-left me-1"></i> Back to <a href="login.php">Sign In</a></p>
    </div>
  </div>
</div>
</body>
</html>