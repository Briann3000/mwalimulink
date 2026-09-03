<?php
require_auth('teacher');

// Fetch all job postings
$jobs = R::findAll('job', 'ORDER BY posted_date DESC');
?>

<div class="container" style="max-width: 900px; margin: 2rem auto;">
    <article class="card">
        <header>
            <h2>Teaching Job Opportunities</h2>
            <p>Browse available vacancies posted by registered schools.</p>
        </header>

        <?php if (empty($jobs)): ?>
            <p>No job postings currently available. Please check back soon!</p>
        <?php else: ?>
            <div class="grid">
                <?php foreach ($jobs as $job): ?>
                    <?php $school = R::load('school', $job->school_id); ?>
                    <article class="card" style="margin-bottom: 1.5rem;">
                        <header>
                            <h3><?= h($job->title) ?></h3>
                            <p><strong>School:</strong> <?= h($school->name ?? 'School') ?> | <strong>County:</strong> <?= h($school->county ?? 'Kenya') ?></p>
                        </header>

                        <p><?= nl2br(h($job->description)) ?></p>

                        <?php if (!empty($job->requirements)): ?>
                            <p><strong>Requirements:</strong><br><?= nl2br(h($job->requirements)) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($job->salary)): ?>
                            <p><strong>Salary Range:</strong> KES <?= number_format($job->salary) ?></p>
                        <?php endif; ?>

                        <footer>
                            <a href="index.php?action=teacher_job_apply&job_id=<?= $job->id ?>" class="button primary">Apply Now</a>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <a href="index.php?action=teacher_dashboard" role="button" class="secondary" style="margin-top: 1rem;">Back to Dashboard</a>
    </article>
</div>
