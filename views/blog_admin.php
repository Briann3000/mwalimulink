<?php
// views/blog_admin.php - Unified Admin Educator Publications & Research Hub Management
require_auth('admin');

$authUser = auth_user();
$message = '';
$msgType = 'success';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $message = "Security token mismatch. Please try again.";
        $msgType = 'error';
    } else {
        $id = (int)($_POST['id'] ?? 0);

        if (isset($_POST['delete_pub']) && $id > 0) {
            $pub = R::load('publications', $id);
            if ($pub && $pub->id) {
                $pubTitle = $pub->title;
                R::trash($pub);
                $message = "Publication '{$pubTitle}' deleted successfully.";
                $msgType = 'success';
            }
        } elseif (isset($_POST['title'], $_POST['summary'])) {
            $title = trim($_POST['title']);
            $category = trim($_POST['category'] ?? 'CBC Pedagogical Research');
            $author = trim($_POST['author'] ?? 'Mwalimu Editorial Desk');
            $author_role = trim($_POST['author_role'] ?? 'Curriculum Specialist');
            $summary = trim($_POST['summary']);
            $content = trim($_POST['content'] ?? '');
            $pdf_url = trim($_POST['pdf_url'] ?? '');
            $is_published = isset($_POST['is_published']) ? 1 : 0;

            if ($id > 0) {
                $pub = R::load('publications', $id);
                if ($pub && $pub->id) {
                    $pub->title = $title;
                    $pub->category = $category;
                    $pub->author = $author;
                    $pub->author_role = $author_role;
                    $pub->summary = $summary;
                    $pub->content = $content ?: $summary;
                    $pub->is_published = $is_published;
                    if (!empty($pdf_url)) {
                        $pub->pdf_url = $pdf_url;
                    }
                    
                    // Handle PDF upload if attached
                    if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                        $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
                        if ($ext === 'pdf') {
                            $uploadDir = __DIR__ . '/../uploads/publications/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }
                            $fileName = 'paper_' . time() . '_' . rand(100, 999) . '.pdf';
                            if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadDir . $fileName)) {
                                $pub->pdf_path = '/uploads/publications/' . $fileName;
                                $pub->pdf_url = '/uploads/publications/' . $fileName;
                            }
                        }
                    }

                    R::store($pub);
                    $message = "Publication updated successfully.";
                    $msgType = 'success';
                }
            } else {
                $pub = R::dispense('publications');
                $pub->title = $title;
                $pub->category = $category;
                $pub->author = $author;
                $pub->author_role = $author_role;
                $pub->summary = $summary;
                $pub->content = $content ?: $summary;
                $pub->pdf_url = $pdf_url;
                $pub->views_count = 1;
                $pub->created_at = date('Y-m-d H:i:s');
                $pub->is_published = $is_published;

                // Handle PDF upload if attached
                if (isset($_FILES['pdf_file']) && $_FILES['pdf_file']['error'] === UPLOAD_ERR_OK) {
                    $ext = strtolower(pathinfo($_FILES['pdf_file']['name'], PATHINFO_EXTENSION));
                    if ($ext === 'pdf') {
                        $uploadDir = __DIR__ . '/../uploads/publications/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }
                        $fileName = 'paper_' . time() . '_' . rand(100, 999) . '.pdf';
                        if (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $uploadDir . $fileName)) {
                            $pub->pdf_path = '/uploads/publications/' . $fileName;
                            if (empty($pub->pdf_url)) {
                                $pub->pdf_url = '/uploads/publications/' . $fileName;
                            }
                        }
                    }
                }

                R::store($pub);
                $message = "New publication created successfully.";
                $msgType = 'success';
            }
        }
    }
}

// Load edit item if requested
$editId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentPub = null;
if ($editId > 0) {
    $currentPub = R::load('publications', $editId);
}

// Standard categories
$pubCategories = [
    'CBC Pedagogical Research',
    'STEM & Digital Learning',
    'Inclusive Education & Guidance',
    'School Leadership & Administration',
    'Assessment & Evaluation Practices',
    'Teacher Professional Development (TPD)'
];

