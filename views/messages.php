<?php
// views/messages.php - Private Teacher & School Direct Messaging Inbox
require_auth();

$authUser = auth_user();
$myId = (int) $authUser['user_id'];
$myRole = $authUser['role'];

// Handle direct compose target (e.g. ?to_teacher=123 or ?to_school=45)
$toTeacherId = intval($_GET['to_teacher'] ?? 0);
$toSchoolId = intval($_GET['to_school'] ?? 0);
$selectedConvId = intval($_GET['conv'] ?? 0);

if ($toTeacherId > 0) {
    $conv = get_or_create_conversation($myId, $myRole, $toTeacherId, 'teacher');
    if ($conv && $conv->id) {
        $selectedConvId = (int) $conv->id;
    }
} elseif ($toSchoolId > 0) {
    $conv = get_or_create_conversation($myId, $myRole, $toSchoolId, 'school');
    if ($conv && $conv->id) {
        $selectedConvId = (int) $conv->id;
    }
}

// Fetch all conversations for current user
$conversations = [];
$activeConv = null;
$otherUser = null;
$messages = [];

try {
    $conversations = R::findAll('directconversation', '
        (user1_id = ? AND user1_role = ?) OR (user2_id = ? AND user2_role = ?)
        ORDER BY last_message_at DESC, created_at DESC
    ', [$myId, $myRole, $myId, $myRole]);

    // If no conversation explicitly selected, pick first conversation
    if (!$selectedConvId && !empty($conversations)) {
        $firstConv = reset($conversations);
        $selectedConvId = (int) $firstConv->id;
    }

    if ($selectedConvId > 0) {
        $activeConv = R::load('directconversation', $selectedConvId);
        if ($activeConv && $activeConv->id) {
            // Verify user is a participant
            $isP1 = ($activeConv->user1_id == $myId && $activeConv->user1_role === $myRole);
            $isP2 = ($activeConv->user2_id == $myId && $activeConv->user2_role === $myRole);

            if ($isP1 || $isP2) {
                $otherId = $isP1 ? $activeConv->user2_id : $activeConv->user1_id;
                $otherRole = $isP1 ? $activeConv->user2_role : $activeConv->user1_role;
                $otherUser = forum_get_author_display($otherId, $otherRole);
                $otherUser['id'] = $otherId;
                $otherUser['role'] = $otherRole;

                // Fetch messages
                $messages = R::findAll('directmessage', 'conversation_id = ? ORDER BY created_at ASC', [$activeConv->id]);

                // Mark unread messages received by me as read
                R::exec('UPDATE directmessage SET is_read = 1 WHERE conversation_id = ? AND recipient_id = ? AND recipient_role = ? AND is_read = 0', [
                    $activeConv->id,
                    $myId,
                    $myRole
                ]);
            } else {
                $activeConv = null;
            }
        }
    }

    if (!$otherUser) {
        if ($toTeacherId > 0) {
            $otherUser = forum_get_author_display($toTeacherId, 'teacher');
            $otherUser['id'] = $toTeacherId;
            $otherUser['role'] = 'teacher';
        } elseif ($toSchoolId > 0) {
            $otherUser = forum_get_author_display($toSchoolId, 'school');
            $otherUser['id'] = $toSchoolId;
            $otherUser['role'] = 'school';
        }
    }
} catch (\Throwable $e) {
    error_log("messages.php query error: " . $e->getMessage());
}

if (!function_exists('msg_time_format')) {
    function msg_time_format($dt)
    {
        if (empty($dt))
            return '';
        $ts = strtotime($dt);
        if (date('Y-m-d') === date('Y-m-d', $ts)) {
            return date('g:i A', $ts);
        }
        return date('M d, g:i A', $ts);
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane"
        style="width: 100%; height: 100%; box-sizing: border-box; padding: 0.75rem 1rem; display: flex; flex-direction: column; overflow: hidden;">

        <div
            style="flex-shrink: 0; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div>
                <h1
                    style="margin: 0 0 2px; font-size: 1.15rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-envelope" style="color: #0f766e;"></i> Direct Messages
                </h1>
                <p style="margin: 0; font-size: 0.78rem; color: #64748b;">
                    Private, secure educator communications.
                </p>
            </div>
            <a href="/forum"
                style="background: white; border: 1px solid #cbd5e1; color: #475569 !important; font-size: 0.78rem; font-weight: 600; padding: 5px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa fa-comments"></i> Back to Forum
            </a>
        </div>

        <!-- 2-Column Responsive Inbox Container -->
        <div
            style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: grid; grid-template-columns: 300px 1fr; flex: 1; min-height: 0; overflow: hidden;">

            <!-- Left Pane: Conversations List -->
            <div id="inboxLeftPane"
                style="border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; background: #ffffff; height: 100%; min-height: 0; overflow: hidden;">
                <div
                    style="flex-shrink: 0; padding: 0.6rem 0.8rem; border-bottom: 1px solid #f1f5f9; background: #f8fafc; display: flex; flex-direction: column; gap: 6px;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <strong style="font-size: 0.84rem; color: #0f172a;">Conversations
                            (<?= count($conversations) ?>)</strong>
                    </div>
                    <!-- Conversation Filter -->
                    <div style="position: relative; display: flex; align-items: center;">
                        <i class="fa fa-search"
                            style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.72rem; color: #94a3b8; pointer-events: none; z-index: 5;"></i>
                        <input type="text" id="convSearchInput" oninput="filterConversations()"
                            placeholder="Filter conversations..." autocomplete="off"
                            style="height: 28px !important; padding: 0 8px 0 30px !important; font-size: 0.78rem; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; width: 100%; margin: 0 !important; box-sizing: border-box;">
                    </div>
                </div>

                <div id="conversationsListFeed" style="flex: 1; overflow-y: auto;">
                    <?php if (empty($conversations)): ?>
                        <div style="padding: 2.5rem 1.25rem; text-align: center; color: #64748b; font-size: 0.86rem;">
                            <i class="fa fa-envelope-open"
                                style="font-size: 1.5rem; color: #cbd5e1; display: block; margin-bottom: 8px;"></i>
                            No direct messages yet.<br>Click on any educator to start a private conversation.
                        </div>
                    <?php else: ?>
                        <?php foreach ($conversations as $c): ?>
                            <?php
                            $isP1 = ($c->user1_id == $myId && $c->user1_role === $myRole);
                            $cOtherId = $isP1 ? $c->user2_id : $c->user1_id;
                            $cOtherRole = $isP1 ? $c->user2_role : $c->user1_role;
                            $cOther = forum_get_author_display($cOtherId, $cOtherRole);

                            $unreadInThis = R::count('directmessage', 'conversation_id = ? AND recipient_id = ? AND recipient_role = ? AND is_read = 0', [
                                $c->id,
                                $myId,
                                $myRole
                            ]);

                            $lastMsg = R::findOne('directmessage', 'conversation_id = ? ORDER BY created_at DESC', [$c->id]);
                            $isActiveThis = ($activeConv && $activeConv->id == $c->id);
                            $convSearchable = strtolower(htmlspecialchars($cOther['display_name'] . ' ' . $cOther['sub_text'] . ' ' . $cOther['county']));
                            ?>
                            <a href="/messages?conv=<?= $c->id ?>" class="conv-item-row"
                                data-search-conv="<?= $convSearchable ?>"
                                style="display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; text-decoration: none; border-bottom: 1px solid #f1f5f9; background: <?= $isActiveThis ? '#f0fdfa' : '#ffffff' ?>; border-left: <?= $isActiveThis ? '3px solid #0f766e' : '3px solid transparent' ?>; transition: background 0.15s;">
                                <div
                                    style="width: 36px; height: 36px; border-radius: 50%; background: <?= $isActiveThis ? '#0f766e' : '#f1f5f9' ?>; color: <?= $isActiveThis ? '#ffffff' : '#334155' ?>; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; flex-shrink: 0;">
                                    <?= h($cOther['initial']) ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div
                                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2px;">
                                        <strong
                                            style="font-size: 0.86rem; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?= h($cOther['display_name']) ?>
                                        </strong>
                                        <span style="font-size: 0.7rem; color: #94a3b8; flex-shrink: 0;">
                                            <?= msg_time_format($c->last_message_at) ?>
                                        </span>
                                    </div>
                                    <div style="display: flex; justify-content: space-between; align-items: center;">
                                        <span
                                            style="font-size: 0.76rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 170px;">
                                            <?= h($lastMsg ? $lastMsg->message : ($cOther['sub_text'] . ' • ' . $cOther['county'])) ?>
                                        </span>
                                        <?php if ($unreadInThis > 0): ?>
                                            <span
                                                style="background: #0f766e; color: white; font-size: 0.65rem; font-weight: 800; padding: 1px 6px; border-radius: 10px; flex-shrink: 0;">
                                                <?= $unreadInThis ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Pane: Active Chat Conversation -->
            <div id="inboxRightPane"
                style="display: flex; flex-direction: column; background: #fafafa; height: 100%; min-height: 0; overflow: hidden;">
                <?php if ($otherUser): ?>
                    <!-- Active Header -->
                    <div
                        style="flex-shrink: 0; padding: 8px 16px; border-bottom: 1px solid #e2e8f0; background: #ffffff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div
                                style="width: 36px; height: 36px; border-radius: 50%; background: #0f766e; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem;">
                                <?= h($otherUser['initial']) ?>
                            </div>
                            <div>
                                <strong style="font-size: 0.92rem; color: #0f172a; display: block;">
                                    <?= h($otherUser['display_name']) ?>
                                </strong>
                                <span style="font-size: 0.74rem; color: #64748b;">
                                    <?= h($otherUser['sub_text']) ?> &bull; <?= h($otherUser['county']) ?>
                                </span>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 8px;">
                            <!-- In-Conversation Message Search -->
                            <div class="search-input-wrapper"
                                style="position: relative; display: flex; align-items: center;">
                                <i class="fa fa-search search-icon"
                                    style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: 0.72rem; color: #94a3b8; pointer-events: none; z-index: 5;"></i>
                                <input type="text" id="dmSearchInput" oninput="filterDmMessages()"
                                    placeholder="Search in chat..." autocomplete="off"
                                    style="padding-left: 32px !important; padding-right: 24px !important; height: 30px !important; margin: 0 !important; border-radius: 15px !important; width: 140px; background: #f8fafc; border: 1px solid #cbd5e1; font-size: 0.78rem; box-sizing: border-box; transition: width 0.2s;"
                                    onfocus="this.style.width='190px'" onblur="if(!this.value) this.style.width='140px'">
                                <span id="clearDmSearchBtn" class="search-clear-btn" onclick="clearDmSearch()"
                                    style="display: none; position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; font-size: 0.85rem; line-height: 1; z-index: 5;">&times;</span>
                            </div>

                            <?php if ($otherUser['role'] === 'teacher'): ?>
                                <a href="/teacher/profile?teacher_id=<?= $otherUser['id'] ?>"
                                    style="background: #f8fafc; border: 1px solid #cbd5e1; color: #475569 !important; font-size: 0.76rem; font-weight: 600; padding: 4px 8px; border-radius: 4px; text-decoration: none;">
                                    Profile
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Messages Scroll Feed -->
                    <div id="messagesFeed"
                        style="flex: 1; min-height: 0; overflow-y: auto; padding: 1.25rem; display: flex; flex-direction: column; gap: 10px;">
                        <div id="dmSearchNoResults"
                            style="display: none; text-align: center; color: #64748b; font-size: 0.85rem; padding: 2rem;">
                            <i class="fa fa-search"
                                style="font-size: 1.5rem; color: #cbd5e1; display: block; margin-bottom: 8px;"></i>
                            No matching messages in this conversation.
                        </div>
                        <?php if (empty($messages)): ?>
                            <div id="dmEmptyNotice"
                                style="margin: auto; text-align: center; color: #64748b; font-size: 0.88rem;">
                                Send a message to start this private conversation.
                            </div>
                        <?php else: ?>
                            <?php $lastDmId = 0; ?>
                            <?php foreach ($messages as $m): ?>
                                <?php
                                $mId = (int) $m->id;
                                if ($mId > $lastDmId)
                                    $lastDmId = $mId;
                                $isMine = ($m->sender_id == $myId && $m->sender_role === $myRole);
                                $dmSearchText = strtolower(htmlspecialchars($m->message . ' ' . ($m->attachment_name ?? '')));
                                ?>
                                <div class="dm-message-row" data-message-id="<?= $mId ?>" data-search-text="<?= $dmSearchText ?>"
                                    style="display: flex; justify-content: <?= $isMine ? 'flex-end' : 'flex-start' ?>; margin-bottom: 4px;">
                                    <div
                                        style="max-width: 75%; background: <?= $isMine ? '#0f766e' : '#ffffff' ?>; color: <?= $isMine ? '#ffffff' : '#1e293b' ?>; border: 1px solid <?= $isMine ? '#0f766e' : '#e2e8f0' ?>; padding: 10px 14px; border-radius: <?= $isMine ? '12px 12px 2px 12px' : '12px 12px 12px 2px' ?>; box-shadow: 0 1px 2px rgba(0,0,0,0.03); word-break: break-word;">
                                        <?php if (!empty($m->message)): ?>
                                            <div style="font-size: 0.88rem; line-height: 1.5;">
                                                <?= nl2br(h($m->message)) ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Attachment Rendering -->
                                        <?php if (!empty($m->attachment_url)): ?>
                                            <div style="margin-top: 6px;">
                                                <?php if ($m->attachment_type === 'image'): ?>
                                                    <a href="<?= h($m->attachment_url) ?>" target="_blank" rel="noopener"
                                                        style="display: block; max-width: 240px; border-radius: 6px; overflow: hidden; border: 1px solid <?= $isMine ? 'rgba(255,255,255,0.2)' : '#e2e8f0' ?>;">
                                                        <img src="<?= h($m->attachment_url) ?>"
                                                            alt="<?= h($m->attachment_name ?: 'Image') ?>"
                                                            style="width: 100%; height: auto; display: block; object-fit: cover; max-height: 200px;">
                                                    </a>
                                                <?php elseif ($m->attachment_type === 'audio'): ?>
                                                    <div style="padding: 4px 0;">
                                                        <audio controls preload="none" src="<?= h($m->attachment_url) ?>"
                                                            style="max-width: 100%; height: 32px;"></audio>
                                                    </div>
                                                <?php else: ?>
                                                    <a href="<?= h($m->attachment_url) ?>" target="_blank"
                                                        download="<?= h($m->attachment_name) ?>"
                                                        style="display: flex; align-items: center; gap: 8px; padding: 8px 10px; background: <?= $isMine ? 'rgba(0,0,0,0.15)' : '#f8fafc' ?>; border: 1px solid <?= $isMine ? 'rgba(255,255,255,0.2)' : '#cbd5e1' ?>; border-radius: 6px; text-decoration: none; color: <?= $isMine ? '#ffffff' : '#0f172a' ?> !important; font-size: 0.8rem;">
                                                        <i class="fa fa-file"
                                                            style="font-size: 1rem; color: <?= $isMine ? '#ccfbf1' : '#0f766e' ?>;"></i>
                                                        <div style="min-width: 0; flex: 1;">
                                                            <div
                                                                style="font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 180px;">
                                                                <?= h($m->attachment_name ?: 'Document') ?></div>
                                                            <div style="font-size: 0.7rem; color: <?= $isMine ? '#ccfbf1' : '#64748b' ?>;">
                                                                <?= h($m->attachment_size ?: 'File') ?></div>
                                                        </div>
                                                        <i class="fa fa-download"
                                                            style="font-size: 0.8rem; color: <?= $isMine ? '#ccfbf1' : '#64748b' ?>;"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <div
                                            style="font-size: 0.7rem; color: <?= $isMine ? '#ccfbf1' : '#94a3b8' ?>; text-align: right; margin-top: 4px;">
                                            <?= msg_time_format($m->created_at) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Staged Attachment Preview Bar -->
                    <div id="stagedDmAttachmentBar"
                        style="display: none; padding: 6px 16px; background: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 0.8rem; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 8px; color: #0f766e;">
                            <i class="fa fa-paperclip"></i>
                            <span id="stagedDmFileName"
                                style="font-weight: 600; max-width: 220px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">file.pdf</span>
                            <span id="stagedDmFileSize" style="color: #64748b; font-size: 0.74rem;">(0 KB)</span>
                        </div>
                        <button type="button" onclick="removeDmStagedAttachment()"
                            style="background: none; border: none; color: #ef4444; font-size: 0.78rem; font-weight: 600; cursor: pointer; padding: 2px 6px;">Remove</button>
                    </div>

                    <!-- Message Composer Form -->
                    <div
                        style="position: relative; flex-shrink: 0; padding: 10px 14px; border-top: 1px solid #e2e8f0; background: #ffffff;">
                        <!-- Hidden File Inputs -->
                        <input type="file" id="dmDocFileInput" accept=".pdf,.doc,.docx,.pptx,.xlsx,.txt,.csv"
                            style="display:none;" onchange="handleDmFileSelected(this, 'document')">
                        <input type="file" id="dmPhotoFileInput" accept="image/*" style="display:none;"
                            onchange="handleDmFileSelected(this, 'image')">
                        <input type="file" id="dmAudioFileInput" accept="audio/*" style="display:none;"
                            onchange="handleDmFileSelected(this, 'audio')">

                        <!-- Hidden Attachment Dropdown Menu -->
                        <div id="dmAttachmentDropdownMenu"
                            style="display: none; position: absolute; bottom: 58px; left: 14px; background: white; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 6px 0; z-index: 100; min-width: 150px;">
                            <div onclick="document.getElementById('dmDocFileInput').click()"
                                style="padding: 8px 14px; display: flex; align-items: center; gap: 10px; font-size: 0.84rem; color: #334155; cursor: pointer;">
                                <i class="fa fa-file-lines" style="color: #0f766e; width: 16px;"></i> Document
                            </div>
                            <div onclick="document.getElementById('dmPhotoFileInput').click()"
                                style="padding: 8px 14px; display: flex; align-items: center; gap: 10px; font-size: 0.84rem; color: #334155; cursor: pointer;">
                                <i class="fa fa-image" style="color: #0f766e; width: 16px;"></i> Photo
                            </div>
                            <div onclick="document.getElementById('dmAudioFileInput').click()"
                                style="padding: 8px 14px; display: flex; align-items: center; gap: 10px; font-size: 0.84rem; color: #334155; cursor: pointer;">
                                <i class="fa fa-microphone" style="color: #0f766e; width: 16px;"></i> Audio Memo
                            </div>
                        </div>

                        <form id="sendMessageForm" onsubmit="sendDirectMessage(event)"
                            style="margin: 0; display: flex; gap: 8px; align-items: center;">
                            <?= csrf_field() ?>
                            <input type="hidden" id="convIdInput" value="<?= $activeConv ? $activeConv->id : 0 ?>">
                            <input type="hidden" id="recipientIdInput" value="<?= $otherUser['id'] ?>">
                            <input type="hidden" id="recipientRoleInput" value="<?= $otherUser['role'] ?>">
                            <input type="hidden" id="stagedDmAttachmentUrl" value="">
                            <input type="hidden" id="stagedDmAttachmentType" value="">
                            <input type="hidden" id="stagedDmAttachmentName" value="">
                            <input type="hidden" id="stagedDmAttachmentSize" value="">

                            <!-- '+' Attachment Button -->
                            <button type="button" id="dmAttachmentToggleBtn" onclick="toggleDmAttachmentMenu(event)"
                                style="background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; font-size: 1.1rem; font-weight: 700; width: 40px; height: 40px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0; margin: 0;"
                                title="Attach document, photo, or audio">
                                +
                            </button>

                            <input type="text" id="messageTextInput" placeholder="Type a private message..."
                                autocomplete="off" autofocus
                                style="flex: 1; height: 40px; margin: 0; padding-left: 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">

                            <button type="submit" id="sendMsgBtn"
                                style="background: #0f766e; color: white; border: none; font-weight: 700; padding: 0 18px; height: 40px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0;">
                                <i class="fa fa-paper-plane"></i> Send
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="margin: auto; text-align: center; color: #64748b; padding: 2rem;">
                        <div
                            style="width: 56px; height: 56px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 12px;">
                            <i class="fa fa-comments"></i>
                        </div>
                        <h3 style="margin: 0 0 4px; font-size: 1.15rem; color: #0f172a; font-weight: 700;">Select a
                            Conversation</h3>
                        <p style="margin: 0; font-size: 0.85rem;">Choose an educator conversation on the left to read
                            messages or reply.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>

    </main>
</div>

<script>
    let currentLastDmId = <?= $lastDmId ?? 0 ?>;

    // Auto-scroll messages feed to bottom & focus composer
    const feed = document.getElementById('messagesFeed');
    if (feed) {
        feed.scrollTop = feed.scrollHeight;
    }
    const msgInput = document.getElementById('messageTextInput');
    if (msgInput) {
        msgInput.focus();
    }

    // Attachment Menu Handlers
    function toggleDmAttachmentMenu(e) {
        e.stopPropagation();
        const menu = document.getElementById('dmAttachmentDropdownMenu');
        if (menu) {
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
    }

    window.addEventListener('click', function () {
        const menu = document.getElementById('dmAttachmentDropdownMenu');
        if (menu) menu.style.display = 'none';
    });

    function handleDmFileSelected(inputEl, expectedType) {
        if (!inputEl.files || !inputEl.files[0]) return;
        const file = inputEl.files[0];

        if (file.size > 10 * 1024 * 1024) {
            alert('File exceeds 10MB limit.');
            inputEl.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('file', file);
        formData.append('expected_type', expectedType);

        const btn = document.getElementById('dmAttachmentToggleBtn');
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        fetch('/api/messages?action=upload_attachment', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                btn.innerHTML = '+';
                if (data.success) {
                    document.getElementById('stagedDmAttachmentUrl').value = data.url;
                    document.getElementById('stagedDmAttachmentType').value = data.type;
                    document.getElementById('stagedDmAttachmentName').value = data.name;
                    document.getElementById('stagedDmAttachmentSize').value = data.size;

                    document.getElementById('stagedDmFileName').innerText = data.name;
                    document.getElementById('stagedDmFileSize').innerText = '(' + data.size + ')';
                    document.getElementById('stagedDmAttachmentBar').style.display = 'flex';

                    inputEl.value = '';
                } else {
                    alert(data.error || 'Failed to upload file.');
                    inputEl.value = '';
                }
            })
            .catch(() => {
                btn.innerHTML = '+';
                alert('Network error uploading file.');
                inputEl.value = '';
            });
    }

    function removeDmStagedAttachment() {
        document.getElementById('stagedDmAttachmentUrl').value = '';
        document.getElementById('stagedDmAttachmentType').value = '';
        document.getElementById('stagedDmAttachmentName').value = '';
        document.getElementById('stagedDmAttachmentSize').value = '';
        document.getElementById('stagedDmAttachmentBar').style.display = 'none';
    }

    // Send Direct Message via AJAX
    function sendDirectMessage(e) {
        e.preventDefault();
        const convId = document.getElementById('convIdInput').value;
        const recipientId = document.getElementById('recipientIdInput').value;
        const recipientRole = document.getElementById('recipientRoleInput').value;
        const input = document.getElementById('messageTextInput');
        const text = input.value.trim();
        const attachUrl = document.getElementById('stagedDmAttachmentUrl').value;
        const attachType = document.getElementById('stagedDmAttachmentType').value;
        const attachName = document.getElementById('stagedDmAttachmentName').value;
        const attachSize = document.getElementById('stagedDmAttachmentSize').value;
        const btn = document.getElementById('sendMsgBtn');

        if (!text && !attachUrl) return;

        btn.disabled = true;

        fetch('/api/messages?action=send', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                conversation_id: convId,
                recipient_id: recipientId,
                recipient_role: recipientRole,
                message: text,
                attachment_url: attachUrl,
                attachment_type: attachType,
                attachment_name: attachName,
                attachment_size: attachSize
            })
        })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                if (data.success) {
                    input.value = '';
                    removeDmStagedAttachment();
                    pollNewDmMessages();
                } else {
                    alert(data.message || 'Failed to send message.');
                    input.focus();
                }
            })
            .catch(err => {
                btn.disabled = false;
                alert('Network error sending message.');
                input.focus();
            });
    }

    // Real-time 5s Poller for Direct Messages
    function pollNewDmMessages() {
        const convIdEl = document.getElementById('convIdInput');
        if (!convIdEl) return;
        const convId = convIdEl.value;
        if (!convId || convId === '0') return;

        fetch('/api/messages?action=get_latest&conversation_id=' + convId + '&since_id=' + currentLastDmId)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.messages && data.messages.length > 0) {
                    const emptyNotice = document.getElementById('dmEmptyNotice');
                    if (emptyNotice) emptyNotice.remove();

                    const isAtBottom = (feed.scrollHeight - feed.scrollTop - feed.clientHeight) < 80;

                    data.messages.forEach(msg => {
                        if (msg.id > currentLastDmId) {
                            currentLastDmId = msg.id;
                        }
                        if (document.querySelector('[data-message-id="' + msg.id + '"]')) {
                            return;
                        }

                        const row = renderDmBubble(msg);
                        feed.appendChild(row);
                    });

                    if (isAtBottom) {
                        feed.scrollTop = feed.scrollHeight;
                    }
                }
            })
            .catch(() => { });
    }

    setInterval(() => {
        if (!document.hidden) {
            pollNewDmMessages();
        }
    }, 5000);

    function renderDmBubble(msg) {
        const row = document.createElement('div');
        row.className = 'dm-message-row';
        row.setAttribute('data-message-id', msg.id);
        row.setAttribute('data-search-text', (msg.message + ' ' + (msg.attachment_name || '')).toLowerCase());
        row.style.display = 'flex';
        row.style.justifyContent = msg.is_mine ? 'flex-end' : 'flex-start';
        row.style.marginBottom = '4px';

        let attachHtml = '';
        if (msg.attachment_url) {
            if (msg.attachment_type === 'image') {
                attachHtml = '<div style="margin-top: 6px;"><a href="' + escapeHtml(msg.attachment_url) + '" target="_blank" rel="noopener" style="display: block; max-width: 240px; border-radius: 6px; overflow: hidden;"><img src="' + escapeHtml(msg.attachment_url) + '" style="width: 100%; height: auto; max-height: 200px; display: block; object-fit: cover;"></a></div>';
            } else if (msg.attachment_type === 'audio') {
                attachHtml = '<div style="padding: 4px 0;"><audio controls preload="none" src="' + escapeHtml(msg.attachment_url) + '" style="max-width: 100%; height: 32px;"></audio></div>';
            } else {
                attachHtml = '<div style="margin-top: 6px;"><a href="' + escapeHtml(msg.attachment_url) + '" target="_blank" download="' + escapeHtml(msg.attachment_name || 'Document') + '" style="display: flex; align-items: center; gap: 8px; padding: 8px 10px; background: ' + (msg.is_mine ? 'rgba(0,0,0,0.15)' : '#f8fafc') + '; border-radius: 6px; text-decoration: none; color: ' + (msg.is_mine ? '#ffffff' : '#0f172a') + ' !important; font-size: 0.8rem;"><i class="fa fa-file" style="color: ' + (msg.is_mine ? '#ccfbf1' : '#0f766e') + ';"></i><span style="font-weight: 600;">' + escapeHtml(msg.attachment_name || 'Document') + '</span></a></div>';
            }
        }

        const bubbleBg = msg.is_mine ? '#0f766e' : '#ffffff';
        const bubbleColor = msg.is_mine ? '#ffffff' : '#1e293b';
        const bubbleBorder = msg.is_mine ? '#0f766e' : '#e2e8f0';
        const radius = msg.is_mine ? '12px 12px 2px 12px' : '12px 12px 12px 2px';
        const contentHtml = msg.message ? ('<div style="font-size: 0.88rem; line-height: 1.5;">' + escapeHtml(msg.message).replace(/\n/g, '<br>') + '</div>') : '';

        row.innerHTML = '<div style="max-width: 75%; background: ' + bubbleBg + '; color: ' + bubbleColor + '; border: 1px solid ' + bubbleBorder + '; padding: 10px 14px; border-radius: ' + radius + '; box-shadow: 0 1px 2px rgba(0,0,0,0.03); word-break: break-word;">' + contentHtml + attachHtml + '<div style="font-size: 0.7rem; color: ' + (msg.is_mine ? '#ccfbf1' : '#94a3b8') + '; text-align: right; margin-top: 4px;">' + escapeHtml(msg.created_at) + '</div></div>';

        return row;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // In-DM Message Search Filter
    function filterDmMessages() {
        const query = (document.getElementById('dmSearchInput').value || '').trim().toLowerCase();
        const clearBtn = document.getElementById('clearDmSearchBtn');
        if (clearBtn) clearBtn.style.display = query ? 'block' : 'none';

        const rows = document.querySelectorAll('#messagesFeed .dm-message-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const text = row.getAttribute('data-search-text') || '';
            if (!query || text.includes(query)) {
                row.style.display = 'flex';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        const noRes = document.getElementById('dmSearchNoResults');
        if (noRes) {
            noRes.style.display = (query && visibleCount === 0) ? 'block' : 'none';
        }
    }

    function clearDmSearch() {
        const input = document.getElementById('dmSearchInput');
        if (input) {
            input.value = '';
            filterDmMessages();
            input.focus();
        }
    }

    // Filter Left Conversations List
    function filterConversations() {
        const query = (document.getElementById('convSearchInput').value || '').trim().toLowerCase();
        const rows = document.querySelectorAll('#conversationsListFeed .conv-item-row');

        rows.forEach(row => {
            const text = row.getAttribute('data-search-conv') || '';
            if (!query || text.includes(query)) {
                row.style.display = 'flex';
            } else {
                row.style.display = 'none';
            }
        });
    }
</script>