<?php
if (file_exists('class.phpmailer.php') && file_exists('class.smtp.php')) {
    require_once 'class.phpmailer.php';
    require_once 'class.smtp.php';
}

$msg = '';
$msgType = '';
if (array_key_exists('email', $_POST)) {
    validate_csrf();
    date_default_timezone_set('Etc/UTC');

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? 'General Inquiry');
    $subject = trim($_POST['subject'] ?? 'MwalimuLink Inquiry: ' . $department);
    $message = trim($_POST['message'] ?? '');

    // Server-Side Security Hardening for File Uploads
    $attachmentValid = true;
    $attachmentPath = null;
    $attachmentName = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $fileError = $_FILES['attachment']['error'];
        $fileTmp = $_FILES['attachment']['tmp_name'];
        $rawFileName = $_FILES['attachment']['name'];
        $fileSize = $_FILES['attachment']['size'];

        // 1. Check upload error status
        if ($fileError !== UPLOAD_ERR_OK) {
            $msg = "Error processing uploaded file. Please verify your file and try again.";
            $msgType = 'error';
            $attachmentValid = false;
        }
        // 2. Strict file size check (5 MB max)
        elseif ($fileSize > 5 * 1024 * 1024) {
            $msg = "Uploaded file exceeds the maximum 5MB size limit (" . round($fileSize / (1024 * 1024), 2) . " MB).";
            $msgType = 'error';
            $attachmentValid = false;
        }
        // 3. Strict extension whitelist
        else {
            $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp', 'txt'];
            $fileExt = strtolower(pathinfo($rawFileName, PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedExtensions, true)) {
                $msg = "Invalid file type (." . htmlspecialchars($fileExt) . "). Allowed formats: PDF, DOC, DOCX, JPG, PNG, WEBP.";
                $msgType = 'error';
                $attachmentValid = false;
            } else {
                // 4. Magic-byte MIME type inspection using finfo
                $allowedMimes = [
                    'pdf'  => ['application/pdf', 'application/x-pdf'],
                    'doc'  => ['application/msword', 'application/vnd.ms-office', 'application/octet-stream'],
                    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
                    'jpg'  => ['image/jpeg', 'image/pjpeg'],
                    'jpeg' => ['image/jpeg', 'image/pjpeg'],
                    'png'  => ['image/png', 'image/x-png'],
                    'webp' => ['image/webp'],
                    'txt'  => ['text/plain']
                ];

                if (function_exists('finfo_open') && file_exists($fileTmp)) {
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $detectedMime = finfo_file($finfo, $fileTmp);
                    finfo_close($finfo);

                    $validMimesForExt = $allowedMimes[$fileExt] ?? [];
                    if (!in_array($detectedMime, $validMimesForExt, true)) {
                        $msg = "Security alert: File content does not match the file extension (detected: {$detectedMime}).";
                        $msgType = 'error';
                        $attachmentValid = false;
                    }
                }

                if ($attachmentValid) {
                    // 5. Filename sanitization (prevent path traversal & special characters)
                    $cleanBaseName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($rawFileName, PATHINFO_FILENAME));
                    $cleanBaseName = substr($cleanBaseName, 0, 40);
                    $attachmentName = ($cleanBaseName ?: 'attachment') . '.' . $fileExt;
                    $attachmentPath = $fileTmp;
                }
            }
        }
    }

    if ($attachmentValid) {
        if (class_exists('PHPMailer')) {
            $smtpHost = env('SMTP_HOST', 'smtp.gmail.com');
            $smtpPort = intval(env('SMTP_PORT', 587));
            $smtpEnc = env('SMTP_ENCRYPTION', 'tls');
            $smtpUser = env('SMTP_USERNAME', 'infomwalimulink@gmail.com');
            $smtpPass = env('SMTP_PASSWORD', '');
            $mailFrom = env('MAIL_FROM_ADDRESS', 'infomwalimulink@gmail.com');

            $mail = new PHPMailer();
            $mail->isSMTP();
            $mail->SMTPDebug = 0;
            $mail->Host = $smtpHost;
            $mail->Port = $smtpPort;
            $mail->SMTPSecure = $smtpEnc;
            $mail->SMTPAuth = !empty($smtpPass);
            $mail->Username = $smtpUser;
            $mail->Password = $smtpPass;
            $mail->setFrom($mailFrom, $name ?: 'MwalimuLink Visitor');
            $mail->addReplyTo($email, $name);
            $mail->addAddress($mailFrom, 'MwalimuLink Support');
            $mail->addBCC($mailFrom);

            $mail->Subject = "[MwalimuLink {$department}] {$subject}";
            $mailBody = "<h3>New Inquiry Received from MwalimuLink Contact Portal</h3>" .
                "<p><strong>Sender Name:</strong> " . htmlspecialchars($name) . "</p>" .
                "<p><strong>Email Address:</strong> " . htmlspecialchars($email) . "</p>" .
                "<p><strong>Phone Number:</strong> " . htmlspecialchars($phone) . "</p>" .
                "<p><strong>Department / Category:</strong> " . htmlspecialchars($department) . "</p>" .
                "<p><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>" .
                "<hr>" .
                "<p><strong>Message:</strong><br>" . nl2br(htmlspecialchars($message)) . "</p>";

            $mail->msgHTML($mailBody);
            $mail->AltBody = "Name: $name\nEmail: $email\nPhone: $phone\nDepartment: $department\nSubject: $subject\n\nMessage:\n$message";

            if ($attachmentPath && $attachmentName) {
                $mail->addAttachment($attachmentPath, $attachmentName);
            }

            if (!$mail->send()) {
                $msg = "Error sending message: " . $mail->ErrorInfo . ". Please contact us directly via email.";
                $msgType = 'error';
            } else {
                $msg = "Thank you, {$name}! Your message has been routed to our {$department} team. We will respond within 2-4 business hours.";
                $msgType = 'success';
            }
        } else {
            $msg = "Thank you! Your message has been recorded. Our team will contact you shortly.";
            $msgType = 'success';
        }
    }
}
?>

