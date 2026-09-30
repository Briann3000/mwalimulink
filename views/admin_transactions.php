<?php
// views/admin_transactions.php - Dedicated Financial Transactions Ledger, Gateway Reconciler & Instant M-Pesa Activation
require_auth('admin');

$authUser = auth_user();
$msg = '';
$error = '';

// Handle On-Demand Actions (Gateway Reconcile, Force Pass, Direct Manual Activation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $action = $_POST['action'] ?? '';
        $paymentId = intval($_POST['payment_id'] ?? 0);

        if ($action === 'reconcile_payment' && $paymentId > 0) {
            $payment = R::load('payment', $paymentId);
            if ($payment && $payment->id) {
                $trackingId = $payment->tracking_id ?: ($payment->invoice_id ?: $payment->invoice_code);
                $secretKey = env('INTASEND_SECRET_KEY');
                $isTestMode = (strtolower((string) env('INTASEND_TEST_MODE', 'false')) === 'true' || env('INTASEND_TEST_MODE') === '1');

                if (empty($secretKey)) {
                    $error = "IntaSend secret key is not configured in the environment.";
                } elseif (empty($trackingId)) {
                    $error = "Payment record has no tracking or invoice ID to query with IntaSend.";
                } else {
                    $statusUrl = $isTestMode
                        ? "https://sandbox.intasend.com/api/v1/payment/status/"
                        : "https://payment.intasend.com/api/v1/payment/status/";

                    $ch = curl_init($statusUrl);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['invoice_id' => $trackingId]));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: Bearer ' . $secretKey,
                        'Content-Type: application/json',
                        'Accept: application/json'
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    $result = json_decode($response, true);
                    $remoteState = strtoupper($result['invoice']['state'] ?? '');

                    if ($httpCode === 200 && in_array($remoteState, ['COMPLETE', 'SUCCESS', 'PAID'])) {
                        $payment->state = 'COMPLETE';
                        $payment->updated_at = date('Y-m-d H:i:s');
                        R::store($payment);

                        if ($payment->purpose === 'directory_access') {
                            grant_directory_access($payment->user_id, $payment->user_type, $payment->email, $payment->amount, $payment->api_ref);
                        } elseif ($payment->purpose === 'school_subscription') {
                            grant_school_pro_subscription($payment->user_id, $payment->amount, $payment->api_ref);
                        }

                        log_admin_audit('payment', 'MANUAL_RECONCILE_SUCCESS', $payment->user_type, $payment->user_id, $payment->email, "Reconciled Payment #{$payment->id} via IntaSend (State: {$remoteState})");
                        $msg = "Transaction #{$payment->id} reconciled successfully! State updated to COMPLETE and access activated.";
                    } else {
                        if ($remoteState === 'PROCESSING') {
                            $error = "IntaSend Status: PROCESSING — STK Push sent, awaiting PIN entry. If payment was settled directly, use 'Force Pass'.";
                        } else {
                            $remoteMsg = $result['detail'] ?? ($result['message'] ?? 'Gateway returned state: ' . ($remoteState ?: 'UNKNOWN'));
                            $error = "Reconciliation check failed: {$remoteMsg}";
                        }
                    }
                }
            } else {
                $error = "Payment record not found.";
            }
        } elseif ($action === 'manual_mark_complete' && $paymentId > 0) {
            $payment = R::load('payment', $paymentId);
            if ($payment && $payment->id) {
                $payment->state = 'COMPLETE';
                $payment->updated_at = date('Y-m-d H:i:s');
                R::store($payment);

                if ($payment->purpose === 'directory_access') {
                    grant_directory_access($payment->user_id, $payment->user_type, $payment->email, $payment->amount, $payment->api_ref);
                } elseif ($payment->purpose === 'school_subscription') {
                    grant_school_pro_subscription($payment->user_id, $payment->amount, $payment->api_ref);
                }

                log_admin_audit('payment', 'ADMIN_MANUAL_COMPLETE', $payment->user_type, $payment->user_id, $payment->email, "Admin {$authUser['email']} manually marked Payment #{$payment->id} as COMPLETE");
                $msg = "Payment #{$payment->id} manually marked as COMPLETE and access granted.";
            }
        } elseif ($action === 'direct_manual_activate') {
            $targetUserType = trim($_POST['target_user_type'] ?? 'teacher');
            $targetUserId = intval($_POST['target_user_id'] ?? 0);
            $mpesaCode = strtoupper(trim($_POST['mpesa_code'] ?? ''));
            $amount = floatval($_POST['amount'] ?? 0);
            $purpose = trim($_POST['purpose'] ?? 'directory_access');
            $notes = trim($_POST['notes'] ?? '');

            if ($targetUserId <= 0) {
                $error = "Please select a valid target user (Teacher or School).";
            } elseif (empty($mpesaCode)) {
                $error = "Please provide an M-Pesa transaction receipt code.";
            } else {
                $targetEmail = '';
                $targetPhone = '';
                $targetName = '';

                if ($targetUserType === 'school') {
                    $school = R::load('school', $targetUserId);
                    if ($school && $school->id) {
                        $targetEmail = $school->email;
                        $targetPhone = $school->phone;
                        $targetName = $school->name;
                    }
                } else {
                    $teacher = R::load('teacher', $targetUserId);
                    if ($teacher && $teacher->id) {
                        $targetEmail = $teacher->email;
                        $targetPhone = $teacher->phone;
                        $targetName = $teacher->name;
                    }
                }

                if (empty($targetName)) {
                    $error = "Selected {$targetUserType} account (ID: #{$targetUserId}) was not found in the database.";
                } else {
                    $payment = R::dispense('payment');
                    $payment->user_id = $targetUserId;
                    $payment->user_type = $targetUserType;
                    $payment->email = $targetEmail;
                    $payment->phone = $targetPhone;
                    $payment->amount = $amount > 0 ? $amount : ($purpose === 'school_subscription' ? 1000 : 100);
                    $payment->currency = 'KES';
                    $payment->purpose = $purpose;
                    $payment->state = 'COMPLETE';
                    $payment->api_ref = $mpesaCode;
                    $payment->invoice_code = 'MANUAL-' . strtoupper(substr(md5(uniqid()), 0, 8));
                    $payment->tracking_id = 'OFFLINE_MPESA';
                    $payment->created_at = date('Y-m-d H:i:s');
                    $payment->updated_at = date('Y-m-d H:i:s');
                    R::store($payment);

                    if ($purpose === 'school_subscription') {
                        grant_school_pro_subscription($targetUserId, $payment->amount, $mpesaCode);
                        $purposeLabel = "School Pro Tier";
                    } else {
                        grant_directory_access($targetUserId, $targetUserType, $targetEmail, $payment->amount, $mpesaCode);
                        $purposeLabel = "Directory Access Pass";
                    }

                    log_admin_audit('payment', 'MANUAL_MPESA_ACTIVATE', $targetUserType, $targetUserId, $targetEmail, "Admin {$authUser['email']} activated {$purposeLabel} with M-Pesa Code {$mpesaCode} (KES {$payment->amount}). Notes: {$notes}");
                    $msg = "Success! {$purposeLabel} activated for {$targetName} ({$targetEmail}) under M-Pesa Code {$mpesaCode}.";
                }
            }
        }
    }
}

