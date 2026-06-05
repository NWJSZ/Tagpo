<?php
require_once dirname(__DIR__) . '/config/session_config.php';

// Check if user session expired (no cookie but had session)
if (!isset($_COOKIE['user_session']) && isset($_SESSION['current_user'])) {
    session_destroy();
    $_SESSION = [];
    header("Location: ../index.php?expired=true");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
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

    // Initialize users array if not exists
    if (!isset($_SESSION['users'])) {
        $_SESSION['users'] = [];
    }

    $_SESSION['users'][] = [
        'name' => $name,
        'email' => $email,
        'password' => $password,
        'role' => 'user'
    ];

    // AUTO LOGIN (IMPORTANT)
    $_SESSION['current_user'] = end($_SESSION['users']);
    $_SESSION['last_activity'] = time();

    // Set cookie for 7 days
    setcookie('user_session', $email, time() + (60 * 60 * 24 * 7), '/');

    header('Location: ' . getBaseUrl() . 'index.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/loginsignup.css">
</head>

<body class="d-flex align-items-center">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow-lg p-4">
                    <div class="text-center mb-4">
                        <h3 class="fw-bold">Create Account</h3>
                        <p class="text-muted small">Join us today!</p>
                    </div>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label small">Full Name</label>
                            <input type="text" name="name" class="form-control" placeholder="Enter name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small">Email</label>
                            <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small">Password</label>
                            <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <button class="btn btn-success w-100 py-2 fw-bold">Register</button>
                    </form>

                    <div class="text-center mt-4">
                        <span class="small text-muted">Already have an account?</span>
                        <a href="login.php" class="small text-decoration-none fw-bold">Login here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
    </script>
    <script src="../assets/loginsignup.js"></script>
</body>

</html>