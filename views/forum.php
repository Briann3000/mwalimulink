<?php
// views/forum.php - Teachers Community Live Chat Forum Feed
init_forum_and_messaging_schema();

$authUser = auth_user();
$isLoggedIn = is_logged_in();
$myId = $authUser ? (int) $authUser['user_id'] : 0;
$myRole = $authUser['role'] ?? 'guest';

// Fetch Categories / Channels
$categories = forum_get_categories();

// Selected Room / Channel Filter
$categorySlug = trim($_GET['category'] ?? '');
$selectedCategory = null;
if (!empty($categorySlug)) {
    $selectedCategory = R::findOne('forumcategory', 'slug = ?', [$categorySlug]);
}

// Default to General Chat or first category if none selected
if (!$selectedCategory && !empty($categories)) {
    $selectedCategory = reset($categories);
}

// Fetch Messages in sequential order (oldest to newest for chat flow)
$messages = [];
if ($selectedCategory && $selectedCategory->id) {
    try {
        $messages = R::findAll('forumthread', 'category_id = ? AND is_hidden = 0 ORDER BY created_at ASC LIMIT 100', [$selectedCategory->id]);
    } catch (\Throwable $e) {
        error_log("forum chat load error: " . $e->getMessage());
    }
}

// Time formatting helper
if (!function_exists('chat_msg_time')) {
    function chat_msg_time($datetime)
    {
        if (empty($datetime))
            return '';
        $ts = strtotime($datetime);
        if (date('Y-m-d') === date('Y-m-d', $ts)) {
            return date('g:i A', $ts);
        }
        return date('M d, g:i A', $ts);
    }
}
?>

