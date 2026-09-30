<?php
// views/reset_school_password.php - Secure Password Reset for Schools

$message = '';
$msgType = 'info';

// Process password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token']) && isset($_POST['email'])) {
    validate_csrf();
    $token = trim($_POST['token'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate email and token
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid institutional email address.";
        $msgType = 'error';
    } elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $msgType = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match. Please re-enter.";
        $msgType = 'error';
    } else {
        // Find school with matching email, token, and non-expired token
        $nowStr = date('Y-m-d H:i:s');
        $school = R::findOne('school', 'email = ? AND reset_token = ? AND reset_expiry > ?', [$email, $token, $nowStr]);

        if (!$school) {
            $message = "This institutional password reset link is invalid or has expired. Please request a new one.";
            $msgType = 'error';
        } else {
            // Hash new password and clear token
            $school->password = password_hash($new_password, PASSWORD_DEFAULT);
            $school->reset_token = null;
            $school->reset_expiry = null;
            R::store($school);

            $message = "Your institutional password has been reset successfully! <a href='/login/school' style='color: #0f766e; font-weight: 700;'>Click here to Login</a>";
            $msgType = 'success';
        }
    }
} elseif (isset($_GET['email']) && !isset($_GET['token'])) {
    // Generate token and send email
    $email = strtolower(trim($_GET['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid institutional email address.";
        $msgType = 'error';
    } else {
        $school = R::findOne('school', 'email = ?', [$email]);

        if (!$school) {
            // Friendly message without leaking user existence
            $message = "If that email is registered as an institutional account, a secure password reset link has been dispatched to your inbox.";
            $msgType = 'info';
        } else {
            $token = bin2hex(random_bytes(32));
            $expiry_time = date('Y-m-d H:i:s', time() + 3600);

            $school->reset_token = $token;
            $school->reset_expiry = $expiry_time;
            R::store($school);

            $appUrl = rtrim(env('APP_URL', 'https://mwalimu.info'), '/');
            $reset_link = "{$appUrl}/reset-password/school?token=" . $token . "&email=" . urlencode($email);

            $subject = "Reset Your MwalimuLink Institutional Portal Password";
            $schoolName = $school->name ?: 'School Administrator';
            $htmlBody = "
            <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;\">
                <div style=\"background: #0f172a; padding: 24px 30px; text-align: center;\">
                    <h1 style=\"color: #ffffff; margin: 0; font-size: 1.4rem; font-weight: 700;\">MwalimuLink</h1>
                    <p style=\"color: #94a3b8; margin: 4px 0 0; font-size: 0.85rem;\">Institutional Security & Access</p>
                </div>
                <div style=\"padding: 30px;\">
                    <h2 style=\"color: #0f172a; font-size: 1.25rem; margin: 0 0 14px;\">Hello " . htmlspecialchars($schoolName) . ",</h2>
                    <p style=\"color: #334155; font-size: 0.95rem; line-height: 1.6; margin: 0 0 16px;\">
                        We received a request to reset the password for your MwalimuLink institutional recruitment account. Click the button below to choose a new password:
                    </p>
                    <div style=\"text-align: center; margin: 28px 0;\">
                        <a href=\"{$reset_link}\" style=\"display: inline-block; background: #0f766e; color: #ffffff !important; font-weight: 700; font-size: 0.95rem; padding: 12px 28px; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(15,118,110,0.2);\">
                            Reset Institutional Password →
                        </a>
                    </div>
                    <p style=\"color: #64748b; font-size: 0.85rem; line-height: 1.5; margin: 20px 0 0;\">
                        This link is valid for <strong>1 hour</strong>. If you did not make this request, your account remains secure and you can safely ignore this email.
                    </p>
                </div>
                <div style=\"background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 30px; text-align: center; font-size: 0.75rem; color: #94a3b8;\">
                    <p style=\"margin: 0;\">MwalimuLink Institutional Support &bull; Nairobi, Kenya</p>
                </div>
            </div>";

            if (send_system_email($school->email, $school->name, $subject, $htmlBody)) {
                $message = "A password reset link has been dispatched to <strong>" . htmlspecialchars($email) . "</strong>. Please check your inbox or spam folder.";
                $msgType = 'success';
            } else {
                $message = "We encountered a temporary issue sending your email. Please try again or contact support at infomwalimulink@gmail.com.";
                $msgType = 'error';
            }
        }
    }
}

$hasToken = isset($_GET['token']) && isset($_GET['email']);
?>

<div class="container" style="max-width: 480px; margin: 3rem auto 4rem; padding: 0 1rem;">
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.25rem; box-shadow: 0 4px 20px rgba(0,0,0,0.04);">
        
        <div style="text-align: center; margin-bottom: 1.75rem;">
            <div style="width: 54px; height: 54px; border-radius: 50%; background: #eff6ff; color: #1d4ed8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 0.75rem; border: 1px solid #dbeafe;">
                <i class="fa fa-school-lock fa-key"></i>
            </div>
            <h2 style="margin: 0 0 6px; font-size: 1.45rem; color: #0f172a; font-weight: 800;">
                <?= $hasToken ? 'Set New School Password' : 'Reset School Password' ?>
            </h2>
            <p style="margin: 0; font-size: 0.88rem; color: #64748b;">
                <?= $hasToken ? 'Enter and confirm your new institutional account password.' : 'Enter your official registered school email to receive a password reset link.' ?>
            </p>
        </div>

        <?php if ($message): ?>
            <div style="padding: 14px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; line-height: 1.5; display: flex; align-items: flex-start; gap: 10px; background: <?= $msgType === 'success' ? '#f0fdf4; border: 1px solid #bbf7d0; color: #166534;' : ($msgType === 'error' ? '#fee2e2; border: 1px solid #fca5a5; color: #991b1b;' : '#eff6ff; border: 1px solid #bfdbfe; color: #1e40af;') ?>">
                <i class="fa <?= $msgType === 'success' ? 'fa-check-circle' : ($msgType === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle') ?>" style="margin-top: 2px;"></i>
                <div><?= $message ?></div>
            </div>
        <?php endif; ?>

        <?php if ($hasToken && $msgType !== 'success'): ?>
            <!-- Set New Password Form -->
            <form method="POST" action="/reset-password/school">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">
                <input type="hidden" name="email" value="<?= htmlspecialchars($_GET['email']) ?>">

                <div style="margin-bottom: 1.25rem;">
                    <label for="new_password" style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">New Password</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" id="new_password" name="new_password" placeholder="At least 8 characters" minlength="8" required style="width: 100%; box-sizing: border-box; height: 44px; margin: 0 !important; margin-bottom: 0 !important; background: #ffffff; padding: 0 42px 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.92rem;" oninput="onSchoolResetPassInput(this.value)">
                        <span role="button" tabindex="0" onclick="togglePasswordVisibility('new_password', 'eye_school_reset_1')" class="password-eye-toggle" title="Show/Hide Password">
                            <i class="fa fa-eye" id="eye_school_reset_1"></i>
                        </span>
                    </div>

                    <div id="schoolResetStrengthContainer" style="margin-top: 6px; display: none;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; font-size: 0.76rem;">
                            <span style="color: #64748b;">Strength:</span>
                            <span id="schoolResetStrengthLabel" style="font-weight: 700; color: #dc3545;">Weak</span>
                        </div>
                        <div style="height: 5px; background: #e2e8f0; border-radius: 3px; overflow: hidden;">
                            <div id="schoolResetStrengthBar" style="height: 100%; width: 0%; background: #dc3545; transition: all 0.3s ease;"></div>
                        </div>
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label for="confirm_password" style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Confirm New Password</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter new password" minlength="8" required style="width: 100%; box-sizing: border-box; height: 44px; margin: 0 !important; margin-bottom: 0 !important; background: #ffffff; padding: 0 42px 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.92rem;" oninput="onSchoolResetConfirmInput(this.value)">
                        <span role="button" tabindex="0" onclick="togglePasswordVisibility('confirm_password', 'eye_school_reset_2')" class="password-eye-toggle" title="Show/Hide Password">
                            <i class="fa fa-eye" id="eye_school_reset_2"></i>
                        </span>
                    </div>
                    <div id="schoolResetMatchFeedback" style="margin-top: 6px; font-size: 0.76rem; display: none;">
                        <span id="schoolResetMatchIcon"><i class="fa fa-check-circle" style="color: #10b981;"></i></span> <span id="schoolResetMatchText" style="color: #10b981; font-weight: 600;">Passwords match</span>
                    </div>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; height: 46px; font-size: 0.95rem; font-weight: 700; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fa fa-lock"></i> Update Institutional Password
                </button>
            </form>
        <?php elseif (!$hasToken): ?>
            <!-- Request Reset Email Form -->
            <form method="GET" action="/reset-password/school">
                <div style="margin-bottom: 1.25rem;">
                    <label for="email" style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Registered School Email</label>
                    <input type="email" id="email" name="email" value="<?= htmlspecialchars($_GET['email'] ?? '') ?>" placeholder="e.g. info@school.ac.ke" required style="width: 100%; box-sizing: border-box; height: 44px; margin: 0; background: #ffffff; padding: 0 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.92rem;">
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; height: 46px; font-size: 0.95rem; font-weight: 700; border-radius: 6px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="fa fa-paper-plane"></i> Send Password Reset Link
                </button>
            </form>
        <?php endif; ?>

        <div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; text-align: center; font-size: 0.85rem; color: #64748b;">
            Remembered your password? <a href="/login/school" style="color: #0f766e; font-weight: 700;">Back to School Login &rarr;</a>
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

    function onSchoolResetPassInput(val) {
        const container = document.getElementById('schoolResetStrengthContainer');
        const bar = document.getElementById('schoolResetStrengthBar');
        const label = document.getElementById('schoolResetStrengthLabel');
        if (!container || !bar || !label) return;

        if (!val) { container.style.display = 'none'; return; }
        container.style.display = 'block';

        let score = 0;
        if (val.length >= 8) score += 25;
        if (/[A-Z]/.test(val)) score += 25;
        if (/[a-z]/.test(val)) score += 25;
        if (/[0-9!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(val)) score += 25;

        if (score <= 25) {
            bar.style.width = '25%'; bar.style.background = '#dc3545';
            label.innerText = 'Weak'; label.style.color = '#dc3545';
        } else if (score <= 50) {
            bar.style.width = '50%'; bar.style.background = '#f59e0b';
            label.innerText = 'Fair'; label.style.color = '#f59e0b';
        } else if (score <= 75) {
            bar.style.width = '75%'; bar.style.background = '#0ea5e9';
            label.innerText = 'Good'; label.style.color = '#0ea5e9';
        } else {
            bar.style.width = '100%'; bar.style.background = '#10b981';
            label.innerText = 'Strong & Secure'; label.style.color = '#10b981';
        }
    }

    function onSchoolResetConfirmInput(val) {
        const pass = document.getElementById('new_password');
        const feedback = document.getElementById('schoolResetMatchFeedback');
        const icon = document.getElementById('schoolResetMatchIcon');
        const text = document.getElementById('schoolResetMatchText');
        if (!feedback || !pass) return;

        if (!val) { feedback.style.display = 'none'; return; }
        feedback.style.display = 'block';

        if (pass.value === val) {
            icon.innerHTML = '<i class="fa fa-check-circle" style="color: #10b981;"></i>';
            text.innerText = 'Passwords match'; text.style.color = '#10b981';
        } else {
            icon.innerHTML = '<i class="fa fa-times-circle" style="color: #dc3545;"></i>';
            text.innerText = 'Passwords do not match'; text.style.color = '#dc3545';
        }
    }
</script>
