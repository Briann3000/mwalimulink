<?php
// views/admin_elibrary.php - Admin E-Library Moderation, Catalog Management & Copyright Claims
$authUser = auth_user();
if (!$authUser || $authUser['role'] !== 'admin') {
  header("Location: /login/admin?redirect=/admin/elibrary");
  exit();
}

$actionSuccess = '';
$actionError = '';
$activeTab = $_GET['tab'] ?? 'pending';

// -------------------------------------------------------------
// POST Actions Handling
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $resId = (int) ($_POST['resource_id'] ?? 0);

  // 1. APPROVE RESOURCE (1-Click Action + 3 Unlocks Award)
  if ($action === 'approve_resource' && $resId > 0) {
    $res = R::load('resource', $resId);
    if ($res && $res->id) {
      $res->status = 'approved';
      $res->updated_at = date('Y-m-d H:i:s');
      R::store($res);

      // Automatically award 3 download unlocks to the uploader
      if ($res->uploader_id && in_array($res->uploader_type, ['teacher', 'school'])) {
        award_user_elibrary_credits($res->uploader_type, $res->uploader_id, 3);
      }

      if (function_exists('log_admin_audit')) {
        log_admin_audit('elibrary', 'RESOURCE_APPROVED', $res->uploader_type, $res->uploader_id, '', "Approved resource #{$res->id} ({$res->title}) and awarded 3 unlocks.");
      }

      $actionSuccess = "Resource #{$res->id} approved and published! 3 download unlocks have been awarded to " . h($res->uploader_name) . ".";
    }
  }

  // 2. REJECT RESOURCE
  elseif ($action === 'reject_resource' && $resId > 0) {
    $res = R::load('resource', $resId);
    if ($res && $res->id) {
      $res->status = 'rejected';
      $res->rejection_reason = trim($_POST['rejection_reason'] ?? 'Did not meet curriculum criteria');
      $res->updated_at = date('Y-m-d H:i:s');
      R::store($res);

      if (function_exists('log_admin_audit')) {
        log_admin_audit('elibrary', 'RESOURCE_REJECTED', $res->uploader_type, $res->uploader_id, '', "Rejected resource #{$res->id} ({$res->title}). Reason: {$res->rejection_reason}");
      }

      $actionSuccess = "Resource #{$res->id} rejected. Author will see the rejection reason.";
    }
  }

  // 3. EDIT RESOURCE METADATA INLINE
  elseif ($action === 'edit_resource' && $resId > 0) {
    $res = R::load('resource', $resId);
    if ($res && $res->id) {
      $res->title = trim($_POST['title'] ?? $res->title);
      $res->curriculum_tier = trim($_POST['curriculum_tier'] ?? $res->curriculum_tier);
      $res->grade_level = trim($_POST['grade_level'] ?? $res->grade_level);
      $res->subject = trim($_POST['subject'] ?? $res->subject);
      $res->category = trim($_POST['category'] ?? $res->category);
      $res->price = max(0.00, (float) ($_POST['price'] ?? 0.00));
      $res->is_featured = isset($_POST['is_featured']) ? 1 : 0;
      $res->updated_at = date('Y-m-d H:i:s');
      R::store($res);

      $actionSuccess = "Resource #{$res->id} metadata updated successfully.";
    }
  }

  // 4. QUARANTINE / TOGGLE STATUS
  elseif ($action === 'quarantine_resource' && $resId > 0) {
    $res = R::load('resource', $resId);
    if ($res && $res->id) {
      $res->status = ($res->status === 'quarantined') ? 'approved' : 'quarantined';
      $res->updated_at = date('Y-m-d H:i:s');
      R::store($res);

      $actionSuccess = "Resource #{$res->id} status set to {$res->status}.";
    }
  }

  // 5. DELETE RESOURCE
  elseif ($action === 'delete_resource' && $resId > 0) {
    $res = R::load('resource', $resId);
    if ($res && $res->id) {
      R::trash($res);
      $actionSuccess = "Resource permanently deleted.";
    }
  }

  // 6. RESOLVE COPYRIGHT CLAIM
  elseif ($action === 'resolve_claim') {
    $claimId = (int) ($_POST['claim_id'] ?? 0);
    $claimStatus = trim($_POST['claim_status'] ?? 'resolved');
    $claim = R::load('resource_copyright_claim', $claimId);
    if ($claim && $claim->id) {
      $claim->status = $claimStatus;
      $claim->admin_notes = trim($_POST['admin_notes'] ?? '');
      R::store($claim);

      // If resolving claim by taking down resource
      if ($claimStatus === 'quarantined') {
        $res = R::load('resource', $claim->resource_id);
        if ($res && $res->id) {
          $res->status = 'quarantined';
          R::store($res);
        }
      }
      $actionSuccess = "Copyright Claim #{$claim->id} marked as {$claimStatus}.";
    }
  }
}