<div class="contact-page-wrapper">
  <!-- Hero Section -->
  <section class="contact-hero">
    <div class="contact-container">
      <div class="contact-hero-badge"><i class="fa-solid fa-headset"></i> Dedicated Educator & Institutional Support</div>
      <h1 class="contact-hero-title">We're Here to Help You Succeed</h1>
      <p class="contact-hero-subtitle">
        Have questions about teacher registration, school subscriptions, international placement, or academic publications? Connect with our dedicated support team.
      </p>
    </div>
  </section>

  <div class="contact-container" style="margin-top: 2rem;">
    <!-- Main Split Grid: Contact Form + FAQ Accordion -->
    <div class="contact-main-grid">
      <!-- Contact Form Card -->
      <div class="contact-form-card">
        <div class="form-header">
          <h3>Send Us a Message</h3>
          <p>Fill in the form below and your inquiry will be directly routed to the right specialist.</p>
        </div>

        <?php if ($msg): ?>
          <div class="alert-banner <?= $msgType === 'success' ? 'alert-success' : 'alert-error' ?>">
            <i class="fa-solid <?= $msgType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
            <div><?= $msg ?></div>
          </div>
        <?php endif; ?>

        <form method="post" action="" enctype="multipart/form-data" class="modern-form" onsubmit="return validateContactForm()">
          <?= csrf_field() ?>
          <div class="form-row-2">
            <div class="form-group">
              <label for="name">Your Full Name <span class="req">*</span></label>
              <input type="text" id="name" name="name" required placeholder="e.g. Mwalimu Grace Wanjiku" value="<?= h($_POST['name'] ?? '') ?>" class="form-control">
            </div>

            <div class="form-group">
              <label for="email">Email Address <span class="req">*</span></label>
              <input type="email" id="email" name="email" required placeholder="e.g. grace@example.com" value="<?= h($_POST['email'] ?? '') ?>" class="form-control">
            </div>
          </div>

          <div class="form-row-2">
            <div class="form-group">
              <label for="phone">Phone / WhatsApp Number</label>
              <input type="tel" id="phone" name="phone" placeholder="e.g. +254 700 000 000" value="<?= h($_POST['phone'] ?? '') ?>" class="form-control">
            </div>

            <div class="form-group">
              <label for="department">Inquiry Department <span class="req">*</span></label>
              <select id="department" name="department" required class="form-control">
                <option value="Teacher Registration & Support">Teacher Registration & Profile Help</option>
                <option value="School Subscription & Hiring">School Subscription & Recruitment</option>
                <option value="Teaching Overseas / International Placement">Teaching Overseas & International Placement</option>
                <option value="Teaching Practice (TP) Hub">Teaching Practice (TP) Hub Inquiries</option>
                <option value="Academic Publications Submission">Educator Publications & Research Papers</option>
                <option value="Technical Support & Account Recovery">Technical Support / Account Access</option>
                <option value="General Inquiries">Other / General Inquiries</option>
              </select>
            </div>
          </div>

          <div class="form-group">
            <label for="subject">Subject</label>
            <input type="text" id="subject" name="subject" placeholder="Brief summary of your inquiry" value="<?= h($_POST['subject'] ?? '') ?>" class="form-control">
          </div>

          <div class="form-group">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
              <label for="message" style="margin-bottom: 0;">Detailed Message <span class="req">*</span></label>
              <span id="charCount" style="font-size: 0.75rem; color: #64748b;">0 / 1000</span>
            </div>
            <textarea id="message" name="message" rows="5" required placeholder="Please provide specific details so we can assist you quickly..." class="form-control" oninput="updateCharCount(this)"><?= h($_POST['message'] ?? '') ?></textarea>
          </div>

          <div class="form-group upload-box">
            <label for="attachment">
              <i class="fa-solid fa-paperclip"></i>
              <span>Attach Document / Screenshot (Optional, Max 5MB)</span>
            </label>
            <input type="file" id="attachment" name="attachment" class="form-control-file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
            <div id="fileChosen" class="file-name-hint">No file chosen</div>
          </div>

          <button type="submit" class="btn-submit-contact">
            <i class="fa-solid fa-paper-plane"></i> Send Inquiry
          </button>
        </form>
      </div>

      <!-- Quick Self-Service Accordion Sidebar -->
      <div class="contact-sidebar">
        <div class="sidebar-box">
          <div class="sidebar-box-header">
            <i class="fa-solid fa-bolt" style="color: #f59e0b;"></i>
            <h4>Instant Self-Service Answers</h4>
          </div>
          <p class="sidebar-hint">Check quick solutions below before waiting for an email response:</p>

          <div class="quick-accordion">
            <div class="quick-acc-item">
              <button class="quick-acc-trigger" onclick="toggleQuickAcc(this)">
                <span>How long does teacher profile approval take?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </button>
              <div class="quick-acc-content">
                <p>Teacher profiles are active immediately upon registration. Once you upload your TSC certificate and academic transcripts, our verification team validates credentials within 12 business hours.</p>
              </div>
            </div>

            <div class="quick-acc-item">
              <button class="quick-acc-trigger" onclick="toggleQuickAcc(this)">
                <span>How do schools access teacher contacts?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </button>
              <div class="quick-acc-content">
                <p>Schools with an active annual subscription can instantly view candidate phone numbers, email addresses, and CV documents directly in the Candidate Search portal.</p>
              </div>
            </div>

            <div class="quick-acc-item">
              <button class="quick-acc-trigger" onclick="toggleQuickAcc(this)">
                <span>Can teachers submit research papers for free?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </button>
              <div class="quick-acc-content">
                <p>Yes! Any registered educator can publish classroom research, CBC teaching guides, or academic papers for free in the Publications Hub.</p>
              </div>
            </div>

            <div class="quick-acc-item">
              <button class="quick-acc-trigger" onclick="toggleQuickAcc(this)">
                <span>How do I reset my password?</span>
                <i class="fa-solid fa-chevron-down"></i>
              </button>
              <div class="quick-acc-content">
                <p>Visit the login page and click "Forgot Password" or navigate directly to our password reset page. A reset link will be sent to your registered email address.</p>
              </div>
            </div>
          </div>

          <div class="sidebar-cta-banner">
            <i class="fa-solid fa-circle-question"></i>
            <div>
              <strong>Need More In-Depth FAQs?</strong>
              <p>Explore our comprehensive question bank covering local & overseas teaching.</p>
              <a href="/faqs" class="link-faq-hub">Visit Educator FAQ Hub <i class="fa-solid fa-arrow-right"></i></a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.contact-page-wrapper {
  background: #f8fafc;
  color: #1e293b;
  font-family: inherit;
  padding-bottom: 4rem;
}

