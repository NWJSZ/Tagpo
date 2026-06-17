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
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $firstName) || !preg_match('/^[a-zA-Z\s]+$/', $lastName)) {
        $error = "Name must contain letters only.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format!";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).{8,}$/', $password)) {
        $error = "Password must be at least 8 characters, with uppercase, lowercase, a number, and a special character.";
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
                                <input type="text" id="first-name-input" name="first_name" class="form-control" placeholder="John" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                            </div>
                            <div id="first-name-feedback" style="font-size: 0.78rem; margin-top: 4px; display: none;"></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="auth-label">Last Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fa-regular fa-user"></i></span>
                                <input type="text" id="last-name-input" name="last_name" class="form-control" placeholder="Doe" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                            </div>
                            <div id="last-name-feedback" style="font-size: 0.78rem; margin-top: 4px; display: none;"></div>
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
                        <input type="password" name="password" id="pw-signup" class="form-control" placeholder="Password" required>
                        <span class="input-group-text auth-password-toggle" onclick="togglePwd('pw-signup', this)" style="cursor: pointer;">
                        <i class="fa-regular fa-eye"></i>
                        </span>
                    </div>
                    <div id="pw-strength-bar-wrap" style="margin-top: 6px; display: none;">
                        <div style="height: 4px; border-radius: 4px; background: rgba(0,0,0,0.08); overflow: hidden;">
                            <div id="pw-strength-bar" style="height: 100%; width: 0%; border-radius: 4px; transition: width 0.3s, background 0.3s;"></div>
                        </div>
                    </div>
                    <div id="pw-feedback" style="font-size: 0.78rem; margin-top: 4px; display: none;"></div>
                    </div>

                    <div class="mb-3">
                    <label class="auth-label">Confirm Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                        <input type="password" name="confirm_password" id="pw-confirm" class="form-control" placeholder="Repeat password" required>
                        <span class="input-group-text auth-password-toggle" onclick="togglePwd('pw-confirm', this)" style="cursor: pointer;">
                        <i class="fa-regular fa-eye"></i>
                        </span>
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

        // --- Name Validation ---
        const firstNameInput = document.getElementById('first-name-input');
        const firstNameFeedback = document.getElementById('first-name-feedback');
        const lastNameInput = document.getElementById('last-name-input');
        const lastNameFeedback = document.getElementById('last-name-feedback');

        function validateName(input, feedbackEl) {
            const val = input.value;
            const lettersOnly = /^[a-zA-Z\s]*$/;
            if (val.length === 0) {
                feedbackEl.style.display = 'none';
                return true;
            }
            if (!lettersOnly.test(val)) {
                feedbackEl.style.display = 'block';
                feedbackEl.style.color = '#ef4444';
                feedbackEl.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Name must contain letters only.';
                return false;
            } else {
                feedbackEl.style.display = 'none';
                return true;
            }
        }

        function updateSubmitState() {
            const firstValid = /^[a-zA-Z\s]*$/.test(firstNameInput.value);
            const lastValid = /^[a-zA-Z\s]*$/.test(lastNameInput.value);
            if (!firstValid || !lastValid) {
                submitBtn.disabled = true;
            } else if (phoneInput.value.length === 10 || phoneInput.value.length === 0) {
                submitBtn.disabled = false;
            }
        }

        firstNameInput.addEventListener('input', function() {
            validateName(firstNameInput, firstNameFeedback);
            updateSubmitState();
        });

        lastNameInput.addEventListener('input', function() {
            validateName(lastNameInput, lastNameFeedback);
            updateSubmitState();
        });
        // --- End Name Validation ---

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

        // --- Password Validation ---
        const pwInput = document.getElementById('pw-signup');
        const pwFeedback = document.getElementById('pw-feedback');
        const pwStrengthBar = document.getElementById('pw-strength-bar');
        const pwStrengthBarWrap = document.getElementById('pw-strength-bar-wrap');

        function validatePassword() {
            const val = pwInput.value;

            if (val.length === 0) {
                pwFeedback.style.display = 'none';
                pwStrengthBarWrap.style.display = 'none';
                updateSubmitState();
                return;
            }

            const hasUpper   = /[A-Z]/.test(val);
            const hasLower   = /[a-z]/.test(val);
            const hasNumber  = /[0-9]/.test(val);
            const hasSpecial = /[\W_]/.test(val);
            const hasLength  = val.length >= 8;

            const score = [hasUpper, hasLower, hasNumber, hasSpecial, hasLength].filter(Boolean).length;

            // Strength bar
            pwStrengthBarWrap.style.display = 'block';
            const widths  = ['20%', '40%', '60%', '80%', '100%'];
            const colors  = ['#ef4444', '#f97316', '#f59e0b', '#84cc16', '#10b981'];
            pwStrengthBar.style.width  = widths[score - 1] || '0%';
            pwStrengthBar.style.background = colors[score - 1] || '#ef4444';

            // Feedback message
            pwFeedback.style.display = 'block';
            if (score === 5) {
                pwFeedback.style.color = '#10b981';
                pwFeedback.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Strong password!';
            } else {
                const missing = [];
                if (!hasLength)  missing.push('8+ characters');
                if (!hasUpper)   missing.push('uppercase letter');
                if (!hasLower)   missing.push('lowercase letter');
                if (!hasNumber)  missing.push('number');
                if (!hasSpecial) missing.push('special character');
                pwFeedback.style.color = '#ef4444';
                pwFeedback.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Missing: ' + missing.join(', ') + '.';
            }

            updateSubmitState();
        }

        pwInput.addEventListener('input', validatePassword);
        // --- End Password Validation ---

        // Patch updateSubmitState to also check password
        const _origUpdateSubmitState = updateSubmitState;
        function updateSubmitState() {
            const firstValid = /^[a-zA-Z\s]*$/.test(firstNameInput.value);
            const lastValid  = /^[a-zA-Z\s]*$/.test(lastNameInput.value);
            const pwVal      = pwInput ? pwInput.value : '';
            const pwValid    = /^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[\W_]).{8,}$/.test(pwVal) || pwVal.length === 0;
            const phoneLen   = phoneInput.value.length;

            if (!firstValid || !lastValid || !pwValid || (phoneLen > 0 && phoneLen !== 10)) {
                submitBtn.disabled = true;
            } else {
                submitBtn.disabled = false;
            }
        }
    });
    </script>
</body>

</html>