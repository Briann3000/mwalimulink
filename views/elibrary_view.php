<?php
// views/elibrary_view.php - Single Resource Overview, Document Specs & Access Flow
if (session_status() === PHP_SESSION_NONE) {
  init_session();
}
$authUser = auth_user();
$userType = $authUser['role'] ?? null;
$userId = $authUser['user_id'] ?? null;
$userEmail = $authUser['email'] ?? '';

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
  header("Location: /elibrary");
  exit();
}

$resource = R::load('resource', $id);
if (!$resource || !$resource->id || ($resource->status !== 'approved' && (!$authUser || $authUser['role'] !== 'admin' && ((int) $resource->uploader_id !== (int) $userId || $resource->uploader_type !== $userType)))) {
  echo '<div class="container" style="max-width: 600px; margin: 3rem auto; text-align: center;">
        <article style="background: white; padding: 2rem; border-radius: 12px; border: 1px solid #e2e8f0;">
            <h2 style="color: #dc2626;">Resource Unavailable</h2>
            <p>This teaching material is currently undergoing moderation or has been removed.</p>
            <a href="/elibrary" class="primary">Back to E-Library</a>
        </article>
    </div>';
  return;
}

$resource->views_count = (int) $resource->views_count + 1;
R::store($resource);

$isFree = (float) $resource->price <= 0;
$hasUnlocked = user_has_unlocked_resource($resource->id, $userId, $userType, $userEmail);
$availableUnlocks = 0;
if ($authUser && in_array($userType, ['teacher', 'school'])) {
  $availableUnlocks = get_user_elibrary_credits($userType, $userId);
}

// Author privacy check
$isSchool = ($resource->uploader_type === 'school');
$authorLabel = 'Verified Educator';
if ($isSchool && !empty($resource->uploader_name) && !str_contains($resource->uploader_name, 'Admin')) {
  $authorLabel = $resource->uploader_name;
}

// Check if physical file exists on disk for iframe preview
$fullFilePath = __DIR__ . '/..' . $resource->file_path;
$hasPhysicalPdf = ($resource->file_type === 'pdf' && file_exists($fullFilePath));

// Related materials
$related = R::findAll('resource', ' id != ? AND (grade_level = ? OR subject = ?) AND status = ? ORDER BY views_count DESC LIMIT 4 ', [
  $resource->id,
  $resource->grade_level,
  $resource->subject,
  'approved'
]);

$intasendPubKey = env('INTASEND_PUBLIC_KEY', 'ISPubKey_live_sample');
$isTestMode = env('INTASEND_TEST_MODE', 'true') === 'true';
?>

<style>
  .mwalimu-elibrary-detail {
    max-width: 1200px;
    margin: 0 auto;
    padding: 1.5rem 1rem 4rem 1rem;
    color: #1e293b;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  }

  .elib-detail-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 2rem;
  }

  @media (min-width: 900px) {
    .elib-detail-grid {
      grid-template-columns: 1fr 360px;
    }
  }

  .elib-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.75rem;
    box-shadow: 0 4px 12px -2px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.5rem;
  }

  .elib-action-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08);
    position: sticky;
    top: 90px;
  }

  .res-btn-lg {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 12px 18px;
    border-radius: 10px;
    font-weight: 800;
    font-size: 0.95rem;
    text-decoration: none;
    cursor: pointer;
    border: none;
    box-sizing: border-box;
    transition: all 0.2s;
    color: #ffffff !important;
  }

  .res-btn-primary {
    background: #0f766e;
  }

  .res-btn-primary:hover {
    background: #0d9488;
  }

  .res-btn-success {
    background: #15803d;
  }

  .res-btn-success:hover {
    background: #166534;
  }

  .res-specs-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem;
    margin-bottom: 1.5rem;
  }

  .res-spec-item {
    font-size: 0.8rem;
  }

  .res-spec-label {
    color: #64748b;
    font-weight: 600;
    margin-bottom: 2px;
  }

  .res-spec-value {
    color: #0f172a;
    font-weight: 800;
  }

  .claim-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 99999;
    padding: 1rem;
  }

  .claim-modal-overlay.active {
    display: flex;
  }

  .claim-modal {
    background: #ffffff;
    border-radius: 16px;
    width: 100%;
    max-width: 520px;
    padding: 1.75rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
  }
</style>

