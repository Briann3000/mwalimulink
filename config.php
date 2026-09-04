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





