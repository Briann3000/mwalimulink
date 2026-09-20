<?php
// views/admin_forum.php - Admin Community Forum Moderation & Management
require_auth('admin');

init_forum_and_messaging_schema();

$categories = forum_get_categories();

// Handle Actions (POST)
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($submittedToken)) {
        $error = "Security token mismatch.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_category') {
            $name = trim($_POST['name'] ?? '');
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            $description = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? 'fa-comments');

            if (!empty($name) && !empty($slug)) {
                $cat = R::dispense('forumcategory');
                $cat->name = $name;
                $cat->slug = $slug;
                $cat->description = $description;
                $cat->icon = $icon;
                $cat->sort_order = R::count('forumcategory') + 1;
                $cat->threads_count = 0;
                $cat->replies_count = 0;
                $cat->created_at = date('Y-m-d H:i:s');
                R::store($cat);
                $msg = "Category '{$name}' created successfully.";
            }
        } elseif ($action === 'dismiss_report') {
            $reportId = intval($_POST['report_id'] ?? 0);
            $report = R::load('forumreport', $reportId);
            if ($report && $report->id) {
                $report->status = 'dismissed';
                $report->actioned_at = date('Y-m-d H:i:s');
                $report->actioned_by = auth_user()['name'] ?? 'Admin';
                R::store($report);
                $msg = "Report dismissed.";
            }
        }
    }
}

// Metrics
$totalThreads = R::count('forumthread');
$totalReplies = R::count('forumreply');
$pendingReports = R::findAll('forumreport', 'status = ? ORDER BY created_at DESC', ['pending']);
$recentThreads = R::findAll('forumthread', 'ORDER BY created_at DESC LIMIT 30');
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="width: 100%; box-sizing: border-box;">
        <div style="max-width: 1040px; margin: 0 auto;">

            <div
                style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <h2
                        style="margin: 0 0 4px; color: #0f172a; font-size: 1.4rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-shield-halved" style="color: #0f766e;"></i> Forum Moderation & Management
                    </h2>
                    <p style="margin: 0; font-size: 0.86rem; color: #64748b;">
                        Review community reports, manage discussions, and maintain safe educator communication.
                    </p>
                </div>
                <a href="/forum" target="_blank"
                    style="background: white; border: 1px solid #cbd5e1; color: #0f766e !important; font-size: 0.85rem; font-weight: 600; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-external-link"></i> View Public Forum
                </a>
            </div>

            <?php if ($msg): ?>
                <div
                    style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-check-circle"></i> <?= h($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div
                    style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <!-- Metrics Grid -->
            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                    <span style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total
                        Topics</span>
                    <strong
                        style="font-size: 1.6rem; color: #0f172a; display: block; margin-top: 4px;"><?= number_format($totalThreads) ?></strong>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                    <span style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total
                        Responses</span>
                    <strong
                        style="font-size: 1.6rem; color: #0f766e; display: block; margin-top: 4px;"><?= number_format($totalReplies) ?></strong>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                    <span
                        style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Flagged
                        Reports</span>
                    <strong
                        style="font-size: 1.6rem; color: <?= count($pendingReports) > 0 ? '#dc2626' : '#16a34a' ?>; display: block; margin-top: 4px;"><?= count($pendingReports) ?></strong>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                    <span
                        style="font-size: 0.78rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Categories</span>
                    <strong
                        style="font-size: 1.6rem; color: #334155; display: block; margin-top: 4px;"><?= count($categories) ?></strong>
                </div>
            </div>

            <!-- Flagged Reports Queue -->
            <?php if (!empty($pendingReports)): ?>
                <div
                    style="background: white; border: 1px solid #fecaca; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem;">
                    <h3
                        style="margin: 0 0 1rem; color: #991b1b; font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-flag"></i> Flagged Content Requiring Review (<?= count($pendingReports) ?>)
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <?php foreach ($pendingReports as $rep): ?>
                            <div
                                style="background: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <strong style="font-size: 0.88rem; color: #991b1b; display: block;">Reason:
                                        <?= h($rep->reason) ?></strong>
                                    <span style="font-size: 0.78rem; color: #64748b;">
                                        Reported <?= date('M d, g:i A', strtotime($rep->created_at)) ?> &bull;
                                        <?php if ($rep->thread_id): ?>
                                            <a href="/forum/thread?id=<?= $rep->thread_id ?>" target="_blank"
                                                style="color: #0f766e; font-weight: 600;">Inspect Topic #<?= $rep->thread_id ?>
                                                &rarr;</a>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div style="display: flex; gap: 8px;">
                                    <form method="POST" action="/admin/forum" style="margin: 0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="dismiss_report">
                                        <input type="hidden" name="report_id" value="<?= $rep->id ?>">
                                        <button type="submit"
                                            style="background: white; border: 1px solid #cbd5e1; color: #475569; font-size: 0.78rem; font-weight: 600; padding: 6px 12px; border-radius: 4px; cursor: pointer;">
                                            Dismiss Report
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Discussions Management Table -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <h3 style="margin: 0 0 1rem; color: #0f172a; font-size: 1.15rem; font-weight: 700;">
                    Manage Recent Discussions
                </h3>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.86rem; text-align: left;">
                        <thead>
                            <tr
                                style="border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 0.78rem; text-transform: uppercase;">
                                <th style="padding: 10px;">Title & Topic</th>
                                <th style="padding: 10px;">Author</th>
                                <th style="padding: 10px;">Replies</th>
                                <th style="padding: 10px;">Status</th>
                                <th style="padding: 10px; text-align: right;">Moderation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentThreads as $t): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 10px;">
                                        <a href="/forum/thread?id=<?= $t->id ?>" target="_blank"
                                            style="color: #0f172a; font-weight: 700; text-decoration: none;">
                                            <?= h(substr($t->title, 0, 50)) ?>    <?= (strlen($t->title) > 50) ? '...' : '' ?>
                                        </a>
                                        <span
                                            style="display: block; font-size: 0.75rem; color: #94a3b8;"><?= date('M d, Y', strtotime($t->created_at)) ?></span>
                                    </td>
                                    <td style="padding: 10px; color: #475569;">
                                        <?= h($t->author_name) ?>
                                    </td>
                                    <td style="padding: 10px; font-weight: 600;">
                                        <?= intval($t->replies_count) ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?php if ($t->is_hidden): ?>
                                            <span
                                                style="background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 0.72rem; font-weight: 700;">Hidden</span>
                                        <?php elseif ($t->is_locked): ?>
                                            <span
                                                style="background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 0.72rem; font-weight: 700;">Locked</span>
                                        <?php else: ?>
                                            <span
                                                style="background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 4px; font-size: 0.72rem; font-weight: 700;">Active</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px; text-align: right;">
                                        <a href="/forum/thread?id=<?= $t->id ?>" target="_blank"
                                            style="color: #0f766e; font-weight: 600; font-size: 0.8rem; text-decoration: none;">
                                            View / Moderate
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>