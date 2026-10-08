<?php
// views/elibrary_claim.php - Copyright Takedown Claim Handler

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header("Location: /elibrary");
  exit();
}

$resourceId = (int) ($_POST['resource_id'] ?? 0);
$claimantName = trim($_POST['claimant_name'] ?? '');
$claimantEmail = trim($_POST['claimant_email'] ?? '');
$claimantOrg = trim($_POST['claimant_org'] ?? '');
$claimDetails = trim($_POST['claim_details'] ?? '');

if ($resourceId <= 0 || empty($claimantName) || empty($claimantEmail) || empty($claimDetails)) {
  $_SESSION['flash_error'] = "Please fill in all required fields for the copyright claim.";
  header("Location: /elibrary/view?id={$resourceId}");
  exit();
}

$resource = R::load('resource', $resourceId);
if (!$resource || !$resource->id) {
  header("Location: /elibrary");
  exit();
}

// Store copyright claim
$claim = R::dispense('resource_copyright_claim');
$claim->resource_id = $resourceId;
$claim->claimant_name = $claimantName;
$claim->claimant_email = $claimantEmail;
$claim->claimant_org = $claimantOrg;
$claim->claim_details = $claimDetails;
$claim->status = 'pending';
$claim->created_at = date('Y-m-d H:i:s');
R::store($claim);

// Increment resource flag counter
$resource->flag_count = (int) $resource->flag_count + 1;
R::store($resource);
?>

<div class="container" style="max-width: 620px; margin: 4rem auto; text-align: center;">
  <article style="background: white; padding: 2.5rem; border-radius: 16px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
    <div style="width: 56px; height: 56px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1.25rem auto;">
      <i class="fa fa-shield-halved"></i>
    </div>
    <h2 style="color: #0f172a; margin-bottom: 0.5rem; font-weight: 800;">Takedown Notice Received</h2>
    <p style="color: #475569; font-size: 0.95rem; line-height: 1.5; margin-bottom: 1.5rem;">
      Thank you, <strong><?= h($claimantName) ?></strong>. Your copyright infringement claim regarding <em>"<?= h($resource->title) ?>"</em> has been logged and submitted to the Mwalimu Compliance Panel (Ticket #<?= $claim->id ?>). We will review the submission and act promptly in accordance with the Kenya Copyright Act (Section 35B).
    </p>
    <a href="/elibrary" class="primary" style="display: inline-block; padding: 10px 24px; border-radius: 8px; font-weight: 700; text-decoration: none;">
      Return to E-Library
    </a>
  </article>
</div>
