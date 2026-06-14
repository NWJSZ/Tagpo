<?php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/session_config.php';
require_once dirname(__DIR__) . '/config/app.php';

$baseUrl = getBaseUrl();

// Check if user session expired (no cookie but had session)
if (!isset($_COOKIE['user_session']) && isset($_SESSION['current_user'])) {
    session_destroy();
    $_SESSION = [];
    header("Location: " . getBaseUrl() . "index.php?expired=true");
    exit();
}

$error = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sinasalo na natin nang hiwalay ang First at Last Name mula sa form
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Binago ang check kung empty ang mga required fields
    if ($firstName === '' || $lastName === '' || $email === '' || $password === '' || $phone === '') {
        $error = "All fields are required!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters!";
    } elseif (strlen($phone) !== 10) {
        $error = "Phone number must be exactly 10 digits.";
    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match!";
    }

    if (!$error) {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // check email in DATABASE
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = "An account with that email already exists!";
        } else {

            // In-update ang query para sa dalawang bagong columns (first_name, last_name)
            $stmt = $conn->prepare("
                INSERT INTO users (first_name, last_name, email, password, phone, role)
                VALUES (?, ?, ?, ?, ?, 'user')
            ");

            // 'sssss' na ngayon ang types dahil limang strings ang ipapasa natin
            $stmt->bind_param("sssss", $firstName, $lastName, $email, $hashedPassword, $phone);
            $stmt->execute();
            $newUserId = $conn->insert_id;

            // AUTO LOGIN
            $_SESSION['current_user'] = [
                'id' => $newUserId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'role' => 'user'
            ];

            $_SESSION['last_activity'] = time();

            setcookie('user_session', $email, time() + (60 * 60 * 24 * 7), '/');

            header('Location: ' . getBaseUrl() . 'index.php');
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

                <form method="POST" id="signup-form">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="auth-label">First Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                                <input type="text" name="first_name" class="form-control" placeholder="John" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="auth-label">Last Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                                <input type="text" name="last_name" class="form-control" placeholder="Doe" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                            </div>
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
                        <label class="auth-label">Phone Number</label>
                        <div class="input-group">
                            <span class="input-group-text">+63</span>
                            <input type="text" id="phone-input" name="phone" class="form-control" placeholder="9XX XXX XXXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" maxlength="10" required>
                        </div>
                        <div id="phone-feedback" style="font-size: 0.78rem; margin-top: 4px; display: none;"></div>
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

                    <button type="submit" id="submit-btn" class="btn-auth-submit">
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const phoneInput = document.getElementById('phone-input');
        const phoneFeedback = document.getElementById('phone-feedback');
        const submitBtn = document.getElementById('submit-btn');

        function validatePhone() {
            // 1. Numbers Only Rule
            let val = phoneInput.value.replace(/\D/g, '');
            phoneInput.value = val;

            if (val.length === 0) {
                phoneFeedback.style.display = 'none';
                submitBtn.disabled = false;
                return;
            }

            phoneFeedback.style.display = 'block';

            // 2. 10 Digits Rule
           if (val.length < 10) {
                phoneFeedback.style.color = '#f59e0b'; //Warning
                phoneFeedback.innerHTML = `<i class="fa-solid fa-circle-info me-1"></i> Must be exactly 10 digits (${10 - val.length} remaining).`;
                submitBtn.disabled = true;
            } else if (val.length === 10) {
                phoneFeedback.style.color = '#10b981'; //Success
                phoneFeedback.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Phone number is valid!';
                submitBtn.disabled = false;
            }
        }

        phoneInput.addEventListener('input', validatePhone);
    });
    </script>
</body>

</html>