<?php
// views/elibrary_download.php - Secure File Download & Unlock Dispatcher

$authUser = auth_user();
$userType = $authUser['role'] ?? null;
$userId = $authUser['user_id'] ?? null;
$userEmail = $authUser['email'] ?? null;

// Handle Credit Unlock Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unlock_with_credit') {
  $resId = (int) ($_POST['resource_id'] ?? 0);
  if ($resId > 0 && $userId && in_array($userType, ['teacher', 'school'])) {
    $deducted = deduct_user_elibrary_credit($userType, $userId, $resId);
    if ($deducted) {
      header("Location: /elibrary/download?id={$resId}");
      exit();
    } else {
      $_SESSION['flash_error'] = "Insufficient download unlocks balance.";
      header("Location: /elibrary/view?id={$resId}");
      exit();
    }
  }
}

// GET Download Handling
$id = (int) ($_GET['id'] ?? 0);
$paymentRef = trim($_GET['ref'] ?? '');
$buyerEmail = trim($_GET['email'] ?? ($userEmail ?: ''));

if ($id <= 0) {
  header("Location: /elibrary");
  exit();
}

$resource = R::load('resource', $id);
if (!$resource || !$resource->id || $resource->status !== 'approved') {
  header("Location: /elibrary");
  exit();
}

// If returning with payment ref from IntaSend
if (!empty($paymentRef)) {
  record_resource_access($resource->id, $userType ?: 'guest', $userId, $buyerEmail, 'mpesa_purchase', (float) $resource->price, $paymentRef);
  if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION['elibrary_unlocked_' . $resource->id] = true;
  }
  // Send delivery email once per payment reference
  $emailSessionKey = 'emailed_ref_' . md5($paymentRef . '_' . $resource->id);
  if (!empty($buyerEmail) && empty($_SESSION[$emailSessionKey])) {
    send_elibrary_purchase_email($buyerEmail, [$resource], $paymentRef);
    $_SESSION[$emailSessionKey] = true;
  }
}

// Verify access
$isFree = (float) $resource->price <= 0;
$hasAccess = $isFree || user_has_unlocked_resource($resource->id, $userId, $userType, $buyerEmail);

if (!$hasAccess) {
  // Unauthorized, redirect to view/purchase page
  header("Location: /elibrary/view?id={$id}#access");
  exit();
}

// If free download, log free access and send email if provided
if ($isFree) {
  record_resource_access($resource->id, $userType ?: 'guest', $userId, $buyerEmail ?: $userEmail, 'free_download', 0.00, null);
  if (!empty($buyerEmail) && filter_var($buyerEmail, FILTER_VALIDATE_EMAIL)) {
    $emailSessionKey = 'emailed_free_' . md5($buyerEmail . '_' . $resource->id);
    if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION[$emailSessionKey])) {
      send_elibrary_purchase_email($buyerEmail, [$resource], 'FREE_DOWNLOAD');
      $_SESSION[$emailSessionKey] = true;
    }
  }
}

// Update resource download counter
$resource->download_count = (int) $resource->download_count + 1;
R::store($resource);

// Resolve physical file path
$fullPath = resolve_elibrary_file_path($resource->file_path);

if (!$fullPath || !file_exists($fullPath)) {
  // If physical file is missing or still being extracted to server storage
  ?>
  <style>
    .mwalimu-dl-container {
      max-width: 680px;
      margin: 3rem auto 5rem auto;
      padding: 0 1rem;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .mwalimu-dl-card {
      background: #ffffff;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 2.5rem 2rem;
      text-align: center;
      box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
    }

    .mwalimu-dl-icon {
      width: 64px;
      height: 64px;
      background: #fef3c7;
      color: #d97706;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.75rem;
      margin-bottom: 1.25rem;
    }

    .mwalimu-dl-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      background: #0f766e;
      color: #ffffff !important;
      text-decoration: none;
      padding: 12px 24px;
      border-radius: 10px;
      font-weight: 800;
      font-size: 0.92rem;
      transition: background 0.2s;
    }

    .mwalimu-dl-btn:hover {
      background: #0d9488;
    }

    .mwalimu-dl-btn-secondary {
      background: #f1f5f9;
      color: #334155 !important;
      border: 1px solid #cbd5e1;
    }

    .mwalimu-dl-btn-secondary:hover {
      background: #e2e8f0;
    }
  </style>

  <div class="mwalimu-dl-container">
    <!-- Breadcrumbs -->
    <div style="font-size: 0.82rem; color: #64748b; margin-bottom: 1rem; display: flex; align-items: center; gap: 6px;">
      <a href="/" style="color: #64748b; text-decoration: none;"><i class="fa fa-home"></i> Home</a>
      <span>/</span>
      <a href="/elibrary" style="color: #64748b; text-decoration: none;">E-Library</a>
      <span>/</span>
      <span style="color: #0f766e; font-weight: 700;">Download</span>
    </div>

    <div class="mwalimu-dl-card">
      <div class="mwalimu-dl-icon">
        <i class="fa fa-file-arrow-down"></i>
      </div>

      <div
        style="display: inline-block; background: #e0f2fe; color: #0369a1; font-weight: 800; font-size: 0.75rem; padding: 2px 10px; border-radius: 6px; margin-bottom: 0.75rem;">
        <?= htmlspecialchars($resource->grade_level ?: 'CBC') ?> &bull;
        <?= htmlspecialchars($resource->subject ?: 'General') ?>
      </div>

      <h1 style="font-size: 1.4rem; font-weight: 900; color: #0f172a; margin: 0 0 0.75rem 0;">
        <?= htmlspecialchars($resource->title) ?>
      </h1>

      <p style="font-size: 0.92rem; color: #475569; line-height: 1.6; max-width: 500px; margin: 0 auto 1.25rem auto;">
        You have unlocked this document. The physical PDF file is currently synchronizing on the server storage cluster.
      </p>

      <div
        style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px; margin-bottom: 1.75rem; font-size: 0.8rem; color: #64748b; text-align: left;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
          <span style="font-weight: 700;">Document Ref:</span>
          <span>#<?= (int) $resource->id ?></span>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px;">
          <span style="font-weight: 700;">Payment / Access Status:</span>
          <span style="color: #16a34a; font-weight: 700;"><i class="fa fa-check-circle"></i> Unlocked</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
          <span style="font-weight: 700;">Expected Storage Path:</span>
          <code
            style="background: #e2e8f0; padding: 1px 4px; border-radius: 4px; font-size: 0.75rem;"><?= htmlspecialchars($resource->file_path) ?></code>
        </div>
      </div>

      <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
        <a href="/elibrary/view?id=<?= $resource->id ?>" class="mwalimu-dl-btn mwalimu-dl-btn-secondary">
          <i class="fa fa-arrow-left"></i> Resource Details
        </a>
        <a href="/elibrary" class="mwalimu-dl-btn">
          <i class="fa fa-book-open"></i> Browse E-Library
        </a>
      </div>
    </div>
  </div>
  <?php
  exit();
}

// Determine correct content type
$ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
$mimeMap = [
  'pdf' => 'application/pdf',
  'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'doc' => 'application/msword',
  'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  'ppt' => 'application/vnd.ms-powerpoint',
  'zip' => 'application/zip'
];
$contentType = $mimeMap[$ext] ?? 'application/octet-stream';
$downloadName = preg_replace('/[^a-zA-Z0-9_\-.]/', '_', $resource->title) . '.' . $ext;

header('Content-Description: File Transfer');
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($fullPath));
readfile($fullPath);
exit();

