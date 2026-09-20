<?php
// scratch/test_page_render.php - Test rendering /forum, /forum/create, and /messages under R::freeze(true)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

$dbPath = __DIR__ . '/../' . env('DB_PATH', 'data/mwalimu.db');
R::setup("sqlite:$dbPath");

// Ensure initialization works when frozen
init_forum_and_messaging_schema();
R::freeze(true);

$passed = 0;
$failed = 0;

function assert_test($desc, $cond)
{
    global $passed, $failed;
    if ($cond) {
        echo "  [PASS] {$desc}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$desc}\n";
        $failed++;
    }
}

echo "=== TESTING FROZEN SCHEMA & PAGE RENDER ===\n\n";

// 1. Check categories under freeze
try {
    $cats = forum_get_categories();
    assert_test("forum_get_categories() returned categories under R::freeze(true)", count($cats) >= 5);
    $catNames = array_map(function ($c) {
        return $c->name;
    }, $cats);
    assert_test("Contains 'General Chat' category", in_array('General Chat', $catNames));
} catch (\Throwable $e) {
    assert_test("forum_get_categories() failed: " . $e->getMessage(), false);
}

// 2. Render forum.php (Guest view)
$_SESSION['auth'] = null;
$_GET = [];
ob_start();
try {
    include __DIR__ . '/../views/forum.php';
    $forumOutput = ob_get_clean();
    $hasHeader = str_contains($forumOutput, 'Teachers Community Chat') || str_contains($forumOutput, 'Teachers Community');
    $hasCategories = str_contains($forumOutput, 'General Chat');
    assert_test("views/forum.php rendered cleanly without throwing", $hasHeader && $hasCategories);
} catch (\Throwable $e) {
    ob_end_clean();
    assert_test("views/forum.php threw exception: " . $e->getMessage(), false);
}

// 3. Render forum_create.php (Authenticated teacher view)
$teacher = R::findOne('teacher');
if (!$teacher) {
    R::freeze(false);
    $teacher = R::dispense('teacher');
    $teacher->name = 'Test Teacher';
    $teacher->email = 'test.render@mwalimu.info';
    R::store($teacher);
    R::freeze(true);
}

$_SESSION['auth'] = [
    'logged_in' => true,
    'user_id' => $teacher->id,
    'role' => 'teacher',
    'name' => $teacher->name,
    'email' => $teacher->email
];
$_GET = [];

ob_start();
try {
    include __DIR__ . '/../views/forum_create.php';
    $createOutput = ob_get_clean();
    $hasCreateHeader = str_contains($createOutput, 'Start a Discussion Topic');
    $hasSubmitBtn = str_contains($createOutput, 'Post to Forum');
    assert_test("views/forum_create.php rendered cleanly for authenticated teacher", $hasCreateHeader && $hasSubmitBtn);
} catch (\Throwable $e) {
    ob_end_clean();
    assert_test("views/forum_create.php threw exception: " . $e->getMessage(), false);
}

// 4. Render messages.php (Direct Compose to Teacher)
$_GET = ['to_teacher' => $teacher->id];

ob_start();
try {
    include __DIR__ . '/../views/messages.php';
    $msgOutput = ob_get_clean();
    $hasMsgHeader = str_contains($msgOutput, 'Direct Messages');
    $hasComposerForm = str_contains($msgOutput, 'sendMessageForm');
    assert_test("views/messages.php?to_teacher=X shows active chat composer immediately", $hasMsgHeader && $hasComposerForm);
} catch (\Throwable $e) {
    ob_end_clean();
    assert_test("views/messages.php threw exception: " . $e->getMessage(), false);
}

echo "\nSummary: {$passed} passed, {$failed} failed\n";
if ($failed > 0)
    exit(1);
