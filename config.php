<?php
// config.php - Application Configuration and Environment Loader

function env($key, $default = null) {
    static $env = null;
    if ($env === null) {
        $env = [];
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value, " \t\n\r\0\x0B\"\'");
                    $env[$name] = $value;
                }
            }
        }
    }
    return $env[$key] ?? getenv($key) ?: $default;
}

if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// -------------------------------------------------------------------
// Session & Authentication Helper Functions
// -------------------------------------------------------------------

function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            session_set_cookie_params([
                'lifetime' => 86400 * 30, // 30 days
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        } else {
            @session_start();
        }
    }
}

function auth_login($user, $role) {
    init_session();
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        @session_regenerate_id(true); // Prevent session fixation
    }
    $_SESSION['auth'] = [
        'logged_in' => true,
        'user_id' => $user->id ?? 0,
        'name' => $user->name ?? ($role === 'admin' ? 'Administrator' : ''),
        'email' => $user->email ?? '',
        'role' => $role, // 'teacher' | 'school' | 'admin'
        'created_at' => time()
    ];
}

function auth_logout() {
    init_session();
    $_SESSION = [];
    if (!headers_sent() && ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_destroy();
    }
}

function auth_user() {
    init_session();
    return $_SESSION['auth'] ?? null;
}

function is_logged_in() {
    init_session();
    return !empty($_SESSION['auth']['logged_in']);
}

function has_role($role) {
    init_session();
    return is_logged_in() && isset($_SESSION['auth']['role']) && $_SESSION['auth']['role'] === $role;
}

function require_auth($role = null) {
    init_session();
    if (!is_logged_in()) {
        $loginRoute = ($role === 'school') ? '/login/school' : (($role === 'admin') ? '/login/admin' : '/login');
        header("Location: $loginRoute");
        exit();
    }
    if ($role !== null && !has_role($role)) {
        header("Location: /");
        exit();
    }
}

// -------------------------------------------------------------------
// CSRF Protection Helpers
// -------------------------------------------------------------------

function csrf_token() {
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

function validate_csrf() {
    init_session();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            die("Security Error: Invalid or expired CSRF token.");
        }
    }
}

function verify_csrf($token) {
    init_session();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// -------------------------------------------------------------------
// Kenyan Geographic & Educational Metadata Helpers
// -------------------------------------------------------------------

function kenyan_counties() {
    return [
        'Baringo', 'Bomet', 'Bungoma', 'Busia', 'Elgeyo Marakwet', 'Embu',
        'Garissa', 'Homa Bay', 'Isiolo', 'Kajiado', 'Kakamega', 'Kericho',
        'Kiambu', 'Kilifi', 'Kirinyaga', 'Kisii', 'Kisumu', 'Kitui',
        'Kwale', 'Laikipia', 'Lamu', 'Machakos', 'Makueni', 'Mandera',
        'Marsabit', 'Meru', 'Migori', 'Mombasa', 'Murang\'a', 'Nairobi',
        'Nakuru', 'Nandi', 'Narok', 'Nyamira', 'Nyandarua', 'Nyeri',
        'Samburu', 'Siaya', 'Taita Taveta', 'Tana River', 'Tharaka Nithi',
        'Trans Nzoia', 'Turkana', 'Uasin Gishu', 'Vihiga', 'Wajir', 'West Pokot'
    ];
}

function kenyan_grade_levels() {
    return [
        'ECDE / Kindergarten / Pre-Primary',
        'Primary School (CBC Grade 1 - 6)',
        'Junior Secondary (JSS Grade 7 - 9)',
        'Senior Secondary (Form 1 - 4)',
        'International Curriculum (IGCSE / IB / Cambridge)',
        'Special Needs Education (SNE)',
        'Tertiary / College'
    ];
}

function kenyan_school_categories() {
    return [
        'Private Primary & Kindergarten',
        'Private Secondary School',
        'Junior Secondary School (JSS)',
        'International School (IGCSE / IB / American)',
        'Kindergarten / Early Childhood Center',
        'Special Needs School',
        'Public Primary School',
        'Public Secondary School'
    ];
}

function normalize_kenyan_phone($phone) {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($cleaned, '0') && (strlen($cleaned) === 10)) {
        return '254' . substr($cleaned, 1);
    }
    if (str_starts_with($cleaned, '254') && (strlen($cleaned) === 12)) {
        return $cleaned;
    }
    if (strlen($cleaned) === 9 && (str_starts_with($cleaned, '7') || str_starts_with($cleaned, '1'))) {
        return '254' . $cleaned;
    }
    return null; // Invalid format
}