<div class="mwalimu-elibrary-detail">

  <!-- Breadcrumb Navigation -->
  <div
    style="font-size: 0.82rem; color: #64748b; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 6px;">
    <a href="/" style="color: #64748b; text-decoration: none;"><i class="fa fa-home"></i> Home</a>
    <span>/</span>
    <a href="/elibrary" style="color: #64748b; text-decoration: none;">E-Library</a>
    <span>/</span>
    <span style="color: #0f766e; font-weight: 700;"><?= h($resource->grade_level) ?>
      <?= h($resource->category) ?></span>
  </div>

  <div class="elib-detail-grid">

    <!-- Left Column: Details & Document Specs -->
    <div>
      <div class="elib-card">
        <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 0.85rem;">
          <span
            style="background: #e0f2fe; color: #0369a1; font-weight: 800; font-size: 0.78rem; padding: 3px 10px; border-radius: 6px; border: 1px solid #bae6fd;">
            <?= h($resource->curriculum_tier ?: 'CBC') ?> &bull; <?= h($resource->grade_level) ?>
          </span>
          <span
            style="background: #f1f5f9; color: #475569; font-weight: 700; font-size: 0.78rem; padding: 3px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <i class="fa fa-book-open"></i> <?= h($resource->subject) ?>
          </span>
          <span
            style="background: #f1f5f9; color: #475569; font-weight: 700; font-size: 0.78rem; padding: 3px 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
            <i class="fa fa-calendar-alt"></i> <?= h($resource->term) ?> <?= h($resource->academic_year ?: date('Y')) ?>
          </span>
        </div>

        <h1 style="font-size: 1.55rem; font-weight: 900; color: #0f172a; line-height: 1.35; margin-bottom: 1rem;">
          <?= h($resource->title) ?>
        </h1>

        <!-- Contributor Attribution (Privacy Protected) -->
        <div
          style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px 14px; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
          <div style="display: flex; align-items: center; gap: 10px;">
            <?php if ($isSchool): ?>
              <div
                style="width: 36px; height: 36px; border-radius: 8px; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                <i class="fa fa-school"></i>
              </div>
              <div>
                <div style="font-size: 0.86rem; font-weight: 800; color: #0f172a;"><?= h($authorLabel) ?></div>
                <div style="font-size: 0.75rem; color: #64748b;">Registered School Partner</div>
              </div>
            <?php else: ?>
              <div
                style="width: 36px; height: 36px; border-radius: 8px; background: #dcfce7; color: #15803d; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                <i class="fa fa-user-graduate"></i>
              </div>
              <div>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <span style="font-size: 0.86rem; font-weight: 800; color: #0f172a;">Verified Educator</span>
                  <span
                    style="font-size: 0.68rem; background: #dcfce7; color: #15803d; padding: 1px 6px; border-radius: 10px; font-weight: 800;">Verified
                    Contributor</span>
                </div>
                <div style="font-size: 0.75rem; color: #64748b;">Active Educator &bull; CBC Curriculum Contributor</div>
              </div>
            <?php endif; ?>
          </div>

          <div style="font-size: 0.8rem; color: #64748b; display: flex; gap: 14px;">
            <span><i class="fa fa-download" style="color: #0f766e;"></i> <?= (int) $resource->download_count ?>
              downloads</span>
            <span><i class="fa fa-eye" style="color: #64748b;"></i> <?= (int) $resource->views_count ?> views</span>
          </div>
        </div>

        <!-- Material Specifications Summary -->
        <div class="res-specs-grid">
          <div class="res-spec-item">
            <div class="res-spec-label">Format</div>
            <div class="res-spec-value"><?= strtoupper(h($resource->file_type)) ?> Document</div>
          </div>
          <div class="res-spec-item">
            <div class="res-spec-label">Size</div>
            <div class="res-spec-value"><?= format_elibrary_file_size($resource->file_size_bytes) ?></div>
          </div>
          <div class="res-spec-item">
            <div class="res-spec-label">Curriculum</div>
            <div class="res-spec-value"><?= h($resource->curriculum_tier ?: 'CBC') ?></div>
          </div>
          <div class="res-spec-item">
            <div class="res-spec-label">Status</div>
            <div class="res-spec-value" style="color: #15803d;">Verified Active</div>
          </div>
        </div>

        <!-- Document Description -->
        <div style="margin-bottom: 1.5rem;">
          <h3 style="font-size: 1rem; font-weight: 800; color: #1e293b; margin-bottom: 0.5rem;">Overview & Contents</h3>
          <p style="color: #475569; font-size: 0.92rem; line-height: 1.6; margin: 0;">
            <?= nl2br(h($resource->description ?: "Comprehensive learning resource for {$resource->grade_level} {$resource->subject} aligned with the Kenyan Competency-Based Curriculum.")) ?>
          </p>
        </div>

        <?php if ($hasPhysicalPdf): ?>
          <!-- In-Browser Document Teaser Preview Frame (Prevents full document scrolling without download) -->
          <div style="margin-bottom: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
              <h3 style="font-size: 1rem; font-weight: 800; color: #1e293b; margin: 0;">
                <i class="fa fa-eye" style="color: #0f766e;"></i> Document Preview
              </h3>
              <span
                style="font-size: 0.75rem; background: #f1f5f9; color: #475569; padding: 2px 8px; border-radius: 6px; font-weight: 700;">
                First Page Teaser
              </span>
            </div>
            <div
              style="position: relative; border-radius: 12px; overflow: hidden; border: 1px solid #cbd5e1; background: #f8fafc; height: 420px;">
              <iframe src="<?= h($resource->file_path) ?>#page=1&toolbar=0&navpanes=0&scrollbar=0&view=FitH"
                style="width: 100%; height: 500px; border: none; pointer-events: none; user-select: none;"></iframe>
              <div
                style="position: absolute; bottom: 0; left: 0; right: 0; height: 130px; background: linear-gradient(to bottom, rgba(248,250,252,0) 0%, rgba(248,250,252,0.92) 55%, #f8fafc 100%); display: flex; flex-direction: column; align-items: center; justify-content: flex-end; padding-bottom: 18px; pointer-events: auto;">
                <div
                  style="background: #0f172a; color: #ffffff !important; font-size: 0.78rem; font-weight: 800; padding: 7px 16px; border-radius: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.18); display: flex; align-items: center; gap: 8px;">
                  <i class="fa fa-lock" style="color: #2dd4bf;"></i>
                  <span>Previewing Page 1 &bull; Download complete PDF below for all pages</span>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Report Copyright Link -->
        <div
          style="display: flex; justify-content: space-between; align-items: center; padding-top: 1rem; border-top: 1px solid #f1f5f9; font-size: 0.78rem; color: #94a3b8;">
          <span>Educational Fair Use &bull; Safe Harbor Protected</span>
          <a href="#" onclick="openClaimModal(event)" style="color: #dc2626; text-decoration: none; font-weight: 600;">
            <i class="fa fa-shield-halved"></i> Report Copyright Infringement
          </a>
        </div>
      </div>

      <!-- Related Materials -->
      <?php if (!empty($related)): ?>
        <div class="elib-card">
          <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem;">Related
            <?= h($resource->grade_level) ?> Materials</h3>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem;">
            <?php foreach ($related as $rel): ?>
              <a href="/elibrary/view?id=<?= $rel->id ?>"
                style="display: block; text-decoration: none; padding: 12px; border-radius: 10px; border: 1px solid #e2e8f0; background: #ffffff;">
                <div style="font-size: 0.72rem; color: #0369a1; font-weight: 800; margin-bottom: 4px;">
                  <?= h($rel->grade_level) ?> &bull; <?= h($rel->subject) ?></div>
                <div
                  style="font-size: 0.85rem; font-weight: 700; color: #0f172a; line-height: 1.3; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                  <?= h($rel->title) ?>
                </div>
                <div style="font-size: 0.75rem; color: #15803d; font-weight: 800;">
                  <?= ((float) $rel->price <= 0) ? 'FREE' : 'KES ' . number_format($rel->price, 0) ?>
                </div>
              </a>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <!-- Right Column: Instant Download & Purchase Actions -->
    <div>
      <div class="elib-action-box" id="access">

        <div style="margin-bottom: 1.25rem;">
          <div style="font-size: 0.8rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Access Option
          </div>
          <?php if ($isFree): ?>
            <div style="font-size: 1.75rem; font-weight: 900; color: #15803d;">FREE DOWNLOAD</div>
            <div style="font-size: 0.8rem; color: #475569;">Community shared educational material</div>
          <?php else: ?>
            <div style="font-size: 1.75rem; font-weight: 900; color: #0f766e;">
              KES <?= number_format($resource->price, 0) ?>
            </div>
            <div style="font-size: 0.8rem; color: #475569;">Instant PDF document & email delivery</div>
          <?php endif; ?>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 1.5rem;">

          <?php if ($hasUnlocked || $isFree): ?>
            <?php if ($isFree): ?>
              <div
                style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 12px; margin-bottom: 4px;">
                <label style="font-size: 0.76rem; font-weight: 700; color: #166534; display: block; margin-bottom: 4px;">
                  <i class="fa fa-envelope"></i> Send a copy to email (Optional)
                </label>
                <input type="email" id="freeBuyerEmail" value="<?= h($userEmail) ?>" placeholder="e.g. yourname@gmail.com"
                  style="width: 100%; border: 1px solid #86efac; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; box-sizing: border-box; background: #ffffff;">
              </div>
              <button type="button" onclick="downloadFreeResource(<?= $resource->id ?>)" class="res-btn-lg res-btn-success"
                style="color: #ffffff !important;">
                <i class="fa fa-download"></i> Download Complete File
              </button>
            <?php else: ?>
              <a href="/elibrary/download?id=<?= $resource->id ?>" class="res-btn-lg res-btn-primary"
                style="color: #ffffff !important;">
                <i class="fa fa-download"></i> Download Complete File
              </a>
            <?php endif; ?>

            <div style="text-align: center; font-size: 0.75rem; color: #15803d; font-weight: 700; margin-top: 4px;">
              ✓ You have full unlocked access to this document.
            </div>

          <?php else: ?>

            <?php if ($availableUnlocks > 0): ?>
              <form action="/elibrary/download" method="POST">
                <input type="hidden" name="action" value="unlock_with_credit">
                <input type="hidden" name="resource_id" value="<?= $resource->id ?>">
                <button type="submit" class="res-btn-lg res-btn-success" style="color: #ffffff !important;">
                  <i class="fa fa-key"></i> Unlock with 1 Credit (Balance: <?= $availableUnlocks ?>)
                </button>
              </form>
              <div style="text-align: center; font-size: 0.75rem; color: #64748b;">
                Uses 1 of your <?= $availableUnlocks ?> download unlocks.
              </div>
              <div style="text-align: center; margin: 4px 0; color: #94a3b8; font-size: 0.8rem;">— OR —</div>
            <?php endif; ?>

            <!-- Email & Phone Box for Instant M-Pesa Purchase -->
            <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 8px;">
              <div>
                <label
                  style="font-size: 0.76rem; font-weight: 700; color: #334155; display: block; margin-bottom: 3px;">Email
                  Address for File Delivery *</label>
                <input type="email" id="directBuyerEmail" value="<?= h($userEmail) ?>" required
                  placeholder="e.g. educator@gmail.com"
                  style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; box-sizing: border-box;">
              </div>
              <div>
                <label
                  style="font-size: 0.76rem; font-weight: 700; color: #334155; display: block; margin-bottom: 3px;">M-Pesa
                  Phone Number</label>
                <input type="tel" id="directBuyerPhone" placeholder="e.g. 0712345678"
                  style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; box-sizing: border-box;">
              </div>
            </div>

            <button type="button"
              onclick="initiateMpesaPurchase(<?= $resource->id ?>, '<?= addslashes(h($resource->title)) ?>', <?= (float) $resource->price ?>)"
              class="res-btn-lg" style="background: #0f766e; color: #ffffff !important;">
              <i class="fa fa-mobile-screen-button"></i> Buy (KES <?= number_format($resource->price, 0) ?>)
            </button>

            <button type="button" onclick="addToCart(<?= $resource->id ?>)" class="res-btn-lg"
              style="background: #0f172a; color: #ffffff !important; border: 1px solid #334155; margin-top: 2px;">
              <i class="fa fa-cart-plus" style="color: #2dd4bf;"></i> Add to Cart
            </button>

          <?php endif; ?>

        </div>

        <!-- Share Actions -->
        <div style="padding-top: 1rem; border-top: 1px solid #f1f5f9;">
          <div style="font-size: 0.78rem; font-weight: 700; color: #475569; margin-bottom: 8px;">Share with Colleagues
          </div>
          <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
            <a href="https://api.whatsapp.com/send?text=<?= urlencode('Download ' . $resource->title . ' on MwalimuLink: ' . (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/elibrary/view?id=' . $resource->id) ?>"
              target="_blank" class="res-btn-lg"
              style="background: #25d366; font-size: 0.82rem; height: 38px; color: #ffffff !important;">
              <i class="fa-brands fa-whatsapp"></i> WhatsApp
            </a>
            <button type="button"
              onclick="navigator.clipboard.writeText(window.location.href); alert('Resource link copied!');"
              class="res-btn-lg"
              style="background: #0f766e; font-size: 0.82rem; height: 38px; color: #ffffff !important;">
              <i class="fa fa-copy"></i> Copy Link
            </button>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>

