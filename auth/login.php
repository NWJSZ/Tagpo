<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

$baseUrl = getBaseUrl();

// Default admin account
$admin = [
    'name' => 'Admin',
    'email' => 'admin@tagpo.com',
    'password' => 'admin123',
    'role' => 'admin'
];

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $found = false;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    }

    // First try database authentication
    if (!$error) {
        $stmt = $conn->prepare("SELECT id, name, email, password, role FROM users WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $found = true;
                $userToSet = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ];
                $_SESSION['current_user'] = $userToSet;
                $_SESSION['last_activity'] = time();
                setcookie('user_session', $user['email'], time() + (60 * 60 * 24 * 7), '/');
                header("Location: " . getBaseUrl() . "/index.php");
                exit();
            }
        }
        $stmt->close();
    }

    // Fallback to hardcoded admin if not found in database
    if (!$found && !$error && $email == $admin['email'] && $password == $admin['password']) {
        $_SESSION['current_user'] = $admin;
        $_SESSION['last_activity'] = time();
        setcookie('user_session', $admin['email'], time() + (60 * 60 * 24 * 7), '/');
        header("Location: " . getBaseUrl() . "/index.php");
        exit();
    }

    // Also check session-stored users for backward compatibility
    if (!$found && !$error && isset($_SESSION['users'])) {
        foreach ($_SESSION['users'] as $user) {
            if ($user['email'] == $email && $user['password'] == $password) {
                $found = true;
                $_SESSION['current_user'] = $user;
                $_SESSION['last_activity'] = time();
                break;
            }
        }
    }

    if ($found) {
        setcookie('user_session', $_SESSION['current_user']['email'], time() + (60 * 60 * 24 * 7), '/');
        header("Location: " . getBaseUrl() . "/index.php");
        exit();
    } elseif (!$error) {
        $error = "Invalid email or password!";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TAGPO</title>
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
                <button type="button" class="tab-btn active">Sign In</button>
                <button type="button" class="tab-btn" onclick="window.location.href='signup.php'">Sign Up</button>
            </div>

            <div class="auth-form">
                <p class="auth-heading">Welcome back</p>
                <p class="auth-subheading">Sign in to manage your bookings</p>

                <?php if (isset($_GET['status']) && $_GET['status'] == 'registered'): ?>
                    <div class="auth-alert" style="background:rgba(16,185,129,.12); border-color:rgba(16,185,129,.25); color:#6ee7b7;">
                        <i class="fa-solid fa-circle-check me-2"></i> Account created! Please sign in.
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['status']) && $_GET['status'] == 'logged_out'): ?>
                    <div class="auth-alert" style="background:rgba(59,130,246,.12); border-color:rgba(59,130,246,.25); color:#93c5fd;">
                        <i class="fa-solid fa-circle-info me-2"></i> You have been logged out.
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="auth-alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
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
                            <input type="password" name="password" id="pw-login" class="form-control" placeholder="Password" required>
                            <button type="button" class="input-group-text auth-password-toggle" onclick="togglePwd('pw-login', this)">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-auth-submit">
                        Sign In <i class="fa-solid fa-arrow-right ms-2"></i>
                    </button>
                </form>

                <div class="auth-divider">
                    <hr><span>Or continue with</span><hr>
                </div>

                <div class="d-flex gap-2">
                    <button type="button" class="btn-social"><i class="fab fa-google"></i> Google</button>
                    <button type="button" class="btn-social"><i class="fab fa-apple"></i> Apple</button>
                </div>

                <p class="auth-switch">
                    New to TAGPO? <a href="signup.php">Create an account</a>
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/loginsignup.js"></script>
</body>

</html>
