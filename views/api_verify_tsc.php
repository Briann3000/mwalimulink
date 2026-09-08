<?php
// views/api_verify_tsc.php - Automated TSC Verification Endpoint
if (!headers_sent()) {
    header('Content-Type: application/json; charset=UTF-8');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';
require_once __DIR__ . '/../services/TscVerificationService.php';

init_session();
$authUser = auth_user();
if (!$authUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

$teacherId = intval($input['teacher_id'] ?? 0);
if (!$teacherId && ($authUser['role'] ?? '') === 'teacher') {
    $teacherId = $authUser['user_id'];
}

// Only the teacher themselves or an admin can trigger verification
if (($authUser['role'] ?? '') !== 'admin' && $teacherId != $authUser['user_id']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized action.']);
    exit();
}

$teacher = R::load('teacher', $teacherId);
if (!$teacher || !$teacher->id) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Teacher record not found.']);
    exit();
}

$action = $input['action'] ?? 'verify_tsc';

// Handle Clear / Reset TSC Verification Status
if ($action === 'clear_tsc') {
    $teacher->verification_status = 'none';
    $teacher->tsc_verified_name = null;
    $teacher->verified_at = null;
    $teacher->verification_ref = null;
    R::store($teacher);

    // Optionally clear any logged audits for this teacher
    R::exec("DELETE FROM tscverificationlog WHERE teacher_id = ?", [$teacher->id]);

    echo json_encode([
        'success' => true,
        'message' => 'TSC verification status has been reset.'
    ]);
    exit();
}

// Handle Referee Endorsement Email Dispatch
if ($action === 'send_referee_request') {
    $refereeName = trim($input['referee_name'] ?? '');
    $refereeEmail = trim($input['referee_email'] ?? '');
    $institution = trim($input['institution'] ?? '');
    $roleTitle = trim($input['role_title'] ?? 'Referee');

    if (empty($refereeEmail) || !filter_var($refereeEmail, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid referee email address.']);
        exit();
    }

    if (empty($refereeName) || empty($institution)) {
        echo json_encode(['success' => false, 'message' => 'Referee full name and school/institution are required.']);
        exit();
    }

    $refUrl = TscVerificationService::generateRefereeToken($teacher->id, $teacher->name, $refereeName, $refereeEmail, $institution, $roleTitle);
    send_referee_endorsement_email($refereeName, $refereeEmail, $teacher, $refUrl);

    echo json_encode([
        'success' => true,
        'message' => "Tenure endorsement invitation email sent to {$refereeEmail}!"
    ]);
    exit();
}

$tscNumber = trim($input['tsc_number'] ?? $teacher->tsc_number ?? '');
$idNumber = trim($input['id_number'] ?? '');
$candidateName = trim($input['candidate_name'] ?? $teacher->name ?? '');

if (empty($tscNumber)) {
    echo json_encode([
        'success' => false,
        'status' => 'failed',
        'message' => 'Please enter a TSC Registration Number.'
    ]);
    exit();
}

// Execute verification
$result = TscVerificationService::verifyTeacher($teacher->id, $tscNumber, $idNumber, $candidateName);

echo json_encode($result);