<div class="<?= $isLoggedIn ? 'workspace-wrapper' : 'container' ?>"
    style="<?= !$isLoggedIn ? 'max-width: 1200px; margin: 1.5rem auto; padding: 0 1rem;' : '' ?>">
    <?php if ($isLoggedIn)
        include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="<?= $isLoggedIn ? 'content-pane' : '' ?>"
        style="width: 100%; height: <?= $isLoggedIn ? '100%' : 'calc(100vh - 120px)' ?>; box-sizing: border-box; padding: 0.75rem 1rem; display: flex; flex-direction: column; overflow: hidden;">

        <!-- Top Navigation & Channel Bar -->
        <div
            style="flex-shrink: 0; background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.6rem 1rem; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <h1
                    style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-comments" style="color: #0f766e;"></i> Teachers Community Chat
                </h1>
                <span style="font-size: 0.8rem; color: #64748b;">
                    &bull; <?= h($selectedCategory->name ?? 'General Chat') ?>
                </span>
            </div>
            <div style="display: flex; gap: 8px; align-items: center;">
                <?php if ($isLoggedIn): ?>
                    <?php $unreadForumCount = get_unread_message_count($myId, $myRole); ?>
                    <a href="/messages"
                        style="background: #f8fafc; border: 1px solid #cbd5e1; color: #475569 !important; font-size: 0.78rem; font-weight: 600; padding: 5px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-envelope"></i> Direct Messages
                        <?php if ($unreadForumCount > 0): ?>
                            <span
                                style="background: #0f766e; color: white; font-size: 0.68rem; font-weight: 800; padding: 1px 6px; border-radius: 10px; margin-left: 2px;">
                                <?= $unreadForumCount ?>
                            </span>
                        <?php endif; ?>
                    </a>
                <?php else: ?>
                    <a href="/login?redirect=/forum" class="btn-primary"
                        style="background: #0f766e; color: white !important; font-size: 0.78rem; font-weight: 700; padding: 5px 12px; border-radius: 6px; text-decoration: none;">
                        Sign In to Chat
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2-Column Responsive Chat Layout -->
        <div
            style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: grid; grid-template-columns: 240px 1fr; flex: 1; min-height: 0; overflow: hidden;">

            <!-- Left Pane: Channels / Rooms List -->
            <div id="chatChannelsPane"
                style="border-right: 1px solid #e2e8f0; display: flex; flex-direction: column; background: #ffffff; height: 100%; min-height: 0; overflow: hidden;">
                <div
                    style="flex-shrink: 0; padding: 0.8rem 1rem; border-bottom: 1px solid #f1f5f9; background: #f8fafc;">
                    <strong
                        style="font-size: 0.82rem; color: #475569; text-transform: uppercase; letter-spacing: 0.05em;">Channels
                        / Rooms</strong>
                </div>

                <div style="flex: 1; overflow-y: auto; padding: 6px;">
                    <?php foreach ($categories as $cat): ?>
                        <?php
                        $isActiveCat = ($selectedCategory && $selectedCategory->id == $cat->id);
                        $count = 0;
                        try {
                            $count = (int) R::count('forumthread', 'category_id = ? AND is_hidden = 0', [$cat->id]);
                        } catch (\Throwable $ignored) {
                        }
                        ?>
                        <a href="/forum?category=<?= urlencode($cat->slug) ?>"
                            style="display: flex; align-items: center; justify-content: space-between; padding: 10px 12px; border-radius: 6px; text-decoration: none; margin-bottom: 4px; font-size: 0.86rem; font-weight: <?= $isActiveCat ? '700' : '600' ?>; background: <?= $isActiveCat ? '#f0fdfa' : 'transparent' ?>; color: <?= $isActiveCat ? '#0f766e' : '#334155' ?>; border-left: <?= $isActiveCat ? '3px solid #0f766e' : '3px solid transparent' ?>; transition: all 0.15s;">
                            <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                <i class="fa <?= h($cat->icon ?: 'fa-hashtag') ?>"
                                    style="color: <?= $isActiveCat ? '#0f766e' : '#94a3b8' ?>; font-size: 0.9rem; width: 18px; text-align: center;"></i>
                                <span
                                    style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= h($cat->name) ?></span>
                            </div>
                            <?php if ($count > 0): ?>
                                <span
                                    style="font-size: 0.72rem; background: <?= $isActiveCat ? '#0f766e' : '#f1f5f9' ?>; color: <?= $isActiveCat ? '#ffffff' : '#64748b' ?>; padding: 1px 6px; border-radius: 10px; font-weight: 700;">
                                    <?= $count ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right Pane: Sequential Message Stream & Persistent Chat Composer -->
            <div id="chatFeedPane" style="display: flex; flex-direction: column; background: #fafafa;">

                <!-- Active Channel Top Header -->
                <div
                    style="flex-shrink: 0; padding: 8px 16px; border-bottom: 1px solid #e2e8f0; background: #ffffff; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <div>
                        <strong
                            style="font-size: 0.95rem; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                            <i class="fa <?= h($selectedCategory->icon ?? 'fa-comments') ?>"
                                style="color: #0f766e;"></i>
                            <?= h($selectedCategory->name ?? 'General Chat') ?>
                        </strong>
                        <span style="font-size: 0.74rem; color: #64748b;">
                            <?= h($selectedCategory->description ?? 'Live educator conversation channel') ?>
                        </span>
                    </div>
                    <!-- In-Chat Search Bar -->
                    <div class="search-input-wrapper" style="position: relative; display: flex; align-items: center;">
                        <i class="fa fa-search search-icon"
                            style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 0.75rem; color: #94a3b8; pointer-events: none; z-index: 5;"></i>
                        <input type="text" id="chatSearchInput" oninput="filterChatMessages()"
                            placeholder="Search in chat..." autocomplete="off"
                            style="padding-left: 36px !important; padding-right: 28px !important; height: 32px !important; margin: 0 !important; border-radius: 20px !important; width: 170px; max-width: 100%; transition: width 0.2s; background: #f8fafc; border: 1px solid #cbd5e1; font-size: 0.8rem; box-sizing: border-box;"
                            onfocus="this.style.width='220px'" onblur="if(!this.value) this.style.width='170px'">
                        <span id="clearChatSearchBtn" class="search-clear-btn" onclick="clearChatSearch()"
                            style="display: none; position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #94a3b8; cursor: pointer; font-size: 0.95rem; line-height: 1; z-index: 5;">&times;</span>
                    </div>
                </div>

                <!-- Messages Feed Stream with Dedicated Scrollbar -->
                <div id="chatStream"
                    style="flex: 1; overflow-y: auto; padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 10px; scrollbar-width: thin; scrollbar-color: #cbd5e1 transparent;">
                    <div id="chatSearchNoResults"
                        style="display: none; text-align: center; color: #64748b; font-size: 0.85rem; padding: 2rem;">
                        <i class="fa fa-search"
                            style="font-size: 1.5rem; color: #cbd5e1; display: block; margin-bottom: 8px;"></i>
                        No matching messages found for your search.
                    </div>
                    <?php if (empty($messages)): ?>
                        <div id="chatEmptyNotice"
                            style="margin: auto; text-align: center; color: #64748b; font-size: 0.88rem; padding: 2rem;">
                            <div
                                style="width: 48px; height: 48px; border-radius: 50%; background: #f1f5f9; color: #94a3b8; display: inline-flex; align-items: center; justify-content: center; font-size: 1.3rem; margin-bottom: 10px;">
                                <i class="fa fa-comment-dots"></i>
                            </div>
                            <p style="margin: 0 0 4px; font-weight: 700; color: #334155;">No messages in this room yet.</p>
                            <span style="font-size: 0.8rem;">Type a message below to start the conversation!</span>
                        </div>
                    <?php else: ?>
                        <?php $lastMsgId = 0; ?>
                        <?php foreach ($messages as $m): ?>
                            <?php
                            $msgId = (int) $m->id;
                            if ($msgId > $lastMsgId)
                                $lastMsgId = $msgId;
                            $isMine = ($isLoggedIn && (int) $m->user_id === $myId && $m->user_role === $myRole);
                            $authorInitial = strtoupper(substr($m->author_alias ?: ($m->author_name ?: 'E'), 0, 1));
                            $authorDisplayName = $m->author_alias ?: $m->author_name;
                            $authorSubjects = $m->author_subjects ?? '';
                            $authorCounty = $m->author_county ?? 'Kenya';
                            $authorUserId = (int) $m->user_id;
                            $authorUserRole = $m->user_role ?: 'teacher';
                            $searchableText = strtolower(htmlspecialchars($authorDisplayName . ' ' . $authorSubjects . ' ' . $authorCounty . ' ' . ($m->content ?: $m->title) . ' ' . ($m->attachment_name ?? '')));
                            ?>
                            <div class="chat-message-row" data-message-id="<?= $msgId ?>"
                                data-search-text="<?= $searchableText ?>"
                                style="display: flex; justify-content: <?= $isMine ? 'flex-end' : 'flex-start' ?>; align-items: flex-end; gap: 8px; margin-bottom: 4px;">
                                <?php if (!$isMine): ?>
                                    <!-- Avatar for others (Clickable for Option B modal) -->
                                    <div onclick="openAuthorModal('<?= htmlspecialchars(addslashes($authorDisplayName), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($authorSubjects), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($authorCounty), ENT_QUOTES) ?>', <?= $authorUserId ?>, '<?= htmlspecialchars(addslashes($authorUserRole), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($authorInitial), ENT_QUOTES) ?>')"
                                        style="width: 32px; height: 32px; border-radius: 50%; background: #334155; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.82rem; flex-shrink: 0; cursor: pointer; user-select: none;"
                                        title="Click to view details or direct message">
                                        <?= h($authorInitial) ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Message Bubble -->
                                <div
                                    style="max-width: 72%; min-width: 140px; background: <?= $isMine ? '#0f766e' : '#ffffff' ?>; color: <?= $isMine ? '#ffffff' : '#1e293b' ?>; border: 1px solid <?= $isMine ? '#0f766e' : '#e2e8f0' ?>; padding: 8px 12px; border-radius: <?= $isMine ? '14px 14px 2px 14px' : '14px 14px 14px 2px' ?>; box-shadow: 0 1px 2px rgba(0,0,0,0.03); word-break: break-word;">
                                    <?php if (!$isMine): ?>
                                        <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 3px;">
                                            <span
                                                onclick="openAuthorModal('<?= htmlspecialchars(addslashes($authorDisplayName), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($authorSubjects), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($authorCounty), ENT_QUOTES) ?>', <?= $authorUserId ?>, '<?= htmlspecialchars(addslashes($authorUserRole), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($authorInitial), ENT_QUOTES) ?>')"
                                                style="font-size: 0.78rem; font-weight: 700; color: #0f766e; line-height: 1; cursor: pointer; text-decoration: none;"
                                                title="Click to view details or direct message">
                                                <?= h($authorDisplayName) ?>
                                            </span>
                                            <?php if (!empty($authorSubjects)): ?>
                                                <span style="font-size: 0.68rem; color: #64748b; line-height: 1;">
                                                    &bull; <?= h($authorSubjects) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($m->content)): ?>
                                        <div style="font-size: 0.88rem; line-height: 1.45;">
                                            <?= nl2br(h($m->content)) ?>
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

                                    <!-- Bottom Time Stamp (Inside bubble) -->
                                    <div
                                        style="font-size: 0.68rem; text-align: right; margin-top: 3px; color: <?= $isMine ? '#ccfbf1' : '#94a3b8' ?>;">
                                        <?= chat_msg_time($m->created_at) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Swap Template Helper (Only in TSC Swaps Channel) -->
                <?php if ($selectedCategory && $selectedCategory->slug === 'tsc-swaps'): ?>
                    <div style="padding: 4px 16px 0; background: #ffffff;">
                        <span onclick="insertSwapTemplate()"
                            style="font-size: 0.78rem; color: #0f766e; cursor: pointer; text-decoration: underline; font-weight: 600;">
                            Insert Swap Format
                        </span>
                    </div>
                <?php endif; ?>

                <!-- Staged Attachment Preview Bar -->
                <div id="stagedAttachmentBar"
                    style="display: none; padding: 6px 16px; background: #f8fafc; border-top: 1px solid #e2e8f0; font-size: 0.8rem; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 8px; color: #0f766e;">
                        <i id="stagedFileIcon" class="fa fa-paperclip"></i>
                        <span id="stagedFileName"
                            style="font-weight: 600; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">attachment.pdf</span>
                        <span id="stagedFileSize" style="color: #64748b; font-size: 0.74rem;">(0 KB)</span>
                    </div>
                    <button type="button" onclick="removeStagedAttachment()"
                        style="background: none; border: none; color: #ef4444; font-size: 0.78rem; font-weight: 600; cursor: pointer; padding: 2px 6px;">Remove</button>
                </div>

                <!-- Persistent Bottom Message Composer -->
                <div
                    style="position: relative; padding: 10px 14px; border-top: 1px solid #e2e8f0; background: #ffffff;">
                    <?php if ($isLoggedIn): ?>
                        <!-- Hidden File Inputs -->
                        <input type="file" id="docFileInput" accept=".pdf,.doc,.docx,.pptx,.xlsx,.txt,.csv"
                            style="display:none;" onchange="handleFileSelected(this, 'document')">
                        <input type="file" id="photoFileInput" accept="image/*" style="display:none;"
                            onchange="handleFileSelected(this, 'image')">
                        <input type="file" id="audioFileInput" accept="audio/*" style="display:none;"
                            onchange="handleFileSelected(this, 'audio')">

                        <!-- Hidden Attachment Dropdown Menu -->
                        <div id="attachmentDropdownMenu"
                            style="display: none; position: absolute; bottom: 62px; left: 14px; background: white; border: 1px solid #cbd5e1; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 6px 0; z-index: 100; min-width: 150px;">
                            <div onclick="triggerDocUpload()"
                                style="padding: 8px 14px; display: flex; align-items: center; gap: 10px; font-size: 0.84rem; color: #334155; cursor: pointer;">
                                <i class="fa fa-file-lines" style="color: #0f766e; width: 16px;"></i> Document
                            </div>
                            <div onclick="triggerPhotoUpload()"
                                style="padding: 8px 14px; display: flex; align-items: center; gap: 10px; font-size: 0.84rem; color: #334155; cursor: pointer;">
                                <i class="fa fa-image" style="color: #0f766e; width: 16px;"></i> Photo
                            </div>
                            <div onclick="triggerAudioUpload()"
                                style="padding: 8px 14px; display: flex; align-items: center; gap: 10px; font-size: 0.84rem; color: #334155; cursor: pointer;">
                                <i class="fa fa-microphone" style="color: #0f766e; width: 16px;"></i> Audio Memo
                            </div>
                        </div>

                        <form id="groupChatForm" onsubmit="sendGroupMessage(event)"
                            style="margin: 0; display: flex; gap: 8px; align-items: center;">
                            <?= csrf_field() ?>
                            <input type="hidden" id="chatCategoryId" value="<?= (int) ($selectedCategory->id ?? 1) ?>">
                            <input type="hidden" id="stagedAttachmentUrl" value="">
                            <input type="hidden" id="stagedAttachmentType" value="">
                            <input type="hidden" id="stagedAttachmentName" value="">
                            <input type="hidden" id="stagedAttachmentSize" value="">

                            <!-- '+' Attachment Button -->
                            <button type="button" id="attachmentToggleBtn" onclick="toggleAttachmentMenu(event)"
                                style="background: #f8fafc; border: 1px solid #cbd5e1; color: #475569; font-size: 1.1rem; font-weight: 700; width: 42px; height: 42px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; padding: 0; margin: 0;"
                                title="Attach document, photo, or audio">
                                +
                            </button>

                            <input type="text" id="chatMessageInput"
                                placeholder="Type a message to <?= h($selectedCategory->name ?? 'General Chat') ?>..."
                                autocomplete="off" autofocus
                                style="flex: 1; height: 42px; margin: 0; padding-left: 14px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;">

                            <button type="submit" id="sendChatBtn"
                                style="background: #0f766e; color: white; border: none; font-weight: 700; padding: 0 18px; height: 42px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0;">
                                <i class="fa fa-paper-plane"></i> Send
                            </button>
                        </form>
                    <?php else: ?>
                        <div
                            style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <span style="font-size: 0.86rem; color: #64748b;">
                                Sign in to post in this room and connect with educators.
                            </span>
                            <a href="/login?redirect=/forum?category=<?= urlencode($selectedCategory->slug ?? 'general-chat') ?>"
                                class="btn-primary"
                                style="background: #0f766e; color: white !important; font-size: 0.82rem; font-weight: 700; padding: 6px 14px; border-radius: 6px; text-decoration: none;">
                                Join Chat &rarr;
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </main>
</div>

