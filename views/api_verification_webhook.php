<?php
// views/api_verification_webhook.php - Webhook receiver for verification callbacks
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$rawInput = file_get_contents('php://input');
$payload = json_decode($rawInput, true);

if (!$payload) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit();
}

$reference = $payload['reference'] ?? ($payload['data']['reference'] ?? '');
$status = strtolower($payload['status'] ?? ($payload['data']['status'] ?? ''));
$notes = $payload['notes'] ?? ($payload['data']['notes'] ?? '');

$teacher = null;
if (!empty($reference)) {
    $teacher = R::findOne('teacher', 'verification_ref = ?', [$reference]);
}

if (!$teacher && !empty($payload['teacher_id'])) {
    $teacher = R::load('teacher', intval($payload['teacher_id']));
}

if (!$teacher || !$teacher->id) {
    http_response_code(404);
    echo json_encode(['error' => 'Teacher record not found for verification reference']);
    exit();
}

$normalizedStatus = 'pending';
if (in_array($status, ['verified', 'completed', 'clear', 'passed', 'approved'])) {
    $normalizedStatus = 'verified';
} elseif (in_array($status, ['failed', 'rejected', 'flagged', 'discrepancy'])) {
    $normalizedStatus = 'failed';
}

$previousStatus = $teacher->verification_status;
$teacher->verification_status = $normalizedStatus;
if ($normalizedStatus === 'verified') {
    $teacher->good_conduct_status = 'verified';
    $teacher->verified_at = date('Y-m-d H:i:s');
} elseif ($normalizedStatus === 'failed') {
    $teacher->good_conduct_status = 'failed';
}
R::store($teacher);

if ($previousStatus !== $normalizedStatus && in_array($normalizedStatus, ['verified', 'failed'])) {
    send_verification_status_email($teacher, $normalizedStatus, $notes);
}

echo json_encode([
    'success' => true,
    'teacher_id' => $teacher->id,
    'status' => $normalizedStatus,
    'email_dispatched' => true
]);