// -------------------------------------------------------------
// Data Queries
// -------------------------------------------------------------
$pendingCount = R::count('resource', ' status = ? ', ['pending_review']);
$approvedCount = R::count('resource', ' status = ? ', ['approved']);
$claimsCount = R::count('resource_copyright_claim', ' status = ? ', ['pending']);

$pendingResources = R::findAll('resource', ' status = ? ORDER BY created_at ASC ', ['pending_review']);
$approvedResources = R::findAll('resource', ' status = ? ORDER BY created_at DESC LIMIT 50 ', ['approved']);
$claims = R::findAll('resource_copyright_claim', ' ORDER BY created_at DESC LIMIT 50 ');
?>

<style>
  .admin-elib-wrap {
    max-width: 1300px;
    margin: 0 auto;
    padding: 1.5rem 1rem 4rem 1rem;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    color: #1e293b;
  }

  .elib-tab-btn {
    padding: 10px 18px;
    border-radius: 8px;
    font-weight: 700;
    font-size: 0.88rem;
    text-decoration: none;
    color: #475569;
    background: #f1f5f9;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.2s;
  }

  .elib-tab-btn.active {
    background: #0f766e;
    color: #ffffff !important;
  }

  .elib-preview-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 999999;
    padding: 1rem;
  }

  .elib-preview-modal-overlay.active {
    display: flex;
  }

  .elib-preview-modal {
    background: #ffffff;
    border-radius: 16px;
    width: 100%;
    max-width: 900px;
    height: 85vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
  }

  .admin-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    margin-bottom: 2rem;
  }
</style>