<!-- Option B: Compact Teacher Profile Modal -->
<div id="authorProfileModal"
    style="display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(2px); align-items: center; justify-content: center; padding: 1rem;">
    <div
        style="background: white; border-radius: 12px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); width: 100%; max-width: 360px; overflow: hidden; border: 1px solid #e2e8f0;">
        <div
            style="background: #0f766e; padding: 1.25rem 1.25rem 1rem; color: white; display: flex; justify-content: space-between; align-items: flex-start;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div id="modalAvatar"
                    style="width: 44px; height: 44px; border-radius: 50%; background: #ffffff; color: #0f766e; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    T
                </div>
                <div>
                    <h3 id="modalName" style="margin: 0; font-size: 1.05rem; font-weight: 800; color: white;">Teacher
                    </h3>
                    <span id="modalRoleBadge"
                        style="font-size: 0.72rem; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; display: inline-block; margin-top: 2px;">Educator</span>
                </div>
            </div>
            <button type="button" onclick="closeAuthorModal()"
                style="background: transparent; border: none; color: white; font-size: 1.2rem; cursor: pointer; padding: 0 4px; line-height: 1; opacity: 0.8;">&times;</button>
        </div>

        <div style="padding: 1.25rem; display: flex; flex-direction: column; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.86rem; color: #475569;">
                <i class="fa fa-book" style="color: #0f766e; width: 16px;"></i>
                <span id="modalSubjects">Teaching Subjects</span>
            </div>
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.86rem; color: #475569;">
                <i class="fa fa-map-marker-alt" style="color: #0f766e; width: 16px;"></i>
                <span id="modalCounty">County / Location</span>
            </div>

            <div style="margin-top: 10px; display: flex; flex-direction: column; gap: 8px;">
                <?php if ($isLoggedIn): ?>
                    <a id="modalDirectMsgBtn" href="/messages"
                        style="background: #0f766e; color: white !important; font-weight: 700; font-size: 0.88rem; padding: 10px; border-radius: 6px; text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fa fa-paper-plane"></i> Direct Message
                    </a>
                    <button type="button" id="modalReportBtn" onclick="openReportModal()"
                        style="background: #ffffff; border: 1px solid #cbd5e1; color: #64748b; font-weight: 600; font-size: 0.82rem; padding: 8px; border-radius: 6px; cursor: pointer;">
                        Report Educator
                    </button>
                <?php else: ?>
                    <a href="/login?redirect=/forum"
                        style="background: #0f766e; color: white !important; font-weight: 700; font-size: 0.88rem; padding: 10px; border-radius: 6px; text-decoration: none; text-align: center;">
                        Sign In to Message
                    </a>
                <?php endif; ?>
                <button type="button" onclick="closeAuthorModal()"
                    style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #475569; font-weight: 600; font-size: 0.82rem; padding: 7px; border-radius: 6px; cursor: pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Report Educator Modal -->
