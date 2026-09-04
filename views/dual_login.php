<?php
// views/dual_login.php - Smart Unified Login (Auto-detects Teacher, School, or Admin)
$error_message = null;

// Auto redirect if already logged in
if (is_logged_in()) {
    if (has_role('school')) {
        header('Location: /school/dashboard');
        exit();
    } elseif (has_role('teacher')) {
        header('Location: /teacher/dashboard');
        exit();
    } elseif (has_role('admin')) {
        header('Location: /admin/dashboard');
        exit();
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error_message = "Please enter both your email address and password.";
    } else {
        // 1. Check if it is the System Administrator
        $admin_email = strtolower(env('ADMIN_EMAIL', 'admin@mwalimu.info'));
        $admin_password = env('ADMIN_PASSWORD', 'Kenya@254');

        if ($email === $admin_email && $password === $admin_password) {
            $adminObj = (object)[
                'id' => 1,
                'name' => 'Administrator',
                'email' => $admin_email
            ];
            auth_login($adminObj, 'admin');
            header('Location: /admin/dashboard');
            exit();
        }

        // 2. Check if it is a Teacher
        $teacher = R::findOne('teacher', 'email = ?', [$email]);
        if ($teacher && password_verify($password, $teacher->password)) {
            auth_login($teacher, 'teacher');
            header('Location: /teacher/dashboard');
            exit();
        }

        // 3. Check if it is a School
        $school = R::findOne('school', 'email = ?', [$email]);
        if ($school && password_verify($password, $school->password)) {
            auth_login($school, 'school');
            header('Location: /school/dashboard');
            exit();
        }

        $error_message = "Invalid email or password. Please check your credentials and try again.";
    }
}
?>

<div class="container" style="max-width: 480px; margin: 3rem auto 4rem; padding: 0 1rem;">
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.25rem; box-shadow: 0 4px 16px rgba(0,0,0,0.04);">
        
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="width: 52px; height: 52px; border-radius: 50%; background: #f0fdfa; color: #0f766e; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 0.75rem;">
                <i class="fa fa-lock"></i>
            </div>
            <h2 style="margin: 0 0 6px; font-size: 1.45rem; color: #0f172a; font-weight: 800;">Sign in to MwalimuLink</h2>
            <p style="margin: 0; font-size: 0.88rem; color: #64748b;">Enter your email to access your Educator or School portal.</p>
        </div>

        <?php if ($error_message): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-exclamation-circle"></i> <?= h($error_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login" style="margin: 0;">
            <?= csrf_field() ?>

            <div style="margin-bottom: 1.25rem;">
                <label for="email" style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Email Address</label>
                <input type="email" name="email" id="email" value="<?= h($_POST['email'] ?? '') ?>" placeholder="e.g. teacher@gmail.com or info@school.ac.ke" required style="width: 100%; box-sizing: border-box; height: 44px; margin: 0; background: #ffffff;">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="password" style="font-size: 0.82rem; font-weight: 700; color: #334155; margin: 0;">Password</label>
                    <a href="/reset-password/teacher" style="font-size: 0.78rem; color: #0f766e; font-weight: 600;">Forgot Password?</a>
                </div>
                <div style="position: relative;">
                    <input type="password" name="password" id="password" placeholder="Enter your password" required style="width: 100%; box-sizing: border-box; height: 44px; margin: 0; background: #ffffff; padding-right: 40px;">
                    <button type="button" onclick="togglePasswordVisibility()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #64748b; cursor: pointer; padding: 4px; font-size: 0.95rem;">
                        <i class="fa fa-eye" id="togglePasswordIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; height: 46px; font-size: 0.95rem; font-weight: 700; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #f1f5f9; text-align: center; font-size: 0.85rem; color: #64748b;">
            Don't have an account yet?<br>
            <div style="margin-top: 8px; display: flex; justify-content: center; gap: 12px;">
                <a href="/register/teacher" style="color: #0f766e; font-weight: 700;">Join as Teacher →</a>
                <span style="color: #cbd5e1;">&bull;</span>
                <a href="/register/school" style="color: #0f766e; font-weight: 700;">Register School →</a>
            </div>
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const input = document.getElementById('password');
    const icon = document.getElementById('togglePasswordIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