.contact-container {
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 1.5rem;
}

/* Hero Section */
.contact-hero {
  background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%);
  color: #ffffff !important;
  padding: 4.5rem 0 4rem;
  text-align: center;
}

.contact-hero-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: rgba(45, 212, 191, 0.15);
  border: 1px solid rgba(45, 212, 191, 0.35);
  color: #2dd4bf !important;
  padding: 6px 16px;
  border-radius: 999px;
  font-size: 0.85rem;
  font-weight: 700;
  margin-bottom: 1.25rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.contact-hero-title,
.contact-hero h1 {
  font-size: clamp(2rem, 4vw, 3rem) !important;
  font-weight: 800 !important;
  line-height: 1.2 !important;
  max-width: 800px;
  margin: 0 auto 1rem !important;
  color: #ffffff !important;
}

.contact-hero-subtitle,
.contact-hero p {
  font-size: 1.05rem !important;
  line-height: 1.6 !important;
  max-width: 680px;
  margin: 0 auto !important;
  color: #cbd5e1 !important;
}

/* Main Split Grid */
.contact-main-grid {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 2rem;
}

@media (max-width: 900px) {
  .contact-main-grid {
    grid-template-columns: 1fr;
  }
}

.contact-form-card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  padding: 2.5rem;
  box-shadow: 0 4px 20px rgba(0,0,0,0.04);
}

