<?php
// views/public_school_detail.php - Modern Interactive Detail View
$authUser = auth_user();

$id = intval($_GET['id'] ?? 0);
$school = R::load('public_school', $id);

if (!$school->id) {
    echo '<div style="padding: 3rem; text-align: center;"><h2>School not found.</h2><a href="/schools/public" class="btn-primary">Back to Directory</a></div>';
    return;
}

// Check if this school has been claimed / verified on MwalimuLink
$claimedSchool = null;
if (!empty($school->email)) {
    $claimedSchool = R::findOne('school', 'school_email = ? OR email = ?', [$school->email, $school->email]);
}
if (!$claimedSchool && !empty($school->name)) {
    $claimedSchool = R::findOne('school', 'LOWER(school_name) = LOWER(?) OR LOWER(name) = LOWER(?)', [$school->name, $school->name]);
}
$isClaimed = !empty($claimedSchool);

$inquirySuccess = false;
$inquiryError = '';

// Handle On-Page Inquiry Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_inquiry') {
    $senderName = trim($_POST['name'] ?? '');
    $senderEmail = trim($_POST['email'] ?? '');
    $senderPhone = trim($_POST['phone'] ?? '');
    $senderMessage = trim($_POST['message'] ?? '');

    if (empty($senderName) || empty($senderEmail) || empty($senderMessage)) {
        $inquiryError = 'Please fill in all required fields (Name, Email, Message).';
    } elseif (!filter_var($senderEmail, FILTER_VALIDATE_EMAIL)) {
        $inquiryError = 'Please enter a valid email address.';
    } else {
        try {
            $inq = R::dispense('inquiry');
            $inq->school_id = $school->id;
            $inq->school_type = 'public';
            $inq->school_name = $school->name;
            $inq->sender_name = $senderName;
            $inq->sender_email = $senderEmail;
            $inq->sender_phone = $senderPhone;
            $inq->message = $senderMessage;
            $inq->created_at = date('Y-m-d H:i:s');
            R::store($inq);

            $adminEmail = env('MAIL_FROM_ADDRESS', 'info@mwalimulink.com');
            $emailSubject = "School Inquiry: " . $school->name . " (" . $school->county . ")";
            $emailBody = "
                <div style='font-family: sans-serif; font-size: 14px; color: #334155; line-height: 1.6;'>
                    <h3 style='color: #0f766e;'>New Public School Inquiry</h3>
                    <p><strong>Institution:</strong> " . h($school->name) . " (" . h($school->county) . " County)</p>
                    <p><strong>From:</strong> " . h($senderName) . " (&lt;" . h($senderEmail) . "&gt;)</p>
                    <p><strong>Phone:</strong> " . h($senderPhone ?: 'Not provided') . "</p>
                    <p><strong>Message:</strong></p>
                    <div style='background: #f8fafc; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px;'>
                        " . nl2br(h($senderMessage)) . "
                    </div>
                </div>
            ";
            send_system_email($adminEmail, 'MwalimuLink Support', $emailSubject, $emailBody, $senderEmail, $senderName);
            $inquirySuccess = true;
        } catch (\Exception $e) {
            $inquiryError = 'Unable to send inquiry at this moment. Please try again or contact us directly.';
        }
    }
}