<div id="reportEducatorModal"
    style="display: none; position: fixed; inset: 0; z-index: 10000; background: rgba(15, 23, 42, 0.5); backdrop-filter: blur(2px); align-items: center; justify-content: center; padding: 1rem;">
    <div
        style="background: white; border-radius: 12px; width: 100%; max-width: 360px; overflow: hidden; border: 1px solid #e2e8f0; padding: 1.25rem;">
        <h4 style="margin: 0 0 10px; font-size: 1rem; color: #0f172a; font-weight: 700;">Report Educator</h4>
        <form onsubmit="submitEducatorReport(event)">
            <input type="hidden" id="reportTargetUserId" value="">
            <input type="hidden" id="reportTargetUserRole" value="teacher">
            <label style="font-size: 0.82rem; color: #475569; display: block; margin-bottom: 4px;">Reason for
                report</label>
            <select id="reportReasonSelect" style="width: 100%; height: 36px; margin-bottom: 10px; font-size: 0.84rem;">
                <option value="Spam / Advertisements">Spam / Unsolicited Ads</option>
                <option value="Fraudulent Swap Offer">Fraudulent Swap Offer</option>
                <option value="Harassment / Unprofessional">Harassment / Unprofessional</option>
                <option value="Other Policy Violation">Other Policy Violation</option>
            </select>
            <textarea id="reportDetailsInput" placeholder="Brief details (optional)..."
                style="width: 100%; height: 60px; font-size: 0.82rem; margin-bottom: 12px;"></textarea>
            <div style="display: flex; gap: 8px;">
                <button type="submit" id="submitReportBtn"
                    style="flex: 1; background: #0f766e; color: white; border: none; border-radius: 6px; height: 36px; font-size: 0.84rem; font-weight: 700; cursor: pointer;">Submit
                    Report</button>
                <button type="button" onclick="closeReportModal()"
                    style="background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 0 12px; height: 36px; font-size: 0.82rem; cursor: pointer;">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    let currentLastMessageId = <?= $lastMsgId ?? 0 ?>;
    let selectedAuthorTarget = { id: 0, role: 'teacher', name: '' };

    // Auto-scroll on load
    const chatStream = document.getElementById('chatStream');
    if (chatStream) {
        chatStream.scrollTop = chatStream.scrollHeight;
    }
    const chatInput = document.getElementById('chatMessageInput');
    if (chatInput) {
        chatInput.focus();
    }

    // Attachment Menu Handlers
    function toggleAttachmentMenu(e) {
        e.stopPropagation();
        const menu = document.getElementById('attachmentDropdownMenu');
        if (menu) {
            menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
        }
    }

    window.addEventListener('click', function () {
        const menu = document.getElementById('attachmentDropdownMenu');
        if (menu) menu.style.display = 'none';
    });

    function triggerDocUpload() {
        document.getElementById('docFileInput').click();
    }
    function triggerPhotoUpload() {
        document.getElementById('photoFileInput').click();
    }
    function triggerAudioUpload() {
        document.getElementById('audioFileInput').click();
    }

    function handleFileSelected(inputEl, expectedType) {
        if (!inputEl.files || !inputEl.files[0]) return;
        const file = inputEl.files[0];

        // Immediate size validation
        if (file.size > 10 * 1024 * 1024) {
            alert('File exceeds 10MB limit.');
            inputEl.value = '';
            return;
        }

        // Upload attachment via AJAX
        const formData = new FormData();
        formData.append('file', file);
        formData.append('expected_type', expectedType);

        const btn = document.getElementById('attachmentToggleBtn');
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        fetch('/api/forum?action=upload_attachment', {
            method: 'POST',
            body: formData
        })
            .then(r => r.json())
            .then(data => {
                btn.innerHTML = '+';
                if (data.success) {
                    document.getElementById('stagedAttachmentUrl').value = data.url;
                    document.getElementById('stagedAttachmentType').value = data.type;
                    document.getElementById('stagedAttachmentName').value = data.name;
                    document.getElementById('stagedAttachmentSize').value = data.size;

                    document.getElementById('stagedFileName').innerText = data.name;
                    document.getElementById('stagedFileSize').innerText = '(' + data.size + ')';
                    document.getElementById('stagedAttachmentBar').style.display = 'flex';

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

    function removeStagedAttachment() {
        document.getElementById('stagedAttachmentUrl').value = '';
        document.getElementById('stagedAttachmentType').value = '';
        document.getElementById('stagedAttachmentName').value = '';
        document.getElementById('stagedAttachmentSize').value = '';
        document.getElementById('stagedAttachmentBar').style.display = 'none';
    }

    // Swap Format Helper
    function insertSwapTemplate() {
        const input = document.getElementById('chatMessageInput');
        if (input) {
            input.value = '[SWAP] Current: [County/School] | Subjects: [e.g. Maths/Physics] -> Target: [Preferred County/Region]';
            input.focus();
        }
    }

    // Option B Profile Modal Handlers
    function openAuthorModal(name, subjects, county, userId, userRole, initial) {
        selectedAuthorTarget = { id: userId, role: userRole, name: name };
        document.getElementById('modalAvatar').innerText = initial || 'T';
        document.getElementById('modalName').innerText = name || 'Educator';
        document.getElementById('modalRoleBadge').innerText = userRole === 'school' ? 'School Administrator' : 'Verified Educator';
        document.getElementById('modalSubjects').innerText = subjects || 'General Education';
        document.getElementById('modalCounty').innerText = county || 'Kenya';

        const dmBtn = document.getElementById('modalDirectMsgBtn');
        if (dmBtn && userId) {
            dmBtn.href = (userRole === 'school') ? ('/messages?to_school=' + userId) : ('/messages?to_teacher=' + userId);
        }

        const modal = document.getElementById('authorProfileModal');
        modal.style.display = 'flex';
    }

    function closeAuthorModal() {
        const modal = document.getElementById('authorProfileModal');
        if (modal) modal.style.display = 'none';
    }

    function openReportModal() {
        closeAuthorModal();
        document.getElementById('reportTargetUserId').value = selectedAuthorTarget.id;
        document.getElementById('reportTargetUserRole').value = selectedAuthorTarget.role;
        document.getElementById('reportEducatorModal').style.display = 'flex';
    }

    function closeReportModal() {
        document.getElementById('reportEducatorModal').style.display = 'none';
    }

    function submitEducatorReport(e) {
        e.preventDefault();
        const targetId = document.getElementById('reportTargetUserId').value;
        const targetRole = document.getElementById('reportTargetUserRole').value;
        const reason = document.getElementById('reportReasonSelect').value;
        const details = document.getElementById('reportDetailsInput').value.trim();
        const btn = document.getElementById('submitReportBtn');

        btn.disabled = true;
        fetch('/api/forum?action=report_user', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                target_user_id: targetId,
                target_user_role: targetRole,
                reason: reason,
                details: details
            })
        })
            .then(r => r.json())
            .then(data => {
                btn.disabled = false;
                closeReportModal();
                alert(data.message || 'Report submitted.');
            })
            .catch(() => {
                btn.disabled = false;
                alert('Error submitting report.');
            });
    }

    // Send Group Message via AJAX
    function sendGroupMessage(e) {
        e.preventDefault();
        const catId = document.getElementById('chatCategoryId').value;
        const input = document.getElementById('chatMessageInput');
        const text = input.value.trim();
        const attachUrl = document.getElementById('stagedAttachmentUrl').value;
        const attachType = document.getElementById('stagedAttachmentType').value;
        const attachName = document.getElementById('stagedAttachmentName').value;
        const attachSize = document.getElementById('stagedAttachmentSize').value;
        const btn = document.getElementById('sendChatBtn');

        if (!text && !attachUrl) return;

        btn.disabled = true;

        fetch('/api/forum?action=post_message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                category_id: catId,
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
                    removeStagedAttachment();
                    // Immediately poll for the new message
                    pollNewMessages();
                } else {
                    alert(data.message || 'Failed to post message.');
                    input.focus();
                }
            })
            .catch(() => {
                btn.disabled = false;
                alert('Network error sending message.');
                input.focus();
            });
    }

    // Real-Time 5s Poller
    function pollNewMessages() {
        const catId = document.getElementById('chatCategoryId').value;
        if (!catId) return;

        fetch('/api/forum?action=get_latest&category_id=' + catId + '&since_id=' + currentLastMessageId)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.messages && data.messages.length > 0) {
                    const emptyNotice = document.getElementById('chatEmptyNotice');
                    if (emptyNotice) emptyNotice.remove();

                    const isAtBottom = (chatStream.scrollHeight - chatStream.scrollTop - chatStream.clientHeight) < 80;

                    data.messages.forEach(msg => {
                        if (msg.id > currentLastMessageId) {
                            currentLastMessageId = msg.id;
                        }
                        if (document.querySelector('[data-message-id="' + msg.id + '"]')) {
                            return; // already rendered
                        }

                        const row = renderMessageBubble(msg);
                        chatStream.appendChild(row);
                    });

                    if (isAtBottom) {
                        chatStream.scrollTop = chatStream.scrollHeight;
                    }
                }
            })
            .catch(() => { });
    }

    // Poller loop (pauses if tab hidden)
    setInterval(() => {
        if (!document.hidden) {
            pollNewMessages();
        }
    }, 5000);

    function renderMessageBubble(msg) {
        const row = document.createElement('div');
        row.className = 'chat-message-row';
        row.setAttribute('data-message-id', msg.id);
        row.setAttribute('data-search-text', (msg.author_name + ' ' + msg.author_subjects + ' ' + msg.author_county + ' ' + msg.content + ' ' + (msg.attachment_name || '')).toLowerCase());
        row.style.display = 'flex';
        row.style.justifyContent = msg.is_mine ? 'flex-end' : 'flex-start';
        row.style.alignItems = 'flex-end';
        row.style.gap = '8px';
        row.style.marginBottom = '4px';

        let avatarHtml = '';
        if (!msg.is_mine) {
            avatarHtml = '<div onclick="openAuthorModal(\'' + escapeJs(msg.author_name) + '\', \'' + escapeJs(msg.author_subjects) + '\', \'' + escapeJs(msg.author_county) + '\', ' + msg.user_id + ', \'' + escapeJs(msg.user_role) + '\', \'' + escapeJs(msg.author_initial) + '\')" style="width: 32px; height: 32px; border-radius: 50%; background: #334155; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.82rem; flex-shrink: 0; cursor: pointer;">' + escapeHtml(msg.author_initial) + '</div>';
        }

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

        const authorHeader = (!msg.is_mine) ? ('<div style="display: flex; align-items: center; gap: 6px; margin-bottom: 3px;"><span onclick="openAuthorModal(\'' + escapeJs(msg.author_name) + '\', \'' + escapeJs(msg.author_subjects) + '\', \'' + escapeJs(msg.author_county) + '\', ' + msg.user_id + ', \'' + escapeJs(msg.user_role) + '\', \'' + escapeJs(msg.author_initial) + '\')" style="font-size: 0.78rem; font-weight: 700; color: #0f766e; cursor: pointer;">' + escapeHtml(msg.author_name) + '</span>' + (msg.author_subjects ? ('<span style="font-size: 0.68rem; color: #64748b;">• ' + escapeHtml(msg.author_subjects) + '</span>') : '') + '</div>') : '';

        const bubbleBg = msg.is_mine ? '#0f766e' : '#ffffff';
        const bubbleColor = msg.is_mine ? '#ffffff' : '#1e293b';
        const bubbleBorder = msg.is_mine ? '#0f766e' : '#e2e8f0';
        const radius = msg.is_mine ? '14px 14px 2px 14px' : '14px 14px 14px 2px';

        const contentHtml = msg.content ? ('<div style="font-size: 0.88rem; line-height: 1.45;">' + escapeHtml(msg.content).replace(/\n/g, '<br>') + '</div>') : '';

        row.innerHTML = avatarHtml + '<div style="max-width: 72%; min-width: 140px; background: ' + bubbleBg + '; color: ' + bubbleColor + '; border: 1px solid ' + bubbleBorder + '; padding: 8px 12px; border-radius: ' + radius + '; box-shadow: 0 1px 2px rgba(0,0,0,0.03); word-break: break-word;">' + authorHeader + contentHtml + attachHtml + '<div style="font-size: 0.68rem; text-align: right; margin-top: 3px; color: ' + (msg.is_mine ? '#ccfbf1' : '#94a3b8') + ';">' + escapeHtml(msg.created_at) + '</div></div>';

        return row;
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escapeJs(str) {
        if (!str) return '';
        return String(str).replace(/'/g, "\\'");
    }

    // In-Chat Search Filter
    function filterChatMessages() {
        const query = (document.getElementById('chatSearchInput').value || '').trim().toLowerCase();
        const clearBtn = document.getElementById('clearChatSearchBtn');
        if (clearBtn) clearBtn.style.display = query ? 'block' : 'none';

        const rows = document.querySelectorAll('#chatStream .chat-message-row');
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

        const noRes = document.getElementById('chatSearchNoResults');
        if (noRes) {
            noRes.style.display = (query && visibleCount === 0) ? 'block' : 'none';
        }
    }

    function clearChatSearch() {
        const input = document.getElementById('chatSearchInput');
        if (input) {
            input.value = '';
            filterChatMessages();
            input.focus();
        }
    }
</script>