<div class="workspace-wrapper">
  <?php include __DIR__ . '/partials/sidebar.php'; ?>

  <div class="workspace-content">
    <div class="admin-elib-wrap">

      <!-- Header -->
      <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
        <div>
          <div style="font-size: 0.82rem; color: #64748b; margin-bottom: 0.25rem;">
            <a href="/admin/dashboard" style="color: #64748b; text-decoration: none;"><i class="fa fa-tachometer-alt"></i> Admin Dashboard</a>
            <span>/</span>
            <span style="color: #0f766e; font-weight: 700;">E-Library Management</span>
          </div>
          <h1 style="font-size: 1.65rem; font-weight: 900; color: #0f172a; margin: 0;">
            E-Library Moderation & Resources
          </h1>
        </div>

        <div>
          <a href="/elibrary" target="_blank" class="elib-tab-btn" style="background: #0f766e; color: white !important;">
            <i class="fa fa-arrow-up-right-from-square"></i> Open Public E-Library
          </a>
        </div>
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

      <!-- Tab Navigation -->
      <div style="display: flex; gap: 10px; margin-bottom: 1.5rem; flex-wrap: wrap;">
        <a href="/admin/elibrary?tab=pending" class="elib-tab-btn <?= ($activeTab === 'pending') ? 'active' : '' ?>">
          <i class="fa fa-clock"></i> Moderation Queue
          <span style="background: #b45309; color: white; padding: 1px 7px; border-radius: 10px; font-size: 0.72rem; font-weight: 800;"><?= $pendingCount ?></span>
        </a>

        <a href="/admin/elibrary?tab=catalog" class="elib-tab-btn <?= ($activeTab === 'catalog') ? 'active' : '' ?>">
          <i class="fa fa-book"></i> Live Catalog
          <span style="background: #0f766e; color: white; padding: 1px 7px; border-radius: 10px; font-size: 0.72rem; font-weight: 800;"><?= $approvedCount ?></span>
        </a>

        <a href="/admin/elibrary?tab=claims" class="elib-tab-btn <?= ($activeTab === 'claims') ? 'active' : '' ?>">
          <i class="fa fa-shield-halved"></i> Copyright Claims
          <?php if ($claimsCount > 0): ?>
            <span style="background: #dc2626; color: white; padding: 1px 7px; border-radius: 10px; font-size: 0.72rem; font-weight: 800;"><?= $claimsCount ?></span>
          <?php endif; ?>
        </a>
      </div>

      <!-- TAB 1: PENDING MODERATION QUEUE -->
      <?php if ($activeTab === 'pending'): ?>
        <div class="admin-card">
          <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.25rem;">
            <div>
              <h2 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 4px 0;">
                Submissions Awaiting Approval (<?= $pendingCount ?>)
              </h2>
              <div style="font-size: 0.8rem; color: #64748b;">
                Click "Preview" to inspect document contents. Approving automatically awards 3 Free Download Unlocks to the author.
              </div>
            </div>
          </div>

          <?php if (empty($pendingResources)): ?>
            <div style="text-align: center; padding: 3rem 1rem; color: #64748b;">
              <i class="fa fa-circle-check" style="font-size: 2.5rem; color: #10b981; margin-bottom: 0.5rem;"></i>
              <p style="font-weight: 700; color: #0f172a; margin: 0 0 4px 0;">All caught up!</p>
              <p style="margin: 0; font-size: 0.85rem;">No pending resource submissions in the moderation queue.</p>
            </div>
          <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
              <?php foreach ($pendingResources as $res): ?>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; display: flex; flex-direction: column; gap: 10px;">
                  
                  <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                    <div>
                      <div style="display: flex; gap: 6px; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 0.72rem; font-weight: 800; background: #e0f2fe; color: #0369a1; padding: 2px 7px; border-radius: 4px;">
                          <?= h($res->grade_level) ?> &bull; <?= h($res->subject) ?>
                        </span>
                        <span style="font-size: 0.72rem; font-weight: 700; background: #f1f5f9; color: #475569; padding: 2px 7px; border-radius: 4px;">
                          <?= h($res->category) ?>
                        </span>
                        <span style="font-size: 0.72rem; font-weight: 800; color: <?= ((float)$res->price <= 0) ? '#15803d' : '#0f766e' ?>;">
                          <?= ((float)$res->price <= 0) ? 'FREE' : 'KES ' . number_format($res->price, 0) ?>
                        </span>
                      </div>

                      <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin: 0 0 6px 0;">
                        <?= h($res->title) ?>
                      </h3>

                      <div style="font-size: 0.8rem; color: #64748b; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                        <span>
                          <strong>Uploader:</strong> <?= h($res->uploader_name) ?> (<?= ucfirst(h($res->uploader_type)) ?>)
                        </span>
                        <span>
                          <strong>Format:</strong> <?= strtoupper(h($res->file_type)) ?> (<?= format_elibrary_file_size($res->file_size_bytes) ?>)
                        </span>
                        <span>
                          <strong>Submitted:</strong> <?= date('M d, Y H:i', strtotime($res->created_at)) ?>
                        </span>
                      </div>
                    </div>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                      
                      <!-- In-Browser Document Preview Trigger -->
                      <button type="button" onclick="openAdminDocPreview('<?= addslashes(h($res->file_path)) ?>', '<?= addslashes(h($res->title)) ?>', '<?= addslashes(h($res->file_type)) ?>')" class="elib-tab-btn" style="background: #f8fafc; border: 1px solid #cbd5e1; padding: 8px 14px; font-size: 0.82rem;">
                        <i class="fa fa-eye" style="color: #0f766e;"></i> Preview Document
                      </button>

                      <!-- 1-Click Approve Form -->
                      <form action="/admin/elibrary" method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="approve_resource">
                        <input type="hidden" name="resource_id" value="<?= $res->id ?>">
                        <button type="submit" class="elib-tab-btn" style="background: #15803d; color: white !important; padding: 8px 16px; border: none; cursor: pointer; font-size: 0.82rem;">
                          <i class="fa fa-check"></i> Approve (+3 Unlocks)
                        </button>
                      </form>

                      <!-- Reject Button -->
                      <button type="button" onclick="promptReject(<?= $res->id ?>, '<?= addslashes(h($res->title)) ?>')" class="elib-tab-btn" style="background: #fee2e2; color: #dc2626 !important; border: 1px solid #fecaca; padding: 8px 14px; font-size: 0.82rem; cursor: pointer;">
                        <i class="fa fa-xmark"></i> Reject
                      </button>

                    </div>
                  </div>

                  <?php if (!empty($res->description)): ?>
                    <div style="font-size: 0.84rem; color: #475569; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border-left: 3px solid #cbd5e1;">
                      <?= nl2br(h($res->description)) ?>
                    </div>
                  <?php endif; ?>

                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- TAB 2: LIVE CATALOG MANAGEMENT -->
      <?php if ($activeTab === 'catalog'): ?>
        <div class="admin-card">
          <h2 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 1rem 0;">
            Live Educational Resources (<?= $approvedCount ?>)
          </h2>

          <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
              <thead>
                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; text-align: left;">
                  <th style="padding: 10px 12px; color: #475569;">Resource Details</th>
                  <th style="padding: 10px 12px; color: #475569;">Grade & Subject</th>
                  <th style="padding: 10px 12px; color: #475569;">Uploader</th>
                  <th style="padding: 10px 12px; color: #475569;">Price</th>
                  <th style="padding: 10px 12px; color: #475569;">Downloads</th>
                  <th style="padding: 10px 12px; color: #475569;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($approvedResources as $res): ?>
                  <tr style="border-bottom: 1px solid #f1f5f9;">
                    <td style="padding: 10px 12px; max-width: 300px;">
                      <a href="/elibrary/view?id=<?= $res->id ?>" target="_blank" style="font-weight: 700; color: #0f172a; text-decoration: none;">
                        <?= h($res->title) ?>
                      </a>
                    </td>
                    <td style="padding: 10px 12px; color: #64748b;">
                      <?= h($res->grade_level) ?> &bull; <?= h($res->subject) ?>
                    </td>
                    <td style="padding: 10px 12px; color: #475569;">
                      <?= h($res->uploader_name) ?> (<?= ucfirst(h($res->uploader_type)) ?>)
                    </td>
                    <td style="padding: 10px 12px; font-weight: 700; color: <?= ((float)$res->price <= 0) ? '#15803d' : '#0f766e' ?>;">
                      <?= ((float)$res->price <= 0) ? 'FREE' : 'KES ' . number_format($res->price, 0) ?>
                    </td>
                    <td style="padding: 10px 12px; color: #475569;">
                      <?= (int)$res->download_count ?>
                    </td>
                    <td style="padding: 10px 12px;">
                      <div style="display: flex; gap: 8px;">
                        <form action="/admin/elibrary" method="POST" onsubmit="return confirm('Quarantine this resource?');" style="margin: 0;">
                          <input type="hidden" name="action" value="quarantine_resource">
                          <input type="hidden" name="resource_id" value="<?= $res->id ?>">
                          <button type="submit" style="background: none; border: none; color: #b45309; cursor: pointer; font-size: 0.82rem;" title="Quarantine / Hide">
                            <i class="fa fa-pause"></i>
                          </button>
                        </form>

                        <form action="/admin/elibrary" method="POST" onsubmit="return confirm('Permanently delete this resource?');" style="margin: 0;">
                          <input type="hidden" name="action" value="delete_resource">
                          <input type="hidden" name="resource_id" value="<?= $res->id ?>">
                          <button type="submit" style="background: none; border: none; color: #dc2626; cursor: pointer; font-size: 0.82rem;" title="Delete">
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
        </div>
      <?php endif; ?>

      <!-- TAB 3: COPYRIGHT CLAIMS -->
      <?php if ($activeTab === 'claims'): ?>
        <div class="admin-card">
          <h2 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0 0 1rem 0;">
            Copyright Takedown Claims & Reports (<?= count($claims) ?>)
          </h2>

          <?php if (empty($claims)): ?>
            <div style="text-align: center; padding: 2.5rem 1rem; color: #64748b;">
              <i class="fa fa-shield-check" style="font-size: 2.5rem; color: #10b981; margin-bottom: 0.5rem;"></i>
              <p>No copyright infringement notices submitted.</p>
            </div>
          <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
              <?php foreach ($claims as $cl):
                $targetRes = R::load('resource', $cl->resource_id);
                ?>
                <div style="border: 1px solid #fee2e2; background: #fff5f5; border-radius: 12px; padding: 1.25rem;">
                  <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                    <div>
                      <span style="font-size: 0.75rem; font-weight: 800; background: #fee2e2; color: #dc2626; padding: 2px 8px; border-radius: 4px;">
                        Ticket #<?= $cl->id ?> &bull; Status: <?= strtoupper(h($cl->status)) ?>
                      </span>
                      <h3 style="font-size: 1rem; font-weight: 800; color: #0f172a; margin: 6px 0 2px 0;">
                        Claimant: <?= h($cl->claimant_name) ?> (<?= h($cl->claimant_org ?: 'Individual') ?>)
                      </h3>
                      <div style="font-size: 0.8rem; color: #64748b;">
                        Email: <strong><?= h($cl->claimant_email) ?></strong> &bull; Received: <?= date('M d, Y H:i', strtotime($cl->created_at)) ?>
                      </div>
                    </div>

                    <div style="display: flex; gap: 8px;">
                      <!-- 1-Click Quarantine Resource -->
                      <form action="/admin/elibrary" method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="resolve_claim">
                        <input type="hidden" name="claim_id" value="<?= $cl->id ?>">
                        <input type="hidden" name="claim_status" value="quarantined">
                        <button type="submit" class="elib-tab-btn" style="background: #dc2626; color: white !important; font-size: 0.78rem; padding: 6px 12px; border: none; cursor: pointer;">
                          <i class="fa fa-shield-halved"></i> Quarantine Resource
                        </button>
                      </form>

                      <!-- Dismiss Claim -->
                      <form action="/admin/elibrary" method="POST" style="margin: 0;">
                        <input type="hidden" name="action" value="resolve_claim">
                        <input type="hidden" name="claim_id" value="<?= $cl->id ?>">
                        <input type="hidden" name="claim_status" value="dismissed">
                        <button type="submit" class="elib-tab-btn" style="background: #f1f5f9; color: #475569; font-size: 0.78rem; padding: 6px 12px; border: 1px solid #cbd5e1; cursor: pointer;">
                          Dismiss
                        </button>
                      </form>
                    </div>
                  </div>

                  <div style="background: #ffffff; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 0.85rem; color: #334155; margin-top: 8px;">
                    <div style="font-weight: 700; margin-bottom: 4px;">Targeted Resource:</div>
                    <?php if ($targetRes && $targetRes->id): ?>
                      <a href="/elibrary/view?id=<?= $targetRes->id ?>" target="_blank" style="color: #0f766e; font-weight: 700;">
                        #<?= $targetRes->id ?>: <?= h($targetRes->title) ?> (Current Status: <?= h($targetRes->status) ?>)
                      </a>
                    <?php else: ?>
                      <span style="color: #94a3b8;">Resource #<?= $cl->resource_id ?> (Deleted)</span>
                    <?php endif; ?>

                    <div style="font-weight: 700; margin-top: 8px; margin-bottom: 2px;">Claim Details / Proof:</div>
                    <div style="color: #475569;"><?= nl2br(h($cl->claim_details)) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- In-Browser Document Preview Modal for Admin -->