// Compute official website URL or fallback search portal URL
$officialWebsiteUrl = !empty($school->website) ? $school->website : ('https://www.google.com/search?q=' . urlencode(trim(($school->name ?? '') . ' official website ' . ($school->county ?? ''))));
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="max-width: 1400px; width: 100%; margin: 0 auto; padding: 2rem 1.5rem; box-sizing: border-box;">
        
        <!-- Breadcrumb & Top Actions -->
        <div style="margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.84rem; color: #64748b;">
                <a href="/schools/public" style="color: #0f766e; text-decoration: none; font-weight: 600;">
                    <i class="fa fa-arrow-left"></i> Back to Public Schools
                </a>
                <span>/</span>
                <span style="color: #94a3b8;"><?= h($school->county) ?></span>
            </div>
            
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <!-- External Website Link -->
                <a href="<?= h($officialWebsiteUrl) ?>" target="_blank" rel="noopener noreferrer" style="margin: 0 !important; height: 36px !important; padding: 0 14px !important; background: #ffffff !important; color: #0f766e !important; font-size: 0.82rem !important; font-weight: 600 !important; border-radius: 6px !important; text-decoration: none !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; border: 1px solid #cbd5e1 !important; box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important; box-sizing: border-box !important; line-height: 1 !important;">
                    <i class="fa fa-external-link-alt" style="font-size: 0.75rem;"></i> Visit Official Website ↗
                </a>

                <!-- TP Letter Generator Trigger -->
                <button type="button" onclick="openTpModal()" style="margin: 0 !important; height: 36px !important; padding: 0 14px !important; background: #f1f5f9 !important; color: #0f766e !important; font-size: 0.82rem !important; font-weight: 600 !important; border-radius: 6px !important; border: 1px solid #cbd5e1 !important; cursor: pointer !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; box-sizing: border-box !important; line-height: 1 !important;">
                    <i class="fa fa-file-alt"></i> Generate TP Letter
                </button>

                <!-- On-Page Inquiry Trigger -->
                <button type="button" onclick="openInquiryModal()" style="margin: 0 !important; height: 36px !important; padding: 0 16px !important; background: #0f766e !important; color: #ffffff !important; font-size: 0.82rem !important; font-weight: 600 !important; border-radius: 6px !important; border: 1px solid #0f766e !important; cursor: pointer !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; box-shadow: 0 1px 2px rgba(15,118,110,0.2) !important; box-sizing: border-box !important; line-height: 1 !important;">
                    <i class="fa fa-envelope"></i> Inquire via MwalimuLink
                </button>
            </div>
        </div>

        <?php if ($inquirySuccess): ?>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px; font-size: 0.9rem;">
                <i class="fa fa-check-circle" style="font-size: 1.1rem; color: #22c55e;"></i>
                <div>
                    <strong>Inquiry Submitted Successfully!</strong> Your inquiry regarding <em><?= h($school->name) ?></em> has been logged and forwarded to our team.
                </div>
            </div>
        <?php elseif (!empty($inquiryError)): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px; font-size: 0.9rem;">
                <i class="fa fa-exclamation-circle" style="font-size: 1.1rem; color: #ef4444;"></i>
                <div><?= h($inquiryError) ?></div>
            </div>
        <?php endif; ?>

        <!-- School Header Card -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem 1.75rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="margin: 0 0 6px; font-size: 1.5rem; font-weight: 800; color: #0f172a; letter-spacing: -0.01em;">
                        <?= h($school->name) ?>
                    </h2>
                    <p style="margin: 0; font-size: 0.9rem; color: #64748b; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i>
                        <span><?= h($school->county) ?> &bull; <?= h($school->constituency ?? $school->district ?? 'Kenya') ?></span>
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span style="background: #e0f2fe; color: #0369a1; font-size: 0.78rem; font-weight: 700; padding: 5px 12px; border-radius: 6px; border: 1px solid #bae6fd;">
                        <?= h($school->level ?: 'Public School') ?>
                    </span>
                    <?php if ($isClaimed): ?>
                        <span style="background: #dcfce7; color: #15803d; font-size: 0.78rem; font-weight: 700; padding: 5px 12px; border-radius: 6px; border: 1px solid #bbf7d0; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fa fa-check-circle"></i> Verified Institution
                        </span>
                    <?php else: ?>
                        <span style="background: #f8fafc; color: #475569; font-size: 0.78rem; font-weight: 700; padding: 5px 12px; border-radius: 6px; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 5px;">
                            <i class="fa fa-folder-open" style="color: #64748b;"></i> General Directory Listing
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!$isClaimed): ?>
            <!-- Claim School Profile Banner -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;">
                <div style="max-width: 700px;">
                    <h4 style="margin: 0 0 4px; font-size: 0.95rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-id-badge" style="color: #0f766e;"></i> Are you a School Administrator or Headteacher?
                    </h4>
                    <p style="margin: 0; font-size: 0.84rem; color: #64748b; line-height: 1.4;">
                        Claim this official profile to manage Teaching Practice (TP) student intakes, set departmental quotas, and publish BOM teacher vacancies directly to verified educators.
                    </p>
                </div>
                <a href="/register/school?claim_school=<?= urlencode($school->name) ?>" style="background: #0f766e; color: white !important; font-size: 0.82rem; font-weight: 600; padding: 8px 16px; border-radius: 6px; text-decoration: none; white-space: nowrap; display: inline-flex; align-items: center; gap: 6px;">
                    Claim School Profile &rarr;
                </a>
            </div>
        <?php endif; ?>

        <!-- Info Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            
            <!-- School Specifications Table Card -->
            <div class="mwalimu-table-card">
                <div style="padding: 1rem 1.25rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; font-size: 0.9rem; color: #0f172a;">
                    <i class="fa fa-info-circle" style="color: #0f766e; margin-right: 6px;"></i> Administrative Details
                </div>
                <table class="mwalimu-table">
                    <tbody>
                        <tr>
                            <th style="width: 35%; background: white !important; color: #64748b !important;">Institution Level</th>
                            <td style="font-weight: 600; color: #0f172a !important;"><?= h($school->level ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Status</th>
                            <td><span style="background: #dcfce7; color: #15803d; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;"><?= h($school->status ?: 'PUBLIC') ?></span></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">County</th>
                            <td style="font-weight: 600; color: #0f172a !important;"><?= h($school->county ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Constituency</th>
                            <td><?= h($school->constituency ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">District / Sub-County</th>
                            <td><?= h($school->district ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Division / Zone</th>
                            <td><?= h($school->division ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Location / Ward</th>
                            <td><?= h($school->location ?: 'N/A') ?></td>
                        </tr>
                        <?php if (!empty($school->email)): ?>
                            <tr>
                                <th style="background: white !important; color: #64748b !important;">Email Address</th>
                                <td><a href="mailto:<?= h($school->email) ?>" style="color: #0f766e;"><?= h($school->email) ?></a></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (!empty($school->website)): ?>
                            <tr>
                                <th style="background: white !important; color: #64748b !important;">Official Website</th>
                                <td><a href="<?= h($school->website) ?>" target="_blank" rel="noopener noreferrer" style="color: #0f766e; font-weight: 600;"><?= h($school->website) ?> ↗</a></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Geographic Location Card & Map -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; flex-direction: column;">
                <div style="font-weight: 700; font-size: 0.9rem; color: #0f172a; margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: space-between;">
                    <span><i class="fa fa-map-location-dot" style="color: #0f766e; margin-right: 6px;"></i> Geographic Location</span>
                    <?php if (!empty($school->latitude) && !empty($school->longitude)): ?>
                        <span style="font-size: 0.75rem; color: #64748b; font-weight: normal;">
                            <?= number_format($school->latitude, 4) ?>, <?= number_format($school->longitude, 4) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($school->latitude) && !empty($school->longitude) && floatval($school->latitude) != 0): ?>
                    <div id="map" style="height: 280px; width: 100%; border-radius: 8px; border: 1px solid #cbd5e1; flex: 1;"></div>
                    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            var lat = <?= floatval($school->latitude) ?>;
                            var lon = <?= floatval($school->longitude) ?>;
                            var map = L.map('map').setView([lat, lon], 14);
                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                maxZoom: 19,
                                attribution: '&copy; OpenStreetMap'
                            }).addTo(map);
                            L.marker([lat, lon]).addTo(map)
                                .bindPopup("<b><?= htmlspecialchars(addslashes($school->name)) ?></b><br><?= htmlspecialchars(addslashes($school->county)) ?>")
                                .openPopup();
                        });
                    </script>
                <?php else: ?>
                    <div style="height: 200px; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #94a3b8; font-size: 0.85rem;">
                        <i class="fa fa-map-pin" style="font-size: 1.5rem; margin-bottom: 6px; opacity: 0.5;"></i>
                        <span>GPS Coordinates not indexed for this location</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>
