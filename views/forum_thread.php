<?php
// views/forum_thread.php - Single Discussion Thread & Replies
init_forum_and_messaging_schema();

$authUser = auth_user();
$isLoggedIn = is_logged_in();

$threadId = intval($_GET['id'] ?? 0);
$thread = null;

if ($threadId > 0) {
    $thread = R::load('forumthread', $threadId);
}

if (!$thread || !$thread->id || $thread->is_hidden) {
    echo "<div class='container' style='max-width: 800px; margin: 3rem auto; padding: 2rem; text-align: center;'><div style='background: #fee2e2; color: #991b1b; padding: 1.5rem; border-radius: 8px;'>This discussion topic does not exist or has been removed. <a href='/forum' style='display:block; margin-top:8px; font-weight:700;'>Return to Forum Hub</a></div></div>";
    exit();
}

// Increment view counter
$thread->views_count = (int) $thread->views_count + 1;
R::store($thread);

$category = R::load('forumcategory', $thread->category_id);
$authorDisplay = forum_get_author_display($thread->user_id, $thread->user_role);

// Check if user has liked this thread
$userHasLiked = false;
if ($isLoggedIn) {
    $userHasLiked = (bool) R::findOne('forumreaction', 'thread_id = ? AND user_id = ? AND user_role = ?', [
        $thread->id,
        $authUser['user_id'],
        $authUser['role']
    ]);
}

// Permissions
$isAuthor = ($isLoggedIn && $authUser['user_id'] == $thread->user_id && $authUser['role'] === $thread->user_role);
$isAdmin = ($isLoggedIn && $authUser['role'] === 'admin');
$canModerate = ($isAuthor || $isAdmin);

// Fetch replies
$replies = R::findAll('forumreply', 'thread_id = ? AND is_hidden = 0 ORDER BY created_at ASC', [$thread->id]);

// Time helper
if (!function_exists('forum_detail_time')) {
    function forum_detail_time($datetime)
    {
        if (empty($datetime))
            return 'recently';
        return date('M d, Y \a\t g:i A', strtotime($datetime));
    }
}
?>

