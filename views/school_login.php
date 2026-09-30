<?php
// views/school_login.php - Dedicated School & Institutional Sign In Portal

$school_error_message = null;
$redirect = trim($_GET['redirect'] ?? ($_POST['redirect'] ?? ''));
if (!empty($redirect) && (!str_starts_with($redirect, '/') || str_starts_with($redirect, '//'))) {
    $redirect = '';
}
$notice = $_GET['notice'] ?? '';

// If already logged in as school, redirect to dashboard or redirect URL
if (is_logged_in() && has_role('school')) {
    $dest = !empty($redirect) ? $redirect : '/school/dashboard';
    header("Location: $dest");
    exit();
}

// Handle login form submission for schools
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    $rateCheck = check_login_rate_limit($email);
    if (!$rateCheck['allowed']) {
        $school_error_message = "Too many failed login attempts. Please wait {$rateCheck['retry_after']} minute(s) before trying again.";
    } elseif (empty($email) || empty($password)) {
        $school_error_message = "Please enter both your institutional email address and password.";
    } else {
        $school = R::findOne('school', 'email = ?', [$email]);

        if ($school && password_verify($password, $school->password)) {
            if ($school->status === 'suspended') {
                record_failed_login($email);
                $school_error_message = "This institutional account has been temporarily suspended by administration. Please contact support.";
            } else {
                clear_login_rate_limit($email);
                auth_login($school, 'school');
                $dest = !empty($redirect) ? $redirect : '/school/dashboard';
                header("Location: $dest");
                exit();
            }
        } else {
            record_failed_login($email);
            $school_error_message = "Invalid institutional email or password.";
        }
    }
}
?>

<div class="container" style="max-width: 460px; margin: 3rem auto 4rem; padding: 0 1rem;">
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04);">
        
        <div style="text-align: center; margin-bottom: 1.75rem;">
            <div style="width: 54px; height: 54px; border-radius: 50%; background: #eff6ff; color: #1d4ed8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 0.75rem; border: 1px solid #dbeafe;">
                <i class="fa fa-school"></i>
            </div>
            <h2 style="margin: 0 0 6px; font-size: 1.45rem; color: #0f172a; font-weight: 800;">School Sign In</h2>
            <p style="margin: 0; font-size: 0.88rem; color: #64748b;">Post teaching vacancies, search educators, and manage staff.</p>
        </div>

        <?php if ($notice === 'auth_required'): ?>
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 12px 14px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.86rem; display: flex; align-items: flex-start; gap: 8px;">
                <i class="fa fa-lock" style="margin-top: 2px;"></i>
                <div>
                    <strong>Institutional Access Required:</strong> Please sign in to your school account to manage recruitment and candidates.
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($school_error_message)): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 14px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-exclamation-circle"></i> <?= h($school_error_message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="/login/school" style="margin: 0;">
            <?= csrf_field() ?>
            <?php if (!empty($redirect)): ?>
                <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
            <?php endif; ?>

            <div style="margin-bottom: 1.25rem;">
                <label for="email" style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Institutional Email</label>
                <input type="email" name="email" id="email" value="<?= h($_POST['email'] ?? '') ?>" placeholder="e.g. info@school.ac.ke" required style="width: 100%; box-sizing: border-box; height: 44px; margin: 0; background: #ffffff; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.92rem;">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <label for="password" style="font-size: 0.82rem; font-weight: 700; color: #334155; margin: 0;">Password</label>
                    <a href="/reset-password/school" style="font-size: 0.78rem; color: #1d4ed8; font-weight: 600; text-decoration: none;">Forgot Password?</a>
                </div>
                <div style="position: relative; display: flex; align-items: center;">
                    <input type="password" name="password" id="password" placeholder="Enter school password" required style="width: 100%; box-sizing: border-box; height: 44px; margin: 0 !important; margin-bottom: 0 !important; background: #ffffff; padding: 0 42px 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.92rem;">
                    <span role="button" tabindex="0" onclick="togglePasswordVisibility('password', 'toggleEyeIcon')" class="password-eye-toggle" title="Show/Hide Password">
                        <i class="fa fa-eye" id="toggleEyeIcon"></i>
                    </span>
                </div>
            </div>

            <button type="submit" class="btn-primary" style="width: 100%; height: 46px; font-size: 0.95rem; font-weight: 700; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                <i class="fa fa-sign-in-alt"></i> Sign In as School
            </button>
        </form>

        <div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; text-align: center; font-size: 0.85rem; color: #64748b;">
            Not registered yet? <a href="/register/school<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>" style="color: #0f766e; font-weight: 700;">Register Institution &rarr;</a>
        </div>

        <div style="margin-top: 1rem; text-align: center; font-size: 0.82rem; color: #64748b; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px solid #f1f5f9;">
            <i class="fa fa-graduation-cap" style="color: #0f766e; margin-right: 4px;"></i> Looking for Teaching Jobs? <a href="/login/teacher<?= !empty($redirect) ? '?redirect=' . urlencode($redirect) : '' ?>" style="color: #0f766e; font-weight: 700;">Teacher Login &rarr;</a>
        </div>
    </div>
</div>

<script>
    function togglePasswordVisibility(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input && icon) {
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
    }
</script>