<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

$baseUrl = getBaseUrl();

if (!isset($_SESSION['reset_email'])) {
    header('Location: ' . $baseUrl . 'auth/forgot_password.php');
    exit();
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $otpInput = trim($_POST['otp'] ?? '');

    if (empty($otpInput)) {
        $error = 'Please enter the 6-digit OTP code.';
    } else {
        $stmt = $conn->prepare("
            SELECT otp_code, expires_at 
            FROM password_resets 
            WHERE email = ? 
            ORDER BY created_at DESC 
            LIMIT 1
        ");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$result) {
            $error = 'No OTP request found. Please try again.';
        } else {
            $currentDateTime = date('Y-m-d H:i:s');

            if ($currentDateTime > $result['expires_at']) {
                $error = 'This OTP has already expired. Please request a new one.';
            } 
            if ($otpInput !== $result['otp_code']) {
                $error = 'Invalid OTP code. Please check your inbox and try again.';
            } else {
                $_SESSION['otp_verified'] = true;
                header('Location: ' . $baseUrl . 'auth/reset_password.php');
                exit();
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
  <title>Verify OTP | TAGPO</title>
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
      <p class="auth-heading">Enter Verification Code</p>
      <p class="auth-subheading">We sent a 6-digit verification code to <strong style="color: #a3704c;"><?= htmlspecialchars($email) ?></strong>.</p>

      <?php if ($error): ?>
        <div class="auth-alert">
          <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-4">
          <label class="auth-label">6-Digit OTP Code</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
            <input type="text" name="otp" class="form-control text-center tracking-widest" 
                   placeholder="000000" maxlength="6" autocomplete="off" required 
                   style="letter-spacing: 6px; font-weight: bold; font-size: 1.2rem;">
          </div>
        </div>
        
        <button type="submit" class="btn-auth-submit mb-3">
          Verify Code <i class="fa-solid fa-circle-check ms-2"></i>
        </button>
      </form>

      <p class="auth-switch">Didn't receive code? <a href="forgot_password.php">Resend OTP</a></p>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>