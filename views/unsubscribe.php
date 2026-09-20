<?php
// views/unsubscribe.php - 1-Click Email Unsubscribe Handler & Confirmation
require_once __DIR__ . '/../config.php';

$email = trim($_GET['email'] ?? $_GET['unsub'] ?? '');
$status = 'idle';
$message = '';

$unsubFile = __DIR__ . '/../data/unsubscribes.json';
if (!is_dir(dirname($unsubFile))) {
    mkdir(dirname($unsubFile), 0755, true);
}

$unsubList = file_exists($unsubFile) ? json_decode(file_get_contents($unsubFile), true) : [];
if (!is_array($unsubList)) {
    $unsubList = [];
}

// Process Unsubscribe
if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $normalizedEmail = strtolower($email);

    // Add to unsubscribes list if not already present
    if (!isset($unsubList[$normalizedEmail])) {
        $unsubList[$normalizedEmail] = [
            'unsubscribed_at' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        file_put_contents($unsubFile, json_encode($unsubList, JSON_PRETTY_PRINT));
    }

    // Also record in campaign tracker if exists
    $trackerFile = __DIR__ . '/../data/campaign_tracker.json';
    if (file_exists($trackerFile)) {
        $tracker = json_decode(file_get_contents($trackerFile), true);
        if (is_array($tracker) && isset($tracker[$normalizedEmail])) {
            $tracker[$normalizedEmail]['unsubscribed'] = true;
            $tracker[$normalizedEmail]['unsubscribed_at'] = date('Y-m-d H:i:s');
            file_put_contents($trackerFile, json_encode($tracker, JSON_PRETTY_PRINT));
        }
    }

    $status = 'success';
    $message = "You have been successfully unsubscribed from MwalimuLink educator notifications.";
} elseif (!empty($email)) {
    $status = 'error';
    $message = "Invalid email address format provided.";
}
?>

<div class="container" style="max-width: 640px; margin: 4rem auto; padding: 0 1rem;">
    <div
        style="background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 2.5rem; text-align: center; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05);">

        <?php if ($status === 'success'): ?>
            <div
                style="width: 64px; height: 64px; background: #ecfdf5; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto; color: #059669; font-size: 28px;">
                ✓
            </div>
            <h1 style="color: #0f172a; font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem;">
                Unsubscribed Successfully
            </h1>
            <p style="color: #475569; font-size: 1rem; line-height: 1.6; margin-bottom: 1.5rem;">
                <strong><?= htmlspecialchars($email) ?></strong> has been permanently removed from our educator outreach and
                newsletter list. You will not receive further marketing emails from us.
            </p>
            <div
                style="background: #f8fafc; border-radius: 8px; padding: 1rem; margin-bottom: 2rem; color: #64748b; font-size: 0.875rem;">
                If you ever wish to join MwalimuLink or browse verified teaching vacancies, you can always visit our
                website.
            </div>
            <div>
                <a href="/" class="primary"
                    style="display: inline-block; background: #0f766e; color: #ffffff; padding: 0.75rem 1.75rem; border-radius: 8px; font-weight: 600; text-decoration: none;">
                    Visit MwalimuLink Homepage
                </a>
            </div>

        <?php elseif ($status === 'error'): ?>
            <div
                style="width: 64px; height: 64px; background: #fef2f2; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto; color: #dc2626; font-size: 28px;">
                ✕
            </div>
            <h1 style="color: #0f172a; font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem;">
                Unsubscribe Request
            </h1>
            <p style="color: #dc2626; font-size: 1rem; line-height: 1.6; margin-bottom: 1.5rem;">
                <?= htmlspecialchars($message) ?>
            </p>
            <form method="GET" action="/unsubscribe" style="margin-top: 1.5rem;">
                <input type="email" name="email" placeholder="Enter your email address" required
                    style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 1rem; box-sizing: border-box;">
                <button type="submit"
                    style="width: 100%; background: #0f766e; color: #ffffff; padding: 0.75rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Unsubscribe Me
                </button>
            </form>

        <?php else: ?>
            <div
                style="width: 64px; height: 64px; background: #f0fdfa; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem auto; color: #0f766e; font-size: 28px;">
                ✉
            </div>
            <h1 style="color: #0f172a; font-size: 1.5rem; font-weight: 700; margin-bottom: 0.75rem;">
                Manage Email Preferences
            </h1>
            <p style="color: #475569; font-size: 1rem; line-height: 1.6; margin-bottom: 1.5rem;">
                Enter your email address below to unsubscribe from all MwalimuLink educator notifications.
            </p>
            <form method="GET" action="/unsubscribe" style="margin-top: 1.5rem;">
                <input type="email" name="email" placeholder="e.g. teacher@gmail.com" required
                    style="width: 100%; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 1rem; box-sizing: border-box;">
                <button type="submit"
                    style="width: 100%; background: #0f766e; color: #ffffff; padding: 0.75rem; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    Unsubscribe
                </button>
            </form>
        <?php endif; ?>

    </div>
</div>