<?php
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $requirements = trim($_POST['requirements'] ?? '');
    $salary = !empty($_POST['salary']) ? (float)$_POST['salary'] : null;

    if (!empty($title) && !empty($description)) {
        $job = R::dispense('job');
        $job->school_id = $school_id;
        $job->title = $title;
        $job->description = $description;
        $job->requirements = $requirements;
        $job->salary = $salary;
        $job->posted_date = date('Y-m-d H:i:s');

        R::store($job);
        $successMessage = "Job vacancy '$title' posted successfully!";
    }
}
?>

<div class="container" style="max-width: 700px; margin: 2rem auto;">
    <article class="card">
        <header>
            <h2><i class="fas fa-briefcase"></i> Post a Job Vacancy</h2>
        </header>

        <?php if ($successMessage): ?>
            <div class="alert alert-success" style="padding: 1rem; background: #d4edda; color: #155724; border-radius: 5px; margin-bottom: 1rem;">
                <h4><i class="fas fa-check-circle"></i> <?= h($successMessage) ?></h4>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrf_field() ?>
        
        <!-- Job Title -->
        <label><i class="fas fa-heading"></i> Job Title:</label>
        <input type="text" name="title" required class="w3-input w3-border w3-round"><br>

        <!-- Job Description -->
        <label><i class="fas fa-align-left"></i> Job Description:</label>
        <textarea name="description" required class="w3-input w3-border w3-round"></textarea><br>

        <!-- Requirements -->
        <label><i class="fas fa-list-alt"></i> Requirements:</label>
        <textarea name="requirements" required class="w3-input w3-border w3-round"></textarea><br>

        <!-- Salary -->
        <label><i class="fas fa-dollar-sign"></i> Salary:</label>
        <input type="number" name="salary" required class="w3-input w3-border w3-round"><br>

        <!-- Submit Button -->
        <button type="submit" class="w3-button w3-blue w3-round-large">
            <i class="fas fa-paper-plane"></i> Post Job
        </button>
    </form>
</div><br/><br/>
