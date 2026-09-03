<?php
require_auth('teacher');

$authUser = auth_user();

// Fetch all job postings
$jobs = R::findAll('job', 'ORDER BY posted_date DESC');
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0; font-size: 1.4rem; color: #0f172a;">Teaching Job Vacancies</h2>
                <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">
                    Browse active teaching opportunities posted by verified institutions across Kenya.
                </p>
            </div>
            <div>
                <a href="/teacher/dashboard" style="background: #e2e8f0; color: #334155; font-size: 0.85rem; font-weight: 600; padding: 8px 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <?php if (empty($jobs)): ?>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; padding: 3rem 1.5rem;">
                <i class="fa fa-briefcase fa-3x" style="color: #cbd5e1; margin-bottom: 1rem;"></i>
                <h4 style="color: #0f172a; margin: 0 0 6px;">No Active Vacancies Right Now</h4>
                <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 1.25rem;">
                    Schools regularly post new vacancies. Check back soon or explore the school directory!
                </p>
                <a href="/schools/public" class="btn-primary">Explore School Directory</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($jobs as $job): ?>
                    <?php $school = R::load('school', $job->school_id); ?>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: transform 0.15s ease, box-shadow 0.15s ease;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <h3 style="margin: 0 0 4px; font-size: 1.15rem; color: #0f172a;">
                                    <a href="/teacher/apply?job_id=<?= $job->id ?>" style="color: #0f172a; text-decoration: none;"><?= h($job->title) ?></a>
                                </h3>
                                <p style="margin: 0; font-size: 0.84rem; color: #64748b;">
                                    <i class="fa fa-school" style="color: #0f766e;"></i> <strong><?= h($school->name ?? 'Registered School') ?></strong> &bull; 
                                    <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($school->county ?? 'Kenya') ?>
                                </p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <?php if (!empty($job->salary)): ?>
                                    <span style="background: #dcfce7; color: #166534; font-size: 0.8rem; font-weight: 700; padding: 4px 10px; border-radius: 12px;">
                                        KES <?= number_format($job->salary) ?> / mo
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p style="color: #334155; font-size: 0.88rem; line-height: 1.6; margin: 0 0 1rem;">
                            <?= nl2br(h($job->description)) ?>
                        </p>

                        <?php if (!empty($job->requirements)): ?>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem 1rem; margin-bottom: 1.25rem;">
                                <span style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; display: block; margin-bottom: 4px;">Requirements:</span>
                                <p style="margin: 0; font-size: 0.82rem; color: #475569; line-height: 1.5;"><?= nl2br(h($job->requirements)) ?></p>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 1rem;">
                            <span style="font-size: 0.78rem; color: #94a3b8;">
                                <i class="fa fa-clock"></i> Posted <?= date('M d, Y', strtotime($job->posted_date ?? 'now')) ?>
                            </span>
                            <div style="display: flex; gap: 8px;">
                                <a href="/teacher/apply?job_id=<?= $job->id ?>" style="background: #0f766e; color: white !important; font-size: 0.84rem; font-weight: 600; padding: 7px 18px; border-radius: 6px; text-decoration: none;">
                                    Apply Now &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>
