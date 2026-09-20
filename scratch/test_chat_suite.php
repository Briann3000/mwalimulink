<?php
// scratch/test_chat_suite.php - Automated Verification for Chat Media, Polling & Security

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

$dbPath = __DIR__ . '/test_chat_suite.db';
if (file_exists($dbPath)) {
    @unlink($dbPath);
}

R::setup("sqlite:$dbPath");
R::freeze(false);
init_forum_and_messaging_schema();

$passed = 0;
$failed = 0;

function assert_test($description, $condition)
{
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$description}\n";
        $failed++;
    }
}

echo "=== TESTING ADVANCED CHAT & DIRECT MESSAGING SUITE ===\n\n";

// 1. Test Attachment Validation & Security
echo "--- Testing Attachment MIME & Extension Validation ---\n";

// Create temp mock files for testing
$tmpPdf = tempnam(sys_get_temp_dir(), 'test_pdf_');
file_put_contents($tmpPdf, "%PDF-1.4\n%mock pdf content for testing scheme of work");

$fakeFile = [
    'name' => 'Grade7_Maths_Scheme.pdf',
    'type' => 'application/pdf',
    'tmp_name' => $tmpPdf,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpPdf)
];

$res = chat_validate_and_store_attachment($fakeFile, 'document');
assert_test("Valid PDF upload accepted", $res['success'] === true && $res['type'] === 'document');
assert_test("Saved file URL in /uploads/chat/", strpos($res['url'], '/uploads/chat/') === 0);

// Test dangerous script file
$tmpExe = tempnam(sys_get_temp_dir(), 'test_exe_');
file_put_contents($tmpExe, "malicious script content");
$badFile = [
    'name' => 'virus.php',
    'type' => 'application/x-php',
    'tmp_name' => $tmpExe,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpExe)
];
$badRes = chat_validate_and_store_attachment($badFile, 'document');
assert_test("PHP executable upload blocked", $badRes['success'] === false);

// Clean up
@unlink($tmpPdf);
@unlink($tmpExe);

// 2. Test Storing Forum Message with Attachment
echo "\n--- Testing Forum Message with Attachment & Polling ---\n";

$cat = R::findOne('forumcategory', 'slug = ?', ['general-chat']);
assert_test("General Chat channel exists", $cat && $cat->id);

$msg = R::dispense('forumthread');
$msg->category_id = (int) $cat->id;
$msg->user_id = 101;
$msg->user_role = 'teacher';
$msg->author_name = 'Tr. Kamau';
$msg->author_alias = 'Tr. Kamau';
$msg->author_county = 'Nakuru';
$msg->author_subjects = 'Physics/Chem';
$msg->is_author_private = 0;
$msg->title = 'Here is the scheme of work';
$msg->slug = 'msg-test-1';
$msg->content = 'Attached is the revised syllabus.';
$msg->attachment_url = $res['url'];
$msg->attachment_type = 'document';
$msg->attachment_name = 'Grade7_Maths_Scheme.pdf';
$msg->attachment_size = '12 KB';
$msg->is_hidden = 0;
$msg->created_at = date('Y-m-d H:i:s');
$msgId1 = R::store($msg);

assert_test("Message with PDF attachment stored with ID {$msgId1}", $msgId1 > 0);

// Add second message
$msg2 = R::dispense('forumthread');
$msg2->category_id = (int) $cat->id;
$msg2->user_id = 102;
$msg2->user_role = 'teacher';
$msg2->author_name = 'Tr. Wanjiku';
$msg2->author_alias = 'Tr. Wanjiku';
$msg2->author_county = 'Kiambu';
$msg2->author_subjects = 'English/Lit';
$msg2->is_author_private = 0;
$msg2->title = 'Thanks for sharing!';
$msg2->slug = 'msg-test-2';
$msg2->content = 'Thanks for sharing!';
$msg2->is_hidden = 0;
$msg2->created_at = date('Y-m-d H:i:s');
$msgId2 = R::store($msg2);

// Test polling since ID $msgId1
$polled = R::findAll('forumthread', 'category_id = ? AND id > ? AND is_hidden = 0 ORDER BY id ASC', [
    (int) $cat->id,
    $msgId1
]);
assert_test("Polling since ID {$msgId1} returned 1 new message", count($polled) === 1);
$firstPolled = reset($polled);
assert_test("Polled message ID is {$msgId2}", (int) $firstPolled->id === (int) $msgId2);

// 3. Test Direct Messaging with Attachment
echo "\n--- Testing Direct Messaging with Attachment ---\n";
$conv = get_or_create_conversation(101, 'teacher', 102, 'teacher');
assert_test("Direct conversation initialized", $conv && $conv->id);

$dm = R::dispense('directmessage');
$dm->conversation_id = $conv->id;
$dm->sender_id = 101;
$dm->sender_role = 'teacher';
$dm->recipient_id = 102;
$dm->recipient_role = 'teacher';
$dm->message = 'Here is the private scheme doc';
$dm->attachment_url = $res['url'];
$dm->attachment_type = 'document';
$dm->attachment_name = 'Grade7_Maths_Scheme.pdf';
$dm->attachment_size = '12 KB';
$dm->is_read = 0;
$dm->created_at = date('Y-m-d H:i:s');
$dmId = R::store($dm);

assert_test("Direct message stored with attachment ID {$dmId}", $dmId > 0);

// 4. Test Report Educator Action
echo "\n--- Testing Report Educator ---\n";
$report = R::dispense('forumreport');
$report->reporter_id = 101;
$report->reporter_role = 'teacher';
$report->reason = 'Spam / Advertisements: Posting loan links';
$report->details = 'Reported teacher #105';
$report->status = 'pending';
$report->created_at = date('Y-m-d H:i:s');
$repId = R::store($report);

assert_test("Report Educator record created with ID {$repId}", $repId > 0);

// 5. Frozen Schema Render Test
echo "\n--- Testing Views under R::freeze(true) ---\n";
R::freeze(true);

$_SESSION['auth'] = [
    'logged_in' => true,
    'user_id' => 101,
    'role' => 'teacher',
    'name' => 'Tr. Kamau'
];
$_GET['category'] = 'general-chat';

ob_start();
try {
    include __DIR__ . '/../views/forum.php';
    $forumHtml = ob_get_clean();
    $hasPlusBtn = (strpos($forumHtml, 'attachmentToggleBtn') !== false);
    $hasDocLink = (strpos($forumHtml, 'Grade7_Maths_Scheme.pdf') !== false);
    $hasReportBtn = (strpos($forumHtml, 'modalReportBtn') !== false);
    assert_test("views/forum.php renders with '+' attachment picker & document link", $hasPlusBtn && $hasDocLink && $hasReportBtn);
} catch (\Throwable $e) {
    ob_end_clean();
    assert_test("views/forum.php render failed: " . $e->getMessage(), false);
}

$_GET['conv'] = $conv->id;
ob_start();
try {
    include __DIR__ . '/../views/messages.php';
    $msgHtml = ob_get_clean();
    $hasDmPlus = (strpos($msgHtml, 'dmAttachmentToggleBtn') !== false);
    $hasDmDoc = (strpos($msgHtml, 'Grade7_Maths_Scheme.pdf') !== false);
    assert_test("views/messages.php renders with DM '+' attachment picker & document link", $hasDmPlus && $hasDmDoc);
} catch (\Throwable $e) {
    ob_end_clean();
    assert_test("views/messages.php render failed: " . $e->getMessage(), false);
}

echo "\n============================================\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "============================================\n";