function validate_tsc_number($tsc) {
    $tsc = trim((string)$tsc);
    if (empty($tsc)) {
        return ['valid' => true, 'formatted' => null, 'is_registered' => false];
    }
    // Kenyan TSC numbers are typically 5 to 7 digits (e.g. 123456, 987654) or alphanumeric prefix (e.g. TSC/123456 or 123456)
    $digitsOnly = preg_replace('/[^0-9]/', '', $tsc);
    if (strlen($digitsOnly) >= 4 && strlen($digitsOnly) <= 8) {
        return ['valid' => true, 'formatted' => $digitsOnly, 'is_registered' => true];
    }
    return ['valid' => false, 'formatted' => null, 'is_registered' => false, 'error' => 'TSC Number must be between 4 and 8 digits.'];
}

/**
 * Secure file upload validator & handler
 * - Validates file size
 * - Validates extension against whitelist
 * - Validates true binary MIME type (magic bytes via finfo)
 * - Strips any embedded executable signatures / double extensions
 * - Generates cryptographically safe random filename
 */
function secure_validate_and_upload($fileArray, $targetDirRelative = 'uploads/documents/', $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'], $maxBytes = 5242880) {
    if (!isset($fileArray['error']) || is_array($fileArray['error'])) {
        return ['success' => false, 'error' => 'Invalid file upload parameters.'];
    }

    switch ($fileArray['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'No file was uploaded.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'Uploaded file exceeds maximum allowed server limit.'];
        default:
            return ['success' => false, 'error' => 'Unknown upload error occurred.'];
    }

    // 1. File Size Verification
    if ($fileArray['size'] > $maxBytes || $fileArray['size'] <= 0) {
        $mb = round($maxBytes / (1024 * 1024));
        return ['success' => false, 'error' => "File exceeds the allowed size limit of {$mb}MB."];
    }

    // 2. Strict File Extension Whitelist Check
    $originalName = basename($fileArray['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExtensions, true)) {
        return ['success' => false, 'error' => 'Invalid file type. Allowed formats: ' . strtoupper(implode(', ', $allowedExtensions))];
    }

    // Prevent double-extension attacks (e.g., shell.php.jpg)
    if (preg_match('/\.(php|phtml|phar|php3|php4|php5|php7|phps|cgi|pl|py|sh|bash|exe|bat|cmd|js|html|htm)\./i', $originalName)) {
        return ['success' => false, 'error' => 'Potentially unsafe file name detected.'];
    }

    // 3. MIME Type & Magic Bytes Verification (File Content Inspection)
    $allowedMimes = [
        'pdf'  => ['application/pdf', 'application/x-pdf'],
        'jpg'  => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png'  => ['image/png', 'image/x-png']
    ];

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileArray['tmp_name']);
        finfo_close($finfo);

        $validMimesForExt = $allowedMimes[$ext] ?? [];
        if (!in_array($mimeType, $validMimesForExt, true)) {
            return ['success' => false, 'error' => 'File content does not match its declared format (detected: ' . htmlspecialchars($mimeType) . ').'];
        }
    }

    // 4. For image types, ensure they pass image integrity checks
    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
        $imgInfo = @getimagesize($fileArray['tmp_name']);
        if ($imgInfo === false) {
            return ['success' => false, 'error' => 'Corrupt or invalid image file.'];
        }
    }

    // 5. Ensure Target Upload Directory Exists
    $uploadDir = __DIR__ . '/' . trim($targetDirRelative, '/') . '/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return ['success' => false, 'error' => 'Failed to prepare destination upload directory.'];
        }
    }

    // 6. Generate Cryptographically Secure & Collision-Free Filename
    $randomHex = bin2hex(random_bytes(16));
    $safeFilename = 'doc_' . $randomHex . '.' . $ext;
    $targetPath = $uploadDir . $safeFilename;

    if (!move_uploaded_file($fileArray['tmp_name'], $targetPath)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file on server.'];
    }

    return [
        'success' => true,
        'relative_path' => trim($targetDirRelative, '/') . '/' . $safeFilename,
        'filename' => $safeFilename,
        'mime_type' => $mimeType ?? 'application/octet-stream',
        'size' => $fileArray['size']
    ];
}

/**
 * Send official platform email notification via Resend API or SMTP
 */