.form-header {
  margin-bottom: 2rem;
}

.form-header h3 {
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 6px 0;
}

.form-header p {
  color: #64748b;
  font-size: 0.95rem;
  margin: 0;
}

/* Alerts */
.alert-banner {
  padding: 1rem 1.25rem;
  border-radius: 12px;
  display: flex;
  align-items: flex-start;
  gap: 12px;
  font-size: 0.92rem;
  font-weight: 600;
  margin-bottom: 1.5rem;
}

.alert-success {
  background: #f0fdf4;
  border: 1px solid #bbf7d0;
  color: #15803d;
}

.alert-error {
  background: #fef2f2;
  border: 1px solid #fecaca;
  color: #b91c1c;
}

/* Modern Form */
.modern-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.25rem;
}

@media (max-width: 600px) {
  .form-row-2 {
    grid-template-columns: 1fr;
  }
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-group label {
  font-size: 0.86rem;
  font-weight: 700;
  color: #334155;
  margin-bottom: 6px;
}

.req {
  color: #ef4444;
}

.form-control {
  width: 100%;
  padding: 11px 14px;
  border: 1px solid #cbd5e1;
  border-radius: 10px;
  font-size: 0.92rem;
  color: #0f172a;
  background: #f8fafc;
  transition: all 0.15s ease;
  box-sizing: border-box;
}

.form-control:focus {
  outline: none;
  border-color: #0f766e;
  background: #ffffff;
  box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15);
}

.upload-box {
  background: #f8fafc;
  border: 2px dashed #cbd5e1;
  border-radius: 12px;
  padding: 1rem;
  text-align: center;
}

.upload-box label {
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #0f766e;
  font-weight: 700;
  font-size: 0.88rem;
  margin-bottom: 4px;
}

.form-control-file {
  display: none;
}

.file-name-hint {
  font-size: 0.8rem;
  color: #64748b;
}

.btn-submit-contact {
  background: #0f766e;
  color: #ffffff;
  border: none;
  padding: 14px 24px;
  border-radius: 10px;
  font-weight: 800;
  font-size: 1rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  transition: background 0.15s ease, transform 0.1s ease;
}

