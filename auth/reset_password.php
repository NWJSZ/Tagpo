<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

$baseUrl = getBaseUrl();

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
    header('Location: ' . $baseUrl . 'auth/forgot_password.php');
    exit();
}

$error = '';
$success = '';
$email = $_SESSION['reset_email'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters!';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match!';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param('ss', $hashedPassword, $email);
        
        if ($stmt->execute()) {
            $stmt->close();

            $stmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $stmt->close();

            unset($_SESSION['reset_email']);
            unset($_SESSION['otp_verified']);

            header('Location: ' . $baseUrl . 'auth/login.php?status=reset_success');
            exit();
        } else {
            $error = 'Something went wrong. Please try again later.';
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password | TAGPO</title>
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
      <p class="auth-heading">Create New Password</p>
      <p class="auth-subheading">Your identity has been verified. Please choose a strong new password for your account.</p>

      <?php if ($error): ?>
        <div class="auth-alert">
          <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="auth-label">New Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input type="password" name="password" id="pw-new" class="form-control" placeholder="Min. 6 characters" required>
            <button type="button" class="input-group-text auth-password-toggle" onclick="togglePwd('pw-new', this)">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="mb-4">
          <label class="auth-label">Confirm New Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input type="password" name="confirm_password" id="pw-confirm-new" class="form-control" placeholder="Repeat new password" required>
            <button type="button" class="input-group-text auth-password-toggle" onclick="togglePwd('pw-confirm-new', this)">
              <i class="fa-regular fa-eye"></i>
            </button>
          </div>
        </div>
        
        <button type="submit" class="btn-auth-submit">
          Reset Password <i class="fa-solid fa-arrow-right ms-2"></i>
        </button>
      </form>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/loginsignup.js"></script>
</body>
</html>