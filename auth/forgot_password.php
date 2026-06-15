<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

global $conn; 
$baseUrl = getBaseUrl();
if (isLoggedIn()) { header('Location: ' . $baseUrl . 'index.php'); exit(); }

function sendDirectGmailHTTP($toEmail, $subject, $htmlMessage, $appPassword) {
    $senderEmail = 'nataliepaduhilao@gmail.com';

    $socket = @stream_socket_client("ssl://smtp.gmail.com:465", $errno, $errstr, 15,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => [
            'verify_peer'       => false,
            'verify_peer_name'  => false,
            'allow_self_signed' => true
        ]])
    );

    if (!$socket) return false;

    $read = function($s) {
        $data = '';
        while ($line = fgets($s, 515)) {
            $data .= $line;
            if (substr($line, 3, 1) === ' ') break; // end of multi-line response
        }
        return $data;
    };

    $read($socket); // 220 greeting
    fputs($socket, "EHLO localhost\r\n");    $read($socket);
    fputs($socket, "AUTH LOGIN\r\n");        $read($socket);
    fputs($socket, base64_encode($senderEmail) . "\r\n"); $read($socket);
    fputs($socket, base64_encode($appPassword) . "\r\n");
    $authReply = $read($socket);

    if (strpos($authReply, '235') !== 0) {
        fclose($socket);
        return false;
    }

    fputs($socket, "MAIL FROM:<$senderEmail>\r\n"); $read($socket);
    fputs($socket, "RCPT TO:<$toEmail>\r\n");       $read($socket);
    fputs($socket, "DATA\r\n");                     $read($socket);

    $headers  = "From: TAGPO Luxury Venues <$senderEmail>\r\n";
    $headers .= "To: <$toEmail>\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    fputs($socket, $headers . "\r\n" . $htmlMessage . "\r\n.\r\n");
    $read($socket);

    fputs($socket, "QUIT\r\n");
    fclose($socket);
    return true;
}

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

            // SENDER MACHINE
            $myAppPassword = 'mbsnrnctintszddf'; 

            $subject = 'TAGPO - Password Reset OTP';
            $message = "
                <div style='font-family: Arial, sans-serif; padding: 20px; border: 1px solid #eee; border-radius: 8px; max-width: 500px; background-color: #ffffff;'>
                    <h2 style='color: #111; margin-bottom: 5px;'>TAGPO<span style='color: #a3704c;'>.</span></h2>
                    <p style='color: #555;'>Hi there,</p>
                    <p style='color: #555;'>We received a request to reset your password for your TAGPO account. Use the 6-digit verification code below to proceed:</p>
                    <div style='background: #f4f4f5; padding: 18px; text-align: center; font-size: 28px; font-weight: bold; letter-spacing: 6px; color: #a3704c; border-radius: 6px; margin: 20px 0; border: 1px solid #e4e4e7;'>
                        $otpCode
                    </div>
                    <p style='font-size: 12px; color: #888; margin-top: 20px;'>This OTP is temporary and is valid for 15 minutes only. If you did not make this request, please ignore this email.</p>
                    <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
                    <p style='font-size: 11px; color: #b5b5b5; text-align: center;'>&copy; 2026 TAGPO Luxury Venues. All rights reserved.</p>
                </div>
            ";

            sendDirectGmailHTTP($email, $subject, $message, $myAppPassword);

            $_SESSION['reset_email'] = $email;
            header('Location: ' . $baseUrl . 'auth/verify_otp.php');
            exit();
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