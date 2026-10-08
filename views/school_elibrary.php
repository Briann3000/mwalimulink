<?php
// views/school_elibrary.php - School Institution Resource Upload & Management Portal
$authUser = auth_user();
if (!$authUser || $authUser['role'] !== 'school') {
  header("Location: /login/school?redirect=/school/elibrary");
  exit();
}

$schoolId = (int) ($authUser['user_id'] ?? 0);
$schoolName = $authUser['name'] ?? 'School Institution';

$actionSuccess = '';
$actionError = '';

// Handle School Resource Upload Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_resource') {
  $title = trim($_POST['title'] ?? '');
  $curriculumTier = trim($_POST['curriculum_tier'] ?? 'Junior School');
  $gradeLevel = trim($_POST['grade_level'] ?? 'Grade 7');
  $subject = trim($_POST['subject'] ?? 'General');
  $category = trim($_POST['category'] ?? 'Revision Papers');
  $term = trim($_POST['term'] ?? 'Term 1');
  $academicYear = (int) ($_POST['academic_year'] ?? date('Y'));
  $description = trim($_POST['description'] ?? '');
  $price = max(0.00, (float) ($_POST['price'] ?? 0.00));
  $copyrightWarranty = isset($_POST['copyright_warranty']);

  if (empty($title)) {
    $actionError = "Please enter a descriptive resource title.";
  } elseif (!$copyrightWarranty) {
    $actionError = "You must confirm the copyright ownership declaration before submitting.";
  } elseif (empty($_FILES['resource_file']['name'])) {
    $actionError = "Please attach a document file (PDF, DOCX, PPTX, or ZIP).";
  } else {
    $uploadRes = validate_and_upload_elibrary_file($_FILES['resource_file']);
    if (!$uploadRes['success']) {
      $actionError = $uploadRes['error'];
    } else {
      $res = R::dispense('resource');
      $res->title = $title;
      $res->slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($title));
      $res->curriculum_tier = $curriculumTier;
      $res->grade_level = $gradeLevel;
      $res->subject = $subject;
      $res->category = $category;
      $res->term = $term;
      $res->academic_year = $academicYear;
      $res->description = $description;
      $res->price = $price;
      $res->file_path = $uploadRes['file_path'];
      $res->file_type = $uploadRes['file_type'];
      $res->file_size_bytes = $uploadRes['file_size'];
      $res->uploader_type = 'school';
      $res->uploader_id = $schoolId;
      $res->uploader_name = $schoolName;
      $res->status = 'pending_review';
      $res->download_count = 0;
      $res->views_count = 0;
      $res->is_featured = 0;
      $res->created_at = date('Y-m-d H:i:s');
      R::store($res);

      $actionSuccess = "Institutional resource submitted successfully! Upon admin approval, it will be published with your school badge and award 3 Free Download Unlocks to your institution.";
    }
  }
}

// Fetch school's submissions
$myUploads = R::findAll('resource', ' uploader_type = ? AND uploader_id = ? ORDER BY created_at DESC ', ['school', $schoolId]);
$availableUnlocks = get_user_elibrary_credits('school', $schoolId);
$totalDownloads = 0;
foreach ($myUploads as $u) {
  $totalDownloads += (int) $u->download_count;
}

$grades = ['PP1', 'PP2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Form 1', 'Form 2', 'Form 3', 'Form 4', 'General'];
$categories = ['Revision Papers', 'Notes', 'Schemes of Work', 'Lesson Plans', 'Curriculum Designs', 'Set Book Guides', 'Ebooks'];
$tiers = ['Junior School', 'Primary', 'Secondary', 'Pre-Primary', 'Teacher Tools'];
$subjects = ['Integrated Science', 'Mathematics', 'English', 'Kiswahili', 'Social Studies', 'CRE', 'IRE', 'Agriculture', 'Pre-Technical Studies', 'Creative Arts', 'Chemistry', 'Physics', 'Biology', 'History', 'Geography', 'Business Studies'];
?>

