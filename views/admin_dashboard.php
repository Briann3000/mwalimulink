<?php
// admin_dashboard.php
require_auth('admin');

$authUser = auth_user();
$teacherCount = R::count('teacher');
$schoolCount = R::count('school');
$publicCount = R::count('public_school');
$privateCount = R::count('private_school');
$intlCount = R::count('international_school');
$jobCount = R::count('job');
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane">
        <div style="max-width: 1000px; margin: 0 auto;">
            <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin: 0; color: #0f172a;">Administrator Dashboard</h2>
                    <p style="margin: 4px 0 0; font-size: 0.88rem; color: #64748b;">Logged in as <strong><?= h($authUser['name']) ?></strong> (<?= h($authUser['email']) ?>)</p>
                </div>
                <div>
                    <a href="/admin/verifications" style="background: #0f766e; color: white !important; font-size: 0.85rem; font-weight: 700; padding: 8px 16px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-shield-check"></i> Clearance Queue
                    </a>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); text-align: center;">
                    <span style="font-size: 0.82rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Registered Teachers</span>
                    <h2 style="margin: 8px 0 0; color: #0f766e; font-size: 2rem;"><?= $teacherCount ?></h2>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); text-align: center;">
                    <span style="font-size: 0.82rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Registered Schools</span>
                    <h2 style="margin: 8px 0 0; color: #0f766e; font-size: 2rem;"><?= $schoolCount ?></h2>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); text-align: center;">
                    <span style="font-size: 0.82rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Active Job Posts</span>
                    <h2 style="margin: 8px 0 0; color: #0f766e; font-size: 2rem;"><?= $jobCount ?></h2>
                </div>
            </div>

            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <h3 style="margin: 0 0 1rem; font-size: 1.1rem; color: #0f172a;">Quick Navigation & Directories</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <a href="/admin/verifications" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1rem; text-decoration: none; color: #0f172a; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-shield-check" style="color: #0f766e;"></i> Educator Clearance Queue
                    </a>
                    <a href="/schools/public" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1rem; text-decoration: none; color: #0f172a; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-landmark" style="color: #0f766e;"></i> Public Schools (<?= $publicCount ?>)
                    </a>
                    <a href="/schools/private" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1rem; text-decoration: none; color: #0f172a; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-building" style="color: #0f766e;"></i> Private Schools (<?= $privateCount ?>)
                    </a>
                    <a href="/schools/international" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1rem; text-decoration: none; color: #0f172a; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-globe" style="color: #0f766e;"></i> International (<?= $intlCount ?>)
                    </a>
                    <a href="/blog/admin" style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1rem; text-decoration: none; color: #0f172a; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-newspaper" style="color: #0f766e;"></i> Manage Blog Articles
                    </a>
                </div>
            </div>
        </div>
    </main>
</div>