</div>

<!-- On-Page Inquiry Modal -->
<div id="inquiryModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2000; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(2px);">
    <div style="background: white; width: 100%; max-width: 520px; border-radius: 10px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); overflow: hidden; border: 1px solid #e2e8f0;">
        <div style="padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a;">Inquire via MwalimuLink</h3>
                <p style="margin: 2px 0 0; font-size: 0.78rem; color: #64748b;">Regarding: <strong><?= h($school->name) ?></strong></p>
            </div>
            <button type="button" onclick="closeInquiryModal()" style="background: transparent; border: none; font-size: 1.25rem; color: #94a3b8; cursor: pointer; padding: 4px 8px;">&times;</button>
        </div>

        <form method="POST" action="" style="padding: 1.5rem; margin: 0;">
            <input type="hidden" name="action" value="submit_inquiry">
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Your Full Name *</label>
                <input type="text" name="name" required value="<?= h($authUser['name'] ?? '') ?>" placeholder="e.g. Jane Wanjiku" style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Email Address *</label>
                    <input type="email" name="email" required value="<?= h($authUser['email'] ?? '') ?>" placeholder="name@example.com" style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                </div>
                <div>
                    <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Phone Number</label>
                    <input type="tel" name="phone" placeholder="e.g. 0712345678" style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                </div>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Your Inquiry / Message *</label>
                <textarea name="message" rows="4" required placeholder="Describe your question, placement request, or subject specialization..." style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; font-family: inherit; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; align-items: center;">
                <button type="button" onclick="closeInquiryModal()" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">Cancel</button>
                <button type="submit" style="background: #0f766e; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-paper-plane"></i> Send Inquiry
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Teaching Practice (TP) Application Letter Generator Modal -->
<div id="tpLetterModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2000; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(2px);">
    <div style="background: white; width: 100%; max-width: 680px; max-height: 90vh; border-radius: 10px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); overflow-y: auto; border: 1px solid #e2e8f0;">
        <div style="padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a;">Official TP Application Letter Generator</h3>
                <p style="margin: 2px 0 0; font-size: 0.78rem; color: #64748b;">Recipient: <strong>The Principal / Head of Institution, <?= h($school->name) ?></strong></p>
            </div>
            <button type="button" onclick="closeTpModal()" style="background: transparent; border: none; font-size: 1.25rem; color: #94a3b8; cursor: pointer; padding: 4px 8px;">&times;</button>
        </div>

        <div style="padding: 1.5rem;">
            <!-- Form to fill letter details -->
            <div id="tpFormFields" style="display: block;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Student Teacher Name *</label>
                        <input type="text" id="tpStudentName" value="<?= h($authUser['name'] ?? '') ?>" placeholder="e.g. John Kamau" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">University / College *</label>
                        <input type="text" id="tpCollege" placeholder="e.g. Kenyatta University" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Student Registration No.</label>
                        <input type="text" id="tpRegNo" placeholder="e.g. E37/1234/2023" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Subject Combinations *</label>
                        <input type="text" id="tpSubjects" placeholder="e.g. Mathematics & Physics" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 1.25rem;">
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Proposed TP Period / Term</label>
                        <input type="text" id="tpPeriod" placeholder="e.g. Term 2 (May - August 2026)" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Contact Phone / Email</label>
                        <input type="text" id="tpContact" value="<?= h($authUser['email'] ?? '') ?>" placeholder="Phone or Email" style="width: 100%; box-sizing: border-box; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" onclick="generateTpPreview()" style="background: #0f766e; color: white; border: none; padding: 9px 20px; border-radius: 6px; font-size: 0.88rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-eye"></i> Generate Letter Preview &rarr;
                    </button>
                </div>
            </div>

            <!-- Printable Letter Preview Block -->
            <div id="tpLetterPreview" style="display: none;">
                <div id="printableArea" style="background: #ffffff; border: 1px solid #cbd5e1; padding: 2rem; border-radius: 6px; font-family: serif; color: #0f172a; line-height: 1.6; font-size: 0.95rem; margin-bottom: 1.25rem;">
                    <div style="text-align: right; margin-bottom: 1.5rem;">
                        <p style="margin: 0; font-weight: bold;" id="prevStudentName">Student Name</p>
                        <p style="margin: 0;" id="prevCollege">College/University</p>
                        <p style="margin: 0;" id="prevRegNo">Reg No</p>
                        <p style="margin: 0;" id="prevContact">Contact</p>
                        <p style="margin: 0; margin-top: 4px;"><?= date('F j, Y') ?></p>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <p style="margin: 0; font-weight: bold;">The Principal / Head of Institution,</p>
                        <p style="margin: 0; font-weight: bold;"><?= h($school->name) ?></p>
                        <p style="margin: 0;"><?= h($school->county) ?> County, Kenya</p>
                    </div>

                    <p style="font-weight: bold; text-decoration: underline; margin-bottom: 1rem;">
                        RE: APPLICATION FOR TEACHING PRACTICE (TP) PLACEMENT - <span id="prevSubjects">SUBJECTS</span>
                    </p>

                    <p>Dear Sir/Madam,</p>

                    <p>
                        I am writing to respectfully request a Teaching Practice (TP) placement at <strong><?= h($school->name) ?></strong> for the upcoming period of <span id="prevPeriod" style="font-weight: bold;">Term Placement</span>.
                    </p>

                    <p>
                        I am currently pursuing my education degree/diploma at <span id="prevCollegeBody" style="font-weight: bold;">Institution</span> specializing in <span id="prevSubjectsBody" style="font-weight: bold;">teaching subjects</span>. I am eager to contribute positively to your academic curriculum, student mentorship, and co-curricular programs under your esteemed faculty's guidance.
                    </p>

                    <p>
                        Enclosed please find my introductory credentials from my university. I would be grateful for the opportunity to fulfill my practicum at your school.
                    </p>

                    <div style="margin-top: 2rem;">
                        <p style="margin: 0;">Yours faithfully,</p>
                        <p style="margin: 2rem 0 0; font-weight: bold;" id="prevSignName">Student Name</p>
                        <p style="margin: 0; font-size: 0.85rem; color: #64748b;">Applicant</p>
                    </div>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <button type="button" onclick="editTpLetter()" style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                        &larr; Edit Details
                    </button>
                    <button type="button" onclick="printTpLetter()" style="background: #0f766e; color: white; border: none; padding: 8px 20px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-print"></i> Print / Save as PDF
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openInquiryModal() {
    var m = document.getElementById('inquiryModal');
    if (m) m.style.display = 'flex';
}
function closeInquiryModal() {
    var m = document.getElementById('inquiryModal');
    if (m) m.style.display = 'none';
}

