<?php
// reset_school_password.php

$message = '';
$msgType = 'info';

// Process password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token']) && isset($_POST['email'])) {
    $token = trim($_POST['token'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate email and token
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $msgType = 'error';
    } elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long.";
        $msgType = 'error';
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
        $msgType = 'error';
    } else {
        // Find the school with the given email and token
        $school = R::findOne('school', 'email = ? AND reset_token = ? AND reset_expiry > NOW()', [$email, $token]);

        if (!$school) {
            $message = "Invalid or expired reset link. Please request a new password reset.";
            $msgType = 'error';
        } else {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password and clear reset token
            $school->password = $hashed_password;
            $school->reset_token = null;
            $school->reset_expiry = null;
            R::store($school);

            $message = "Your institutional password has been reset successfully! <a href='/login/school' style='color: #0f766e; font-weight: 700;'>Click here to Login</a>";
            $msgType = 'success';
        }
    }
} elseif (isset($_GET['email'])) {
    // Email is provided, generate token and send email
    $email = strtolower(trim($_GET['email'] ?? ''));

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid institutional email address.";
        $msgType = 'error';
    } else {
        // Check if email exists in the database
        $school = R::findOne('school', 'email = ?', [$email]);

        if (!$school) {
            // Friendly message without leaking user existence
            $message = "If that email is registered as an institutional account, a secure password reset link has been dispatched to your inbox.";
            $msgType = 'info';
        } else {
            // Generate unique token
            $token = bin2hex(random_bytes(32));

            // Set expiration time (1 hour from now)
            $expiry_time = date('Y-m-d H:i:s', time() + 3600);

            // Store the token and expiry time in the database
            $school->reset_token = $token;
            $school->reset_expiry = $expiry_time;
            R::store($school);

            // Construct reset password link
            $appUrl = rtrim(env('APP_URL', 'https://mwalimu.info'), '/');
            $reset_link = "{$appUrl}/reset-password/school?token=" . $token . "&email=" . urlencode($email);

            // Styled HTML Email Notification
            $subject = "Reset Your MwalimuLink School Portal Password";
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
?>

<article class="card" style="max-width: 60%; margin: 2rem auto;">
    <header>
        <h2 style="text-align: center;">Reset School Password</h2>
    </header>

    <?php if ($message): ?>
        <div class="alert <?= $msgType === 'success' ? 'alert-success' : ($msgType === 'error' ? 'alert-error' : 'alert-info') ?>" style="padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px;">
            <p style="margin: 0; font-size: 0.95rem; line-height: 1.5;"><?= $message ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['token']) && isset($_GET['email'])): ?>
        <form method="post">
            <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">
            <input type="hidden" name="email" value="<?= htmlspecialchars($_GET['email']) ?>">

            <div class="grid">
                <label for="new_password">New Password</label>
                <input type="password" id="new_password" name="new_password" required>
            </div>

            <div class="grid">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <button type="submit">Update Password</button>
        </form>
    <?php else: ?>
        <form method="get">
            <div class="grid">
                <input type="email" id="email" placeholder="Email" name="email" required>
            </div>
            <button type="submit">Submit</button>
        </form>
    <?php endif; ?>
</article>
