<?php
// views/admin_pricing.php - Commercial Pricing, Subscription Plans & System Settings Control Panel
require_auth('admin');

$authUser = auth_user();
$msg = '';
$error = '';

// Handle Form Submission for Updating Settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please refresh and try again.";
    } else {
        $dirFee = floatval($_POST['directory_fee'] ?? 100);
        $dirMonths = intval($_POST['directory_access_months'] ?? 12);
        $schoolFee = floatval($_POST['school_pro_fee'] ?? 1000);
        $schoolMonths = intval($_POST['school_pro_months'] ?? 12);
        $currency = strtoupper(trim($_POST['payment_currency'] ?? 'KES'));

        if ($dirFee <= 0 || $schoolFee <= 0) {
            $error = "Pricing fees must be positive numbers greater than 0.";
        } elseif ($dirMonths <= 0 || $schoolMonths <= 0) {
            $error = "Access durations must be at least 1 month.";
        } else {
            set_setting('directory_fee', $dirFee, 'Directory Access Pass Fee (KES)', $authUser['email']);
            set_setting('directory_access_months', $dirMonths, 'Directory Access Pass Duration (Months)', $authUser['email']);
            set_setting('school_pro_fee', $schoolFee, 'School Pro Subscription Fee (KES)', $authUser['email']);
            set_setting('school_pro_months', $schoolMonths, 'School Pro Subscription Duration (Months)', $authUser['email']);
            set_setting('payment_currency', $currency, 'Default Gateway Currency', $authUser['email']);

            $msg = "System pricing and access settings updated successfully!";
        }
    }
}

// Current Values from DB with safe defaults
$currentDirFee = floatval(get_setting('directory_fee', 100));
$currentDirMonths = intval(get_setting('directory_access_months', 12));
$currentSchoolFee = floatval(get_setting('school_pro_fee', 1000));
$currentSchoolMonths = intval(get_setting('school_pro_months', 12));
$currentCurrency = (string) get_setting('payment_currency', 'KES');

// Analytics & Metrics
$totalDirPasses = 0;
$totalDirRevenue = 0;
$activeSchoolPro = 0;
$totalSchoolRevenue = 0;
$recentPayments = [];

try {
    if (class_exists('R')) {
        $totalDirPasses = R::count('directoryaccess', 'status = "active"');
        $dirRow = R::getRow("SELECT SUM(amount) as total FROM payment WHERE purpose = 'directory_access' AND state = 'COMPLETE'");
        $totalDirRevenue = floatval($dirRow['total'] ?? 0);

        $activeSchoolPro = R::count('school', 'status = "active" AND subscription_expiry >= NOW()');
        $schoolRow = R::getRow("SELECT SUM(amount) as total FROM payment WHERE purpose = 'school_subscription' AND state = 'COMPLETE'");
        $totalSchoolRevenue = floatval($schoolRow['total'] ?? 0);

        $recentPayments = R::find('payment', 'ORDER BY id DESC LIMIT 10');
    }
} catch (\Exception $e) {
    // fallback
}

