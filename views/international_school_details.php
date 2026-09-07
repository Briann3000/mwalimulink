<?php
// views/international_school_details.php - Transparent Discovery & Interactive Inquiry
$authUser = auth_user();

$id = intval($_GET['id'] ?? 0);
$rec = R::load('international_school', $id);

if (!$rec->id) {
    echo '<div style="padding: 3rem; text-align: center;"><h2>International school not found.</h2><a href="/schools/international" class="btn-primary">Back to Directory</a></div>';
    return;
}

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
            $inq->school_id = $rec->id;
            $inq->school_type = 'international';
            $inq->school_name = $rec->name;
            $inq->sender_name = $senderName;
            $inq->sender_email = $senderEmail;
            $inq->sender_phone = $senderPhone;
            $inq->message = $senderMessage;
            $inq->created_at = date('Y-m-d H:i:s');
            R::store($inq);

            $adminEmail = env('MAIL_FROM_ADDRESS', 'info@mwalimulink.com');
            $emailSubject = "International School Inquiry: " . $rec->name;
            $emailBody = "
                <div style='font-family: sans-serif; font-size: 14px; color: #334155; line-height: 1.6;'>
                    <h3 style='color: #0f766e;'>New International Institution Inquiry</h3>
                    <p><strong>Institution:</strong> " . h($rec->name) . " (" . h($rec->country) . ")</p>
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
$officialWebsiteUrl = !empty($rec->website) ? $rec->website : ('https://www.google.com/search?q=' . urlencode(trim(($rec->name ?? '') . ' official website ' . ($rec->country ?? ''))));
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="max-width: 1400px; width: 100%; margin: 0 auto; padding: 2rem 1.5rem; box-sizing: border-box;">
        
        <!-- Breadcrumb & Top Actions -->
        <div style="margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
            <div style="display: flex; align-items: center; gap: 8px; font-size: 0.84rem; color: #64748b;">
                <a href="/schools/international" style="color: #0f766e; text-decoration: none; font-weight: 600;">
                    <i class="fa fa-arrow-left"></i> Back to International Schools
                </a>
                <span>/</span>
                <span style="color: #94a3b8;"><?= h($rec->country ?: 'Global') ?></span>
            </div>
            
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <!-- External Website Link -->
                <a href="<?= h($officialWebsiteUrl) ?>" target="_blank" rel="noopener noreferrer" style="margin: 0 !important; height: 36px !important; padding: 0 14px !important; background: #ffffff !important; color: #0f766e !important; font-size: 0.82rem !important; font-weight: 600 !important; border-radius: 6px !important; text-decoration: none !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; border: 1px solid #cbd5e1 !important; box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important; box-sizing: border-box !important; line-height: 1 !important;">
                    <i class="fa fa-external-link-alt" style="font-size: 0.75rem;"></i> Visit Official Website ↗
                </a>

                <!-- On-Page Inquiry Trigger -->
                <button type="button" onclick="openInquiryModal()" style="margin: 0 !important; height: 36px !important; padding: 0 16px !important; background: #0f766e !important; color: #ffffff !important; font-size: 0.82rem !important; font-weight: 600 !important; border-radius: 6px !important; border: 1px solid #0f766e !important; cursor: pointer !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; box-shadow: 0 1px 2px rgba(15,118,110,0.2) !important; box-sizing: border-box !important; line-height: 1 !important;">
                    <i class="fa fa-envelope"></i> Inquire with MwalimuLink
                </button>
            </div>
        </div>

        <?php if ($inquirySuccess): ?>
            <div
                style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px; font-size: 0.9rem;">
                <i class="fa fa-check-circle" style="font-size: 1.1rem; color: #22c55e;"></i>
                <div>
                    <strong>Inquiry Submitted Successfully!</strong> Our advisory team will review your query regarding
                    <em><?= h($rec->name) ?></em> and get in touch with you shortly.
                </div>
            </div>
        <?php elseif (!empty($inquiryError)): ?>
            <div
                style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 10px; font-size: 0.9rem;">
                <i class="fa fa-exclamation-circle" style="font-size: 1.1rem; color: #ef4444;"></i>
                <div><?= h($inquiryError) ?></div>
            </div>
        <?php endif; ?>

        <!-- School Header Card -->
        <div
            style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem 1.75rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div
                style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2
                        style="margin: 0 0 6px; font-size: 1.5rem; font-weight: 800; color: #0f172a; letter-spacing: -0.01em;">
                        <?= h($rec->name) ?>
                    </h2>
                    <p
                        style="margin: 0; font-size: 0.9rem; color: #64748b; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-globe" style="color: #0f766e;"></i>
                        <span><?= h($rec->city ?: 'City') ?>, <?= h($rec->country ?: 'Country') ?></span>
                    </p>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                    <span
                        style="background: #ede9fe; color: #6d28d9; font-size: 0.78rem; font-weight: 700; padding: 5px 12px; border-radius: 6px; border: 1px solid #ddd6fe;">
                        International School
                    </span>
                    <span
                        style="background: #f8fafc; color: #475569; font-size: 0.78rem; font-weight: 700; padding: 5px 12px; border-radius: 6px; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 5px;">
                        <i class="fa fa-compass" style="color: #0f766e;"></i> Curated Global Listing
                    </span>
                </div>
            </div>
        </div>


        <!-- 3-Card Interactive Info Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 2rem; align-items: stretch;">

            <!-- Card 1: School Specifications Table Card -->
            <div class="mwalimu-table-card" style="margin-bottom: 0; display: flex; flex-direction: column;">
                <div style="padding: 1rem 1.25rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-weight: 700; font-size: 0.9rem; color: #0f172a;">
                    <i class="fa fa-info-circle" style="color: #0f766e; margin-right: 6px;"></i> Institution Details
                </div>
                <table class="mwalimu-table" style="flex: 1;">
                    <tbody>
                        <tr>
                            <th style="width: 35%; background: white !important; color: #64748b !important;">Institution Name</th>
                            <td style="font-weight: 600; color: #0f172a !important;"><?= h($rec->name) ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Category</th>
                            <td><span style="background: #ede9fe; color: #6d28d9; padding: 2px 8px; border-radius: 4px; font-weight: 700; font-size: 0.75rem;">INTERNATIONAL</span></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Country</th>
                            <td style="font-weight: 600; color: #0f172a !important;"><?= h($rec->country ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">City / Town</th>
                            <td><?= h($rec->city ?: 'N/A') ?></td>
                        </tr>
                        <tr>
                            <th style="background: white !important; color: #64748b !important;">Address / Location</th>
                            <td><?= h($rec->address ?: 'N/A') ?></td>
                        </tr>
                        <?php if (!empty($rec->website)): ?>
                            <tr>
                                <th style="background: white !important; color: #64748b !important;">Website</th>
                                <td><a href="<?= h($rec->website) ?>" target="_blank" rel="noopener noreferrer" style="color: #0f766e; font-weight: 600;"><?= h($rec->website) ?> ↗</a></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Card 2: Global Career Support Card -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <h4 style="margin: 0 0 8px; font-size: 1rem; font-weight: 700; color: #0f172a;">
                        <i class="fa fa-plane-departure" style="color: #0f766e; margin-right: 6px;"></i> Overseas Teaching Preparation
                    </h4>
                    <p style="font-size: 0.85rem; color: #64748b; line-height: 1.5; margin: 0 0 1.25rem;">
                        Learn about international curriculum pathways (IB, Cambridge IGCSE, British, American), visa prerequisites, TSC attestation, and documentation needed to teach abroad.
                    </p>
                </div>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="/faqs-overseas" style="background: #f1f5f9; color: #0f766e; font-weight: 600; font-size: 0.82rem; padding: 8px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 5px; border: 1px solid #e2e8f0;">
                        <i class="fa fa-book"></i> Overseas FAQs
                    </a>
                    <button type="button" onclick="openInquiryModal()" style="margin: 0 !important; background: #0f766e; color: white !important; font-size: 0.82rem; font-weight: 600; padding: 8px 14px; border-radius: 6px; border: 1px solid #0f766e; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 2px rgba(15,118,110,0.2);">
                        <i class="fa fa-paper-plane"></i> Request Advisory
                    </button>
                </div>
            </div>

            <!-- Card 3: Geographic Location & Interactive Map Card -->
            <?php
                $mapSearchQuery = urlencode(trim(($rec->name ?? '') . ' ' . ($rec->city ?? '') . ' ' . ($rec->country ?? '')));
            ?>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; flex-direction: column; min-height: 280px;">
                <div style="padding: 0.85rem 1.25rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 6px;">
                    <span style="font-weight: 700; font-size: 0.9rem; color: #0f172a;">
                        <i class="fa fa-map-marked-alt" style="color: #0f766e; margin-right: 6px;"></i> Map Location
                    </span>
                    <span style="font-size: 0.78rem; color: #64748b; white-space: nowrap;">
                        <?= h($rec->city ?: '') ?><?= (!empty($rec->city) && !empty($rec->country)) ? ', ' : '' ?><?= h($rec->country ?: '') ?>
                    </span>
                </div>
                <div style="position: relative; width: 100%; flex: 1; min-height: 220px; background: #f1f5f9;">
                    <iframe 
                        width="100%" 
                        height="100%" 
                        style="border:0; width: 100%; height: 100%; min-height: 220px;" 
                        loading="lazy" 
                        allowfullscreen 
                        referrerpolicy="no-referrer-when-downgrade" 
                        src="https://maps.google.com/maps?q=<?= $mapSearchQuery ?>&t=&z=13&ie=UTF8&iwloc=&output=embed">
                    </iframe>
                </div>
            </div>

        </div>

    </main>
</div>

<!-- On-Page Inquiry Modal -->
<div id="inquiryModal"
    style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.7); z-index: 2000; align-items: center; justify-content: center; padding: 1rem; backdrop-filter: blur(2px);">
    <div
        style="background: white; width: 100%; max-width: 520px; border-radius: 10px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2); overflow: hidden; border: 1px solid #e2e8f0;">
        <div
            style="padding: 1.25rem 1.5rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
            <div>
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: #0f172a;">Inquire with MwalimuLink
                </h3>
                <p style="margin: 2px 0 0; font-size: 0.78rem; color: #64748b;">Regarding:
                    <strong><?= h($rec->name) ?></strong></p>
            </div>
            <button type="button" onclick="closeInquiryModal()"
                style="background: transparent; border: none; font-size: 1.25rem; color: #94a3b8; cursor: pointer; padding: 4px 8px;">&times;</button>
        </div>

        <form method="POST" action="" style="padding: 1.5rem; margin: 0;">
            <input type="hidden" name="action" value="submit_inquiry">

            <div style="margin-bottom: 1rem;">
                <label
                    style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Your
                    Full Name *</label>
                <input type="text" name="name" required value="<?= h($authUser['name'] ?? '') ?>"
                    placeholder="e.g. Jane Wanjiku"
                    style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 1rem;">
                <div>
                    <label
                        style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Email
                        Address *</label>
                    <input type="email" name="email" required value="<?= h($authUser['email'] ?? '') ?>"
                        placeholder="name@example.com"
                        style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                </div>
                <div>
                    <label
                        style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Phone
                        Number</label>
                    <input type="tel" name="phone" placeholder="e.g. 0712345678"
                        style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                </div>
            </div>

            <div style="margin-bottom: 1.25rem;">
                <label
                    style="display: block; font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px;">Your
                    Inquiry / Message *</label>
                <textarea name="message" rows="4" required
                    placeholder="Describe your question, subject specialization, or guidance request..."
                    style="width: 100%; box-sizing: border-box; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; font-family: inherit; resize: vertical;"></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; align-items: center;">
                <button type="button" onclick="closeInquiryModal()"
                    style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 8px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">Cancel</button>
                <button type="submit"
                    style="background: #0f766e; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-paper-plane"></i> Send Inquiry
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function openInquiryModal() {
        var m = document.getElementById('inquiryModal');
        if (m) {
            m.style.display = 'flex';
        }
    }
    function closeInquiryModal() {
        var m = document.getElementById('inquiryModal');
        if (m) {
            m.style.display = 'none';
        }
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeInquiryModal();
        }
    });
</script>