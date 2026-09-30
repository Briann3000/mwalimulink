<?php
// views/admin_forum.php - Admin Community Forum Moderation & Management
require_auth('admin');

init_forum_and_messaging_schema();

// Handle Actions (POST)
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($submittedToken)) {
        $error = "Security token mismatch.";
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'sync_default_categories') {
            $defaultCategories = [
                [
                    'name' => 'General Chat',
                    'slug' => 'general-chat',
                    'description' => 'General educator discussions, daily advice, wellness, and community networking.',
                    'icon' => 'fa-comments',
                    'sort_order' => 1
                ],
                [
                    'name' => 'TSC Swaps & Transfers',
                    'slug' => 'tsc-swaps',
                    'description' => 'Transfer swap requests across counties, replacement stations, and relocation matching.',
                    'icon' => 'fa-landmark',
                    'sort_order' => 2
                ],
                [
                    'name' => 'Teaching Practice & Internships',
                    'slug' => 'tp-internships',
                    'description' => 'Guidance, TP letters, university placement coordination, and mentoring.',
                    'icon' => 'fa-graduation-cap',
                    'sort_order' => 3
                ],
                [
                    'name' => 'CBC & Curriculum Exchange',
                    'slug' => 'cbc-curriculum',
                    'description' => 'Schemes of work, lesson plans, assessment materials, and pedagogical methodologies.',
                    'icon' => 'fa-book-open',
                    'sort_order' => 4
                ],
                [
                    'name' => 'Job Opportunities & Interviews',
                    'slug' => 'job-opportunities',
                    'description' => 'Private school hiring insights, BOM openings, interview preparation, and salary guidance.',
                    'icon' => 'fa-briefcase',
                    'sort_order' => 5
                ]
            ];

            $added = 0;
            foreach ($defaultCategories as $catData) {
                $existing = R::findOne('forumcategory', 'slug = ?', [$catData['slug']]);
                if (!$existing) {
                    $newCat = R::dispense('forumcategory');
                    $newCat->name = $catData['name'];
                    $newCat->slug = $catData['slug'];
                    $newCat->description = $catData['description'];
                    $newCat->icon = $catData['icon'];
                    $newCat->sort_order = $catData['sort_order'];
                    $newCat->threads_count = 0;
                    $newCat->replies_count = 0;
                    $newCat->created_at = date('Y-m-d H:i:s');
                    R::store($newCat);
                    $added++;
                }
            }
            log_admin_audit('forum', 'CATEGORIES_SYNCED', 'forumcategory', 0, 'Categories', "Synced default forum channels ({$added} added)");
            $msg = "Forum channels synced successfully! {$added} missing channels created.";
        } elseif ($action === 'delete_category') {
            $catId = intval($_POST['category_id'] ?? 0);
            $cat = R::load('forumcategory', $catId);
            if ($cat && $cat->id) {
                $cName = $cat->name;
                R::trash($cat);
                log_admin_audit('forum', 'CATEGORY_DELETED', 'forumcategory', $catId, $cName, "Deleted category {$cName}");
                $msg = "Category '{$cName}' deleted.";
            }
        } elseif ($action === 'create_category') {
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
                $report->actioned_by = auth_user()['email'] ?? 'Admin';
                R::store($report);
                log_admin_audit('forum', 'REPORT_DISMISSED', 'forumreport', $report->id, "Report #{$report->id}", "Report dismissed with no action taken");
                $msg = "Report dismissed.";
            }
        } elseif ($action === 'lock_thread') {
            $threadId = intval($_POST['thread_id'] ?? 0);
            $t = R::load('forumthread', $threadId);
            if ($t && $t->id) {
                $t->is_locked = 1;
                $t->updated_at = date('Y-m-d H:i:s');
                R::store($t);
                log_admin_audit('forum', 'THREAD_LOCKED', 'forumthread', $t->id, $t->title, "Thread locked by admin");
                $msg = "Thread '{$t->title}' has been locked.";
            }
        } elseif ($action === 'unlock_thread') {
            $threadId = intval($_POST['thread_id'] ?? 0);
            $t = R::load('forumthread', $threadId);
            if ($t && $t->id) {
                $t->is_locked = 0;
                $t->updated_at = date('Y-m-d H:i:s');
                R::store($t);
                log_admin_audit('forum', 'THREAD_UNLOCKED', 'forumthread', $t->id, $t->title, "Thread unlocked by admin");
                $msg = "Thread '{$t->title}' unlocked.";
            }
        } elseif ($action === 'hide_thread') {
            $threadId = intval($_POST['thread_id'] ?? 0);
            $t = R::load('forumthread', $threadId);
            if ($t && $t->id) {
                $t->is_hidden = $t->is_hidden ? 0 : 1;
                $t->updated_at = date('Y-m-d H:i:s');
                R::store($t);
                $stateText = $t->is_hidden ? 'hidden' : 'unhidden';
                log_admin_audit('forum', 'THREAD_VISIBILITY_TOGGLED', 'forumthread', $t->id, $t->title, "Thread {$stateText} by admin");
                $msg = "Thread visibility changed to: {$stateText}.";
            }
        } elseif ($action === 'delete_thread') {
            $threadId = intval($_POST['thread_id'] ?? 0);
            $t = R::load('forumthread', $threadId);
            if ($t && $t->id) {
                $title = $t->title;
                $catId = $t->category_id;
                
                // Remove replies, reactions, reports
                R::exec('DELETE FROM forumreaction WHERE thread_id = ?', [$t->id]);
                R::exec('DELETE FROM forumreport WHERE thread_id = ?', [$t->id]);
                R::exec('DELETE FROM forumreply WHERE thread_id = ?', [$t->id]);
                R::trash($t);

                // Update category counts
                $cat = R::load('forumcategory', $catId);
                if ($cat && $cat->id) {
                    $cat->threads_count = max(0, R::count('forumthread', 'category_id = ?', [$catId]));
                    $cat->replies_count = max(0, R::count('forumreply', 'category_id = ?', [$catId]));
                    R::store($cat);
                }

                log_admin_audit('forum', 'THREAD_DELETED', 'forumthread', $threadId, $title, "Thread and its replies deleted by admin");
                $msg = "Thread '{$title}' and its replies were deleted permanently.";
            }
        } elseif ($action === 'bulk_threads') {
            $bulkAction = $_POST['bulk_action'] ?? '';
            $selectedIds = $_POST['thread_ids'] ?? [];
            if (empty($selectedIds) || !is_array($selectedIds)) {
                $error = "No discussions selected for bulk operation.";
            } else {
                $count = 0;
                foreach ($selectedIds as $tid) {
                    $t = R::load('forumthread', intval($tid));
                    if ($t && $t->id) {
                        if ($bulkAction === 'delete') {
                            $catId = $t->category_id;
                            R::exec('DELETE FROM forumreaction WHERE thread_id = ?', [$t->id]);
                            R::exec('DELETE FROM forumreport WHERE thread_id = ?', [$t->id]);
                            R::exec('DELETE FROM forumreply WHERE thread_id = ?', [$t->id]);
                            R::trash($t);
                            $cat = R::load('forumcategory', $catId);
                            if ($cat && $cat->id) {
                                $cat->threads_count = max(0, R::count('forumthread', 'category_id = ?', [$catId]));
                                $cat->replies_count = max(0, R::count('forumreply', 'category_id = ?', [$catId]));
                                R::store($cat);
                            }
                            $count++;
                        } elseif ($bulkAction === 'lock') {
                            $t->is_locked = 1;
                            $t->updated_at = date('Y-m-d H:i:s');
                            R::store($t);
                            $count++;
                        } elseif ($bulkAction === 'unlock') {
                            $t->is_locked = 0;
                            $t->updated_at = date('Y-m-d H:i:s');
                            R::store($t);
                            $count++;
                        } elseif ($bulkAction === 'hide') {
                            $t->is_hidden = 1;
                            $t->updated_at = date('Y-m-d H:i:s');
                            R::store($t);
                            $count++;
                        } elseif ($bulkAction === 'unhide') {
                            $t->is_hidden = 0;
                            $t->updated_at = date('Y-m-d H:i:s');
                            R::store($t);
                            $count++;
                        }
                    }
                }
                log_admin_audit('forum', 'BULK_FORUM_ACTION', 'forumthread', 0, 'Bulk Action', "Performed bulk action '{$bulkAction}' on {$count} discussions");
                $msg = "Bulk action '{$bulkAction}' executed on {$count} discussions.";
            }
        } elseif ($action === 'bulk_reports') {
            $bulkAction = $_POST['bulk_action'] ?? '';
            $selectedRepIds = $_POST['report_ids'] ?? [];
            if (empty($selectedRepIds) || !is_array($selectedRepIds)) {
                $error = "No flagged reports selected.";
            } else {
                $repCount = 0;
                foreach ($selectedRepIds as $rid) {
                    $rep = R::load('forumreport', intval($rid));
                    if ($rep && $rep->id) {
                        if ($bulkAction === 'dismiss') {
                            $rep->status = 'dismissed';
                            $rep->actioned_at = date('Y-m-d H:i:s');
                            $rep->actioned_by = auth_user()['email'] ?? 'Admin';
                            R::store($rep);
                            $repCount++;
                        } elseif ($bulkAction === 'delete_content' && $rep->thread_id) {
                            $t = R::load('forumthread', $rep->thread_id);
                            if ($t && $t->id) {
                                $catId = $t->category_id;
                                R::exec('DELETE FROM forumreaction WHERE thread_id = ?', [$t->id]);
                                R::exec('DELETE FROM forumreport WHERE thread_id = ?', [$t->id]);
                                R::exec('DELETE FROM forumreply WHERE thread_id = ?', [$t->id]);
                                R::trash($t);
                                $cat = R::load('forumcategory', $catId);
                                if ($cat && $cat->id) {
                                    $cat->threads_count = max(0, R::count('forumthread', 'category_id = ?', [$catId]));
                                    $cat->replies_count = max(0, R::count('forumreply', 'category_id = ?', [$catId]));
                                    R::store($cat);
                                }
                            }
                            $rep->status = 'actioned';
                            $rep->actioned_at = date('Y-m-d H:i:s');
                            $rep->actioned_by = auth_user()['email'] ?? 'Admin';
                            R::store($rep);
                            $repCount++;
                        }
                    }
                }
                log_admin_audit('forum', 'BULK_REPORTS_ACTION', 'forumreport', 0, 'Bulk Reports', "Performed bulk action '{$bulkAction}' on {$repCount} reports");
                $msg = "Bulk action '{$bulkAction}' executed on {$repCount} reports.";
            }
        }
    }
}

