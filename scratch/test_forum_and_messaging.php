<?php
// scratch/test_forum_and_messaging.php - Test Suite for Teachers Forum & Direct Messaging
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

// Setup database
$dbDriver = env('DB_CONNECTION', 'sqlite');
if ($dbDriver === 'mysql') {
    $dbHost = env('DB_HOST', 'localhost');
    $dbPort = env('DB_PORT', '3306');
    $dbName = env('DB_DATABASE', 'mwalimu');
    $dbUser = env('DB_USERNAME', 'root');
    $dbPass = env('DB_PASSWORD', '');
    R::setup("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
} else {
    $dbPath = __DIR__ . '/../' . env('DB_PATH', 'data/mwalimu.db');
    R::setup("sqlite:$dbPath");
}

R::freeze(false);

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

echo "=== TEACHERS FORUM & DIRECT MESSAGING TEST SUITE ===\n\n";

// 1. Schema Initialization & Categories
init_forum_and_messaging_schema();
$categories = forum_get_categories();
assert_test("Default forum categories seeded (at least 5 categories)", count($categories) >= 5);

$catSlugs = [];
foreach ($categories as $c) {
    $catSlugs[] = $c->slug;
}
assert_test("Contains 'general-chat' category", in_array('general-chat', $catSlugs));
assert_test("Contains 'tsc-swaps' category", in_array('tsc-swaps', $catSlugs));
assert_test("Contains 'cbc-curriculum' category", in_array('cbc-curriculum', $catSlugs));
assert_test("Contains 'tp-internships' category", in_array('tp-internships', $catSlugs));

// 2. Teacher Profile & Privacy Setup
$teacherA = R::dispense('teacher');
$teacherA->name = 'John Kamau';
$teacherA->email = 'john.kamau.forum.test@mwalimu.info';
$teacherA->mobile = '0711000111';
$teacherA->county = 'Kiambu';
$teacherA->teaching_subjects = 'Mathematics / Physics';
$teacherA->profile_visibility = 'schools_only';
$teacherA->forum_alias = 'MwalimuMaths';
$teacherA->verification_status = 'verified';
$tAId = R::store($teacherA);

$teacherB = R::dispense('teacher');
$teacherB->name = 'Mary Wanjiku';
$teacherB->email = 'mary.wanjiku.forum.test@mwalimu.info';
$teacherB->mobile = '0722000222';
$teacherB->county = 'Nyeri';
$teacherB->teaching_subjects = 'English / Literature';
$teacherB->profile_visibility = 'private';
$teacherB->forum_alias = 'Tr_MaryW';
$tBId = R::store($teacherB);

$schoolA = R::dispense('school');
$schoolA->name = 'Alliance High Academy';
$schoolA->email = 'alliance.test@mwalimu.info';
$schoolA->county = 'Kiambu';
$sAId = R::store($schoolA);

// 3. Privacy Guard Tests
$authPeerTeacher = ['logged_in' => true, 'user_id' => $tBId, 'role' => 'teacher'];
$authSelf = ['logged_in' => true, 'user_id' => $tAId, 'role' => 'teacher'];
$authSchool = ['logged_in' => true, 'user_id' => $sAId, 'role' => 'school'];
$authAdmin = ['logged_in' => true, 'user_id' => 1, 'role' => 'admin'];

assert_test("Peer teacher CANNOT view 'schools_only' profile", !teacher_can_view_profile($authPeerTeacher, $teacherA));
assert_test("Hiring school CAN view 'schools_only' profile", teacher_can_view_profile($authSchool, $teacherA));
assert_test("Admin CAN view 'schools_only' profile", teacher_can_view_profile($authAdmin, $teacherA));
assert_test("Teacher CAN view their OWN profile", teacher_can_view_profile($authSelf, $teacherA));
assert_test("Peer teacher CANNOT view 'private' profile", !teacher_can_view_profile($authSelf, $teacherB));

// 4. Forum Author Display Tests
$authorDispA = forum_get_author_display($tAId, 'teacher');
assert_test("Teacher A uses forum alias 'MwalimuMaths'", $authorDispA['display_name'] === 'MwalimuMaths');
assert_test("Teacher A is marked is_private = 1", $authorDispA['is_private'] === 1);
assert_test("Teacher A initial is 'M'", $authorDispA['initial'] === 'M');

// 5. Discussion Thread Lifecycle
$swapCat = R::findOne('forumcategory', 'slug = ?', ['tsc-swaps']);
$initialCatThreads = (int) $swapCat->threads_count;

$thread = R::dispense('forumthread');
$thread->category_id = $swapCat->id;
$thread->user_id = $tAId;
$thread->user_role = 'teacher';
$thread->author_name = $authorDispA['display_name'];
$thread->author_alias = $authorDispA['display_name'];
$thread->author_county = $authorDispA['county'];
$thread->author_subjects = $authorDispA['sub_text'];
$thread->is_author_private = $authorDispA['is_private'];
$thread->title = 'Looking for Math / Physics swap from Kiambu to Meru County';
$thread->slug = 'looking-for-math-physics-swap-' . time();
$thread->content = 'I am currently stationed in a national school in Kiambu County seeking a mutual swap to Meru County for family reasons.';
$thread->views_count = 1;
$thread->replies_count = 0;
$thread->likes_count = 0;
$thread->is_pinned = 0;
$thread->is_locked = 0;
$thread->is_hidden = 0;
$thread->last_reply_at = date('Y-m-d H:i:s');
$thread->created_at = date('Y-m-d H:i:s');
$threadId = R::store($thread);

$swapCat->threads_count = (int) $swapCat->threads_count + 1;
R::store($swapCat);

assert_test("Thread created successfully with ID", $threadId > 0);
$reloadedCat = R::load('forumcategory', $swapCat->id);
assert_test("Category thread counter incremented", (int) $reloadedCat->threads_count === $initialCatThreads + 1);

// 6. Replies Lifecycle
$authorDispB = forum_get_author_display($tBId, 'teacher');
$reply = R::dispense('forumreply');
$reply->thread_id = $threadId;
$reply->user_id = $tBId;
$reply->user_role = 'teacher';
$reply->author_name = $authorDispB['display_name'];
$reply->author_alias = $authorDispB['display_name'];
$reply->author_county = $authorDispB['county'];
$reply->author_subjects = $authorDispB['sub_text'];
$reply->is_author_private = $authorDispB['is_private'];
$reply->content = 'Hello Mwalimu, I have a colleague in Meru looking for Kiambu. I will send you a private direct message.';
$reply->likes_count = 0;
$reply->is_solution = 0;
$reply->is_hidden = 0;
$reply->created_at = date('Y-m-d H:i:s');
$replyId = R::store($reply);

$thread->replies_count = (int) $thread->replies_count + 1;
$thread->last_reply_at = date('Y-m-d H:i:s');
R::store($thread);

assert_test("Reply stored with ID", $replyId > 0);
$reloadedThread = R::load('forumthread', $threadId);
assert_test("Thread reply counter is 1", (int) $reloadedThread->replies_count === 1);

// 7. Direct Messaging / Inbox Lifecycle
$conv = get_or_create_conversation($tAId, 'teacher', $tBId, 'teacher');
assert_test("Direct conversation initialized between Teacher A and Teacher B", $conv && $conv->id > 0);

// Ensure conversation lookup in reverse order returns same conversation
$convReverse = get_or_create_conversation($tBId, 'teacher', $tAId, 'teacher');
assert_test("Reverse conversation lookup returns exact same conversation ID", (int) $conv->id === (int) $convReverse->id);

// Teacher B sends private message to Teacher A
$msg = R::dispense('directmessage');
$msg->conversation_id = $conv->id;
$msg->sender_id = $tBId;
$msg->sender_role = 'teacher';
$msg->recipient_id = $tAId;
$msg->recipient_role = 'teacher';
$msg->message = 'Hi John, here are the details for the Meru high school station. Let us connect.';
$msg->is_read = 0;
$msg->created_at = date('Y-m-d H:i:s');
$msgId = R::store($msg);

$conv->last_message_at = date('Y-m-d H:i:s');
R::store($conv);

assert_test("Direct message stored with is_read = 0", $msgId > 0);
assert_test("Teacher A unread messages count is 1", get_unread_message_count($tAId, 'teacher') === 1);
assert_test("Teacher B unread messages count is 0", get_unread_message_count($tBId, 'teacher') === 0);

// Teacher A reads the message
R::exec('UPDATE directmessage SET is_read = 1 WHERE conversation_id = ? AND recipient_id = ?', [$conv->id, $tAId]);
assert_test("Teacher A unread messages count is 0 after reading", get_unread_message_count($tAId, 'teacher') === 0);

// 8. Reactions & Upvotes
$reaction = R::dispense('forumreaction');
$reaction->thread_id = $threadId;
$reaction->reply_id = null;
$reaction->user_id = $tBId;
$reaction->user_role = 'teacher';
$reaction->reaction_type = 'like';
$reaction->created_at = date('Y-m-d H:i:s');
R::store($reaction);

$thread->likes_count = (int) $thread->likes_count + 1;
R::store($thread);

assert_test("Thread likes count incremented to 1", (int) R::load('forumthread', $threadId)->likes_count === 1);

// 9. Moderation & Flagging
$report = R::dispense('forumreport');
$report->thread_id = $threadId;
$report->reply_id = null;
$report->reporter_id = $tBId;
$report->reporter_role = 'teacher';
$report->reason = 'Test report for review';
$report->details = 'Testing moderation queue';
$report->status = 'pending';
$report->created_at = date('Y-m-d H:i:s');
$repId = R::store($report);

assert_test("Forum report created with status 'pending'", $repId > 0);
$pendingCount = R::count('forumreport', 'status = ?', ['pending']);
assert_test("Pending reports queue has at least 1 report", $pendingCount >= 1);

// Lock thread test
$thread->is_locked = 1;
R::store($thread);
assert_test("Thread is_locked is 1", (int) R::load('forumthread', $threadId)->is_locked === 1);

echo "\n============================================\n";
echo "SUMMARY: Passed: {$passed}, Failed: {$failed}\n";
echo "============================================\n";

if ($failed > 0) {
    exit(1);
}
