<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

$baseUrl = getBaseUrl();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . $baseUrl . 'index.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format.';
    } elseif (empty($password)) {
        $error = 'Password is required.';
    } else {
        // Look up user in DB
        $stmt = $conn->prepare(
            "SELECT id, first_name, last_name, email, password, role
             FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['current_user'] = [
                'id'         => (int) $user['id'],
                'first_name' => $user['first_name'],
                'last_name'  => $user['last_name'],
                'email'      => $user['email'],
                'role'       => $user['role'],
            ];
            $_SESSION['last_activity'] = time();
            setcookie('user_session', $user['email'], time() + 86400 * 7, '/');
            header('Location: ' . $baseUrl . 'index.php');
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In | TAGPO</title>
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
    <div class="tab-switcher">
      <button type="button" class="tab-btn active">Sign In</button>
      <button type="button" class="tab-btn" onclick="window.location.href='signup.php'">Sign Up</button>
    </div>

    <div class="auth-form">
      <p class="auth-heading">Welcome back</p>
      <p class="auth-subheading">Sign in to manage your bookings</p>

      <?php if (isset($_GET['status']) && $_GET['status'] === 'registered'): ?>
        <div class="auth-alert" style="background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.25);color:#6ee7b7;">
          <i class="fa-solid fa-circle-check me-2"></i> Account created! Please sign in.
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['status']) && $_GET['status'] === 'reset_success'): ?>
        <div class="auth-alert" style="background:rgba(16,185,129,.12);border-color:rgba(16,185,129,.25);color:#6ee7b7;">
          <i class="fa-solid fa-circle-check me-2"></i> Password successfully reset! Please login with your new password.
        </div>
      <?php endif; ?>

      <!-- INACTIVITY KICK-OUT NOTIFICATION -->
      <?php if (isset($_GET['session_expired']) && $_GET['session_expired'] === 'inactivity'): ?>
        <div class="auth-alert" style="background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.25);color:#fcd34d;">
          <i class="fa-solid fa-triangle-exclamation me-2"></i>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="auth-alert">
          <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="mb-3">
          <label class="auth-label">Email Address</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
            <input type="email" name="email" class="form-control" placeholder="name@example.com"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="auth-label">Password</label>
          <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
            <input type="password" name="password" id="pw-login" class="form-control" placeholder="Password" required>
            <!-- FIXED: eye icon as clickable span -->
            <span class="input-group-text auth-password-toggle" onclick="togglePwd('pw-login',this)">
              <i class="fa-regular fa-eye"></i>
            </span>
          </div>
          <div class="text-end mt-1">
            <a href="forgot_password.php" style="font-size: 0.82rem; color: #a3704c; text-decoration: none;">Forgot Password?</a>
          </div>
        </div>
        <button type="submit" class="btn-auth-submit">
          Sign In <i class="fa-solid fa-arrow-right ms-2"></i>
        </button>
      </form>

      <p class="auth-switch">New to TAGPO? <a href="signup.php">Create an account</a></p>
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/loginsignup.js"></script>
</body>
</html>
