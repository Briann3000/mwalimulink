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
                $months = intval($_POST['pro_months'] ?? 1);
                $months = max(1, min(24, $months));
                grant_school_pro_subscription($school->id, 0, 'admin_manual_grant', $months);
                $school = R::load('school', $school_id);
                $msg = "Granted Pro Subscription ({$months} mo) to " . htmlspecialchars($school->name) . " until " . date('M d, Y', strtotime($school->subscription_expiry)) . ".";
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
        <div style="padding-bottom: 3rem;">
            
            <!-- Breadcrumb & Header -->
            <div class="admin-header-flex" style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h2 style="margin: 0 0 4px; color: #0f172a; font-size: 1.35rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-school" style="color: #0f766e;"></i> School & Institution Management
                    </h2>
                    <p style="margin: 0; font-size: 0.84rem; color: #64748b;">
                        Manage registered educational institutions, recruitment privileges, and Pro subscriptions.
                    </p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="/admin/pricing" style="background: white; border: 1px solid #cbd5e1; color: #0f766e !important; font-size: 0.82rem; font-weight: 600; padding: 7px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-tags"></i> Pricing Config
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

            <!-- Metrics Overview Cards -->
            <div class="stat-grid-responsive" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem;">
                    <span style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Institutions</span>
                    <strong style="font-size: 1.4rem; color: #0f172a; display: block; margin-top: 2px;"><?= number_format($statTotal) ?></strong>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem;">
                    <span style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Pro Active (Paying)</span>
                    <strong style="font-size: 1.4rem; color: #0f766e; display: block; margin-top: 2px;"><?= number_format($statPro) ?></strong>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem;">
                    <span style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Freemium Accounts</span>
                    <strong style="font-size: 1.4rem; color: #334155; display: block; margin-top: 2px;"><?= number_format($statFree) ?></strong>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem;">
                    <span style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Suspended</span>
                    <strong style="font-size: 1.4rem; color: <?= $statSuspended > 0 ? '#dc2626' : '#64748b' ?>; display: block; margin-top: 2px;"><?= number_format($statSuspended) ?></strong>
                </div>
            </div>

            <!-- Filters & Search Toolbar -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem 1rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <form method="GET" action="/admin/schools" class="admin-filter-form" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: flex-end; margin: 0;">
                    <div style="flex: 2; min-width: 200px;">
                        <label style="font-size: 0.76rem; font-weight: 600; color: #475569; display: block; margin-bottom: 3px;">Search School / Email / County</label>
                        <input type="text" name="q" value="<?= h($search) ?>" placeholder="e.g. Alliance High, Nairobi, info@school.ke" style="width: 100%; box-sizing: border-box; padding: 7px 10px; font-size: 0.84rem; border: 1px solid #cbd5e1; border-radius: 6px;">
                    </div>
                    
                    <div style="flex: 1; min-width: 140px;">
                        <label style="font-size: 0.76rem; font-weight: 600; color: #475569; display: block; margin-bottom: 3px;">Subscription Plan</label>
                        <select name="plan" style="width: 100%; box-sizing: border-box; padding: 7px 10px; font-size: 0.84rem; border: 1px solid #cbd5e1; border-radius: 6px; background: white;">
                            <option value="">All Tiers</option>
                            <option value="pro" <?= $planStatus === 'pro' ? 'selected' : '' ?>>Pro Active</option>
                            <option value="expired" <?= $planStatus === 'expired' ? 'selected' : '' ?>>Pro Expired</option>
                            <option value="free" <?= $planStatus === 'free' ? 'selected' : '' ?>>Freemium</option>
                        </select>
                    </div>

                    <div style="flex: 1; min-width: 130px;">
                        <label style="font-size: 0.76rem; font-weight: 600; color: #475569; display: block; margin-bottom: 3px;">Account Status</label>
                        <select name="acc_status" style="width: 100%; box-sizing: border-box; padding: 7px 10px; font-size: 0.84rem; border: 1px solid #cbd5e1; border-radius: 6px; background: white;">
                            <option value="all">All Accounts</option>
                            <option value="active" <?= $accStatus === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="suspended" <?= $accStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>

                    <div>
                        <button type="submit" style="background: #0f766e; color: white !important; padding: 7px 14px; border: none; border-radius: 6px; font-size: 0.84rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <?php if ($search || $planStatus || $accStatus): ?>
                            <a href="/admin/schools" style="margin-left: 6px; font-size: 0.82rem; color: #64748b; text-decoration: underline;">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Schools List Table -->
            <div class="admin-table-container">
                <?php if (empty($schools)): ?>
                    <div style="padding: 3rem; text-align: center; color: #64748b;">
                        <i class="fa fa-school-circle-xmark" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px;"></i>
                        <p style="margin: 0; font-size: 1rem;">No school accounts matched your search and filter criteria.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto; overflow-y: visible;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569;">
                                    <th style="padding: 12px 14px;">School Details</th>
                                    <th style="padding: 12px 14px;">Location</th>
                                    <th style="padding: 12px 14px;">Subscription Tier</th>
                                    <th style="padding: 12px 14px;">Vacancies</th>
                                    <th style="padding: 12px 14px;">Status</th>
                                    <th style="padding: 12px 14px;">Date Joined</th>
                                    <th style="padding: 12px 14px; text-align: center; width: 60px;">Actions</th>
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
                                                <?php if ($s->mobile || $s->phone_number): ?>
                                                    <span style="margin-left: 8px;"><i class="fa fa-phone" style="font-size: 0.75rem;"></i> <?= h($s->mobile ?: $s->phone_number) ?></span>
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
                                        <td style="padding: 12px 14px; font-size: 0.8rem; color: #64748b; white-space: nowrap;">
                                            <?php
                                                $regDate = $s->created_at ?: $s->registration_date ?: $s->date_created ?: $s->created_date ?: $s->reg_date;
                                                echo $regDate ? date('M d, Y', strtotime($regDate)) : '—';
                                            ?>
                                        </td>
                                        <td style="padding: 12px 14px; text-align: center; position: relative;">
                                            <!-- 3-Dot Dropdown Menu -->
                                            <div style="position: relative; display: inline-block;">
                                                <button type="button" onclick="toggleSchoolMenu(event, <?= $s->id ?>)" style="background: transparent; border: 1px solid transparent; border-radius: 6px; padding: 6px 10px; cursor: pointer; color: #64748b; font-size: 1rem; transition: all 0.15s;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1'" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'" title="Actions">
                                                    <i class="fa fa-ellipsis-v"></i>
                                                </button>

                                                <div id="school-menu-<?= $s->id ?>" class="school-dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; z-index: 100; min-width: 190px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); padding: 4px 0; text-align: left;">
                                                    <form method="POST" action="/admin/schools" id="school-form-<?= $s->id ?>" style="margin: 0;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="school_id" value="<?= $s->id ?>">
                                                        <input type="hidden" name="pro_months" id="pro-months-<?= $s->id ?>" value="1">
                                                        
                                                        <a href="/admin/impersonate?type=school&id=<?= $s->id ?>" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.82rem; color: #0284c7; font-weight: 600; text-decoration: none;" onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='transparent'">
                                                            <i class="fa fa-user-secret" style="width: 16px;"></i> Log In As School
                                                        </a>

                                                        <a href="/admin/jobs?school_id=<?= $s->id ?>" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.82rem; color: #334155; text-decoration: none;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                                            <i class="fa fa-briefcase" style="width: 16px; color: #64748b;"></i> View Vacancies (<?= $jobCount ?>)
                                                        </a>

                                                        <hr style="margin: 4px 0; border: none; border-top: 1px solid #f1f5f9;">

                                                        <button type="submit" name="admin_action" value="grant_pro" onclick="document.getElementById('pro-months-<?= $s->id ?>').value = 1;" style="display: flex; align-items: center; gap: 8px; width: 100%; border: none; background: transparent; padding: 8px 14px; font-size: 0.82rem; color: #166534; font-weight: 600; cursor: pointer; text-align: left;" onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'">
                                                            <i class="fa fa-crown" style="width: 16px; color: #eab308;"></i> <?= $isPro ? '+1 Month Pro Extension' : 'Activate 1 Month Pro' ?>
                                                        </button>

                                                        <button type="submit" name="admin_action" value="grant_pro" onclick="document.getElementById('pro-months-<?= $s->id ?>').value = 3;" style="display: flex; align-items: center; gap: 8px; width: 100%; border: none; background: transparent; padding: 8px 14px; font-size: 0.82rem; color: #166534; font-weight: 600; cursor: pointer; text-align: left;" onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'">
                                                            <i class="fa fa-gem" style="width: 16px; color: #0f766e;"></i> +3 Months Pro (Quarterly)
                                                        </button>

                                                        <?php if ($isPro): ?>
                                                            <button type="submit" name="admin_action" value="revoke_pro" onclick="return confirm('Revoke Pro plan for this school?')" style="display: flex; align-items: center; gap: 8px; width: 100%; border: none; background: transparent; padding: 8px 14px; font-size: 0.82rem; color: #b45309; cursor: pointer; text-align: left;" onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background='transparent'">
                                                                <i class="fa fa-ban" style="width: 16px;"></i> Revoke Pro Tier
                                                            </button>
                                                        <?php endif; ?>

                                                        <hr style="margin: 4px 0; border: none; border-top: 1px solid #f1f5f9;">

                                                        <?php if ($s->status === 'suspended'): ?>
                                                            <button type="submit" name="admin_action" value="activate" style="display: flex; align-items: center; gap: 8px; width: 100%; border: none; background: transparent; padding: 8px 14px; font-size: 0.82rem; color: #0284c7; cursor: pointer; text-align: left;" onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='transparent'">
                                                                <i class="fa fa-circle-check" style="width: 16px;"></i> Reactivate School
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="submit" name="admin_action" value="suspend" onclick="return confirm('Suspend this school account?')" style="display: flex; align-items: center; gap: 8px; width: 100%; border: none; background: transparent; padding: 8px 14px; font-size: 0.82rem; color: #dc2626; cursor: pointer; text-align: left;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                                                                <i class="fa fa-ban" style="width: 16px;"></i> Suspend Account
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                </div>
                                            </div>
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

<script>
    function toggleSchoolMenu(event, schoolId) {
        event.stopPropagation();
        const menu = document.getElementById('school-menu-' + schoolId);
        const allMenus = document.querySelectorAll('.school-dropdown-menu');

        allMenus.forEach(function(m) {
            if (m !== menu) {
                m.style.display = 'none';
            }
        });

        if (menu) {
            menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
        }
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.school-dropdown-menu')) {
            document.querySelectorAll('.school-dropdown-menu').forEach(function(m) {
                m.style.display = 'none';
            });
        }
    });
</script>