$publications = R::findAll('publications', 'ORDER BY id DESC LIMIT 50');
$totalPubsCount = R::count('publications');
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane">
        <div style="max-width: 1100px; margin: 0 auto; padding-bottom: 3rem;">
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <a href="/admin/dashboard" style="color: #64748b; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 6px; text-decoration: none;">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h2 style="margin: 0; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-book-open" style="color: #0f766e;"></i> Educator Publications & Research Hub
                    </h2>
                </div>
                <div>
                    <a href="/publications" target="_blank" style="background: #f0fdfa; color: #0f766e; border: 1px solid #ccfbf1; padding: 6px 14px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-arrow-up-right-from-square"></i> View Live Publications Page
                    </a>
                </div>
            </div>

            <?php if ($message): ?>
                <div style="background: <?= $msgType === 'error' ? '#fee2e2' : '#f0fdf4' ?>; border: 1px solid <?= $msgType === 'error' ? '#fca5a5' : '#86efac' ?>; color: <?= $msgType === 'error' ? '#991b1b' : '#166534' ?>; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa <?= $msgType === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
                    <?= h($message) ?>
                </div>
            <?php endif; ?>

            <!-- Form Card: Create or Edit Publication -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                    <h3 style="margin: 0; font-size: 1.15rem; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                        <i class="fa <?= $currentPub ? 'fa-pen-to-square' : 'fa-plus-circle' ?>" style="color: #0f766e;"></i>
                        <?= $currentPub ? 'Edit Publication: ' . h($currentPub->title) : 'Publish New Paper / Article' ?>
                    </h3>
                    <?php if ($currentPub): ?>
                        <a href="/blog/admin" style="font-size: 0.84rem; color: #64748b; text-decoration: underline;">
                            Cancel Edit & Create New
                        </a>
                    <?php endif; ?>
                </div>

                <form method="POST" action="/blog/admin" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1rem; margin: 0;">
                    <?= csrf_field() ?>
                    <?php if ($currentPub): ?>
                        <input type="hidden" name="id" value="<?= $currentPub->id ?>">
                    <?php endif; ?>

                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">
                            Publication Title *
                        </label>
                        <input type="text" name="title" required value="<?= h($currentPub->title ?? '') ?>" placeholder="e.g. Empirical Study on CBC Junior Secondary Transition in Kenya" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.92rem; box-sizing: border-box;">
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">
                                Category *
                            </label>
                            <select name="category" style="width: 100%; padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; background: white; box-sizing: border-box;">
                                <?php foreach ($pubCategories as $cat): ?>
                                    <option value="<?= h($cat) ?>" <?= ($currentPub && $currentPub->category === $cat) ? 'selected' : '' ?>>
                                        <?= h($cat) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">
                                Author Name
                            </label>
                            <input type="text" name="author" value="<?= h($currentPub->author ?? ($authUser['name'] ?? 'Mwalimu Editorial')) ?>" placeholder="e.g. Dr. Elizabeth Mwangi" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; box-sizing: border-box;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">
                                Author Title / Designation
                            </label>
                            <input type="text" name="author_role" value="<?= h($currentPub->author_role ?? 'Senior Curriculum Specialist') ?>" placeholder="e.g. Head of Science / TSC Educator" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; box-sizing: border-box;">
                        </div>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">
                            Executive Summary / Abstract *
                        </label>
                        <textarea name="summary" required rows="3" placeholder="Brief 2-3 sentence overview of the research study or findings..." style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; font-family: inherit; box-sizing: border-box;"><?= h($currentPub->summary ?? '') ?></textarea>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">
                            Full Content / Detailed Findings
                        </label>
                        <textarea name="content" rows="6" placeholder="Full paper content, methodology, data tables, and pedagogical recommendations..." style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; font-family: inherit; box-sizing: border-box;"><?= h($currentPub->content ?? '') ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">
                                PDF Document Upload (Optional)
                            </label>
                            <input type="file" name="pdf_file" accept=".pdf" style="font-size: 0.85rem; color: #475569;">
                            <?php if ($currentPub && (!empty($currentPub->pdf_path) || !empty($currentPub->pdf_url))): ?>
                                <div style="font-size: 0.78rem; color: #0f766e; margin-top: 4px;">
                                    Current PDF: <a href="<?= h($currentPub->pdf_url ?: $currentPub->pdf_path) ?>" target="_blank" style="text-decoration: underline;">View Attached Document</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px;">
                                External PDF Link / DOI URL (Optional)
                            </label>
                            <input type="text" name="pdf_url" value="<?= h($currentPub->pdf_url ?? '') ?>" placeholder="https://..." style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; box-sizing: border-box;">
                        </div>
                    </div>

                    <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 1rem; flex-wrap: wrap; gap: 12px;">
                        <label style="display: inline-flex; align-items: center; gap: 8px; font-size: 0.88rem; color: #334155; cursor: pointer;">
                            <input type="checkbox" name="is_published" value="1" <?= (!$currentPub || $currentPub->is_published) ? 'checked' : '' ?> style="width: 17px; height: 17px;">
                            <span><strong>Publish Live</strong> (make visible on the Educator Publications Hub)</span>
                        </label>

                        <button type="submit" style="background: #0f766e; color: white !important; padding: 9px 22px; border: none; border-radius: 6px; font-size: 0.92rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fa fa-floppy-disk"></i> <?= $currentPub ? 'Update Publication' : 'Publish Article' ?>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Existing Publications Table -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden;">
                <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #f1f5f9; display: flex; justify-content: space-between; align-items: center;">
                    <h3 style="margin: 0; font-size: 1.05rem; color: #0f172a;">
                        All Publications & Research Papers (<?= count($publications) ?>)
                    </h3>
                </div>

                <?php if (empty($publications)): ?>
                    <div style="padding: 3rem; text-align: center; color: #64748b;">
                        <i class="fa fa-book-bookmark" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px;"></i>
                        <p style="margin: 0; font-size: 0.95rem;">No publications created yet.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569;">
                                    <th style="padding: 12px 14px;">Paper / Article</th>
                                    <th style="padding: 12px 14px;">Category</th>
                                    <th style="padding: 12px 14px;">Author</th>
                                    <th style="padding: 12px 14px;">Status</th>
                                    <th style="padding: 12px 14px;">PDF Attachment</th>
                                    <th style="padding: 12px 14px; text-align: right;">Operations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($publications as $pub): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#fbfcfd'" onmouseout="this.style.background='white'">
                                        <td style="padding: 12px 14px;">
                                            <div style="font-weight: 700; color: #0f172a;">
                                                <a href="/publications/view?id=<?= $pub->id ?>" target="_blank" style="color: #0f766e; text-decoration: none;" title="Open article in new tab">
                                                    <?= h($pub->title) ?> <i class="fa fa-arrow-up-right-from-square" style="font-size: 0.72rem; opacity: 0.7;"></i>
                                                </a>
                                            </div>
                                            <div style="font-size: 0.76rem; color: #64748b; margin-top: 2px;">
                                                <?= date('M d, Y', strtotime($pub->created_at ?? 'now')) ?> &bull; <?= number_format(intval($pub->views_count ?? 0)) ?> views
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <span style="background: #f1f5f9; color: #475569; font-size: 0.75rem; font-weight: 600; padding: 3px 8px; border-radius: 4px;">
                                                <?= h($pub->category ?: 'General Research') ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <div style="font-size: 0.82rem; color: #334155; font-weight: 600;">
                                                <?= h($pub->author ?: 'Educator') ?>
                                            </div>
                                            <div style="font-size: 0.74rem; color: #64748b;">
                                                <?= h($pub->author_role ?: 'Author') ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <?php if (!isset($pub->is_published) || $pub->is_published == 1): ?>
                                                <span style="background: #dcfce7; color: #166534; font-weight: 700; font-size: 0.74rem; padding: 3px 8px; border-radius: 4px;">
                                                    Published
                                                </span>
                                            <?php else: ?>
                                                <span style="background: #fef9c3; color: #854d0e; font-weight: 700; font-size: 0.74rem; padding: 3px 8px; border-radius: 4px;">
                                                    Draft
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <?php if (!empty($pub->pdf_url) || !empty($pub->pdf_path)): ?>
                                                <a href="<?= h($pub->pdf_url ?: $pub->pdf_path) ?>" target="_blank" style="font-size: 0.78rem; color: #0284c7; text-decoration: underline; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fa fa-file-pdf"></i> PDF Document
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 0.78rem;">Inline Only</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px; text-align: right;">
                                            <div style="display: inline-flex; gap: 6px;">
                                                <a href="/blog/admin?id=<?= $pub->id ?>" style="background: #f1f5f9; color: #1e293b; padding: 4px 10px; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.74rem; font-weight: 600; text-decoration: none;">
                                                    <i class="fa fa-pen"></i> Edit
                                                </a>
                                                <form method="POST" action="/blog/admin" style="margin: 0; display: inline-block;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= $pub->id ?>">
                                                    <input type="hidden" name="delete_pub" value="1">
                                                    <button type="submit" onclick="return confirm('Delete this publication?')" style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 0.74rem; font-weight: 600; padding: 4px 8px; border-radius: 4px; cursor: pointer;">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>