<!-- Copyright Infringement Takedown Modal -->
<div id="claimModalOverlay" class="claim-modal-overlay" onclick="closeClaimModal()">
  <div class="claim-modal" onclick="event.stopPropagation()">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0;">
        <i class="fa fa-shield-halved" style="color: #dc2626;"></i> Copyright Infringement Notice
      </h3>
      <button type="button" onclick="closeClaimModal()"
        style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #64748b;">&times;</button>
    </div>

    <form action="/elibrary/claim" method="POST" style="display: flex; flex-direction: column; gap: 10px;">
      <input type="hidden" name="resource_id" value="<?= $resource->id ?>">

      <div>
        <label style="font-size: 0.78rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Your
          Full Name *</label>
        <input type="text" name="claimant_name" required placeholder="e.g. John Doe / Legal Officer"
          style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; box-sizing: border-box;">
      </div>

      <div>
        <label
          style="font-size: 0.78rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Official
          Email Address *</label>
        <input type="email" name="claimant_email" required placeholder="e.g. legal@publisher.co.ke"
          style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; box-sizing: border-box;">
      </div>

      <div>
        <label
          style="font-size: 0.78rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Organization
          / Publishing Firm</label>
        <input type="text" name="claimant_org" placeholder="e.g. Oxford / Longhorn / Self"
          style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; box-sizing: border-box;">
      </div>

      <div>
        <label
          style="font-size: 0.78rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Infringement
          Details & Proof of Ownership *</label>
        <textarea name="claim_details" required rows="3"
          placeholder="Provide details of your original copyrighted work..."
          style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; box-sizing: border-box;"></textarea>
      </div>

      <button type="submit" class="res-btn-lg" style="background: #dc2626; margin-top: 6px;">
        Submit Takedown Notice
      </button>
    </form>
  </div>
