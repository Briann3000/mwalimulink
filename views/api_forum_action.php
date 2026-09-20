<?php
// views/api_forum_action.php - Forum Asynchronous Actions API
init_session();
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.', 'message' => 'Please sign in to participate.']);
    exit();
}

$authUser = auth_user();
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

try {
    if ($action === 'upload_attachment') {
        $uploaded = $_FILES['file'] ?? ($_FILES['attachment'] ?? null);
        if (!$uploaded) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded.']);
            exit();
        }

        $res = chat_validate_and_store_attachment($uploaded);
        echo json_encode($res);
        exit();
    }

    if ($action === 'get_latest') {
        $categoryId = intval($_GET['category_id'] ?? ($input['category_id'] ?? 0));
        $sinceId = intval($_GET['since_id'] ?? ($input['since_id'] ?? 0));

        if (!$categoryId) {
            echo json_encode(['success' => false, 'messages' => []]);
            exit();
        }

        $threads = R::findAll('forumthread', 'category_id = ? AND id > ? AND is_hidden = 0 ORDER BY id ASC LIMIT 50', [
            $categoryId,
            $sinceId
        ]);

        $formatted = [];
        foreach ($threads as $t) {
            $isMine = ((int) $t->user_id === (int) $authUser['user_id'] && $t->user_role === $authUser['role']);
            $displayName = $t->author_alias ?: ($t->author_name ?: 'Educator');
            $initial = strtoupper(substr($displayName, 0, 1));

            $ts = strtotime($t->created_at);
            $timeStr = (date('Y-m-d') === date('Y-m-d', $ts)) ? date('g:i A', $ts) : date('M d, g:i A', $ts);

            $formatted[] = [
                'id' => (int) $t->id,
                'user_id' => (int) $t->user_id,
                'user_role' => $t->user_role ?: 'teacher',
                'is_mine' => $isMine,
                'author_name' => $displayName,
                'author_initial' => $initial,
                'author_subjects' => $t->author_subjects ?? '',
                'author_county' => $t->author_county ?? 'Kenya',
                'content' => $t->content ?: $t->title,
                'attachment_url' => $t->attachment_url ?? null,
                'attachment_type' => $t->attachment_type ?? null,
                'attachment_name' => $t->attachment_name ?? null,
                'attachment_size' => $t->attachment_size ?? null,
                'created_at' => $timeStr
            ];
        }

        echo json_encode(['success' => true, 'messages' => $formatted]);
        exit();
    }

    if ($action === 'post_message') {
        $categoryId = intval($input['category_id'] ?? 0);
        $messageText = trim($input['message'] ?? '');
        $replyToId = intval($input['reply_to_id'] ?? 0);
        $attachmentUrl = trim($input['attachment_url'] ?? '');
        $attachmentType = trim($input['attachment_type'] ?? '');
        $attachmentName = trim($input['attachment_name'] ?? '');
        $attachmentSize = trim($input['attachment_size'] ?? '');

        if (empty($messageText) && empty($attachmentUrl)) {
            echo json_encode(['success' => false, 'message' => 'Message or attachment is required.']);
            exit();
        }

        if (empty($categoryId)) {
            $defaultCat = R::findOne('forumcategory', 'slug = ?', ['general-chat']) ?? R::findOne('forumcategory', 'ORDER BY sort_order ASC');
            $categoryId = $defaultCat ? (int) $defaultCat->id : 1;
        }

        $authorDisplay = forum_get_author_display($authUser['user_id'], $authUser['role']);

        $thread = R::dispense('forumthread');
        $thread->category_id = $categoryId;
        $thread->user_id = $authUser['user_id'];
        $thread->user_role = $authUser['role'];
        $thread->author_name = $authorDisplay['display_name'];
        $thread->author_alias = $authorDisplay['display_name'];
        $thread->author_county = $authorDisplay['county'] ?? 'Kenya';
        $thread->author_subjects = $authorDisplay['sub_text'] ?? 'Teacher';
        $thread->is_author_private = $authorDisplay['is_private'];
        $thread->title = !empty($messageText) ? substr($messageText, 0, 80) : ($attachmentName ?: 'Attachment');
        $thread->slug = 'msg-' . time() . '-' . rand(100, 999);
        $thread->content = $messageText;
        $thread->attachment_url = $attachmentUrl ?: null;
        $thread->attachment_type = $attachmentType ?: null;
        $thread->attachment_name = $attachmentName ?: null;
        $thread->attachment_size = $attachmentSize ?: null;
        $thread->reply_to_id = $replyToId ?: null;
        $thread->views_count = 1;
        $thread->replies_count = 0;
        $thread->likes_count = 0;
        $thread->is_pinned = 0;
        $thread->is_locked = 0;
        $thread->is_hidden = 0;
        $thread->last_reply_at = date('Y-m-d H:i:s');
        $thread->created_at = date('Y-m-d H:i:s');
        $threadId = R::store($thread);

        // Increment category counter
        try {
            $cat = R::load('forumcategory', $categoryId);
            if ($cat && $cat->id) {
                $cat->threads_count = (int) $cat->threads_count + 1;
                R::store($cat);
            }
        } catch (\Throwable $ignored) {
        }

        echo json_encode([
            'success' => true,
            'message_id' => $threadId,
            'created_at' => date('g:i A', strtotime($thread->created_at))
        ]);
        exit();
    }

    if ($action === 'report_user' || $action === 'report') {
        $targetUserId = intval($input['target_user_id'] ?? ($input['thread_id'] ?? 0));
        $targetRole = trim($input['target_user_role'] ?? 'teacher');
        $reason = trim($input['reason'] ?? 'Inappropriate behavior or spam');
        $details = trim($input['details'] ?? '');

        $report = R::dispense('forumreport');
        $report->thread_id = null;
        $report->reply_id = null;
        $report->reporter_id = $authUser['user_id'];
        $report->reporter_role = $authUser['role'];
        $report->reason = substr($reason . ($details ? ": {$details}" : ''), 0, 255);
        $report->details = "Reported {$targetRole} #{$targetUserId}. Details: {$details}";
        $report->status = 'pending';
        $report->created_at = date('Y-m-d H:i:s');
        R::store($report);

        echo json_encode(['success' => true, 'message' => 'Report submitted for administrator review.']);
        exit();
    }

    if ($action === 'like_thread') {
        $threadId = intval($input['thread_id'] ?? 0);
        $thread = R::load('forumthread', $threadId);
        if (!$thread || !$thread->id) {
            echo json_encode(['success' => false, 'message' => 'Topic not found.']);
            exit();
        }

        $existing = R::findOne('forumreaction', 'thread_id = ? AND user_id = ? AND user_role = ?', [
            $threadId,
            $authUser['user_id'],
            $authUser['role']
        ]);

        $userHasLiked = false;
        if ($existing && $existing->id) {
            R::trash($existing);
            $thread->likes_count = max(0, (int) $thread->likes_count - 1);
            $userHasLiked = false;
        } else {
            $reaction = R::dispense('forumreaction');
            $reaction->thread_id = $threadId;
            $reaction->reply_id = null;
            $reaction->user_id = $authUser['user_id'];
            $reaction->user_role = $authUser['role'];
            $reaction->reaction_type = 'like';
            $reaction->created_at = date('Y-m-d H:i:s');
            R::store($reaction);

            $thread->likes_count = (int) $thread->likes_count + 1;
            $userHasLiked = true;
        }
        R::store($thread);

        echo json_encode(['success' => true, 'likes_count' => (int) $thread->likes_count, 'user_has_liked' => $userHasLiked]);
        exit();
    }

    if ($action === 'submit_reply') {
        $threadId = intval($input['thread_id'] ?? 0);
        $content = trim($input['content'] ?? '');

        $thread = R::load('forumthread', $threadId);
        if (!$thread || !$thread->id || $thread->is_hidden) {
            echo json_encode(['success' => false, 'message' => 'Topic not found or removed.']);
            exit();
        }

        if ($thread->is_locked) {
            echo json_encode(['success' => false, 'message' => 'This discussion is locked.']);
            exit();
        }

        if (strlen($content) < 3) {
            echo json_encode(['success' => false, 'message' => 'Response is too short.']);
            exit();
        }

        $authorDisplay = forum_get_author_display($authUser['user_id'], $authUser['role']);

        $reply = R::dispense('forumreply');
        $reply->thread_id = $threadId;
        $reply->user_id = $authUser['user_id'];
        $reply->user_role = $authUser['role'];
        $reply->author_name = $authorDisplay['display_name'];
        $reply->author_alias = $authorDisplay['display_name'];
        $reply->author_county = $authorDisplay['county'];
        $reply->author_subjects = $authorDisplay['sub_text'];
        $reply->is_author_private = $authorDisplay['is_private'];
        $reply->content = $content;
        $reply->likes_count = 0;
        $reply->is_solution = 0;
        $reply->is_hidden = 0;
        $reply->created_at = date('Y-m-d H:i:s');
        $replyId = R::store($reply);

        // Update thread counters
        $thread->replies_count = (int) $thread->replies_count + 1;
        $thread->last_reply_at = date('Y-m-d H:i:s');
        R::store($thread);

        // Update category counters
        $cat = R::load('forumcategory', $thread->category_id);
        if ($cat && $cat->id) {
            $cat->replies_count = (int) $cat->replies_count + 1;
            R::store($cat);
        }

        echo json_encode(['success' => true, 'reply_id' => $replyId]);
        exit();
    }

    if ($action === 'delete_thread') {
        $threadId = intval($input['thread_id'] ?? 0);
        $thread = R::load('forumthread', $threadId);
        if (!$thread || !$thread->id) {
            echo json_encode(['success' => false, 'message' => 'Topic not found.']);
            exit();
        }

        $isAuthor = ($authUser['user_id'] == $thread->user_id && $authUser['role'] === $thread->user_role);
        $isAdmin = ($authUser['role'] === 'admin');

        if (!$isAuthor && !$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Permission denied.']);
            exit();
        }

        $thread->is_hidden = 1;
        R::store($thread);

        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'delete_reply') {
        $replyId = intval($input['reply_id'] ?? 0);
        $reply = R::load('forumreply', $replyId);
        if (!$reply || !$reply->id) {
            echo json_encode(['success' => false, 'message' => 'Response not found.']);
            exit();
        }

        $isAuthor = ($authUser['user_id'] == $reply->user_id && $authUser['role'] === $reply->user_role);
        $isAdmin = ($authUser['role'] === 'admin');

        if (!$isAuthor && !$isAdmin) {
            echo json_encode(['success' => false, 'message' => 'Permission denied.']);
            exit();
        }

        $reply->is_hidden = 1;
        R::store($reply);

        // Decrement thread reply count
        $thread = R::load('forumthread', $reply->thread_id);
        if ($thread && $thread->id) {
            $thread->replies_count = max(0, (int) $thread->replies_count - 1);
            R::store($thread);
        }

        echo json_encode(['success' => true]);
        exit();
    }

    if ($action === 'moderate') {
        if ($authUser['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Admin permission required.']);
            exit();
        }

        $threadId = intval($input['thread_id'] ?? 0);
        $modAction = $input['mod_action'] ?? '';
        $thread = R::load('forumthread', $threadId);
        if (!$thread || !$thread->id) {
            echo json_encode(['success' => false, 'message' => 'Topic not found.']);
            exit();
        }

        if ($modAction === 'toggle_pin') {
            $thread->is_pinned = $thread->is_pinned ? 0 : 1;
        } elseif ($modAction === 'toggle_lock') {
            $thread->is_locked = $thread->is_locked ? 0 : 1;
        }

        R::store($thread);
        echo json_encode(['success' => true, 'is_pinned' => (bool) $thread->is_pinned, 'is_locked' => (bool) $thread->is_locked]);
        exit();
    }

    if ($action === 'report') {
        $threadId = intval($input['thread_id'] ?? 0);
        $replyId = intval($input['reply_id'] ?? 0);
        $reason = trim($input['reason'] ?? 'Flagged as inappropriate');

        $report = R::dispense('forumreport');
        $report->thread_id = $threadId ?: null;
        $report->reply_id = $replyId ?: null;
        $report->reporter_id = $authUser['user_id'];
        $report->reporter_role = $authUser['role'];
        $report->reason = substr($reason, 0, 255);
        $report->details = $reason;
        $report->status = 'pending';
        $report->created_at = date('Y-m-d H:i:s');
        R::store($report);

        echo json_encode(['success' => true]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (\Throwable $e) {
    error_log("api_forum_action error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error processing request.']);
}