// Audit Logs for Pricing
$pricingAudits = [];
try {
    if (class_exists('R')) {
        $pricingAudits = R::find('adminauditlog', 'category = "pricing" OR action_type = "SETTING_UPDATED" ORDER BY id DESC LIMIT 15');
    }
} catch (\Exception $e) {
    // fallback
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="max-width: 1150px; margin: 0 auto; padding: 2rem 1.5rem;">

        <!-- Header -->
        <div
            style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="margin: 0; font-size: 1.5rem; color: #0f172a; font-weight: 800;">
                    ⚙️ Commercial Pricing & Access Control
                </h1>
                <p style="margin: 4px 0 0; font-size: 0.88rem; color: #64748b;">
                    Dynamically configure pricing tiers, directory access passes, institutional subscriptions, and view
                    audit history.
                </p>
            </div>
            <div>
                <a href="/admin/audit"
                    style="background: white; color: #334155; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-clipboard-list"></i> Full System Audit Trail &rarr;
                </a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div
                style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 14px 18px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-check-circle" style="font-size: 1.2rem;"></i>
                <div><?= h($msg) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div
                style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-exclamation-circle" style="font-size: 1.2rem;"></i>
                <div><?= h($error) ?></div>
            </div>
        <?php endif; ?>

        <!-- KPI Metric Cards -->
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">

            <div class="metric-card"
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; border-left: 4px solid #0f766e;">
                <div style="font-size: 0.76rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Active
                    Directory Passes</div>
                <div style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 4px 0;">
                    <?= number_format($totalDirPasses) ?></div>
                <div style="font-size: 0.8rem; color: #0f766e; font-weight: 600;">
                    <i class="fa fa-receipt"></i> <?= $currentCurrency ?> <?= number_format($totalDirRevenue) ?>
                    collected
                </div>
            </div>

            <div class="metric-card"
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; border-left: 4px solid #2563eb;">
                <div style="font-size: 0.76rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Active
                    School Pro Subscriptions</div>
                <div style="font-size: 1.85rem; font-weight: 800; color: #0f172a; margin: 4px 0;">
                    <?= number_format($activeSchoolPro) ?></div>
                <div style="font-size: 0.8rem; color: #2563eb; font-weight: 600;">
                    <i class="fa fa-crown"></i> <?= $currentCurrency ?> <?= number_format($totalSchoolRevenue) ?>
                    collected
                </div>
            </div>

            <div class="metric-card"
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; border-left: 4px solid #16a34a;">
                <div style="font-size: 0.76rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total
                    Platform Revenue</div>
                <div style="font-size: 1.85rem; font-weight: 800; color: #16a34a; margin: 4px 0;">
                    <?= $currentCurrency ?> <?= number_format($totalDirRevenue + $totalSchoolRevenue) ?></div>
                <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">
                    <i class="fa fa-shield-alt"></i> Verified via IntaSend
                </div>
            </div>

        </div>

        <!-- 2-Column Grid: Pricing Form & Audit Log -->
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 1.75rem; align-items: start; margin-bottom: 2.5rem;">

            <!-- Left Card: Dynamic Pricing Edit Form -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 1.25rem;">
                    <i class="fa fa-sliders" style="color: #0f766e; font-size: 1.2rem;"></i>
                    <h3 style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 700;">Pricing & Duration
                        Rules</h3>
                </div>

                <form method="POST" action="/admin/pricing" style="margin: 0;">
                    <?= csrf_field() ?>

                    <!-- Section: Directory Access Pass -->
                    <div
                        style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                        <h4
                            style="margin: 0 0 10px; font-size: 0.95rem; color: #0f766e; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                            <i class="fa fa-book-open"></i> Private & International School Directory Pass
                        </h4>
                        <p style="margin: 0 0 12px; font-size: 0.8rem; color: #64748b;">
                            Applies to individual teachers and unregistered accounts accessing private & international
                            school tables.
                        </p>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label for="directory_fee"
                                    style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 4px;">
                                    Fee (<?= h($currentCurrency) ?>)
                                </label>
                                <input type="number" step="1" min="1" name="directory_fee" id="directory_fee"
                                    value="<?= h($currentDirFee) ?>" required
                                    style="width: 100%; box-sizing: border-box; height: 40px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 10px; font-weight: 700; font-size: 1rem; color: #0f172a;">
                            </div>
                            <div>
                                <label for="directory_access_months"
                                    style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 4px;">
                                    Access Duration (Months)
                                </label>
                                <input type="number" step="1" min="1" max="60" name="directory_access_months"
                                    id="directory_access_months" value="<?= h($currentDirMonths) ?>" required
                                    style="width: 100%; box-sizing: border-box; height: 40px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 10px; font-weight: 700; font-size: 1rem; color: #0f172a;">
                            </div>
                        </div>
                    </div>

                    <!-- Section: School Pro Subscription -->
                    <div
                        style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem;">
                        <h4
                            style="margin: 0 0 10px; font-size: 0.95rem; color: #2563eb; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                            <i class="fa fa-school"></i> School Pro Institutional Subscription
                        </h4>
                        <p style="margin: 0 0 12px; font-size: 0.8rem; color: #64748b;">
                            Enables unlimited job postings, teacher candidate search, and verified badge for registered
                            schools.
                        </p>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div>
                                <label for="school_pro_fee"
                                    style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 4px;">
                                    Fee (<?= h($currentCurrency) ?>)
                                </label>
                                <input type="number" step="1" min="1" name="school_pro_fee" id="school_pro_fee"
                                    value="<?= h($currentSchoolFee) ?>" required
                                    style="width: 100%; box-sizing: border-box; height: 40px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 10px; font-weight: 700; font-size: 1rem; color: #0f172a;">
                            </div>
                            <div>
                                <label for="school_pro_months"
                                    style="display: block; font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 4px;">
                                    Duration (Months)
                                </label>
                                <input type="number" step="1" min="1" max="60" name="school_pro_months"
                                    id="school_pro_months" value="<?= h($currentSchoolMonths) ?>" required
                                    style="width: 100%; box-sizing: border-box; height: 40px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 10px; font-weight: 700; font-size: 1rem; color: #0f172a;">
                            </div>
                        </div>
                    </div>

                    <!-- Section: Gateway Currency -->
                    <div style="margin-bottom: 1.5rem;">
                        <label for="payment_currency"
                            style="display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 4px;">
                            Gateway Billing Currency
                        </label>
                        <select name="payment_currency" id="payment_currency"
                            style="width: 100%; box-sizing: border-box; height: 40px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 10px; font-weight: 600;">
                            <option value="KES" <?= $currentCurrency === 'KES' ? 'selected' : '' ?>>KES - Kenyan Shilling
                            </option>
                            <option value="USD" <?= $currentCurrency === 'USD' ? 'selected' : '' ?>>USD - US Dollar
                            </option>
                        </select>
                    </div>

                    <button type="submit"
                        style="width: 100%; background: #0f766e; color: white; border: none; padding: 12px; border-radius: 6px; font-weight: 700; font-size: 0.95rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 2px 4px rgba(15,118,110,0.25);">
                        <i class="fa fa-save"></i> Save & Apply Pricing Changes
                    </button>
                </form>
            </div>

            <!-- Right Card: Pricing Change Audit Trail -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div
                    style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-history" style="color: #64748b; font-size: 1.2rem;"></i>
                        <h3 style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 700;">Pricing Audit Log
                        </h3>
                    </div>
                    <span
                        style="font-size: 0.75rem; color: #64748b; background: #f1f5f9; padding: 2px 8px; border-radius: 12px;">Auto-Logged</span>
                </div>

                <div style="max-height: 480px; overflow-y: auto; padding-right: 4px;">
                    <?php if (empty($pricingAudits)): ?>
                        <div style="text-align: center; padding: 2.5rem 1rem; color: #94a3b8; font-size: 0.88rem;">
                            <i class="fa fa-info-circle" style="font-size: 1.5rem; margin-bottom: 8px; display: block;"></i>
                            No custom pricing changes recorded yet. Default baseline pricing is active.
                        </div>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach ($pricingAudits as $audit): ?>
                                <div
                                    style="background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 8px; padding: 12px; font-size: 0.82rem;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                        <strong
                                            style="color: #0f172a;"><?= h($audit->target_name ?: $audit->action_type) ?></strong>
                                        <span
                                            style="color: #94a3b8; font-size: 0.72rem;"><?= date('M d, H:i', strtotime($audit->created_at)) ?></span>
                                    </div>
                                    <div style="color: #475569; margin-bottom: 4px;"><?= h($audit->details) ?></div>
                                    <div style="color: #64748b; font-size: 0.72rem;">
                                        <i class="fa fa-user"></i> Admin: <strong><?= h($audit->actor_email) ?></strong>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>

        <!-- Recent Transactions Table -->
        <div
            style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 700;">Recent Payment Transactions
                </h3>
                <span style="font-size: 0.78rem; color: #64748b;">Latest 10 records</span>
            </div>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.82rem; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; color: #475569;">
                            <th style="padding: 10px 12px;">ID / Date</th>
                            <th style="padding: 10px 12px;">User / Email</th>
                            <th style="padding: 10px 12px;">Phone</th>
                            <th style="padding: 10px 12px;">Purpose</th>
                            <th style="padding: 10px 12px;">Amount</th>
                            <th style="padding: 10px 12px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentPayments)): ?>
                            <tr>
                                <td colspan="6" style="padding: 24px; text-align: center; color: #94a3b8;">No payment
                                    records found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentPayments as $p): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 10px 12px;">
                                        <strong>#<?= $p->id ?></strong><br>
                                        <span
                                            style="color: #94a3b8; font-size: 0.72rem;"><?= date('M d, Y H:i', strtotime($p->created_at)) ?></span>
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <?= h($p->email) ?><br>
                                        <span
                                            style="color: #64748b; font-size: 0.72rem; text-transform: capitalize;"><?= h($p->user_type) ?></span>
                                    </td>
                                    <td style="padding: 10px 12px; font-weight: 600;"><?= h($p->phone) ?></td>
                                    <td style="padding: 10px 12px;">
                                        <?php if ($p->purpose === 'school_subscription'): ?>
                                            <span
                                                style="background: #eff6ff; color: #1d4ed8; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 0.72rem;">School
                                                Pro</span>
                                        <?php else: ?>
                                            <span
                                                style="background: #f0fdf4; color: #166534; padding: 2px 6px; border-radius: 4px; font-weight: 700; font-size: 0.72rem;">Directory
                                                Pass</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px 12px; font-weight: 700; color: #0f172a;">
                                        <?= h($p->currency ?: 'KES') ?>         <?= number_format($p->amount) ?>
                                    </td>
                                    <td style="padding: 10px 12px;">
                                        <?php if (strtoupper($p->state) === 'COMPLETE'): ?>
                                            <span
                                                style="background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 12px; font-weight: 700; font-size: 0.72rem;">✓
                                                Complete</span>
                                        <?php elseif (strtoupper($p->state) === 'FAILED'): ?>
                                            <span
                                                style="background: #fee2e2; color: #991b1b; padding: 3px 8px; border-radius: 12px; font-weight: 700; font-size: 0.72rem;">✗
                                                Failed</span>
                                        <?php else: ?>
                                            <span
                                                style="background: #fef3c7; color: #b45309; padding: 3px 8px; border-radius: 12px; font-weight: 700; font-size: 0.72rem;">⏳
                                                <?= h($p->state) ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>
</div>