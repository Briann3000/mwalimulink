<?php
// reset_school_password.php

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$message = '';

// Process password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token']) && isset($_POST['email'])) {
    $token = filter_var($_POST['token'], FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate email and token
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email address.";
    } elseif (strlen($new_password) < 8) {
        $message = "Password must be at least 8 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
    } else {
        // Find the school with the given email and token
        $school = R::findOne('school', 'email = ? AND reset_token = ? AND reset_expiry > NOW()', [$email, $token]);

        if (!$school) {
            $message = "Invalid or expired reset link. Please request a new password reset.";
        } else {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password and clear reset token
            $school->password = $hashed_password;
            $school->reset_token = null;
            $school->reset_expiry = null;
            R::store($school);

            $message = "Password updated successfully. <a href='index.php?action=school_login'>Login here</a>";
        }
    }
} elseif (isset($_GET['email'])) {
    // Email is provided, generate token and send email
    $email = filter_var($_GET['email'], FILTER_SANITIZE_EMAIL);

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Invalid email address.";
    } else {
        // Check if email exists in the database
        $school = R::findOne('school', 'email = ?', [$email]);

        if (!$school) {
            $message = "Email address not found in our database.";
        } else {
            // Generate unique token
            $token = bin2hex(random_bytes(32));

            // Set expiration time (e.g., 1 hour)
            $expiry_time = date('Y-m-d H:i:s', time() + 3600); // 1 hour from now

            // Store the token and expiry time in the database
            $school->reset_token = $token;
            $school->reset_expiry = $expiry_time;
            R::store($school);

            // Construct reset password link
            $reset_link = "https://mwalimu.info/index.php?action=reset_school_password&token=" . $token . "&email=" . urlencode($email);

            // Send email with reset password link
            $subject = "Reset Your Password";
            $message_body = "Please click on the following link to reset your password:\n\n" . $reset_link;
            $headers = "From: noreply@mwalimu.info"; // Replace with your email address

            if (mail($email, $subject, $message_body, $headers)) {
                $message = "A password reset link has been sent to your email address.";
            } else {
                $message = "Failed to send email. Please try again later.";
            }
        }
    }
} else {
    $message = "Email address is required to reset password.";
}
?>

<article class="card" style="max-width: 60%; margin: 2rem auto;">
    <header>
        <h2 style="text-align: center;">Reset School Password</h2>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-info">
            <h4><?= htmlspecialchars($message) ?></h4>
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