</div>

<!-- IntaSend Payment Script -->
<script src="https://unpkg.com/intasend-inlinejs-sdk@3.0.4/build/intasend-inline.js"></script>
<script>
  function openClaimModal(e) {
    if (e) e.preventDefault();
    document.getElementById('claimModalOverlay').classList.add('active');
  }

  function closeClaimModal() {
    document.getElementById('claimModalOverlay').classList.remove('active');
  }

  function downloadFreeResource(id) {
    const emailInput = document.getElementById('freeBuyerEmail');
    const email = emailInput ? emailInput.value.trim() : '';
    let url = '/elibrary/download?id=' + id;
    if (email && email.includes('@')) {
      url += '&email=' + encodeURIComponent(email);
    }
    window.location.href = url;
  }

  function addToCart(id) {
    fetch('/api/elibrary?action=add_to_cart&id=' + id, { method: 'POST' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert('Added to cart! You can view your cart at /elibrary/cart');
        }
      });
  }

  function initiateMpesaPurchase(resourceId, title, amount) {
    const email = document.getElementById('directBuyerEmail').value.trim();
    if (!email || !email.includes('@')) {
      alert('Please enter a valid email address so the file can be delivered to your inbox.');
      document.getElementById('directBuyerEmail').focus();
      return;
    }

    const phone = document.getElementById('directBuyerPhone').value.trim();

    if (typeof window.IntaSend === 'undefined') {
      alert('Payment engine loading... Please try again in a few moments.');
      return;
    }

    new window.IntaSend({
      public_key: '<?= h($intasendPubKey) ?>',
      live: <?= $isTestMode ? 'false' : 'true' ?>
    })
      .on('COMPLETE', function (response) {
        const trackingId = response.tracking_id || response.signature || 'INTASEND_PAID';
        window.location.href = '/elibrary/download?id=' + resourceId + '&ref=' + encodeURIComponent(trackingId) + '&email=' + encodeURIComponent(email);
      })
      .on('FAILED', function (response) {
        alert('Payment was not completed. Please try again.');
      })
      .run({
        amount: amount,
        currency: 'KES',
        email: email,
        phone_number: phone,
        title: 'Mwalimu E-Library: ' + title,
        comment: 'Resource #' + resourceId
      });
  }
</script>