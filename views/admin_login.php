<?php
// admin_login.php
$error_message = null;

if (is_logged_in() && has_role('admin')) {
    header('Location: /admin/dashboard');
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    
    $admin_email = strtolower(env('ADMIN_EMAIL', 'admin@mwalimu.info'));
    $admin_password = env('ADMIN_PASSWORD', 'Kenya@254');

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $rateCheck = check_login_rate_limit($email);
    if (!$rateCheck['allowed']) {
        $error_message = "Too many failed login attempts. Please wait {$rateCheck['retry_after']} minute(s) before trying again.";
    } elseif (empty($email) || empty($password)) {
        $error_message = "Please enter both your email address and password.";
    } elseif (hash_equals($admin_email, $email) && hash_equals($admin_password, $password)) {
        clear_login_rate_limit($email);
        $adminObj = (object)[
            'id' => 1,
            'name' => 'Administrator',
            'email' => $admin_email
        ];
        auth_login($adminObj, 'admin');
        header('Location: /admin/dashboard');
        exit();
    } else {
        record_failed_login($email);
        $error_message = "Invalid email or password.";
    }
}
?>

<article class="card" style="max-width: 80%; margin: 2rem auto;">
    <header>
        <h2 style="text-align: center;">Admin Login</h2>
    </header>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-error">
        <h4><?php echo htmlspecialchars($error_message); ?></h4>
    </div>
    <?php endif; ?>

    <form method="POST" action="" style="padding: 1rem;">
        <?= csrf_field() ?>
        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required>

        <label for="password">Password:</label>
        <div class="password-input">
            <input type="password" name="password" id="password" required>
            <span class="password-toggle" onclick="togglePassword()">👁</span>
        </div>

        <button type="submit" class="primary" style="width: 100%;">Login</button>
    </form>

    <style>
    .password-input {
        position: relative;
        display: flex;
        align-items: center;
    }

    .password-input input[type="password"],
    .password-input input[type="text"] {
        padding-right: 30px;
        /* Space for the eye icon */
        width: 100%;
    }

    .password-toggle {
        position: absolute;
        right: 5px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        user-select: none;
    }
    </style>

    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
    }
    </script>
</article>
