<?php
// views/api_messages.php - Asynchronous Messaging API
init_session();
header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

$authUser = auth_user();
$myId = (int) $authUser['user_id'];
$myRole = $authUser['role'];
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
        $convId = intval($_GET['conversation_id'] ?? ($input['conversation_id'] ?? 0));
        $sinceId = intval($_GET['since_id'] ?? ($input['since_id'] ?? 0));

        if (!$convId) {
            echo json_encode(['success' => false, 'messages' => []]);
            exit();
        }

        $conv = R::load('directconversation', $convId);
        if (!$conv || !$conv->id) {
            echo json_encode(['success' => false, 'messages' => []]);
            exit();
        }

        // Verify participant
        $isP1 = ($conv->user1_id == $myId && $conv->user1_role === $myRole);
        $isP2 = ($conv->user2_id == $myId && $conv->user2_role === $myRole);
        if (!$isP1 && !$isP2) {
            echo json_encode(['success' => false, 'messages' => []]);
            exit();
        }

        $newMsgs = R::findAll('directmessage', 'conversation_id = ? AND id > ? ORDER BY id ASC LIMIT 50', [
            $convId,
            $sinceId
        ]);

        $formatted = [];
        foreach ($newMsgs as $m) {
            $isMine = ((int) $m->sender_id === $myId && $m->sender_role === $myRole);
            $ts = strtotime($m->created_at);
            $timeStr = (date('Y-m-d') === date('Y-m-d', $ts)) ? date('g:i A', $ts) : date('M d, g:i A', $ts);

            $formatted[] = [
                'id' => (int) $m->id,
                'sender_id' => (int) $m->sender_id,
                'sender_role' => $m->sender_role,
                'is_mine' => $isMine,
                'message' => $m->message,
                'attachment_url' => $m->attachment_url ?? null,
                'attachment_type' => $m->attachment_type ?? null,
                'attachment_name' => $m->attachment_name ?? null,
                'attachment_size' => $m->attachment_size ?? null,
                'created_at' => $timeStr
            ];
        }

        // Mark incoming messages as read
        if (!empty($newMsgs)) {
            R::exec('UPDATE directmessage SET is_read = 1 WHERE conversation_id = ? AND recipient_id = ? AND recipient_role = ? AND is_read = 0', [
                $convId,
                $myId,
                $myRole
            ]);
        }

        echo json_encode(['success' => true, 'messages' => $formatted]);
        exit();
    }

    if ($action === 'send') {
        $convId = intval($input['conversation_id'] ?? 0);
        $recipientId = intval($input['recipient_id'] ?? 0);
        $recipientRole = trim($input['recipient_role'] ?? 'teacher');
        $messageText = trim($input['message'] ?? '');
        $attachmentUrl = trim($input['attachment_url'] ?? '');
        $attachmentType = trim($input['attachment_type'] ?? '');
        $attachmentName = trim($input['attachment_name'] ?? '');
        $attachmentSize = trim($input['attachment_size'] ?? '');

        if (empty($messageText) && empty($attachmentUrl)) {
            echo json_encode(['success' => false, 'message' => 'Message text or attachment is required.']);
            exit();
        }

        $conv = null;
        if ($convId > 0) {
            $conv = R::load('directconversation', $convId);
        }

        if (!$conv || !$conv->id) {
            $conv = get_or_create_conversation($myId, $myRole, $recipientId, $recipientRole);
        }

        if (!$conv || !$conv->id) {
            echo json_encode(['success' => false, 'message' => 'Could not initialize conversation.']);
            exit();
        }

        // Verify sender is participant
        $isP1 = ($conv->user1_id == $myId && $conv->user1_role === $myRole);
        $isP2 = ($conv->user2_id == $myId && $conv->user2_role === $myRole);

        if (!$isP1 && !$isP2) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized conversation access.']);
            exit();
        }

        $actualRecipientId = $isP1 ? $conv->user2_id : $conv->user1_id;
        $actualRecipientRole = $isP1 ? $conv->user2_role : $conv->user1_role;

        $msg = R::dispense('directmessage');
        $msg->conversation_id = $conv->id;
        $msg->sender_id = $myId;
        $msg->sender_role = $myRole;
        $msg->recipient_id = $actualRecipientId;
        $msg->recipient_role = $actualRecipientRole;
        $msg->message = $messageText;
        $msg->attachment_url = $attachmentUrl ?: null;
        $msg->attachment_type = $attachmentType ?: null;
        $msg->attachment_name = $attachmentName ?: null;
        $msg->attachment_size = $attachmentSize ?: null;
        $msg->is_read = 0;
        $msg->created_at = date('Y-m-d H:i:s');
        $msgId = R::store($msg);

        // Update conversation timestamp
        $conv->last_message_at = date('Y-m-d H:i:s');
        R::store($conv);

        echo json_encode([
            'success' => true,
            'message_id' => $msgId,
            'conversation_id' => $conv->id,
            'created_at' => date('g:i A')
        ]);
        exit();
    }

    if ($action === 'unread_count') {
        $unread = get_unread_message_count($myId, $myRole);
        echo json_encode(['success' => true, 'unread_count' => $unread]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
} catch (\Throwable $e) {
    error_log("api_messages error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error sending message.']);
}