$categories = forum_get_categories();

// Metrics
$totalThreads = R::count('forumthread');
$totalReplies = R::count('forumreply');
$pendingReports = R::findAll('forumreport', 'status = ? ORDER BY created_at DESC', ['pending']);
$recentThreads = R::findAll('forumthread', 'ORDER BY created_at DESC LIMIT 50');
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

            <div class="admin-header-flex"
                style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h2
                        style="margin: 0 0 4px; color: #0f172a; font-size: 1.35rem; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-shield-halved" style="color: #0f766e;"></i> Forum Moderation & Management
                    </h2>
                    <p style="margin: 0; font-size: 0.84rem; color: #64748b;">
                        Review community reports, manage discussions, and maintain safe educator communication.
                    </p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <form method="POST" action="/admin/forum" style="margin: 0;">
                        <?= csrf_field() ?>
                        <button type="submit" name="action" value="sync_default_categories"
                            style="background: #f0fdfa; border: 1px solid #99f6e4; color: #0f766e; font-size: 0.82rem; font-weight: 700; padding: 7px 12px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-arrows-rotate"></i> Sync Default Channels
                        </button>
                    </form>
                    <a href="/forum" target="_blank"
                        style="background: white; border: 1px solid #cbd5e1; color: #0f766e !important; font-size: 0.82rem; font-weight: 600; padding: 7px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-external-link"></i> View Forum
                    </a>
                </div>
            </div>

            <?php if ($msg): ?>
                <div
                    style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 10px 14px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.86rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-check-circle"></i> <?= h($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div
                    style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 10px 14px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.86rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <!-- Metrics Grid -->
            <div class="stat-grid-responsive"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem;">
                    <span style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Topics</span>
                    <strong style="font-size: 1.4rem; color: #0f172a; display: block; margin-top: 2px;"><?= number_format($totalThreads) ?></strong>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem;">
                    <span style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Responses</span>
                    <strong style="font-size: 1.4rem; color: #0f766e; display: block; margin-top: 2px;"><?= number_format($totalReplies) ?></strong>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem;">
                    <span style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Flagged Reports</span>
                    <strong style="font-size: 1.4rem; color: <?= count($pendingReports) > 0 ? '#dc2626' : '#16a34a' ?>; display: block; margin-top: 2px;"><?= count($pendingReports) ?></strong>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem;">
                    <span style="font-size: 0.74rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Active Channels</span>
                    <strong style="font-size: 1.4rem; color: #334155; display: block; margin-top: 2px;"><?= count($categories) ?></strong>
                </div>
            </div>

            <!-- Active Forum Channels / Discussion Rooms -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <h3 style="margin: 0 0 2px; color: #0f172a; font-size: 1.15rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-folder-tree" style="color: #0f766e;"></i> Active Forum Channels (<?= count($categories) ?>)
                        </h3>
                        <span style="font-size: 0.8rem; color: #64748b;">Public discussion rooms visible in the forum sidebar.</span>
                    </div>
                </div>

                <div class="admin-table-container" style="margin-bottom: 1.25rem;">
                    <table class="admin-table">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 0.76rem; text-transform: uppercase;">
                                <th style="padding: 10px;">Icon & Name</th>
                                <th style="padding: 10px;">Slug</th>
                                <th style="padding: 10px;">Description</th>
                                <th style="padding: 10px;">Topics</th>
                                <th style="padding: 10px; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categories)): ?>
                                <tr>
                                    <td colspan="5" style="padding: 2rem; text-align: center; color: #94a3b8;">
                                        No categories found. Click "Sync Default Channels" above to seed standard channels.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categories as $c): ?>
                                    <?php
                                    $tCount = R::count('forumthread', 'category_id = ? AND is_hidden = 0', [$c->id]);
                                    ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 10px; font-weight: 700; color: #0f172a;">
                                            <i class="fa <?= h($c->icon ?: 'fa-comments') ?>" style="color: #0f766e; width: 18px; text-align: center; margin-right: 6px;"></i>
                                            <?= h($c->name) ?>
                                        </td>
                                        <td style="padding: 10px;">
                                            <code style="font-size: 0.78rem; color: #0f766e; background: #f0fdfa; padding: 2px 6px; border-radius: 4px;"><?= h($c->slug) ?></code>
                                        </td>
                                        <td style="padding: 10px; color: #64748b; font-size: 0.8rem; max-width: 350px;">
                                            <?= h($c->description) ?>
                                        </td>
                                        <td style="padding: 10px; font-weight: 700; color: #334155;">
                                            <?= $tCount ?>
                                        </td>
                                        <td style="padding: 10px; text-align: right;">
                                            <form method="POST" action="/admin/forum" style="margin: 0; display: inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="category_id" value="<?= $c->id ?>">
                                                <button type="submit" name="action" value="delete_category" onclick="return confirm('Delete channel \'<?= addslashes(h($c->name)) ?>\'?')" style="background: transparent; border: none; color: #dc2626; cursor: pointer; font-size: 0.8rem; padding: 4px 8px;" title="Delete Channel">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Create Custom Channel Inline Form -->
                <details style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 14px;">
                    <summary style="font-size: 0.84rem; font-weight: 700; color: #0f766e; cursor: pointer;">
                        + Create New Channel / Discussion Room
                    </summary>
                    <form method="POST" action="/admin/forum" style="margin-top: 12px; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; align-items: flex-end;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_category">
                        <div>
                            <label style="font-size: 0.78rem; font-weight: 600; color: #475569; display: block; margin-bottom: 2px;">Channel Name</label>
                            <input type="text" name="name" required placeholder="e.g. Science Teachers Club" style="width: 100%; box-sizing: border-box; padding: 6px 10px; font-size: 0.84rem; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="font-size: 0.78rem; font-weight: 600; color: #475569; display: block; margin-bottom: 2px;">FontAwesome Icon</label>
                            <input type="text" name="icon" placeholder="fa-flask" value="fa-comments" style="width: 100%; box-sizing: border-box; padding: 6px 10px; font-size: 0.84rem; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </div>
                        <div style="grid-column: span 2;">
                            <label style="font-size: 0.78rem; font-weight: 600; color: #475569; display: block; margin-bottom: 2px;">Description</label>
                            <input type="text" name="description" placeholder="Brief purpose of this room" style="width: 100%; box-sizing: border-box; padding: 6px 10px; font-size: 0.84rem; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </div>
                        <div>
                            <button type="submit" style="background: #0f766e; color: white; border: none; font-size: 0.84rem; font-weight: 700; padding: 7px 16px; border-radius: 4px; cursor: pointer;">
                                Create Channel
                            </button>
                        </div>
                    </form>
                </details>
            </div>

            <!-- Flagged Reports Queue with Bulk Actions -->
            <?php if (!empty($pendingReports)): ?>
                <div style="background: white; border: 1px solid #fecaca; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 8px;">
                        <h3 style="margin: 0; color: #991b1b; font-size: 1.1rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-flag"></i> Flagged Content Requiring Review (<?= count($pendingReports) ?>)
                        </h3>
                    </div>

                    <form method="POST" action="/admin/forum" id="bulkReportsForm">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="bulk_reports">

                        <!-- Bulk Action Toolbar -->
                        <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="selectAllReports" onclick="toggleSelectAllReports(this)" style="cursor: pointer;">
                                <label for="selectAllReports" style="font-size: 0.82rem; font-weight: 700; color: #991b1b; cursor: pointer; margin: 0;">Select All Reports</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <select name="bulk_action" required style="padding: 4px 8px; font-size: 0.8rem; border-radius: 4px; border: 1px solid #fda4af;">
                                    <option value="">-- Choose Bulk Action --</option>
                                    <option value="dismiss">Bulk Dismiss Reports</option>
                                    <option value="delete_content">Bulk Delete Reported Discussions</option>
                                </select>
                                <button type="submit" onclick="return confirm('Apply bulk action to selected reports?')" style="background: #991b1b; color: white; border: none; font-size: 0.8rem; font-weight: 700; padding: 5px 12px; border-radius: 4px; cursor: pointer;">
                                    Apply
                                </button>
                            </div>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <?php foreach ($pendingReports as $rep): ?>
                                <div style="background: #fef2f2; border: 1px solid #fca5a5; border-radius: 6px; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                        <input type="checkbox" name="report_ids[]" value="<?= $rep->id ?>" class="report-chk" style="margin-top: 4px; cursor: pointer;">
                                        <div>
                                            <strong style="font-size: 0.88rem; color: #991b1b; display: block;">Reason: <?= h($rep->reason) ?></strong>
                                            <span style="font-size: 0.78rem; color: #64748b;">
                                                Reported <?= date('M d, g:i A', strtotime($rep->created_at)) ?> &bull;
                                                <?php if ($rep->thread_id): ?>
                                                    <a href="/forum/thread?id=<?= $rep->thread_id ?>" target="_blank" style="color: #0f766e; font-weight: 600;">Inspect Topic #<?= $rep->thread_id ?> &rarr;</a>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                        <?php if ($rep->thread_id): ?>
                                            <a href="/forum/thread?id=<?= $rep->thread_id ?>" target="_blank" style="background: white; border: 1px solid #cbd5e1; color: #0f766e; font-size: 0.78rem; font-weight: 600; padding: 5px 10px; border-radius: 4px; text-decoration: none;">View</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Recent Discussions Management Table with Bulk Actions -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 8px;">
                    <h3 style="margin: 0; color: #0f172a; font-size: 1.15rem; font-weight: 700;">
                        Manage Recent Discussions
                    </h3>
                </div>

                <form method="POST" action="/admin/forum" id="bulkThreadsForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="bulk_threads">

                    <!-- Bulk Actions Toolbar -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="selectAllThreads" onclick="toggleSelectAllThreads(this)" style="cursor: pointer;">
                            <label for="selectAllThreads" style="font-size: 0.82rem; font-weight: 700; color: #334155; cursor: pointer; margin: 0;">Select All Discussions</label>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <select name="bulk_action" required style="padding: 4px 8px; font-size: 0.8rem; border-radius: 4px; border: 1px solid #cbd5e1;">
                                <option value="">-- Choose Bulk Action --</option>
                                <option value="delete">Bulk Delete Selected Topics</option>
                                <option value="lock">Bulk Lock Selected Topics</option>
                                <option value="unlock">Bulk Unlock Selected Topics</option>
                                <option value="hide">Bulk Hide Selected Topics</option>
                                <option value="unhide">Bulk Unhide Selected Topics</option>
                            </select>
                            <button type="submit" onclick="return confirm('Apply bulk action to selected discussions?')" style="background: #0f766e; color: white; border: none; font-size: 0.8rem; font-weight: 700; padding: 5px 14px; border-radius: 4px; cursor: pointer;">
                                Apply Bulk Action
                            </button>
                        </div>
                    </div>

                    <div class="admin-table-container">
                        <table class="admin-table">
                            <thead>
                                <tr style="border-bottom: 2px solid #e2e8f0; color: #64748b; font-size: 0.76rem; text-transform: uppercase;">
                                    <th style="padding: 10px; width: 40px; text-align: center;">#</th>
                                    <th style="padding: 10px;">Title & Topic</th>
                                    <th style="padding: 10px;">Author</th>
                                    <th style="padding: 10px;">Replies</th>
                                    <th style="padding: 10px;">Status</th>
                                    <th style="padding: 10px; text-align: right;">Moderation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentThreads)): ?>
                                    <tr>
                                        <td colspan="6" style="padding: 2rem; text-align: center; color: #94a3b8;">
                                            No discussions have been posted yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentThreads as $t): ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9;">
                                            <td style="padding: 10px; text-align: center;">
                                                <input type="checkbox" name="thread_ids[]" value="<?= $t->id ?>" class="thread-chk" style="cursor: pointer;">
                                            </td>
                                            <td style="padding: 10px;">
                                                <a href="/forum/thread?id=<?= $t->id ?>" target="_blank" style="color: #0f172a; font-weight: 700; text-decoration: none;">
                                                    <?= h(substr($t->title, 0, 50)) ?><?= (strlen($t->title) > 50) ? '...' : '' ?>
                                                </a>
                                                <span style="display: block; font-size: 0.75rem; color: #94a3b8;"><?= date('M d, Y \a\t g:i A', strtotime($t->created_at)) ?></span>
                                            </td>
                                            <td style="padding: 10px; color: #475569;">
                                                <?= h($t->author_name) ?>
                                            </td>
                                            <td style="padding: 10px; font-weight: 600;">
                                                <?= intval($t->replies_count) ?>
                                            </td>
                                            <td style="padding: 10px;">
                                                <?php if ($t->is_hidden): ?>
                                                    <span style="background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 0.72rem; font-weight: 700;">Hidden</span>
                                                <?php elseif ($t->is_locked): ?>
                                                    <span style="background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 0.72rem; font-weight: 700;">Locked</span>
                                                <?php else: ?>
                                                    <span style="background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 4px; font-size: 0.72rem; font-weight: 700;">Active</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 10px; text-align: right; white-space: nowrap;">
                                                <div style="display: inline-flex; gap: 4px; align-items: center;">
                                                    <a href="/forum/thread?id=<?= $t->id ?>" target="_blank" style="color: #0f766e; font-weight: 600; font-size: 0.78rem; text-decoration: none; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                                                        View
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<script>
    function toggleSelectAllThreads(master) {
        document.querySelectorAll('.thread-chk').forEach(function(chk) {
            chk.checked = master.checked;
        });
    }

    function toggleSelectAllReports(master) {
        document.querySelectorAll('.report-chk').forEach(function(chk) {
            chk.checked = master.checked;
        });
    }
</script>