<style>
  .school-elib-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.5rem 1rem 4rem 1rem;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: #1e293b;
  }

  .elib-stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
  }

  .elib-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  }

  .elib-form-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.75rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
  }

  .form-grid-2 {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1rem;
  }

  @media (min-width: 768px) {
    .form-grid-2 {
      grid-template-columns: 1fr 1fr;
    }

    .form-grid-3 {
      grid-template-columns: 1fr 1fr 1fr;
    }
  }

  .school-elib-container input,
  .school-elib-container select,
  .school-elib-container textarea {
    width: 100%;
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    padding: 9px 12px !important;
    font-size: 0.88rem !important;
    color: #0f172a !important;
    background: #ffffff !important;
    box-sizing: border-box !important;
    margin-bottom: 0 !important;
  }

  .school-elib-container label {
    font-size: 0.8rem;
    font-weight: 700;
    color: #334155;
    margin-bottom: 4px;
    display: block;
  }

  .badge-status {
    font-size: 0.72rem;
    font-weight: 800;
    padding: 3px 8px;
    border-radius: 6px;
    display: inline-block;
  }

  .badge-status.approved {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
  }

  .badge-status.pending_review {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
  }

  .badge-status.rejected {
    background: #fee2e2;
    color: #dc2626;
    border: 1px solid #fecaca;
  }
</style>

