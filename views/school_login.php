<?php
// School Login
$school_error_message = null;
$redirect = trim($_GET['redirect'] ?? ($_POST['redirect'] ?? ''));
if (!empty($redirect) && (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//'))) {
    $redirect = '';
}
$notice = $_GET['notice'] ?? '';

if (is_logged_in() && has_role('school')) {
    $dest = !empty($redirect) ? $redirect : '/school/dashboard';
    header("Location: $dest");
    exit();
}

// Handle login form submission for schools
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validate_csrf();

        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Check if email exists
        $school = R::findOne('school', 'email = ?', [$email]);
        if (!$school) {
            throw new Exception("School does not exist.");
        }

        // Check if password matches
        if (!password_verify($password, $school->password)) {
            throw new Exception("Incorrect password.");
        }

        auth_login($school, 'school');

        // Redirect to school dashboard or destination
        $dest = !empty($redirect) ? $redirect : '/school/dashboard';
        header("Location: $dest");
        exit();
    } catch (Exception $e) {
        $school_error_message = $e->getMessage();
    }
}
?>

<article style="flex: 1; min-width: 300px; margin: 1rem;">
    <header>
        <h3>School Login</h3>
    </header>

    <?php if ($notice === 'auth_required'): ?>
        <div
            style="background: #f0fdfa; border: 1px solid #99f6e4; color: #0f766e; padding: 12px 16px; border-radius: 6px; margin-bottom: 1rem; font-size: 0.88rem;">
            <strong>Access Private & International Schools:</strong> Please sign in to your School account to unlock
            directory access.
        </div>
    <?php endif; ?>

    <?php if (isset($school_error_message)): ?>
        <div class="alert alert-error">
            <h4><?php echo htmlspecialchars($school_error_message); ?></h4>
        </div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>
        <?php if (!empty($redirect)): ?>
            <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
        <?php endif; ?>
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required>

        <label for="password">Password</label>
        <div class="password-input">
            <input type="password" placeholder="Password" id="password" name="password" required>
            <span class="password-toggle" onclick="togglePassword()">👁</span>
        </div>

        <button type="submit" class="primary">Login</button>
    </form>

    <div style="display: flex; justify-content: space-between; padding: 0.5rem 0;">
        <p>Don't have an account? <a
                href="/register/school<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>">Register
                here</a></p>
        <p><a href="/reset-password/school" style="color: #666;">Forgot password?</a></p>
    </div>

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