<?php
require_once dirname(__DIR__) . '/config/session_config.php';

// Default admin account
$admin = [
    'name' => 'Admin',
    'email' => 'admin@tagpo.com',
    'password' => 'admin123',
    'role' => 'admin'
];

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    }

    // Validate password strength in signup
    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    }
    $password = $_POST['password'];
    $found = false;

    // Check against Admin
    if ($email == $admin['email'] && $password == $admin['password']) {
        $_SESSION['current_user'] = $admin;
        $_SESSION['last_activity'] = time();
        // Set cookie for 7 days
        setcookie('user_session', $admin['email'], time() + (60 * 60 * 24 * 7), '/');
        header("Location: " . getBaseUrl() . "index.php");
        exit();
    }

    // Check against registered session users
    if (isset($_SESSION['users'])) {
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
        // Set cookie for 7 days
        setcookie('user_session', $_SESSION['current_user']['email'], time() + (60 * 60 * 24 * 7), '/');
        header("Location: " . getBaseUrl() . "index.php");
        exit();
    } else {
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

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5 col-xl-4">

                <div class="text-center mb-4 fade-up">
                    <h1 class="brand-font fw-bold text-gold"" style=" font-size: 2.5rem;">TAGPO<span class="text-gold">.</span></h1>
                </div>

                <div class="card shadow-lg p-4 fade-up-1">
                    <div class="login-body">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold mb-1">Welcome Back</h3>
                            <p class="text-muted small">Sign in to manage your bookings</p>
                        </div>

                        <?php if (isset($_GET['status']) && $_GET['status'] == 'registered'): ?>
                            <div class="alert alert-success alert-custom mb-4">
                                <i class="fa-solid fa-circle-check me-2"></i> Account created! Please login.
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['status']) && $_GET['status'] == 'logged_out'): ?>
                            <div class="alert alert-info alert-custom mb-4">
                                <i class="fa-solid fa-circle-info me-2"></i> Session expired. Please sign in again.
                            </div>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-custom mb-4">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i> <?php echo $error; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-regular fa-envelope text-muted"></i></span>
                                    <input type="email" name="email" class="form-control border-start-0" placeholder="name@example.com" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="d-flex justify-content-between">
                                    <label class="form-label small fw-bold text-uppercase" style="letter-spacing: 0.5px;">Password</label>
                                </div>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                                    <input type="password" name="password" class="form-control border-start-0" placeholder="••••••••" required>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-book w-100 py-3 mb-3">
                                Sign In
                            </button>
                        </form>

                        <div class="text-center mt-3">
                            <p class="small text-muted">
                                New to TAGPO?
                                <a href="signup.php" class="text-accent fw-bold text-decoration-none">Create an account</a>
                            </p>
                        </div>
                    </div>
                </div>

                <p class="text-center mt-4 small text-muted fade-up-2">
                    &copy; 2026 TAGPO Luxury Venues. All rights reserved.
                </p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/loginsignup.js"></script>

</body>

</html>