function send_system_email($toEmail, $toName, $subject, $htmlBody, $replyToEmail = null, $replyToName = null) {
    if (empty($toEmail)) return false;

    $resendApiKey = env('RESEND_API_KEY');
    $fromAddress = env('MAIL_FROM_ADDRESS', 'onboarding@resend.dev');
    $fromName = env('MAIL_FROM_NAME', 'Mwalimu Link');

    // 1. If RESEND_API_KEY is configured, dispatch via Resend REST API (Fastest & Most Reliable)
    if (!empty($resendApiKey)) {
        $payload = [
            'from' => "{$fromName} <{$fromAddress}>",
            'to' => [$toEmail],
            'subject' => $subject,
            'html' => $htmlBody,
            'text' => strip_tags($htmlBody)
        ];

        if ($replyToEmail) {
            $payload['reply_to'] = $replyToEmail;
        }

        $ch = curl_init('https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $resendApiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return true;
        }
        error_log("Resend API error (HTTP {$httpCode}): " . $response);
    }

    // 2. Fallback to PHPMailer SMTP if Resend is not set or fails
    require_once __DIR__ . '/class.phpmailer.php';
    require_once __DIR__ . '/class.smtp.php';

    $mail = new PHPMailer();
    try {
        $mail->isSMTP();
        $mail->Host = env('SMTP_HOST', 'smtp.gmail.com');
        $mail->SMTPAuth = true;
        $mail->Username = env('SMTP_USERNAME', 'themwalimulink@gmail.com');
        $mail->Password = env('SMTP_PASSWORD', '');
        $mail->SMTPSecure = env('SMTP_ENCRYPTION', 'tls');
        $mail->Port = intval(env('SMTP_PORT', 587));

        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($toEmail, $toName ?: 'Educator/School');

        if ($replyToEmail) {
            $mail->addReplyTo($replyToEmail, $replyToName ?: $replyToEmail);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Peleza Background Screening Service (Sandbox & Live)
 */
class PelezaService {
    private $environment;
    private $apiKey;
    private $clientId;
    private $baseUrl;

    public function __construct() {
        $this->environment = env('PELEZA_ENV', 'sandbox');
        $this->apiKey = env('PELEZA_API_KEY', '');
        $this->clientId = env('PELEZA_CLIENT_ID', '');
        
        $this->baseUrl = ($this->environment === 'production')
            ? 'https://api.peleza.com/v1'
            : 'https://api-sandbox.peleza.com/v1';
    }

    public function isConfigured(): bool {
        return !empty($this->apiKey) && !empty($this->clientId);
    }

    public function getEnvironment(): string {
        return $this->environment;
    }

    /**
     * Submit background check for Good Conduct & TSC clearance
     */
    public function submitVerification($teacher, $sponsor = 'teacher'): array {
        if (!$this->isConfigured()) {
            // Simulated Sandbox Response when API credentials are being provisioned
            return [
                'success' => true,
                'status' => 'pending',
                'reference' => 'PLZ-MOCK-' . strtoupper(substr(md5(uniqid()), 0, 8)),
                'message' => 'Verification request received (Sandbox Simulation). Result will be processed in background.'
            ];
        }

        $payload = [
            'client_id' => $this->clientId,
            'sponsor' => $sponsor,
            'candidate' => [
                'full_name' => $teacher->name,
                'email' => $teacher->email,
                'phone' => $teacher->mobile,
                'tsc_number' => $teacher->tsc_number ?? null,
                'good_conduct_serial' => $teacher->good_conduct_cert_no ?? null
            ],
            'checks' => ['criminal_record', 'tsc_registration']
        ];

        $ch = curl_init($this->baseUrl . '/verifications');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'status' => $data['status'] ?? 'pending',
                'reference' => $data['reference'] ?? null,
                'data' => $data
            ];
        }

        return [
            'success' => false,
            'error' => 'Peleza API responded with status ' . $httpCode . ': ' . $response
        ];
    }
}

/**
 * Dispatch automatic, beautifully-styled email notification to teacher when background verification status updates
 */
function send_verification_status_email($teacher, $status, $notes = '') {
    if (empty($teacher->email)) return false;

    $appUrl = env('APP_URL', 'http://localhost:8000');
    $profileUrl = rtrim($appUrl, '/') . '/teacher/profile?id=' . $teacher->id;
    $updateUrl = rtrim($appUrl, '/') . '/teacher/update';
    $teacherName = $teacher->name ?: 'Educator';

    if ($status === 'verified') {
        $subject = "🎉 Congratulations! Your Educator Profile is Now Verified on MwalimuLink";
        $htmlBody = "
        <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;\">
            <div style=\"background: #0f766e; padding: 24px 30px; text-align: center;\">
                <h1 style=\"color: #ffffff; margin: 0; font-size: 1.4rem; font-weight: 700;\">MwalimuLink</h1>
                <p style=\"color: #ccfbf1; margin: 6px 0 0; font-size: 0.85rem;\">Educator Clearance & Verification</p>
            </div>
            
            <div style=\"padding: 30px;\">
                <div style=\"display: inline-block; background: #dcfce7; color: #166534; font-size: 0.8rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; margin-bottom: 16px; border: 1px solid #86efac;\">
                    ✓ STATUS: VERIFIED EDUCATOR
                </div>
                
                <h2 style=\"color: #0f172a; font-size: 1.3rem; margin: 0 0 12px;\">Congratulations, " . htmlspecialchars($teacherName) . "!</h2>
                
                <p style=\"color: #334155; font-size: 0.95rem; line-height: 1.6; margin: 0 0 16px;\">
                    We are pleased to inform you that your <strong>Certificate of Good Conduct & Background Credentials</strong> have been officially authenticated.
                </p>

                <div style=\"background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 18px; margin: 20px 0;\">
                    <h4 style=\"margin: 0 0 8px; color: #166534; font-size: 0.95rem;\">🌟 Your Profile is Now Upgraded:</h4>
                    <ul style=\"margin: 0; padding-left: 20px; color: #15803d; font-size: 0.88rem; line-height: 1.6;\">
                        <li><strong>Verified Educator ✓</strong> badge is now active on your public profile.</li>
                        <li>Your CV gets <strong>Priority Candidate Ranking</strong> in school search filters.</li>
                        <li>Hiring headteachers and school HRs can instantly verify your clearance standing.</li>
                    </ul>
                </div>

                <div style=\"text-align: center; margin: 28px 0 20px;\">
                    <a href=\"{$profileUrl}\" style=\"display: inline-block; background: #0f766e; color: #ffffff !important; font-weight: 700; font-size: 0.95rem; padding: 12px 28px; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(15,118,110,0.2);\">
                        View Your Verified Profile →
                    </a>
                </div>
                
                <p style=\"font-size: 0.82rem; color: #64748b; line-height: 1.5; margin: 20px 0 0;\">
                    <em>Tip: Keep your subject specializations and availability status updated to receive relevant interview invitations.</em>
                </p>
            </div>

            <div style=\"background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 0.75rem; color: #94a3b8;\">
                <p style=\"margin: 0;\">MwalimuLink &bull; Empowering Kenya's Teaching Workforce &bull; Nairobi, Kenya</p>
            </div>
        </div>";
    } else {
        $subject = "Action Required: Background Clearance Update - MwalimuLink";
        $reasonText = !empty($notes) ? htmlspecialchars($notes) : "The certificate serial number or document uploaded could not be matched with official clearance records.";
        $htmlBody = "
        <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;\">
            <div style=\"background: #0f766e; padding: 24px 30px; text-align: center;\">
                <h1 style=\"color: #ffffff; margin: 0; font-size: 1.4rem; font-weight: 700;\">MwalimuLink</h1>
                <p style=\"color: #ccfbf1; margin: 6px 0 0; font-size: 0.85rem;\">Educator Clearance & Verification</p>
            </div>
            
            <div style=\"padding: 30px;\">
                <div style=\"display: inline-block; background: #fee2e2; color: #991b1b; font-size: 0.8rem; font-weight: 800; padding: 4px 12px; border-radius: 20px; margin-bottom: 16px; border: 1px solid #fca5a5;\">
                    ⚠️ ACTION REQUIRED
                </div>
                
                <h2 style=\"color: #0f172a; font-size: 1.3rem; margin: 0 0 12px;\">Hello " . htmlspecialchars($teacherName) . ",</h2>
                
                <p style=\"color: #334155; font-size: 0.95rem; line-height: 1.6; margin: 0 0 16px;\">
                    We recently attempted to verify your Police Clearance / Good Conduct credentials. Unfortunately, authentication was unsuccessful:
                </p>

                <div style=\"background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 16px; margin: 20px 0; color: #991b1b; font-size: 0.88rem; line-height: 1.5;\">
                    <strong>Reason / Note:</strong><br>
                    {$reasonText}
                </div>

                <p style=\"color: #475569; font-size: 0.88rem; line-height: 1.6;\">
                    This usually happens due to a minor typographical error in the Certificate Serial Number or an unreadable document upload. You can easily re-submit your details in your profile dashboard.
                </p>

                <div style=\"text-align: center; margin: 28px 0 20px;\">
                    <a href=\"{$updateUrl}\" style=\"display: inline-block; background: #0f766e; color: #ffffff !important; font-weight: 700; font-size: 0.95rem; padding: 12px 28px; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(15,118,110,0.2);\">
                        Review & Re-submit Clearance →
                    </a>
                </div>
            </div>

            <div style=\"background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 30px; text-align: center; font-size: 0.75rem; color: #94a3b8;\">
                <p style=\"margin: 0;\">MwalimuLink Support &bull; Nairobi, Kenya</p>
            </div>
        </div>";
    }

    return send_system_email($teacher->email, $teacher->name, $subject, $htmlBody);
}

/**
 * Dispatch notification email to school when an educator applies for a job
 */
function send_job_application_notification_email($school, $job, $teacher, $application) {
    if (empty($school->email)) return false;

    $appUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
    $applicantsUrl = "{$appUrl}/school/applicants?job_id=" . ($job->id ?? 0);
    $teacherProfileUrl = "{$appUrl}/teacher/profile?teacher_id=" . ($teacher->id ?? 0);
    $schoolName = $school->name ?: 'School Administration';
    $teacherName = $teacher->name ?: 'Educator';
    $jobTitle = $job->title ?: 'Teaching Vacancy';

    $isVerified = ($teacher->verification_status === 'verified');
    $badgeText = $isVerified ? '✓ Verified Educator (Police Clearance Authenticated)' : 'Clearance Pending';
    $badgeColor = $isVerified ? '#166534' : '#854d0e';
    $badgeBg = $isVerified ? '#dcfce7' : '#fef9c3';

    $pitchHtml = !empty($application->cover_note) 
        ? "<div style=\"background: #f8fafc; border-left: 4px solid #0f766e; padding: 12px 16px; margin: 16px 0; border-radius: 4px; font-style: italic; color: #334155; font-size: 0.9rem;\">
            \"" . nl2br(htmlspecialchars($application->cover_note)) . "\"
           </div>"
        : "";

    $salaryHtml = !empty($application->expected_salary)
        ? "<tr><td style=\"padding: 6px 0; color: #64748b; font-size: 0.85rem;\">Expected Salary:</td><td style=\"padding: 6px 0; font-weight: 600; color: #0f172a;\">KES " . number_format($application->expected_salary) . " / mo</td></tr>"
        : "";

    $availHtml = !empty($application->available_from)
        ? "<tr><td style=\"padding: 6px 0; color: #64748b; font-size: 0.85rem;\">Availability:</td><td style=\"padding: 6px 0; font-weight: 600; color: #0f172a;\">" . htmlspecialchars($application->available_from) . "</td></tr>"
        : "";

    $subject = "📢 New Application for {$jobTitle}: {$teacherName}";
    $htmlBody = "
    <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 620px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;\">
        <div style=\"background: #0f766e; padding: 22px 28px; text-align: center;\">
            <h1 style=\"color: #ffffff; margin: 0; font-size: 1.35rem; font-weight: 700;\">MwalimuLink Recruiter Alert</h1>
            <p style=\"color: #ccfbf1; margin: 4px 0 0; font-size: 0.84rem;\">Candidate Application Pipeline</p>
        </div>

        <div style=\"padding: 28px;\">
            <h2 style=\"color: #0f172a; font-size: 1.25rem; margin: 0 0 10px;\">New Candidate Received!</h2>
            <p style=\"color: #475569; font-size: 0.92rem; line-height: 1.5; margin: 0 0 16px;\">
                Hello <strong>" . htmlspecialchars($schoolName) . "</strong>, an educator has just submitted an application for your active vacancy: <strong>" . htmlspecialchars($jobTitle) . "</strong>.
            </p>

            <div style=\"display: inline-block; background: {$badgeBg}; color: {$badgeColor}; font-size: 0.78rem; font-weight: 800; padding: 4px 10px; border-radius: 16px; margin-bottom: 16px;\">
                {$badgeText}
            </div>

            <div style=\"background: #f1f5f9; border-radius: 8px; padding: 16px; margin: 12px 0 20px;\">
                <h4 style=\"margin: 0 0 10px; color: #0f172a; font-size: 1rem;\">Candidate Snapshot: " . htmlspecialchars($teacherName) . "</h4>
                <table style=\"width: 100%; font-size: 0.88rem;\">
                    <tr><td style=\"width: 35%; padding: 4px 0; color: #64748b;\">Specialization:</td><td style=\"padding: 4px 0; font-weight: 600; color: #0f172a;\">" . htmlspecialchars($teacher->teaching_subjects ?: 'General') . "</td></tr>
                    <tr><td style=\"padding: 4px 0; color: #64748b;\">Experience:</td><td style=\"padding: 4px 0; font-weight: 600; color: #0f172a;\">" . intval($teacher->years_of_experience) . " Years</td></tr>
                    <tr><td style=\"padding: 4px 0; color: #64748b;\">Location / County:</td><td style=\"padding: 4px 0; font-weight: 600; color: #0f172a;\">" . htmlspecialchars($teacher->county ?: 'Kenya') . "</td></tr>
                    {$salaryHtml}
                    {$availHtml}
                </table>
                {$pitchHtml}
            </div>

            <div style=\"text-align: center; margin: 24px 0 16px;\">
                <a href=\"{$applicantsUrl}\" style=\"display: inline-block; background: #0f766e; color: #ffffff !important; font-weight: 700; font-size: 0.92rem; padding: 12px 26px; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(15,118,110,0.2);\">
                    Review Applicant & Schedule Interview →
                </a>
            </div>
            
            <p style=\"font-size: 0.8rem; color: #94a3b8; text-align: center; margin: 0;\">
                You can shortlist, message, or decline candidates directly inside your School Dashboard.
            </p>
        </div>

        <div style=\"background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 28px; text-align: center; font-size: 0.75rem; color: #94a3b8;\">
            <p style=\"margin: 0;\">MwalimuLink &bull; Verified School Recruiting &bull; Nairobi, Kenya</p>
        </div>
    </div>";

    return send_system_email($school->email, $school->name, $subject, $htmlBody);
}

/**
 * Dispatch notification email to teacher when a school sends a message or schedules an interview
 */
function send_interview_invite_email($teacher, $school, $job, $messageContent, $interviewDate = null, $interviewLocation = null, $interviewFormat = 'in_person', $interviewVirtualLink = null) {
    if (empty($teacher->email)) return false;

    $appUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
    $applicationsUrl = "{$appUrl}/teacher/applications";
    $teacherName = $teacher->name ?: 'Educator';
    $schoolName = $school->name ?: 'School Administration';
    $jobTitle = $job->title ?: 'Teaching Position';

    $interviewScheduleHtml = "";
    if (!empty($interviewDate)) {
        $formattedDate = date('D, M d, Y \a\t h:i A', strtotime($interviewDate));
        
        if ($interviewFormat === 'virtual' && !empty($interviewVirtualLink)) {
            $venueSection = "
            <p style=\"margin: 0 0 6px; font-size: 0.88rem; color: #1e3a8a;\"><strong>Mode:</strong> 🌐 Virtual / Online Meeting</p>
            <div style=\"margin-top: 10px;\">
                <a href=\"" . htmlspecialchars($interviewVirtualLink) . "\" target=\"_blank\" style=\"display: inline-block; background: #2563eb; color: #ffffff !important; font-size: 0.85rem; font-weight: 700; padding: 8px 16px; border-radius: 6px; text-decoration: none;\">
                    🎥 Join Virtual Interview (Google Meet / Zoom) →
                </a>
            </div>";
        } else {
            $loc = !empty($interviewLocation) ? htmlspecialchars($interviewLocation) : "School Campus / Administration Office";
            $venueSection = "<p style=\"margin: 0; font-size: 0.88rem; color: #1e3a8a;\"><strong>Venue & Reporting:</strong> {$loc}</p>";
        }

        $interviewScheduleHtml = "
        <div style=\"background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 16px; margin: 18px 0;\">
            <h4 style=\"margin: 0 0 8px; color: #1e40af; font-size: 0.95rem;\">📅 Scheduled Interview Details:</h4>
            <p style=\"margin: 0 0 6px; font-size: 0.88rem; color: #1e3a8a;\"><strong>Date & Time:</strong> {$formattedDate}</p>
            {$venueSection}
        </div>";
    }

    $subject = "🎉 Interview Update from {$schoolName} for {$jobTitle}";
    $htmlBody = "
    <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 620px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;\">
        <div style=\"background: #0f766e; padding: 22px 28px; text-align: center;\">
            <h1 style=\"color: #ffffff; margin: 0; font-size: 1.35rem; font-weight: 700;\">MwalimuLink Candidate Portal</h1>
            <p style=\"color: #ccfbf1; margin: 4px 0 0; font-size: 0.84rem;\">Application Status & Communication</p>
        </div>

        <div style=\"padding: 28px;\">
            <h2 style=\"color: #0f172a; font-size: 1.25rem; margin: 0 0 10px;\">Great news, " . htmlspecialchars($teacherName) . "!</h2>
            <p style=\"color: #334155; font-size: 0.92rem; line-height: 1.6; margin: 0 0 14px;\">
                <strong>" . htmlspecialchars($schoolName) . "</strong> has reviewed your application for <strong>" . htmlspecialchars($jobTitle) . "</strong> and sent you a direct message:
            </p>

            <div style=\"background: #f8fafc; border-left: 4px solid #0f766e; border-radius: 4px; padding: 14px 18px; margin: 16px 0; color: #1e293b; font-size: 0.92rem; line-height: 1.6;\">
                " . nl2br(htmlspecialchars($messageContent)) . "
            </div>

            {$interviewScheduleHtml}

            <div style=\"text-align: center; margin: 26px 0 18px;\">
                <a href=\"{$applicationsUrl}\" style=\"display: inline-block; background: #0f766e; color: #ffffff !important; font-weight: 700; font-size: 0.92rem; padding: 12px 26px; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(15,118,110,0.2);\">
                    View Application & Respond in Portal →
                </a>
            </div>

            <p style=\"font-size: 0.8rem; color: #64748b; text-align: center; margin: 0;\">
                You can respond directly through your MwalimuLink dashboard to confirm your availability.
            </p>
        </div>

        <div style=\"background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 28px; text-align: center; font-size: 0.75rem; color: #94a3b8;\">
            <p style=\"margin: 0;\">MwalimuLink &bull; Connecting Kenya's Finest Teachers &bull; Nairobi, Kenya</p>
        </div>
    </div>";

    return send_system_email($teacher->email, $teacher->name, $subject, $htmlBody);
}

/**
 * Dispatch respectful regret notification email to applicant
 */
function send_application_regret_email($teacher, $school, $job, $customMessage = null) {
    if (empty($teacher->email)) return false;

    $appUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
    $teacherName = $teacher->name ?: 'Educator';
    $schoolName = $school->name ?: 'School Administration';
    $jobTitle = $job->title ?: 'Teaching Position';

    $bodyText = $customMessage ?: "Thank you for taking the time to apply for the {$jobTitle} vacancy at {$schoolName}. After careful review of all submissions, the recruitment committee has decided to proceed with other candidates whose profiles more closely matched our specific requirements for this term.\n\nWe were impressed by your background and will keep your profile in our talent pool for future vacancies. We wish you every success in your educational career.";

    $subject = "Application Update: {$jobTitle} at {$schoolName}";
    $htmlBody = "
    <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 620px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;\">
        <div style=\"background: #334155; padding: 22px 28px; text-align: center;\">
            <h1 style=\"color: #ffffff; margin: 0; font-size: 1.35rem; font-weight: 700;\">MwalimuLink Candidate Portal</h1>
            <p style=\"color: #cbd5e1; margin: 4px 0 0; font-size: 0.84rem;\">Application Status Notification</p>
        </div>

        <div style=\"padding: 28px;\">
            <h2 style=\"color: #0f172a; font-size: 1.2rem; margin: 0 0 12px;\">Dear " . htmlspecialchars($teacherName) . ",</h2>
            
            <div style=\"color: #334155; font-size: 0.92rem; line-height: 1.7; margin-bottom: 20px;\">
                " . nl2br(htmlspecialchars($bodyText)) . "
            </div>

            <div style=\"text-align: center; margin: 24px 0 16px;\">
                <a href=\"{$appUrl}/teacher/jobs\" style=\"display: inline-block; background: #0f766e; color: #ffffff !important; font-weight: 700; font-size: 0.9rem; padding: 10px 22px; border-radius: 6px; text-decoration: none;\">
                    Explore Other Teaching Vacancies →
                </a>
            </div>
        </div>

        <div style=\"background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 28px; text-align: center; font-size: 0.75rem; color: #94a3b8;\">
            <p style=\"margin: 0;\">MwalimuLink &bull; Verified School Recruiting &bull; Nairobi, Kenya</p>
        </div>
    </div>";

    return send_system_email($teacher->email, $teacher->name, $subject, $htmlBody);
}

/**
 * Dispatch notification email to school when an applicant replies to a message
 */
function send_teacher_reply_email($school, $teacher, $job, $replyContent) {
    if (empty($school->email)) return false;

    $appUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
    $applicantsUrl = "{$appUrl}/school/applicants?job_id=" . ($job->id ?? 0);
    $teacherName = $teacher->name ?: 'Educator';
    $schoolName = $school->name ?: 'School Administration';
    $jobTitle = $job->title ?: 'Teaching Position';

    $subject = "💬 Candidate Response: {$teacherName} regarding {$jobTitle}";
    $htmlBody = "
    <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 620px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;\">
        <div style=\"background: #0f766e; padding: 22px 28px; text-align: center;\">
            <h1 style=\"color: #ffffff; margin: 0; font-size: 1.35rem; font-weight: 700;\">MwalimuLink Candidate Portal</h1>
            <p style=\"color: #ccfbf1; margin: 4px 0 0; font-size: 0.84rem;\">Candidate Communication Thread</p>
        </div>

        <div style=\"padding: 28px;\">
            <h2 style=\"color: #0f172a; font-size: 1.25rem; margin: 0 0 10px;\">Response from " . htmlspecialchars($teacherName) . "</h2>
            <p style=\"color: #334155; font-size: 0.92rem; line-height: 1.6; margin: 0 0 14px;\">
                Candidate <strong>" . htmlspecialchars($teacherName) . "</strong> replied regarding the <strong>" . htmlspecialchars($jobTitle) . "</strong> vacancy:
            </p>

            <div style=\"background: #f8fafc; border-left: 4px solid #0f766e; border-radius: 4px; padding: 14px 18px; margin: 16px 0; color: #1e293b; font-size: 0.92rem; line-height: 1.6;\">
                " . nl2br(htmlspecialchars($replyContent)) . "
            </div>

            <div style=\"text-align: center; margin: 26px 0 18px;\">
                <a href=\"{$applicantsUrl}\" style=\"display: inline-block; background: #0f766e; color: #ffffff !important; font-weight: 700; font-size: 0.92rem; padding: 12px 26px; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(15,118,110,0.2);\">
                    Open Applicants Pipeline →
                </a>
            </div>
        </div>

        <div style=\"background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 28px; text-align: center; font-size: 0.75rem; color: #94a3b8;\">
            <p style=\"margin: 0;\">MwalimuLink &bull; Verified School Recruiting &bull; Nairobi, Kenya</p>
        </div>
    </div>";

    return send_system_email($school->email, $school->name, $subject, $htmlBody);
}

/**
 * Dispatch faculty appointment invitation email to teacher
 */
function send_staff_invitation_email($school, $recipientEmail, $roleTitle, $employmentType, $token, $isExistingUser = false) {
    if (empty($recipientEmail)) return false;

    $appUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
    $schoolName = $school->name ?: 'School Administration';
    $role = $roleTitle ?: 'Teacher';
    $type = $employmentType ?: 'Permanent';

    if ($isExistingUser) {
        $actionUrl = "{$appUrl}/staff-invitation?token={$token}";
        $subject = "🎓 Faculty Invitation from {$schoolName} as {$role}";
        $actionText = "Review & Accept Invitation →";
        $instruction = "You have been invited by <strong>" . htmlspecialchars($schoolName) . "</strong> to join their official faculty roster on MwalimuLink as <strong>" . htmlspecialchars($role) . "</strong> (" . htmlspecialchars($type) . ").";
    } else {
        $actionUrl = "{$appUrl}/register/teacher?invite_token={$token}&email=" . urlencode($recipientEmail);
        $subject = "🎓 Faculty Appointment Invitation from {$schoolName}";
        $actionText = "Create Account & Accept Appointment →";
        $instruction = "<strong>" . htmlspecialchars($schoolName) . "</strong> has selected you to join their faculty as <strong>" . htmlspecialchars($role) . "</strong> (" . htmlspecialchars($type) . ") on MwalimuLink.<br><br>Please create your MwalimuLink educator account to activate your verified profile and link directly to " . htmlspecialchars($schoolName) . ".";
    }

    $htmlBody = "
    <div style=\"font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; max-width: 620px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden;\">
        <div style=\"background: #0f766e; padding: 24px 28px; text-align: center;\">
            <h1 style=\"color: #ffffff; margin: 0; font-size: 1.35rem; font-weight: 700;\">MwalimuLink Faculty Network</h1>
            <p style=\"color: #ccfbf1; margin: 4px 0 0; font-size: 0.85rem;\">Official Institution Appointment</p>
        </div>

        <div style=\"padding: 28px;\">
            <h2 style=\"color: #0f172a; font-size: 1.25rem; margin: 0 0 12px;\">You've Been Invited to Join {$schoolName}</h2>
            <p style=\"color: #334155; font-size: 0.92rem; line-height: 1.6; margin: 0 0 16px;\">
                {$instruction}
            </p>

            <div style=\"background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; margin: 18px 0; font-size: 0.88rem;\">
                <div style=\"margin-bottom: 6px;\"><strong style=\"color: #64748b;\">Institution:</strong> <span style=\"color: #0f172a; font-weight: 600;\">" . htmlspecialchars($schoolName) . "</span></div>
                <div style=\"margin-bottom: 6px;\"><strong style=\"color: #64748b;\">Role / Title:</strong> <span style=\"color: #0f766e; font-weight: 700;\">" . htmlspecialchars($role) . "</span></div>
                <div><strong style=\"color: #64748b;\">Classification:</strong> <span style=\"color: #1e293b;\">" . htmlspecialchars($type) . "</span></div>
            </div>

            <div style=\"text-align: center; margin: 26px 0 18px;\">
                <a href=\"{$actionUrl}\" style=\"display: inline-block; background: #0f766e; color: #ffffff !important; font-weight: 700; font-size: 0.95rem; padding: 12px 28px; border-radius: 6px; text-decoration: none; box-shadow: 0 2px 4px rgba(15,118,110,0.25);\">
                    {$actionText}
                </a>
            </div>

            <p style=\"color: #94a3b8; font-size: 0.78rem; line-height: 1.5; text-align: center; margin-top: 20px;\">
                If you did not expect this invitation or believe it was sent in error, you can safely ignore this email.
            </p>
        </div>

        <div style=\"background: #f8fafc; border-top: 1px solid #e2e8f0; padding: 14px 28px; text-align: center; font-size: 0.75rem; color: #94a3b8;\">
            <p style=\"margin: 0;\">&copy; " . date('Y') . " MwalimuLink &bull; Verified Teacher Roster &bull; Nairobi, Kenya</p>
        </div>
    </div>";

    return send_system_email($recipientEmail, '', $subject, $htmlBody);
}







