<?php
// views/forum_create.php - Start a Discussion
require_auth();

$authUser = auth_user();
$categories = forum_get_categories();

$selectedCategorySlug = trim($_GET['category'] ?? '');
$selectedCatId = 0;
foreach ($categories as $c) {
    if ($c->slug === $selectedCategorySlug) {
        $selectedCatId = $c->id;
        break;
    }
}

$authorDisplay = forum_get_author_display($authUser['user_id'], $authUser['role']);
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($submittedToken)) {
        $error = "Security token mismatch. Please submit again.";
    } else {
        $categoryId = intval($_POST['category_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $county = trim($_POST['county'] ?? ($authorDisplay['county'] ?? 'Kenya'));
        $subjects = trim($_POST['subjects'] ?? ($authorDisplay['sub_text'] ?? 'Teacher'));

        // If category was not selected, default to General Chat or the first available category
        if (empty($categoryId)) {
            $defaultCat = R::findOne('forumcategory', 'slug = ?', ['general-chat']) ?? reset($categories);
            $categoryId = $defaultCat ? (int) $defaultCat->id : 1;
        }

        // If content is empty but title is provided, use title as content or vice versa
        if (empty($title) && !empty($content)) {
            $title = substr($content, 0, 80);
        } elseif (empty($content) && !empty($title)) {
            $content = $title;
        }

        if (empty($title)) {
            $error = "Please enter your message or topic title.";
        } else {
            // Check rate limiting: max 1 thread per 10 seconds per user
            $recentThread = R::findOne('forumthread', 'user_id = ? AND user_role = ? AND created_at > ?', [
                $authUser['user_id'],
                $authUser['role'],
                date('Y-m-d H:i:s', time() - 10)
            ]);

            if ($recentThread && $recentThread->id && ($authUser['role'] !== 'admin')) {
                $error = "Please wait a few seconds before posting another topic.";
            } else {
                // Generate base slug
                $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', substr($title, 0, 80)));
                $slug = trim($slug, '-');
                if (empty($slug))
                    $slug = 'topic-' . time();

                // Unique slug check
                $existing = R::findOne('forumthread', 'slug = ?', [$slug]);
                if ($existing && $existing->id) {
                    $slug .= '-' . rand(100, 999);
                }

                $thread = R::dispense('forumthread');
                $thread->category_id = $categoryId;
                $thread->user_id = $authUser['user_id'];
                $thread->user_role = $authUser['role'];
                $thread->author_name = $authorDisplay['display_name'];
                $thread->author_alias = $authorDisplay['display_name'];
                $thread->author_county = $county;
                $thread->author_subjects = $subjects;
                $thread->is_author_private = $authorDisplay['is_private'];
                $thread->title = $title;
                $thread->slug = $slug;
                $thread->content = $content;
                $thread->views_count = 1;
                $thread->replies_count = 0;
                $thread->likes_count = 0;
                $thread->is_pinned = 0;
                $thread->is_locked = 0;
                $thread->is_hidden = 0;
                $thread->last_reply_at = date('Y-m-d H:i:s');
                $thread->created_at = date('Y-m-d H:i:s');
                $thread->updated_at = date('Y-m-d H:i:s');
                $threadId = R::store($thread);

                // Update category counter
                $cat = R::load('forumcategory', $categoryId);
                if ($cat && $cat->id) {
                    $cat->threads_count = (int) $cat->threads_count + 1;
                    R::store($cat);
                }

                header("Location: /forum/thread?id=" . $threadId);
                exit();
            }
        }
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="width: 100%; box-sizing: border-box;">
        <div style="max-width: 820px; margin: 0 auto;">

            <div
                style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div>
                    <a href="/forum"
                        style="color: #64748b; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-arrow-left"></i> Back to Forum Hub
                    </a>
                    <h2 style="margin: 6px 0 2px; color: #0f172a; font-size: 1.35rem; font-weight: 800;">
                        Start a Discussion Topic
                    </h2>
                </div>
            </div>

            <?php if ($error): ?>
                <div
                    style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <form method="POST" action="/forum/create" style="margin: 0;">
                    <?= csrf_field() ?>

                    <!-- Identity & Privacy Reminder Card -->
                    <div
                        style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <div
                                style="width: 36px; height: 36px; border-radius: 50%; background: #0f766e; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem;">
                                <?= h($authorDisplay['initial']) ?>
                            </div>
                            <div>
                                <span style="font-size: 0.86rem; font-weight: 700; color: #0f172a; display: block;">
                                    Posting as <?= h($authorDisplay['display_name']) ?>
                                </span>
                                <span style="font-size: 0.76rem; color: #64748b;">
                                    <?= $authorDisplay['is_private'] ? 'Profile Privacy Enabled (Phone & Email protected)' : 'Public Profile' ?>
                                </span>
                            </div>
                        </div>
                        <a href="/teacher/update"
                            style="font-size: 0.78rem; color: #0f766e; text-decoration: none; font-weight: 600;">
                            Change Display Alias &rarr;
                        </a>
                    </div>

                    <!-- Category Selector (Optional / Defaulted) -->
                    <div style="margin-bottom: 1.25rem;">
                        <label for="category_id"
                            style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            Topic Category
                        </label>
                        <select name="category_id" id="category_id"
                            style="width: 100%; box-sizing: border-box; background: white; height: 44px; margin: 0;">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat->id ?>" <?= ($selectedCatId == $cat->id || (empty($selectedCatId) && $cat->slug === 'general-chat')) ? 'selected' : '' ?>>
                                    <?= h($cat->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Topic Title / Question -->
                    <div style="margin-bottom: 1.25rem;">
                        <label for="title"
                            style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            Topic / Question <span style="color: #ef4444;">*</span>
                        </label>
                        <input type="text" name="title" id="title" value="<?= h($_POST['title'] ?? '') ?>"
                            placeholder="What would you like to discuss or ask fellow teachers?" required
                            style="width: 100%; box-sizing: border-box; height: 44px; margin: 0;">
                    </div>

                    <!-- Content Textarea -->
                    <div style="margin-bottom: 1.5rem;">
                        <label for="content"
                            style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px;">
                            Additional Details (Optional)
                        </label>
                        <textarea name="content" id="content" rows="6"
                            placeholder="Add more details, subject tags, swap stations, or context if needed..."
                            style="width: 100%; box-sizing: border-box; margin: 0; padding: 12px; font-family: inherit; font-size: 0.9rem; line-height: 1.6;"><?= h($_POST['content'] ?? '') ?></textarea>
                    </div>

                    <div
                        style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <a href="/forum"
                            style="color: #64748b; font-size: 0.85rem; text-decoration: none; font-weight: 600;">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary"
                            style="background: #0f766e; color: white !important; font-size: 0.92rem; font-weight: 700; padding: 10px 24px; border-radius: 6px; border: none; cursor: pointer;">
                            <i class="fa fa-paper-plane"></i> Post to Forum
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>