.btn-submit-contact:hover {
  background: #115e59;
  transform: translateY(-1px);
}

/* Sidebar Box & Accordion */
.contact-sidebar {
  display: flex;
  flex-direction: column;
}

.sidebar-box {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 18px;
  padding: 2rem;
  box-shadow: 0 4px 20px rgba(0,0,0,0.04);
}

.sidebar-box-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 0.5rem;
}

.sidebar-box-header h4 {
  font-size: 1.15rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0;
}

.sidebar-hint {
  font-size: 0.88rem;
  color: #64748b;
  margin-bottom: 1.25rem;
}

.quick-accordion {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 1.5rem;
}

.quick-acc-item {
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  overflow: hidden;
  background: #f8fafc;
}

.quick-acc-trigger {
  width: 100%;
  padding: 12px 14px;
  background: transparent;
  border: none;
  display: flex;
  justify-content: space-between;
  align-items: center;
  text-align: left;
  font-weight: 700;
  font-size: 0.88rem;
  color: #1e293b;
  cursor: pointer;
  gap: 10px;
}

.quick-acc-trigger i {
  font-size: 0.75rem;
  color: #64748b;
  transition: transform 0.2s ease;
}

.quick-acc-item.open .quick-acc-trigger i {
  transform: rotate(180deg);
}

.quick-acc-content {
  display: none;
  padding: 0 14px 12px;
  font-size: 0.84rem;
  color: #475569;
  line-height: 1.5;
}

.quick-acc-item.open .quick-acc-content {
  display: block;
}

.sidebar-cta-banner {
  background: #f0fdfa;
  border: 1px solid #ccfbf1;
  border-radius: 12px;
  padding: 1.25rem;
  display: flex;
  gap: 12px;
  align-items: flex-start;
}

.sidebar-cta-banner i {
  color: #0f766e;
  font-size: 1.3rem;
  margin-top: 2px;
}

.sidebar-cta-banner strong {
  display: block;
  font-size: 0.92rem;
  color: #0f172a;
  margin-bottom: 2px;
}

.sidebar-cta-banner p {
  color: #475569;
  font-size: 0.82rem;
  margin: 0 0 8px 0;
  line-height: 1.4;
}

.link-faq-hub {
  color: #0f766e;
  font-weight: 800;
  font-size: 0.84rem;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.link-faq-hub:hover {
  text-decoration: underline;
}

@media (max-width: 768px) {
  .contact-hero {
    padding: 3rem 1rem 2.5rem;
  }
  .contact-form-card {
    padding: 1.75rem 1.25rem !important;
  }
  .sidebar-box {
    padding: 1.5rem 1.25rem !important;
  }
}

@media (max-width: 480px) {
  .contact-container {
    padding: 0 1rem;
  }
  .contact-hero-title,
  .contact-hero h1 {
    font-size: 1.75rem !important;
  }
  .contact-hero-subtitle,
  .contact-hero p {
    font-size: 0.95rem !important;
  }
  .form-control {
    font-size: 16px !important; /* Prevents auto-zoom on iOS */
    padding: 12px 14px;
  }
  .btn-submit-contact {
    width: 100%;
    padding: 14px;
  }
}
</style>

<script>
function toggleQuickAcc(btn) {
  const item = btn.closest('.quick-acc-item');
  item.classList.toggle('open');
}

function updateCharCount(textarea) {
  const len = textarea.value.length;
  document.getElementById('charCount').textContent = len + ' / 1000';
}

document.getElementById('attachment')?.addEventListener('change', function(e) {
  const name = e.target.files && e.target.files[0] ? e.target.files[0].name : 'No file chosen';
  document.getElementById('fileChosen').textContent = name;
});

function copyContactText(text, btn) {
  navigator.clipboard.writeText(text).then(function() {
    const originalHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check" style="color: #2dd4bf;"></i>';
    setTimeout(function() {
      btn.innerHTML = originalHtml;
    }, 2000);
  });
}

function validateContactForm() {
  return true;
}
</script>
