<?php
// views/admin_schools.php - Admin School & Institution Management Portal
require_auth('admin');

$msg = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $school_id = intval($_POST['school_id'] ?? 0);
        $action = $_POST['admin_action'] ?? '';

        $school = R::load('school', $school_id);
        if ($school && $school->id) {
            if ($action === 'grant_pro') {
                // Extend/Grant Pro for 1 year from now (or from current expiry if in future)
                $now = new DateTime();
                $currentExpiry = !empty($school->subscription_expiry) ? new DateTime($school->subscription_expiry) : null;
                $baseDate = ($currentExpiry && $currentExpiry > $now) ? $currentExpiry : $now;
                $baseDate->modify('+1 year');
                
                $school->subscription_expiry = $baseDate->format('Y-m-d H:i:s');
                $school->status = 'active';
                R::store($school);
                log_admin_audit('school', 'PRO_ACTIVATED', 'school', $school->id, $school->name, "Pro subscription granted until {$school->subscription_expiry}");
                $msg = "Granted Pro Subscription to " . htmlspecialchars($school->name) . " until " . $school->subscription_expiry . ".";
            } elseif ($action === 'revoke_pro') {
                $school->subscription_expiry = null;
                R::store($school);
                log_admin_audit('school', 'PRO_REVOKED', 'school', $school->id, $school->name, "Pro subscription revoked; downgraded to Freemium");
                $msg = "Pro Subscription revoked for " . htmlspecialchars($school->name) . ". Account set to Freemium.";
            } elseif ($action === 'suspend') {
                $school->status = 'suspended';
                R::store($school);
                log_admin_audit('school', 'ACCOUNT_SUSPENDED', 'school', $school->id, $school->name, "School account suspended by admin");
                $msg = "School account for " . htmlspecialchars($school->name) . " has been suspended.";
            } elseif ($action === 'activate') {
                $school->status = 'active';
                R::store($school);
                log_admin_audit('school', 'ACCOUNT_ACTIVATED', 'school', $school->id, $school->name, "School account reactivated by admin");
                $msg = "School account for " . htmlspecialchars($school->name) . " has been reactivated.";
            }
        } else {
            $error = "School account not found.";
        }
    }
}