// Instant M-Pesa & User Diagnostic Lookup Query
$lookupQuery = trim($_GET['lookup'] ?? '');
$lookupResults = [
    'payments' => [],
    'teachers' => [],
    'schools' => []
];

if (!empty($lookupQuery)) {
    $searchTerm = "%{$lookupQuery}%";
    
    // Payments Lookup
    try {
        $lookupResults['payments'] = R::find('payment', 'api_ref LIKE ? OR tracking_id LIKE ? OR invoice_code LIKE ? OR email LIKE ? OR phone LIKE ? ORDER BY id DESC LIMIT 5', [
            $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm
        ]);
    } catch (\Throwable $e) {}

    // Teachers Lookup
    try {
        $lookupResults['teachers'] = R::find('teacher', 'phone LIKE ? OR email LIKE ? OR tsc_number LIKE ? OR name LIKE ? ORDER BY id DESC LIMIT 5', [
            $searchTerm, $searchTerm, $searchTerm, $searchTerm
        ]);
    } catch (\Throwable $e) {}

    // Schools Lookup
    try {
        $lookupResults['schools'] = R::find('school', 'phone LIKE ? OR email LIKE ? OR name LIKE ? ORDER BY id DESC LIMIT 5', [
            $searchTerm, $searchTerm, $searchTerm
        ]);
    } catch (\Throwable $e) {}
}

