<?php
require_auth('teacher');

$authUser = auth_user();
$teacher_id = $authUser['user_id'];

// Check if job_id is provided
if (!isset($_GET['job_id']) || !is_numeric($_GET['job_id'])) {
    echo "<div class='card error'><h2>Error</h2><p>Invalid Job ID.</p></div>";
    echo "<a href='index.php?action=teacher_job_search' class='button secondary'>Back to Job Search</a>";
    exit;
}

$job_id = (int)$_GET['job_id'];
$job = R::load('job', $job_id);

if (!$job->id) {
    echo "<div class='card error'><h2>Error</h2><p>Job not found.</p></div>";
    echo "<a href='index.php?action=teacher_job_search' class='button secondary'>Back to Job Search</a>";
    exit;
}

// Check if the teacher has already applied for the job
$existingApplication = R::findOne('applications', ' teacher_id = ? AND job_id = ?', [$teacher_id, $job_id]);
if ($existingApplication) {
    echo "<div class='card info'><h2>Info</h2><p>You have already applied for this job.</p></div>";
    echo "<a href='index.php?action=teacher_job_search' class='button secondary'>Back to Job Search</a>";
    exit;
}

// Handle the application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Create a new application record
    $application = R::dispense('applications');
    $application->teacher_id = $teacher_id;
    $application->job_id = $job_id;
    $application->application_date = R::isoDateTime();

    R::store($application);

    echo "<div class='card success'><h2>Success!</h2><p>Your application has been submitted.</p></div>";
    echo "<a href='index.php?action=teacher_job_search' class='button secondary'>Back to Job Search</a>";
    exit;
}
?>

<div class="card">
    <h2>Apply for Job</h2>
    <p>Confirm Application for:</p>
    <p><strong><?php echo htmlspecialchars($job->title); ?></strong> -
        <?php
        $school = R::load('school', $job->school_id);
        echo htmlspecialchars($school->name);
        ?>
    </p>
    <p>Are you sure you want to apply for this job?</p>

    <form method="POST" action="">
        <button type="submit" class="button primary">Confirm Application</button>
        <a href="index.php?action=teacher_job_search" class="button secondary">Cancel</a>
    </form>
</div>
