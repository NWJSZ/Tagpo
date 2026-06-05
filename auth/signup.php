<?php
require_once dirname(__DIR__) . '/config/session_config.php';

// Check if user session expired (no cookie but had session)
if (!isset($_COOKIE['user_session']) && isset($_SESSION['current_user'])) {
    session_destroy();
    $_SESSION = [];
    header("Location: " . getBaseUrl() . "index.php?expired=true");
    exit();
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match!";
    }

    if (!isset($_SESSION['users'])) {
        $_SESSION['users'] = [];
    }

    if (!$error && $email === 'admin@tagpo.com') {
        $error = "An account with that email already exists!";
    }

    if (!$error) {
        foreach ($_SESSION['users'] as $user) {
            if ($user['email'] === $email) {
                $error = "An account with that email already exists!";
                break;
            }
        }
    }

    if (!$error) {
        $_SESSION['users'][] = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'user'
        ];

        // AUTO LOGIN (IMPORTANT)
        $_SESSION['current_user'] = end($_SESSION['users']);
        $_SESSION['last_activity'] = time();

        setcookie('user_session', $email, time() + (60 * 60 * 24 * 7), '/');

        header('Location: ' . getBaseUrl() . 'index.php');
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | TAGPO</title>
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
                <p>
                    From coast-side weddings to rooftop galas, find your perfect venue
                    and bring your celebration to life.
                </p>
                <div class="auth-dots">
                    <span></span><span></span><span></span>
                </div>
            </div>

            <div style="font-size: 0.78rem; color: rgba(255,255,255,.3);">
                &copy; 2026 TAGPO Luxury Venues
            </div>
        </div>

        <div class="auth-panel-right">
            <div class="tab-switcher">
                <button type="button" class="tab-btn" onclick="window.location.href='login.php'">Sign In</button>
                <button type="button" class="tab-btn active">Sign Up</button>
            </div>

            <div class="auth-form">
                <p class="auth-heading">Create account</p>
                <p class="auth-subheading">Join TAGPO and start planning your event</p>

                <?php if ($error): ?>
                    <div class="auth-alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="auth-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                            <input type="text" name="name" class="form-control" placeholder="Your full name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="auth-label">Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-regular fa-envelope"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="name@example.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="auth-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="password" id="pw-signup" class="form-control" placeholder="Min. 6 characters" required>
                            <button type="button" class="input-group-text auth-password-toggle" onclick="togglePwd('pw-signup', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="auth-label">Confirm Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="confirm_password" id="pw-confirm" class="form-control" placeholder="Repeat password" required>
                            <button type="button" class="input-group-text auth-password-toggle" onclick="togglePwd('pw-confirm', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-submit">
                        Create Account <i class="fa-solid fa-arrow-right ms-2"></i>
                    </button>
                </form>

                <p class="auth-switch">
                    Already have an account? <a href="login.php">Sign in</a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/loginsignup.js"></script>
</body>

</html>