// Filters & Pagination for Ledger
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$purposeFilter = trim($_GET['purpose'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = ['1=1'];

if ($search !== '') {
    $whereClauses[] = '(email LIKE ? OR phone LIKE ? OR api_ref LIKE ? OR invoice_code LIKE ? OR tracking_id LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($statusFilter !== '' && $statusFilter !== 'all') {
    $whereClauses[] = 'state = ?';
    $params[] = $statusFilter;
}

if ($purposeFilter !== '' && $purposeFilter !== 'all') {
    $whereClauses[] = 'purpose = ?';
    $params[] = $purposeFilter;
}

$whereSql = implode(' AND ', $whereClauses);
$totalCount = R::count('payment', $whereSql, $params);
$payments = R::find('payment', "{$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

// Summary Totals
$totalRevenueRow = R::getRow("SELECT SUM(amount) as rev FROM payment WHERE state = 'COMPLETE'");
$totalRevenue = floatval($totalRevenueRow['rev'] ?? 0);

$completedCount = R::count('payment', "state = 'COMPLETE'");
$pendingCount = R::count('payment', "state = 'PENDING'");
$failedCount = R::count('payment', "state = 'FAILED'");

$totalPages = ceil($totalCount / $limit);
?>

<style>
.admin-table-container {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.admin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.86rem;
    table-layout: auto;
}
.admin-table th, .admin-table td {
    padding: 10px 12px;
    vertical-align: middle;
}
@media (max-width: 768px) {
    .admin-header-flex {
        flex-direction: column !important;
        align-items: flex-start !important;
    }
    .admin-filter-form {
        flex-direction: column !important;
    }
    .admin-filter-form > div, .admin-filter-form button {
        width: 100% !important;
    }
    .stat-grid-responsive {
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)) !important;
        gap: 0.5rem !important;
    }
}
</style>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="max-width: 1200px; margin: 0 auto; padding: 1.25rem 1rem;">

        <!-- Header -->
        <div class="admin-header-flex" style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <a href="/admin/dashboard" style="color: #64748b; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 4px; text-decoration: none;">
                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                </a>
                <h1 style="margin: 0; font-size: 1.4rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-receipt" style="color: #0f766e;"></i> Financial Transactions & Gateway Ledger
                </h1>
                <p style="margin: 3px 0 0; font-size: 0.84rem; color: #64748b;">
                    Audit payments, instantly reconcile M-Pesa receipts, and manually activate educator or school access.
                </p>
            </div>
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" onclick="document.getElementById('instantMpesaCard').scrollIntoView({behavior: 'smooth'})" style="background: #0f766e; color: white; border: none; padding: 7px 14px; border-radius: 6px; font-size: 0.82rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-bolt"></i> Instant M-Pesa Lookup
                </button>
                <a href="/admin/pricing" style="background: white; border: 1px solid #cbd5e1; color: #334155; padding: 7px 14px; border-radius: 6px; text-decoration: none; font-size: 0.82rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-tags"></i> Pricing Settings &rarr;
                </a>
            </div>
        </div>

        <?php if ($msg): ?>
            <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 10px 14px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.86rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-check-circle"></i> <?= h($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 10px 14px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.86rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- KPIs -->
        <div class="stat-grid-responsive" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem;">
            <div class="metric-card" style="border-left: 4px solid #16a34a; background: white; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; border-left-width: 4px;">
                <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Revenue</div>
                <div style="font-size: 1.4rem; font-weight: 800; color: #16a34a; margin: 2px 0;">
                    KES <?= number_format($totalRevenue, 2) ?>
                </div>
                <div style="font-size: 0.74rem; color: #64748b; font-weight: 600;">
                    <?= number_format($completedCount) ?> successful
                </div>
            </div>

            <div class="metric-card" style="border-left: 4px solid #f59e0b; background: white; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; border-left-width: 4px;">
                <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Pending</div>
                <div style="font-size: 1.4rem; font-weight: 800; color: #d97706; margin: 2px 0;">
                    <?= number_format($pendingCount) ?>
                </div>
                <div style="font-size: 0.74rem; color: #64748b; font-weight: 600;">
                    Awaiting PIN / webhook
                </div>
            </div>

            <div class="metric-card" style="border-left: 4px solid #ef4444; background: white; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; border-left-width: 4px;">
                <div style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Failed / Abandoned</div>
                <div style="font-size: 1.4rem; font-weight: 800; color: #dc2626; margin: 2px 0;">
                    <?= number_format($failedCount) ?>
                </div>
                <div style="font-size: 0.74rem; color: #64748b; font-weight: 600;">
                    Declined or timed out
                </div>
            </div>
        </div>

        <!-- INSTANT M-PESA LOOKUP & DIRECT ACTIVATION TOOL -->
        <div id="instantMpesaCard" style="background: #ffffff; border: 1.5px solid #0f766e; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 2px 4px rgba(15,118,110,0.06);">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 0.75rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                <div>
                    <h2 style="margin: 0; font-size: 1.05rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-bolt" style="color: #0f766e;"></i> Instant M-Pesa Payment Lookup & Activation
                    </h2>
                    <p style="margin: 2px 0 0; font-size: 0.8rem; color: #64748b;">
                        Search M-Pesa code (<code style="background: #f1f5f9; padding: 1px 4px; border-radius: 4px;">QK789...</code>), phone number, or email to reconcile immediately.
                    </p>
                </div>
                <button type="button" onclick="toggleManualForm()" style="background: #f0fdfa; border: 1px solid #0f766e; color: #0f766e; font-size: 0.78rem; font-weight: 700; padding: 5px 12px; border-radius: 6px; cursor: pointer;">
                    <i class="fa fa-plus-circle"></i> Direct Manual Form
                </button>
            </div>

            <!-- Instant Search Input -->
            <form method="GET" action="/admin/transactions#instantMpesaCard" style="display: flex; gap: 6px; margin-bottom: 0.75rem; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 220px;">
                    <input type="text" name="lookup" value="<?= h($lookupQuery) ?>" placeholder="Enter M-Pesa Code (QK...), Phone Number (07...), Email, or TSC No..." required style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1.5px solid #0f766e; border-radius: 6px; font-size: 0.88rem; background: #ffffff;">
                </div>
                <button type="submit" style="background: #0f766e; color: white; padding: 8px 16px; border: none; border-radius: 6px; font-size: 0.84rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-search"></i> Inspect
                </button>
                <?php if ($lookupQuery): ?>
                    <a href="/admin/transactions" style="padding: 8px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; color: #475569; text-decoration: none; display: inline-flex; align-items: center;">
                        Clear
                    </a>
                <?php endif; ?>
            </form>

            <!-- Diagnostic Results View -->
            <?php if (!empty($lookupQuery)): ?>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-top: 0.75rem;">
                    <h3 style="margin: 0 0 0.75rem; font-size: 0.9rem; color: #1e293b; font-weight: 700;">
                        Lookup Results for "<strong><?= h($lookupQuery) ?></strong>"
                    </h3>

                    <?php if (empty($lookupResults['teachers']) && empty($lookupResults['schools']) && empty($lookupResults['payments'])): ?>
                        <div style="padding: 1.25rem; text-align: center; color: #64748b; font-size: 0.84rem; background: white; border-radius: 6px; border: 1px dashed #cbd5e1;">
                            No matching user or payment records found.
                            <div style="margin-top: 6px;">
                                <button type="button" onclick="openManualFormWithCode('<?= h(addslashes($lookupQuery)) ?>')" style="background: #0f766e; color: white; border: none; padding: 5px 12px; border-radius: 6px; font-size: 0.78rem; font-weight: 700; cursor: pointer;">
                                    Activate M-Pesa Code for a User &rarr;
                                </button>
                            </div>
                        </div>
                    <?php else: ?>

                        <!-- Matched Educators -->
                        <?php if (!empty($lookupResults['teachers'])): ?>
                            <div style="margin-bottom: 1rem;">
                                <div style="font-size: 0.74rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px;">Matched Educators</div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 8px;">
                                    <?php foreach ($lookupResults['teachers'] as $t): ?>
                                        <?php
                                        $tAccess = R::findOne('directoryaccess', '(user_id = ? AND user_type = "teacher") OR (email = ? AND email != "")', [$t->id, $t->email]);
                                        $isAccessActive = ($tAccess && $tAccess->status === 'active' && strtotime($tAccess->expires_at) > time());
                                        ?>
                                        <div style="background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                            <div>
                                                <div style="font-weight: 700; color: #0f172a; font-size: 0.88rem;"><?= h($t->name) ?></div>
                                                <div style="font-size: 0.76rem; color: #64748b;">
                                                    Phone: <strong><?= h($t->phone ?: 'N/A') ?></strong> &bull; TSC: <strong><?= h($t->tsc_number ?: 'N/A') ?></strong>
                                                </div>
                                                <div style="font-size: 0.74rem; color: #64748b;">
                                                    <?= h($t->email) ?>
                                                </div>
                                                <div style="margin-top: 4px;">
                                                    <?php if ($isAccessActive): ?>
                                                        <span style="background: #dcfce7; color: #166534; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 8px;">
                                                            ✓ Active (Exp: <?= date('M d, Y', strtotime($tAccess->expires_at)) ?>)
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="background: #fee2e2; color: #991b1b; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 8px;">
                                                            ✗ Pass Inactive / Expired
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <button type="button" onclick="fillManualActivation('teacher', <?= (int)$t->id ?>, '<?= h(addslashes($t->name)) ?>', '<?= h(addslashes($t->email)) ?>', '<?= h(addslashes($t->phone)) ?>', 'directory_access', 100)" style="background: #0f766e; color: white; border: none; padding: 5px 10px; font-size: 0.75rem; font-weight: 700; border-radius: 5px; cursor: pointer; white-space: nowrap;">
                                                Activate &rarr;
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Matched Schools -->
                        <?php if (!empty($lookupResults['schools'])): ?>
                            <div style="margin-bottom: 1rem;">
                                <div style="font-size: 0.74rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px;">Matched Schools</div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 8px;">
                                    <?php foreach ($lookupResults['schools'] as $s): ?>
                                        <?php
                                        $isPro = (!empty($s->subscription_expiry) && strtotime($s->subscription_expiry) > time());
                                        ?>
                                        <div style="background: white; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                                            <div>
                                                <div style="font-weight: 700; color: #0f172a; font-size: 0.88rem;"><?= h($s->name) ?></div>
                                                <div style="font-size: 0.76rem; color: #64748b;">
                                                    Phone: <strong><?= h($s->phone ?: 'N/A') ?></strong> &bull; <?= h($s->county ?: 'Kenya') ?>
                                                </div>
                                                <div style="font-size: 0.74rem; color: #64748b;">
                                                    <?= h($s->email) ?>
                                                </div>
                                                <div style="margin-top: 4px;">
                                                    <?php if ($isPro): ?>
                                                        <span style="background: #e0f2fe; color: #0369a1; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 8px;">
                                                            ✓ Pro Active (Exp: <?= date('M d, Y', strtotime($s->subscription_expiry)) ?>)
                                                        </span>
                                                    <?php else: ?>
                                                        <span style="background: #f1f5f9; color: #64748b; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 8px;">
                                                            Free Basic Tier
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <button type="button" onclick="fillManualActivation('school', <?= (int)$s->id ?>, '<?= h(addslashes($s->name)) ?>', '<?= h(addslashes($s->email)) ?>', '<?= h(addslashes($s->phone)) ?>', 'school_subscription', 1000)" style="background: #0369a1; color: white; border: none; padding: 5px 10px; font-size: 0.75rem; font-weight: 700; border-radius: 5px; cursor: pointer; white-space: nowrap;">
                                                Activate Pro &rarr;
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Matched Payment Records -->
                        <?php if (!empty($lookupResults['payments'])): ?>
                            <div>
                                <div style="font-size: 0.74rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px;">Linked Payment Records</div>
                                <div class="admin-table-container">
                                    <table class="admin-table">
                                        <thead>
                                            <tr style="background: #f1f5f9; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 0.76rem;">
                                                <th>ID / Date</th>
                                                <th>User / Entity</th>
                                                <th>M-Pesa / Invoice</th>
                                                <th>Amount</th>
                                                <th>State</th>
                                                <th style="text-align: right;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($lookupResults['payments'] as $lp): ?>
                                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                                    <td>
                                                        <strong>#<?= $lp->id ?></strong><br>
                                                        <span style="font-size: 0.72rem; color: #94a3b8;"><?= date('M d, g:i A', strtotime($lp->created_at)) ?></span>
                                                    </td>
                                                    <td>
                                                        <div style="font-weight: 600; color: #0f172a;"><?= h($lp->email) ?></div>
                                                        <span style="font-size: 0.72rem; color: #64748b; text-transform: capitalize;"><?= h($lp->user_type) ?> #<?= (int)$lp->user_id ?></span>
                                                    </td>
                                                    <td>
                                                        <code style="color: #0f766e; font-weight: 700;"><?= h($lp->api_ref ?: 'N/A') ?></code>
                                                        <?php if ($lp->invoice_code): ?>
                                                            <div style="font-size: 0.7rem; color: #64748b;"><?= h($lp->invoice_code) ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="font-weight: 700;">
                                                        KES <?= number_format($lp->amount, 2) ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($lp->state === 'COMPLETE'): ?>
                                                            <span style="background: #dcfce7; color: #166534; font-weight: 700; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">COMPLETE</span>
                                                        <?php elseif ($lp->state === 'PENDING'): ?>
                                                            <span style="background: #fef3c7; color: #92400e; font-weight: 700; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;">PENDING</span>
                                                        <?php else: ?>
                                                            <span style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px;"><?= h($lp->state) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td style="text-align: right;">
                                                        <?php if ($lp->state !== 'COMPLETE'): ?>
                                                            <form method="POST" action="/admin/transactions" style="display: inline-flex; gap: 4px; margin: 0;">
                                                                <?= csrf_field() ?>
                                                                <input type="hidden" name="payment_id" value="<?= $lp->id ?>">
                                                                <button type="submit" name="action" value="reconcile_payment" style="background: #0f766e; color: white; border: none; padding: 4px 8px; font-size: 0.72rem; font-weight: 600; border-radius: 4px; cursor: pointer;">
                                                                    Re-check
                                                                </button>
                                                                <button type="submit" name="action" value="manual_mark_complete" onclick="return confirm('Force mark this payment as COMPLETE and grant access?')" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; padding: 4px 8px; font-size: 0.72rem; border-radius: 4px; cursor: pointer;">
                                                                    Force Pass
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span style="color: #16a34a; font-weight: 600; font-size: 0.74rem;">Active</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Collapsible Direct Manual Activation Form -->
            <div id="manualActivationFormContainer" style="display: none; background: #f0fdfa; border: 1px solid #99f6e4; border-radius: 8px; padding: 1.25rem; margin-top: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                    <strong style="color: #0f766e; font-size: 0.92rem; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-hand-holding-dollar"></i> Direct Manual M-Pesa Activation
                    </strong>
                    <button type="button" onclick="toggleManualForm()" style="background: transparent; border: none; color: #64748b; font-size: 1.2rem; cursor: pointer;">&times;</button>
                </div>

                <form method="POST" action="/admin/transactions">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="direct_manual_activate">
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
                        <div>
                            <label style="display: block; font-size: 0.76rem; font-weight: 700; color: #334155; margin-bottom: 3px;">Account Type</label>
                            <select name="target_user_type" id="manualUserType" onchange="updatePurposeDefault(this.value)" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; background: white;">
                                <option value="teacher">Educator / Teacher</option>
                                <option value="school">School Account</option>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.76rem; font-weight: 700; color: #334155; margin-bottom: 3px;">Target User ID</label>
                            <input type="number" name="target_user_id" id="manualUserId" placeholder="e.g. 42" required style="width: 100%; box-sizing: border-box; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; background: white;">
                            <span id="manualTargetHint" style="font-size: 0.7rem; color: #64748b; margin-top: 2px; display: block;">Enter User ID from search above</span>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.76rem; font-weight: 700; color: #334155; margin-bottom: 3px;">M-Pesa Receipt Code</label>
                            <input type="text" name="mpesa_code" id="manualMpesaCode" placeholder="e.g. QK87XYZ123" required style="width: 100%; box-sizing: border-box; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; text-transform: uppercase; background: white; font-weight: 700; color: #0f766e;">
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.76rem; font-weight: 700; color: #334155; margin-bottom: 3px;">Access Plan</label>
                            <select name="purpose" id="manualPurpose" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; background: white;">
                                <option value="directory_access">Directory Access Pass (Educator)</option>
                                <option value="school_subscription">School Pro Subscription</option>
                            </select>
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.76rem; font-weight: 700; color: #334155; margin-bottom: 3px;">Amount (KES)</label>
                            <input type="number" name="amount" id="manualAmount" value="100" min="0" step="1" required style="width: 100%; box-sizing: border-box; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; background: white;">
                        </div>

                        <div>
                            <label style="display: block; font-size: 0.76rem; font-weight: 700; color: #334155; margin-bottom: 3px;">Audit Notes</label>
                            <input type="text" name="notes" placeholder="e.g. Settled directly via M-Pesa" style="width: 100%; box-sizing: border-box; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; background: white;">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 8px;">
                        <button type="button" onclick="toggleManualForm()" style="background: white; border: 1px solid #cbd5e1; color: #475569; padding: 7px 14px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; cursor: pointer;">
                            Cancel
                        </button>
                        <button type="submit" style="background: #0f766e; color: white; border: none; padding: 7px 18px; border-radius: 6px; font-size: 0.82rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-check"></i> Activate Access
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Filters & Search Bar for Main Ledger -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem 1rem; margin-bottom: 1.25rem;">
            <form method="GET" action="/admin/transactions" class="admin-filter-form" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin: 0;">
                <div style="flex: 1; min-width: 200px;">
                    <input type="text" name="q" value="<?= h($search) ?>" placeholder="Filter ledger by email, phone, reference..." style="width: 100%; box-sizing: border-box; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem;">
                </div>

                <div>
                    <select name="status" style="padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; background: white;">
                        <option value="all">All States</option>
                        <option value="COMPLETE" <?= $statusFilter === 'COMPLETE' ? 'selected' : '' ?>>COMPLETE</option>
                        <option value="PENDING" <?= $statusFilter === 'PENDING' ? 'selected' : '' ?>>PENDING</option>
                        <option value="FAILED" <?= $statusFilter === 'FAILED' ? 'selected' : '' ?>>FAILED</option>
                    </select>
                </div>

                <div>
                    <select name="purpose" style="padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; background: white;">
                        <option value="all">All Purposes</option>
                        <option value="directory_access" <?= $purposeFilter === 'directory_access' ? 'selected' : '' ?>>Directory Access</option>
                        <option value="school_subscription" <?= $purposeFilter === 'school_subscription' ? 'selected' : '' ?>>School Pro</option>
                    </select>
                </div>

                <button type="submit" style="background: #0f766e; color: white; padding: 7px 14px; border: none; border-radius: 6px; font-size: 0.84rem; font-weight: 600; cursor: pointer;">
                    Filter
                </button>

                <?php if ($search || ($statusFilter && $statusFilter !== 'all') || ($purposeFilter && $purposeFilter !== 'all')): ?>
                    <a href="/admin/transactions" style="color: #64748b; font-size: 0.82rem; text-decoration: none; padding: 6px;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Transactions Ledger Table -->
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: #475569; font-size: 0.76rem; text-transform: uppercase;">
                        <th style="padding: 12px 14px; min-width: 100px;">ID & Date</th>
                        <th style="padding: 12px 14px; min-width: 200px;">Customer / Entity</th>
                        <th style="padding: 12px 14px; min-width: 110px;">Purpose</th>
                        <th style="padding: 12px 14px; min-width: 100px;">Amount</th>
                        <th style="padding: 12px 14px; min-width: 160px;">M-Pesa / Gateway Ref</th>
                        <th style="padding: 12px 14px; min-width: 95px;">Status</th>
                        <th style="padding: 12px 14px; text-align: center; width: 60px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="7" style="padding: 3rem; text-align: center; color: #64748b;">
                                <i class="fa fa-receipt" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px; display: block;"></i>
                                No transactions match your search criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#fbfcfd'" onmouseout="this.style.background='white'">
                                <td style="padding: 12px 14px;">
                                    <div style="font-weight: 700; color: #0f172a;">#<?= $p->id ?></div>
                                    <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 1px; white-space: nowrap;">
                                        <?= date('M d, Y g:i A', strtotime($p->created_at)) ?>
                                    </div>
                                </td>
                                <td style="padding: 12px 14px;">
                                    <div style="font-weight: 600; color: #0f172a; overflow-wrap: anywhere; word-break: break-word;">
                                        <?= h($p->email) ?>
                                    </div>
                                    <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                        <span><?= h($p->phone ?: 'No phone') ?></span>
                                        <span>&bull;</span>
                                        <?php if ($p->user_type === 'school' && $p->user_id): ?>
                                            <a href="/admin/jobs?school_id=<?= $p->user_id ?>" style="color: #0f766e; text-decoration: underline; font-weight: 600;">
                                                School #<?= intval($p->user_id) ?>
                                            </a>
                                        <?php elseif ($p->user_type === 'teacher' && $p->user_id): ?>
                                            <a href="/teacher/profile?teacher_id=<?= $p->user_id ?>" target="_blank" style="color: #0f766e; text-decoration: underline; font-weight: 600;">
                                                Teacher #<?= intval($p->user_id) ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="text-transform: capitalize;"><?= h($p->user_type) ?> #<?= intval($p->user_id) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="padding: 12px 14px;">
                                    <?php if ($p->purpose === 'school_subscription'): ?>
                                        <span style="background: #e0f2fe; color: #0369a1; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-school"></i> School Pro
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #f0fdf4; color: #166534; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-address-book"></i> Directory Pass
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 14px; font-weight: 700; color: #0f172a; white-space: nowrap;">
                                    <?= h($p->currency ?: 'KES') ?> <?= number_format($p->amount, 2) ?>
                                </td>
                                <td style="padding: 12px 14px;">
                                    <div style="display: flex; align-items: center; gap: 4px;">
                                        <code title="<?= h($p->api_ref) ?>" style="color: #0f766e; font-weight: 700; font-size: 0.76rem; background: #f0fdfa; padding: 2px 6px; border-radius: 4px; border: 1px solid #ccfbf1; max-width: 145px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; display: inline-block; vertical-align: middle;">
                                            <?= h($p->api_ref ?: 'N/A') ?>
                                        </code>
                                    </div>
                                    <?php if ($p->invoice_code || $p->tracking_id): ?>
                                        <div style="color: #64748b; font-size: 0.72rem; margin-top: 2px; overflow-wrap: anywhere;">
                                            Inv: <span style="font-family: monospace; font-weight: 600;"><?= h($p->invoice_code ?: $p->tracking_id) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 14px;">
                                    <?php if ($p->state === 'COMPLETE'): ?>
                                        <span style="background: #dcfce7; color: #166534; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-check-circle"></i> COMPLETE
                                        </span>
                                    <?php elseif ($p->state === 'PENDING'): ?>
                                        <span style="background: #fef3c7; color: #92400e; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-clock"></i> PENDING
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-circle-xmark"></i> <?= h($p->state ?: 'FAILED') ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 12px 14px; text-align: center; position: relative;">
                                    <!-- 3-Dot Dropdown Menu -->
                                    <div style="position: relative; display: inline-block;">
                                        <button type="button" onclick="togglePaymentMenu(event, <?= $p->id ?>)" style="background: transparent; border: 1px solid transparent; border-radius: 6px; padding: 6px 10px; cursor: pointer; color: #64748b; font-size: 1rem; transition: all 0.15s;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1'" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'" title="Actions">
                                            <i class="fa fa-ellipsis-v"></i>
                                        </button>

                                        <div id="payment-menu-<?= $p->id ?>" class="payment-dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; z-index: 100; min-width: 190px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); padding: 4px 0; text-align: left;">
                                            <?php if ($p->state === 'PENDING'): ?>
                                                <form method="POST" action="/admin/transactions" style="margin: 0;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="payment_id" value="<?= $p->id ?>">
                                                    
                                                    <button type="submit" name="action" value="reconcile_payment" style="display: flex; align-items: center; gap: 8px; width: 100%; border: none; background: transparent; padding: 8px 14px; font-size: 0.82rem; color: #0f766e; font-weight: 600; cursor: pointer; text-align: left;" onmouseover="this.style.background='#f0fdfa'" onmouseout="this.style.background='transparent'">
                                                        <i class="fa fa-rotate" style="width: 16px;"></i> Re-check Status (API)
                                                    </button>

                                                    <button type="submit" name="action" value="manual_mark_complete" onclick="return confirm('Force mark this payment as COMPLETE and activate access?')" style="display: flex; align-items: center; gap: 8px; width: 100%; border: none; background: transparent; padding: 8px 14px; font-size: 0.82rem; color: #166534; font-weight: 600; cursor: pointer; text-align: left;" onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'">
                                                        <i class="fa fa-check-double" style="width: 16px;"></i> Force Complete & Pass
                                                    </button>
                                                </form>
                                                <hr style="margin: 4px 0; border: none; border-top: 1px solid #f1f5f9;">
                                            <?php endif; ?>

                                            <a href="/admin/transactions?q=<?= urlencode($p->email) ?>" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.82rem; color: #334155; text-decoration: none;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                                <i class="fa fa-filter" style="width: 16px; color: #64748b;"></i> Filter by this User
                                            </a>

                                            <?php if ($p->user_type === 'teacher' && $p->user_id): ?>
                                                <a href="/teacher/profile?teacher_id=<?= $p->user_id ?>" target="_blank" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.82rem; color: #0284c7; text-decoration: none;" onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='transparent'">
                                                    <i class="fa fa-user" style="width: 16px;"></i> View Teacher Profile
                                                </a>
                                            <?php elseif ($p->user_type === 'school' && $p->user_id): ?>
                                                <a href="/admin/jobs?school_id=<?= $p->user_id ?>" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.82rem; color: #0284c7; text-decoration: none;" onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='transparent'">
                                                    <i class="fa fa-school" style="width: 16px;"></i> View School Jobs
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div style="padding: 0.75rem 1rem; background: #ffffff; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 10px 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <span style="font-size: 0.8rem; color: #64748b;">
                    Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalCount) ?> records)
                </span>
                <div style="display: flex; gap: 6px;">
                    <?php if ($page > 1): ?>
                        <a href="/admin/transactions?p=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&purpose=<?= urlencode($purposeFilter) ?>" style="padding: 4px 10px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.8rem; color: #334155; text-decoration: none;">
                            &laquo; Prev
                        </a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="/admin/transactions?p=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&purpose=<?= urlencode($purposeFilter) ?>" style="padding: 4px 10px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.8rem; color: #334155; text-decoration: none;">
                            Next &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    </main>