function openTpModal() {
    var m = document.getElementById('tpLetterModal');
    if (m) m.style.display = 'flex';
}
function closeTpModal() {
    var m = document.getElementById('tpLetterModal');
    if (m) m.style.display = 'none';
}

function generateTpPreview() {
    var sName = document.getElementById('tpStudentName').value.trim() || 'Student Teacher';
    var college = document.getElementById('tpCollege').value.trim() || 'Teacher Training University';
    var reg = document.getElementById('tpRegNo').value.trim() || 'N/A';
    var subjects = document.getElementById('tpSubjects').value.trim() || 'General Subjects';
    var period = document.getElementById('tpPeriod').value.trim() || 'Upcoming Term';
    var contact = document.getElementById('tpContact').value.trim() || '';

    document.getElementById('prevStudentName').innerText = sName;
    document.getElementById('prevCollege').innerText = college;
    document.getElementById('prevCollegeBody').innerText = college;
    document.getElementById('prevRegNo').innerText = 'Reg No: ' + reg;
    document.getElementById('prevContact').innerText = contact;
    document.getElementById('prevSubjects').innerText = subjects.toUpperCase();
    document.getElementById('prevSubjectsBody').innerText = subjects;
    document.getElementById('prevPeriod').innerText = period;
    document.getElementById('prevSignName').innerText = sName;

    document.getElementById('tpFormFields').style.display = 'none';
    document.getElementById('tpLetterPreview').style.display = 'block';
}

function editTpLetter() {
    document.getElementById('tpFormFields').style.display = 'block';
    document.getElementById('tpLetterPreview').style.display = 'none';
}

function printTpLetter() {
    var printContent = document.getElementById('printableArea').innerHTML;
    var originalContent = document.body.innerHTML;
    var printWin = window.open('', '', 'height=650,width=850');
    printWin.document.write('<html><head><title>TP Application Letter - <?= htmlspecialchars(addslashes($school->name)) ?></title>');
    printWin.document.write('<style>body{font-family:serif;padding:30px;line-height:1.6;font-size:14pt;color:#000;} p{margin-bottom:12px;}</style>');
    printWin.document.write('</head><body>');
    printWin.document.write(printContent);
    printWin.document.write('</body></html>');
    printWin.document.close();
    printWin.focus();
    setTimeout(function() {
        printWin.print();
        printWin.close();
    }, 300);
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeInquiryModal();
        closeTpModal();
    }
});
</script>

