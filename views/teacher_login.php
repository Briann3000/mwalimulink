<?php
// views/teacher_login.php

$error_message = null;
$redirect = trim($_GET['redirect'] ?? ($_POST['redirect'] ?? ''));
if (!empty($redirect) && (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//'))) {
    $redirect = '';
}
$notice = $_GET['notice'] ?? '';

// If already logged in, redirect to dashboard or redirect URL
if (is_logged_in() && has_role('teacher')) {
    $dest = !empty($redirect) ? $redirect : '/teacher/dashboard';
    header("Location: $dest");
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $teacher = R::findOne('teacher', 'email = ?', [$email]);

    if ($teacher && password_verify($password, $teacher->password)) {
        auth_login($teacher, 'teacher');
        $dest = !empty($redirect) ? $redirect : '/teacher/dashboard';
        header("Location: $dest");
        exit();
    } else {
        $error_message = "Invalid email or password.";
    }
}
?>

<article class="card" style="max-width: 80%; margin: 2rem auto;">
    <header>
        <h2 style="text-align: center;">Teacher Login</h2>
    </header>

    <?php if ($notice === 'auth_required'): ?>
        <div
            style="background: #f0fdfa; border: 1px solid #99f6e4; color: #0f766e; padding: 12px 16px; border-radius: 6px; margin: 1rem; font-size: 0.88rem;">
            <strong>Access Private & International Schools:</strong> Please sign in to your Teacher account to unlock
            directory access.
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-error">
            <h4><?php echo htmlspecialchars($error_message); ?></h4>
        </div>
    <?php endif; ?>

    <form method="POST" style="padding: 1rem;">
        <?= csrf_field() ?>
        <?php if (!empty($redirect)): ?>
            <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
        <?php endif; ?>
        <label for="email">Email:</label>
        <input type="email" name="email" id="email" required>

        <label for="password">Password:</label>
        <div class="password-input">
            <input type="password" name="password" id="password" required>
            <span class="password-toggle" onclick="togglePassword()">👁</span>
        </div>

        <button type="submit" class="primary" style="width: 100%;">Login</button>
    </form>

    <div style="display: flex; justify-content: space-between; padding: 0 1rem 1rem;">
        <p>Don't have an account? <a
                href="/register/teacher<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>">Register
                here</a></p>
        <p><a href="/reset-password/teacher" style="color: #666;">Forgot password?</a></p>
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