</div>

<script>
function toggleManualForm() {
    var container = document.getElementById('manualActivationFormContainer');
    if (container.style.display === 'none' || container.style.display === '') {
        container.style.display = 'block';
        container.scrollIntoView({ behavior: 'smooth' });
    } else {
        container.style.display = 'none';
    }
}

function updatePurposeDefault(userType) {
    var purposeSelect = document.getElementById('manualPurpose');
    var amountInput = document.getElementById('manualAmount');
    if (userType === 'school') {
        purposeSelect.value = 'school_subscription';
        amountInput.value = '1000';
    } else {
        purposeSelect.value = 'directory_access';
        amountInput.value = '100';
    }
}

function fillManualActivation(userType, userId, userName, userEmail, userPhone, purpose, amount) {
    var container = document.getElementById('manualActivationFormContainer');
    container.style.display = 'block';
    
    document.getElementById('manualUserType').value = userType;
    document.getElementById('manualUserId').value = userId;
    document.getElementById('manualTargetHint').innerText = userName + ' (' + userEmail + ')';
    document.getElementById('manualPurpose').value = purpose;
    document.getElementById('manualAmount').value = amount;
    
    var mpesaInput = document.getElementById('manualMpesaCode');
    mpesaInput.focus();
    container.scrollIntoView({ behavior: 'smooth' });
}

function openManualFormWithCode(code) {
    toggleManualForm();
    var mpesaInput = document.getElementById('manualMpesaCode');
    mpesaInput.value = code.toUpperCase();
    mpesaInput.focus();
}

function togglePaymentMenu(event, paymentId) {
    event.stopPropagation();
    var targetMenu = document.getElementById('payment-menu-' + paymentId);
    var isVisible = targetMenu && targetMenu.style.display === 'block';

    // Close all open payment menus first
    document.querySelectorAll('.payment-dropdown-menu').forEach(function(menu) {
        menu.style.display = 'none';
    });

    if (targetMenu && !isVisible) {
        targetMenu.style.display = 'block';
    }
}

// Global click-outside listener
document.addEventListener('click', function(event) {
    if (!event.target.closest('.payment-dropdown-menu') && !event.target.closest('button')) {
        document.querySelectorAll('.payment-dropdown-menu').forEach(function(menu) {
            menu.style.display = 'none';
        });
    }
});
</script>