<div class="workspace-wrapper">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <div class="workspace-content">
    <div class="school-elib-container">

      <!-- Header -->
      <div style="margin-bottom: 1.5rem;">
        <div style="font-size: 0.82rem; color: #64748b; margin-bottom: 0.5rem;">
          <a href="/school/dashboard" style="color: #64748b; text-decoration: none;"><i class="fa fa-tachometer-alt"></i> Dashboard</a>
          <span>/</span>
          <span style="color: #0369a1; font-weight: 700;">Institution Resources & Uploads</span>
        </div>
        <h1 style="font-size: 1.65rem; font-weight: 900; color: #0f172a; margin: 0 0 0.35rem 0;">
          <?= h($schoolName) ?> &bull; Learning Resources
        </h1>
        <p style="color: #64748b; font-size: 0.92rem; margin: 0;">
          Publish your school's mock examinations, departmental schemes of work, and holiday assignment booklets.
        </p>
      </div>

      <?php if (!empty($actionSuccess)): ?>
        <div style="background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 14px 18px; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
          <i class="fa fa-check-circle" style="font-size: 1.2rem;"></i>
          <div><?= h($actionSuccess) ?></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($actionError)): ?>
        <div style="background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 14px 18px; border-radius: 10px; margin-bottom: 1.5rem; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
          <i class="fa fa-circle-exclamation" style="font-size: 1.2rem;"></i>
          <div><?= h($actionError) ?></div>
        </div>
      <?php endif; ?>

      <!-- Stat Cards -->
      <div class="elib-stat-grid">
        <div class="elib-stat-card" style="border-left: 4px solid #0369a1;">
          <div style="font-size: 0.78rem; font-weight: 800; color: #0369a1; text-transform: uppercase;">Available School Unlocks</div>
          <div style="font-size: 1.75rem; font-weight: 900; color: #0f172a; margin: 4px 0;"><?= (int)$availableUnlocks ?></div>
          <div style="font-size: 0.75rem; color: #64748b;">For your teaching staff</div>
        </div>

        <div class="elib-stat-card" style="border-left: 4px solid #0f766e;">
          <div style="font-size: 0.78rem; font-weight: 800; color: #0f766e; text-transform: uppercase;">Institution Materials</div>
          <div style="font-size: 1.75rem; font-weight: 900; color: #0f172a; margin: 4px 0;"><?= count($myUploads) ?></div>
          <div style="font-size: 0.75rem; color: #64748b;">Published under school name</div>
        </div>

        <div class="elib-stat-card" style="border-left: 4px solid #15803d;">
          <div style="font-size: 0.78rem; font-weight: 800; color: #15803d; text-transform: uppercase;">Total Downloads</div>
          <div style="font-size: 1.75rem; font-weight: 900; color: #0f172a; margin: 4px 0;"><?= $totalDownloads ?></div>
          <div style="font-size: 0.75rem; color: #64748b;">Learners and educators reached</div>
        </div>
      </div>

      <!-- Upload Form Card -->
      <div class="elib-form-card">
        <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem; margin-bottom: 1.25rem;">
          <div>
            <h2 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">
              <i class="fa fa-upload" style="color: #0369a1;"></i> Upload Institutional Material
            </h2>
            <div style="font-size: 0.8rem; color: #64748b;">Upload mock exams, departmental schemes, or school revision guides.</div>
          </div>
        </div>

        <form action="/school/elibrary" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.15rem;">
          <input type="hidden" name="action" value="upload_resource">

          <div>
            <label>Resource Title *</label>
            <input type="text" name="title" required placeholder="e.g. Form 4 Joint Mock Examination Papers with Marking Scheme" maxlength="255">
          </div>

          <div class="form-grid-2 form-grid-3">
            <div>
              <label>Curriculum Tier *</label>
              <select name="curriculum_tier" required>
                <?php foreach ($tiers as $t): ?>
                  <option value="<?= h($t) ?>"><?= h($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label>Grade / Form Level *</label>
              <select name="grade_level" required>
                <?php foreach ($grades as $g): ?>
                  <option value="<?= h($g) ?>"><?= h($g) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label>Subject / Department *</label>
              <select name="subject" required>
                <?php foreach ($subjects as $s): ?>
                  <option value="<?= h($s) ?>"><?= h($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-grid-2 form-grid-3">
            <div>
              <label>Category *</label>
              <select name="category" required>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= h($cat) ?>"><?= h($cat) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div>
              <label>Academic Term</label>
              <select name="term">
                <option value="Term 1">Term 1</option>
                <option value="Term 2">Term 2</option>
                <option value="Term 3">Term 3</option>
                <option value="General">General / Annual</option>
              </select>
            </div>

            <div>
              <label>Price (KES) — Set 0 for Free</label>
              <input type="number" name="price" min="0" step="10" value="0" placeholder="0.00">
            </div>
          </div>

          <div>
            <label>Description / Details</label>
            <textarea name="description" rows="3" placeholder="Describe the topics covered, marking scheme details, or examination scope..."></textarea>
          </div>

          <div>
            <label>Document File (PDF, DOCX, PPTX, ZIP — Max 30 MB) *</label>
            <input type="file" name="resource_file" required accept=".pdf,.docx,.doc,.pptx,.ppt,.zip" style="padding: 6px !important;">
          </div>

          <!-- Warranty -->
          <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 12px 14px;">
            <label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer; margin: 0; font-weight: normal; font-size: 0.82rem; color: #334155;">
              <input type="checkbox" name="copyright_warranty" required style="width: auto !important; margin-top: 3px !important;">
              <span>
                <strong>Institutional Declaration:</strong> I confirm that this school has the authority to share this material for educational purposes, and it is not an unauthorized copy of commercial publisher textbooks.
              </span>
            </label>
          </div>

          <div>
            <button type="submit" style="background: #0369a1; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-weight: 800; font-size: 0.92rem; cursor: pointer;">
              <i class="fa fa-upload"></i> Publish Under <?= h($schoolName) ?>
            </button>
          </div>
        </form>
      </div>

      <!-- Uploaded List -->
      <div class="elib-form-card">
        <h2 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 1rem 0;">
          Materials Published by <?= h($schoolName) ?>
        </h2>

        <?php if (empty($myUploads)): ?>
          <div style="text-align: center; padding: 2.5rem 1rem; color: #64748b;">
            <i class="fa fa-school" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 0.5rem;"></i>
            <p>No institutional materials uploaded yet. Use the form above to share your school's exams or notes!</p>
          </div>
        <?php else: ?>
          <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
              <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left;">
                  <th style="padding: 10px 12px; color: #475569;">Title</th>
                  <th style="padding: 10px 12px; color: #475569;">Grade & Subject</th>
                  <th style="padding: 10px 12px; color: #475569;">Price</th>
                  <th style="padding: 10px 12px; color: #475569;">Status</th>
                  <th style="padding: 10px 12px; color: #475569;">Downloads</th>
                  <th style="padding: 10px 12px; color: #475569;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($myUploads as $item): ?>
                  <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 10px 12px; font-weight: 700; color: #0f172a; max-width: 280px;">
                      <?= h($item->title) ?>
                    </td>
                    <td style="padding: 10px 12px; color: #64748b;">
                      <?= h($item->grade_level) ?> &bull; <?= h($item->subject) ?>
                    </td>
                    <td style="padding: 10px 12px; font-weight: 700; color: <?= ((float)$item->price <= 0) ? '#15803d' : '#0369a1' ?>;">
                      <?= ((float)$item->price <= 0) ? 'FREE' : 'KES ' . number_format($item->price, 0) ?>
                    </td>
                    <td style="padding: 10px 12px;">
                      <span class="badge-status <?= h($item->status) ?>">
                        <?= ucfirst(str_replace('_', ' ', $item->status)) ?>
                      </span>
                    </td>
                    <td style="padding: 10px 12px; color: #475569;">
                      <?= (int)$item->download_count ?>
                    </td>
                    <td style="padding: 10px 12px;">
                      <a href="/elibrary/view?id=<?= $item->id ?>" style="color: #0369a1; text-decoration: none; font-weight: 700;">
                        <i class="fa fa-eye"></i> View
                      </a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>