<div class="<?= $isLoggedIn ? 'workspace-wrapper' : 'container' ?>"
    style="<?= !$isLoggedIn ? 'max-width: 1040px; margin: 2rem auto; padding: 0 1rem;' : '' ?>">
    <?php if ($isLoggedIn)
        include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="<?= $isLoggedIn ? 'content-pane' : '' ?>" style="width: 100%; box-sizing: border-box;">
        <div style="max-width: 860px; margin: 0 auto;">

            <!-- Breadcrumb Navigation Bar -->
            <div
                style="margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div
                    style="display: flex; align-items: center; gap: 8px; font-size: 0.84rem; color: #64748b; flex-wrap: wrap;">
                    <a href="/forum" style="color: #0f766e; text-decoration: none; font-weight: 600;">
                        <i class="fa fa-comments"></i> Forum Hub
                    </a>
                    <span>/</span>
                    <?php if ($category && $category->id): ?>
                        <a href="/forum?category=<?= urlencode($category->slug) ?>"
                            style="color: #0f766e; text-decoration: none; font-weight: 600;">
                            <?= h($category->name) ?>
                        </a>
                        <span>/</span>
                    <?php endif; ?>
                    <span
                        style="color: #94a3b8; max-width: 260px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        <?= h($thread->title) ?>
                    </span>
                </div>

                <div style="display: flex; gap: 8px;">
                    <a href="/forum"
                        style="background: white; border: 1px solid #cbd5e1; color: #475569 !important; font-size: 0.82rem; font-weight: 600; padding: 6px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-arrow-left"></i> All Topics
                    </a>
                </div>
            </div>

            <!-- Main Thread Article Card -->
            <article
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 1.5rem;">
                <!-- Header Tags & Badges -->
                <div
                    style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 1rem;">
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <?php if ($thread->is_pinned): ?>
                            <span
                                style="background: #fef3c7; color: #92400e; font-size: 0.75rem; font-weight: 800; padding: 3px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fa fa-thumbtack"></i> Pinned
                            </span>
                        <?php endif; ?>

                        <?php if ($thread->is_locked): ?>
                            <span
                                style="background: #f1f5f9; color: #64748b; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fa fa-lock"></i> Locked Discussion
                            </span>
                        <?php endif; ?>

                        <?php if ($category && $category->id): ?>
                            <a href="/forum?category=<?= urlencode($category->slug) ?>"
                                style="background: #f0fdfa; color: #0f766e; font-size: 0.75rem; font-weight: 700; padding: 3px 10px; border-radius: 4px; text-decoration: none;">
                                <?= h($category->name) ?>
                            </a>
                        <?php endif; ?>

                        <?php if (!empty($thread->author_county)): ?>
                            <span
                                style="background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 4px;">
                                <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i>
                                <?= h($thread->author_county) ?>
                            </span>
                        <?php endif; ?>

                        <?php if (!empty($thread->author_subjects)): ?>
                            <span
                                style="background: #f8fafc; border: 1px solid #e2e8f0; color: #475569; font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 4px;">
                                <?= h($thread->author_subjects) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <span style="font-size: 0.8rem; color: #94a3b8;">
                        <i class="fa fa-clock"></i> <?= forum_detail_time($thread->created_at) ?>
                    </span>
                </div>

                <!-- Title -->
                <h1
                    style="margin: 0 0 1.25rem; font-size: 1.5rem; color: #0f172a; font-weight: 800; line-height: 1.35;">
                    <?= h($thread->title) ?>
                </h1>

                <!-- Author Identity Bar -->
                <div
                    style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div
                            style="width: 44px; height: 44px; border-radius: 50%; background: #0f766e; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 800;">
                            <?= h($authorDisplay['initial']) ?>
                        </div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <strong style="font-size: 0.95rem; color: #0f172a;">
                                    <?= h($authorDisplay['display_name']) ?>
                                </strong>
                                <?php if ($authorDisplay['verified']): ?>
                                    <span style="color: #16a34a; font-size: 0.8rem;" title="Verified User">
                                        <i class="fa fa-circle-check"></i>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span style="font-size: 0.78rem; color: #64748b;">
                                <?= h($authorDisplay['sub_text']) ?> &bull; <?= h($authorDisplay['county']) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Direct Message Author Button -->
                    <div>
                        <?php if ($isLoggedIn && !$isAuthor): ?>
                            <?php
                            $msgParam = ($thread->user_role === 'school') ? 'to_school=' . $thread->user_id : 'to_teacher=' . $thread->user_id;
                            ?>
                            <a href="/messages?<?= $msgParam ?>"
                                style="background: white; border: 1px solid #cbd5e1; color: #0f766e !important; font-size: 0.82rem; font-weight: 600; padding: 7px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa fa-envelope"></i> Message Author (Private)
                            </a>
                        <?php elseif (!$isLoggedIn): ?>
                            <a href="/login?redirect=<?= urlencode('/forum/thread?id=' . $thread->id) ?>"
                                style="background: white; border: 1px solid #cbd5e1; color: #0f766e !important; font-size: 0.82rem; font-weight: 600; padding: 7px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa fa-envelope"></i> Message Author
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Discussion Body -->
                <div
                    style="color: #334155; font-size: 0.95rem; line-height: 1.7; margin-bottom: 2rem; word-break: break-word;">
                    <?= nl2br(h($thread->content)) ?>
                </div>

                <!-- Action Bar & Stats -->
                <div
                    style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <!-- Upvote / Like Button -->
                        <button type="button" id="likeThreadBtn" onclick="toggleThreadLike(<?= $thread->id ?>)"
                            style="background: <?= $userHasLiked ? '#f0fdfa' : '#f8fafc' ?>; border: 1px solid <?= $userHasLiked ? '#0f766e' : '#cbd5e1' ?>; color: <?= $userHasLiked ? '#0f766e' : '#475569' ?>; padding: 7px 14px; border-radius: 6px; font-size: 0.84rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-thumbs-up"></i> <span
                                id="likeThreadCount"><?= intval($thread->likes_count) ?></span> Helpful
                        </button>

                        <span style="font-size: 0.82rem; color: #64748b;">
                            <i class="fa fa-eye"></i> <?= intval($thread->views_count) ?> views
                        </span>
                    </div>

                    <div style="display: flex; gap: 8px; align-items: center;">
                        <!-- Report Button -->
                        <?php if ($isLoggedIn && !$isAuthor): ?>
                            <button type="button" onclick="openReportModal(<?= $thread->id ?>, 0)"
                                style="background: transparent; border: none; color: #94a3b8; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fa fa-flag"></i> Report
                            </button>
                        <?php endif; ?>

                        <!-- Admin / Author Moderation Controls -->
                        <?php if ($isAdmin): ?>
                            <button type="button" onclick="moderateThread(<?= $thread->id ?>, 'toggle_pin')"
                                style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; font-size: 0.78rem; font-weight: 600; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                                <?= $thread->is_pinned ? 'Unpin' : 'Pin' ?>
                            </button>
                            <button type="button" onclick="moderateThread(<?= $thread->id ?>, 'toggle_lock')"
                                style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; font-size: 0.78rem; font-weight: 600; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                                <?= $thread->is_locked ? 'Unlock' : 'Lock' ?>
                            </button>
                        <?php endif; ?>

                        <?php if ($canModerate): ?>
                            <button type="button" onclick="deleteThread(<?= $thread->id ?>)"
                                style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; font-size: 0.78rem; font-weight: 600; padding: 5px 10px; border-radius: 4px; cursor: pointer;">
                                Delete
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </article>

            <!-- Replies Section Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3
                    style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-comments" style="color: #0f766e;"></i> Responses (<?= count($replies) ?>)
                </h3>
            </div>

            <!-- Replies Feed -->
            <div id="repliesContainer" style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
                <?php if (empty($replies)): ?>
                    <div id="noRepliesPlaceholder"
                        style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 2rem; text-align: center; color: #64748b; font-size: 0.88rem;">
                        No replies yet. Be the first to share advice, offer a swap, or provide resources!
                    </div>
                <?php else: ?>
                    <?php foreach ($replies as $r): ?>
                        <?php
                        $repAuthor = forum_get_author_display($r->user_id, $r->user_role);
                        $isRepAuthor = ($isLoggedIn && $authUser['user_id'] == $r->user_id && $authUser['role'] === $r->user_role);
                        ?>
                        <div class="reply-card"
                            style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            <div
                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div
                                        style="width: 36px; height: 36px; border-radius: 50%; background: #f1f5f9; color: #334155; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem;">
                                        <?= h($repAuthor['initial']) ?>
                                    </div>
                                    <div>
                                        <strong style="font-size: 0.9rem; color: #0f172a; display: block;">
                                            <?= h($repAuthor['display_name']) ?>
                                        </strong>
                                        <span style="font-size: 0.76rem; color: #64748b;">
                                            <?= h($repAuthor['sub_text']) ?> &bull; <?= h($repAuthor['county']) ?>
                                        </span>
                                    </div>
                                </div>
                                <span style="font-size: 0.78rem; color: #94a3b8;">
                                    <?= forum_time_elapsed($r->created_at) ?>
                                </span>
                            </div>

                            <div
                                style="color: #334155; font-size: 0.9rem; line-height: 1.6; margin-bottom: 1rem; word-break: break-word;">
                                <?= nl2br(h($r->content)) ?>
                            </div>

                            <div
                                style="border-top: 1px solid #f8fafc; padding-top: 8px; display: flex; justify-content: space-between; align-items: center;">
                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <button type="button" onclick="toggleReplyLike(<?= $r->id ?>, this)"
                                        style="background: transparent; border: none; color: #64748b; font-size: 0.8rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; padding: 0;">
                                        <i class="fa fa-thumbs-up"></i> <span><?= intval($r->likes_count) ?></span>
                                    </button>
                                </div>
                                <div>
                                    <?php if ($isLoggedIn && !$isRepAuthor): ?>
                                        <?php $msgParam = ($r->user_role === 'school') ? 'to_school=' . $r->user_id : 'to_teacher=' . $r->user_id; ?>
                                        <a href="/messages?<?= $msgParam ?>"
                                            style="color: #0f766e; font-size: 0.78rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-envelope"></i> Message
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($isRepAuthor || $isAdmin): ?>
                                        <button type="button" onclick="deleteReply(<?= $r->id ?>)"
                                            style="background: transparent; border: none; color: #991b1b; font-size: 0.78rem; cursor: pointer; margin-left: 8px;">
                                            Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Reply Form or Guest Callout -->
            <?php if ($thread->is_locked): ?>
                <div
                    style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1.25rem; text-align: center; color: #64748b; font-size: 0.88rem;">
                    <i class="fa fa-lock"></i> This discussion has been locked. New replies are disabled.
                </div>
            <?php elseif ($isLoggedIn): ?>
                <div
                    style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <h4 style="margin: 0 0 10px; font-size: 1rem; color: #0f172a; font-weight: 700;">
                        Post a Response
                    </h4>
                    <form id="replyForm" onsubmit="submitReply(event)" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="thread_id" value="<?= $thread->id ?>">
                        <div style="margin-bottom: 1rem;">
                            <textarea name="reply_content" id="replyContentInput" rows="4" required
                                placeholder="Type your advice, swap details, or answer here..."
                                style="width: 100%; box-sizing: border-box; margin: 0; padding: 10px; font-family: inherit; font-size: 0.9rem; line-height: 1.5;"></textarea>
                        </div>
                        <div
                            style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <span style="font-size: 0.78rem; color: #64748b;">
                                Posting as <strong><?= h($authUser['name']) ?></strong> (Privacy protected)
                            </span>
                            <button type="submit" id="submitReplyBtn" class="btn-primary"
                                style="background: #0f766e; color: white !important; font-size: 0.88rem; font-weight: 700; padding: 9px 20px; border-radius: 6px; border: none; cursor: pointer;">
                                <i class="fa fa-paper-plane"></i> Submit Response
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Guest Conversion Box -->
                <div
                    style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 1.75rem; text-align: center;">
                    <h4 style="margin: 0 0 6px; color: #166534; font-size: 1.1rem; font-weight: 700;">
                        Join the Conversation
                    </h4>
                    <p
                        style="margin: 0 0 1.25rem; font-size: 0.88rem; color: #15803d; max-width: 480px; margin-left: auto; margin-right: auto;">
                        Sign in or register your free teacher account to post responses, connect with colleagues, and
                        message educators directly.
                    </p>
                    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                        <a href="/login?redirect=<?= urlencode('/forum/thread?id=' . $thread->id) ?>"
                            style="background: #166534; color: white !important; font-size: 0.88rem; font-weight: 700; padding: 10px 22px; border-radius: 6px; text-decoration: none;">
                            Sign In to Reply
                        </a>
                        <a href="/register/teacher"
                            style="background: white; border: 1.5px solid #166534; color: #166534 !important; font-size: 0.88rem; font-weight: 700; padding: 10px 22px; border-radius: 6px; text-decoration: none;">
                            Register Free
                        </a>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<script>
    // Asynchronous Like Thread
    function toggleThreadLike(threadId) {
        <?php if (!$isLoggedIn): ?>
            window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
            return;
        <?php endif; ?>

        fetch('/api/forum?action=like_thread', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ thread_id: threadId })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('likeThreadCount').innerText = data.likes_count;
                    const btn = document.getElementById('likeThreadBtn');
                    if (data.user_has_liked) {
                        btn.style.background = '#f0fdfa';
                        btn.style.borderColor = '#0f766e';
                        btn.style.color = '#0f766e';
                    } else {
                        btn.style.background = '#f8fafc';
                        btn.style.borderColor = '#cbd5e1';
                        btn.style.color = '#475569';
                    }
                }
            })
            .catch(e => console.error(e));
    }

    // Asynchronous Reply Submission
    function submitReply(e) {
        e.preventDefault();
        const input = document.getElementById('replyContentInput');
        const content = input.value.trim();
        const btn = document.getElementById('submitReplyBtn');

        if (!content) return;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Submitting...';

        fetch('/api/forum?action=submit_reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                thread_id: <?= $thread->id ?>,
                content: content
            })
        })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Submit Response';
                if (data.success) {
                    input.value = '';
                    // Reload to display formatted reply or append
                    window.location.reload();
                } else {
                    alert(data.message || 'Failed to post reply.');
                }
            })
            .catch(err => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa fa-paper-plane"></i> Submit Response';
                alert('Network error. Please try again.');
            });
    }

    // Moderate Thread (Pin / Lock)
    function moderateThread(threadId, action) {
        if (!confirm('Apply this moderation change?')) return;
        fetch('/api/forum?action=moderate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ thread_id: threadId, mod_action: action })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) window.location.reload();
                else alert(data.message || 'Action failed.');
            });
    }

    // Delete Thread
    function deleteThread(threadId) {
        if (!confirm('Are you sure you want to delete this discussion topic? This cannot be undone.')) return;
        fetch('/api/forum?action=delete_thread', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ thread_id: threadId })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) window.location.href = '/forum';
                else alert(data.message || 'Failed to delete.');
            });
    }

    // Delete Reply
    function deleteReply(replyId) {
        if (!confirm('Delete this response?')) return;
        fetch('/api/forum?action=delete_reply', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reply_id: replyId })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) window.location.reload();
                else alert(data.message || 'Failed to delete response.');
            });
    }

    // Report Item
    function openReportModal(threadId, replyId) {
        const reason = prompt('Please specify why you are reporting this content:');
        if (!reason || !reason.trim()) return;

        fetch('/api/forum?action=report', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                thread_id: threadId,
                reply_id: replyId,
                reason: reason.trim()
            })
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) alert('Thank you. This topic has been flagged for admin review.');
                else alert(data.message || 'Report failed.');
            });
    }
</script>