// Filters & Search
$search = trim($_GET['q'] ?? '');
$planStatus = trim($_GET['plan'] ?? '');
$accStatus = trim($_GET['acc_status'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = ['1=1'];

if ($search !== '') {
    $whereClauses[] = '(name LIKE ? OR email LIKE ? OR mobile LIKE ? OR county LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$nowStr = date('Y-m-d H:i:s');

if ($planStatus === 'pro') {
    $whereClauses[] = 'subscription_expiry >= ?';
    $params[] = $nowStr;
} elseif ($planStatus === 'expired') {
    $whereClauses[] = 'subscription_expiry IS NOT NULL AND subscription_expiry < ?';
    $params[] = $nowStr;
} elseif ($planStatus === 'free') {
    $whereClauses[] = 'subscription_expiry IS NULL';
}

if ($accStatus !== '' && $accStatus !== 'all') {
    $whereClauses[] = 'status = ?';
    $params[] = $accStatus;
}

$whereSql = implode(' AND ', $whereClauses);
$totalSchools = R::count('school', $whereSql, $params);
$schools = R::find('school', "{$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

// Aggregate statistics
$statTotal = R::count('school');
$statPro = R::count('school', 'subscription_expiry >= ?', [$nowStr]);
$statFree = R::count('school', 'subscription_expiry IS NULL');
$statSuspended = R::count('school', 'status = ?', ['suspended']);

$totalPages = ceil($totalSchools / $limit);
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane">
        <div style="max-width: 1200px; margin: 0 auto; padding-bottom: 3rem;">
            
            <!-- Breadcrumb & Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <a href="/admin/dashboard" style="color: #64748b; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 6px; text-decoration: none;">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h2 style="margin: 0; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-school" style="color: #0f766e;"></i> School & Institution Operations
                    </h2>
                </div>
                <div>
                    <span style="background: #f1f5f9; color: #475569; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">
                        Showing <?= count($schools) ?> of <?= number_format($totalSchools) ?> Schools
                    </span>
                </div>
            </div>

            <?php if ($msg): ?>
                <div style="background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-circle-check"></i> <?= h($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-circle-exclamation"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <!-- Metric Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Total Institutions</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 4px;"><?= number_format($statTotal) ?></div>
                </div>
                <div style="background: white; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #166534; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Pro Plan Active</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #16a34a; margin-top: 4px;"><?= number_format($statPro) ?></div>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Freemium Plan</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #475569; margin-top: 4px;"><?= number_format($statFree) ?></div>
                </div>
                <div style="background: white; border: 1px solid #fee2e2; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #991b1b; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Suspended Schools</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #dc2626; margin-top: 4px;"><?= number_format($statSuspended) ?></div>
                </div>
            </div>

            <!-- Filter & Search Controls -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <form method="GET" action="/admin/schools" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin: 0;">
                    <div style="flex: 2; min-width: 220px;">
                        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search school name, email, county, phone..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; box-sizing: border-box;">
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <select name="plan" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; background: white; box-sizing: border-box;">
                            <option value="">All Subscription Tiers</option>
                            <option value="pro" <?= $planStatus === 'pro' ? 'selected' : '' ?>>Pro Active</option>
                            <option value="free" <?= $planStatus === 'free' ? 'selected' : '' ?>>Freemium</option>
                            <option value="expired" <?= $planStatus === 'expired' ? 'selected' : '' ?>>Expired Pro</option>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <select name="acc_status" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; background: white; box-sizing: border-box;">
                            <option value="all">All Accounts</option>
                            <option value="active" <?= $accStatus === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="suspended" <?= $accStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" style="background: #0f766e; color: white !important; padding: 8px 16px; border: none; border-radius: 6px; font-size: 0.88rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <?php if ($search || $planStatus || $accStatus): ?>
                            <a href="/admin/schools" style="margin-left: 6px; font-size: 0.84rem; color: #64748b; text-decoration: underline;">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Schools List Table -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden;">
                <?php if (empty($schools)): ?>
                    <div style="padding: 3rem; text-align: center; color: #64748b;">
                        <i class="fa fa-school-circle-xmark" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px;"></i>
                        <p style="margin: 0; font-size: 1rem;">No school accounts matched your search and filter criteria.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569;">
                                    <th style="padding: 12px 14px;">School Details</th>
                                    <th style="padding: 12px 14px;">Location</th>
                                    <th style="padding: 12px 14px;">Subscription Tier</th>
                                    <th style="padding: 12px 14px;">Vacancies</th>
                                    <th style="padding: 12px 14px;">Status</th>
                                    <th style="padding: 12px 14px; text-align: right;">Operations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($schools as $s): ?>
                                    <?php
                                    $isPro = false;
                                    $isExpired = false;
                                    if (!empty($s->subscription_expiry)) {
                                        try {
                                            $exp = new DateTime($s->subscription_expiry);
                                            $now = new DateTime();
                                            if ($exp >= $now) {
                                                $isPro = true;
                                            } else {
                                                $isExpired = true;
                                            }
                                        } catch (Exception $e) {}
                                    }
                                    $jobCount = R::count('job', 'school_id = ?', [$s->id]);
                                    ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#fbfcfd'" onmouseout="this.style.background='white'">
                                        <td style="padding: 12px 14px;">
                                            <div style="font-weight: 700; color: #0f172a;">
                                                <?= h($s->name) ?>
                                            </div>
                                            <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;">
                                                <span><i class="fa fa-envelope" style="font-size: 0.75rem;"></i> <?= h($s->email) ?></span>
                                                <?php if ($s->mobile): ?>
                                                    <span style="margin-left: 8px;"><i class="fa fa-phone" style="font-size: 0.75rem;"></i> <?= h($s->mobile) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <div style="font-size: 0.82rem; color: #334155;">
                                                <?= h($s->county ?: 'Kenya') ?>
                                            </div>
                                            <?php if ($s->sub_county): ?>
                                                <div style="font-size: 0.76rem; color: #64748b; margin-top: 2px;">
                                                    <?= h($s->sub_county) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <?php if ($isPro): ?>
                                                <span style="background: #dcfce7; color: #166534; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fa fa-crown" style="color: #eab308;"></i> PRO ACTIVE
                                                </span>
                                                <div style="font-size: 0.74rem; color: #64748b; margin-top: 3px;">
                                                    Exp: <?= date('M d, Y', strtotime($s->subscription_expiry)) ?>
                                                </div>
                                            <?php elseif ($isExpired): ?>
                                                <span style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px;">
                                                    PRO EXPIRED
                                                </span>
                                                <div style="font-size: 0.74rem; color: #64748b; margin-top: 3px;">
                                                    Was: <?= date('M d, Y', strtotime($s->subscription_expiry)) ?>
                                                </div>
                                            <?php else: ?>
                                                <span style="background: #f1f5f9; color: #475569; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px;">
                                                    Freemium (1 Free Job)
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <a href="/admin/jobs?school_id=<?= $s->id ?>" style="font-weight: 700; color: #0f766e; text-decoration: underline;">
                                                <?= $jobCount ?> Job<?= $jobCount !== 1 ? 's' : '' ?>
                                            </a>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <?php if ($s->status === 'suspended'): ?>
                                                <span style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.74rem; padding: 3px 8px; border-radius: 4px;">Suspended</span>
                                            <?php else: ?>
                                                <span style="background: #f0fdf4; color: #166534; font-weight: 600; font-size: 0.74rem; padding: 3px 8px; border-radius: 4px;">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px; text-align: right;">
                                            <form method="POST" action="/admin/schools" style="margin: 0; display: inline-flex; gap: 4px; flex-wrap: wrap; justify-content: flex-end;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="school_id" value="<?= $s->id ?>">
                                                
                                                <button type="submit" name="admin_action" value="grant_pro" title="Grant or Extend Pro Subscription by 1 Year" style="background: #0f766e; color: white !important; font-size: 0.74rem; font-weight: 600; padding: 5px 9px; border: none; border-radius: 4px; cursor: pointer;">
                                                    <i class="fa fa-crown"></i> <?= $isPro ? '+1 Yr Pro' : 'Activate Pro' ?>
                                                </button>

                                                <?php if ($isPro): ?>
                                                    <button type="submit" name="admin_action" value="revoke_pro" onclick="return confirm('Revoke Pro plan for this school?')" title="Downgrade to Freemium" style="background: #f8fafc; color: #64748b; font-size: 0.74rem; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer;">
                                                        Revoke Pro
                                                    </button>
                                                <?php endif; ?>

                                                <?php if ($s->status === 'suspended'): ?>
                                                    <button type="submit" name="admin_action" value="activate" title="Reactivate School" style="background: #f1f5f9; color: #1e293b; font-size: 0.74rem; padding: 5px 8px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer;">
                                                        Activate
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" name="admin_action" value="suspend" onclick="return confirm('Suspend this school account?')" title="Suspend School" style="background: #f8fafc; color: #64748b; font-size: 0.74rem; padding: 5px 8px; border: 1px solid #e2e8f0; border-radius: 4px; cursor: pointer;">
                                                        Suspend
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.82rem; color: #64748b;">
                                Page <?= $page ?> of <?= $totalPages ?>
                            </span>
                            <div style="display: flex; gap: 6px;">
                                <?php if ($page > 1): ?>
                                    <a href="/admin/schools?p=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&plan=<?= urlencode($planStatus) ?>&acc_status=<?= urlencode($accStatus) ?>" style="padding: 5px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem; color: #334155; text-decoration: none;">
                                        &laquo; Prev
                                    </a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="/admin/schools?p=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&plan=<?= urlencode($planStatus) ?>&acc_status=<?= urlencode($accStatus) ?>" style="padding: 5px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem; color: #334155; text-decoration: none;">
                                        Next &raquo;
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>