<div id="adminDocPreviewOverlay" class="elib-preview-modal-overlay" onclick="closeAdminDocPreview()">
  <div class="elib-preview-modal" onclick="event.stopPropagation()">
    <div style="padding: 12px 18px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
      <div style="display: flex; align-items: center; gap: 8px;">
        <i class="fa fa-file-pdf" style="color: #0f766e; font-size: 1.2rem;"></i>
        <span id="adminDocPreviewTitle" style="font-weight: 800; font-size: 0.95rem; color: #0f172a;"></span>
      </div>
      <button type="button" onclick="closeAdminDocPreview()" style="background: none; border: none; font-size: 1.4rem; color: #64748b; cursor: pointer;">&times;</button>
    </div>
    <div style="flex: 1; background: #334155;">
      <iframe id="adminDocPreviewFrame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
    </div>
  </div>
</div>

<!-- Rejection Prompt Modal -->
<div id="adminRejectPromptOverlay" class="elib-preview-modal-overlay" onclick="closeRejectPrompt()">
  <div class="admin-card" onclick="event.stopPropagation()" style="width: 100%; max-width: 480px; margin: 0;">
    <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0 0 8px 0;">Reject Resource Submission</h3>
    <p id="rejectResourceTitle" style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;"></p>

    <form action="/admin/elibrary" method="POST">
      <input type="hidden" name="action" value="reject_resource">
      <input type="hidden" name="resource_id" id="rejectResourceId" value="">

      <div style="margin-bottom: 1rem;">
        <label style="font-size: 0.8rem; font-weight: 700; color: #334155; display: block; margin-bottom: 4px;">Reason for Rejection *</label>
        <select name="rejection_reason" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.85rem; margin-bottom: 8px;">
          <option value="Incomplete or unreadable document">Incomplete or unreadable document</option>
          <option value="Does not match indicated grade level or subject">Does not match indicated grade level or subject</option>
          <option value="Duplicate upload already in catalog">Duplicate upload already in catalog</option>
          <option value="Direct scan of commercial textbook">Direct scan of commercial textbook</option>
          <option value="Missing marking guide or solutions">Missing marking guide or solutions</option>
        </select>
      </div>

      <div style="display: flex; gap: 8px; justify-content: flex-end;">
        <button type="button" onclick="closeRejectPrompt()" class="elib-tab-btn" style="border: 1px solid #cbd5e1;">Cancel</button>
        <button type="submit" class="elib-tab-btn" style="background: #dc2626; color: white !important; border: none; cursor: pointer;">Confirm Rejection</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openAdminDocPreview(filePath, title, fileType) {
    document.getElementById('adminDocPreviewTitle').textContent = title;
    document.getElementById('adminDocPreviewFrame').src = filePath + '#toolbar=0';
    document.getElementById('adminDocPreviewOverlay').classList.add('active');
  }

  function closeAdminDocPreview() {
    document.getElementById('adminDocPreviewOverlay').classList.remove('active');
    document.getElementById('adminDocPreviewFrame').src = '';
  }

  function promptReject(resId, title) {
    document.getElementById('rejectResourceId').value = resId;
    document.getElementById('rejectResourceTitle').textContent = title;
    document.getElementById('adminRejectPromptOverlay').classList.add('active');
  }

  function closeRejectPrompt() {
    document.getElementById('adminRejectPromptOverlay').classList.remove('active');
  }
</script>
