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

<article class="card" style="max-width: 80%; margin: 2rem auto;">
    <header>
        <h2 style="text-align: center;">Administrator Dashboard</h2>
    </header>

    <div style="padding: 1rem;">
        <p>Welcome, <strong><?= h($authUser['name']) ?></strong> (<?= h($authUser['email']) ?>)</p>
        
        <div class="grid" style="margin-bottom: 2rem;">
            <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; text-align: center;">
                <h4>Registered Teachers</h4>
                <h2><?= $teacherCount ?></h2>
            </div>
            <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; text-align: center;">
                <h4>Registered Schools</h4>
                <h2><?= $schoolCount ?></h2>
            </div>
            <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; text-align: center;">
                <h4>Active Job Posts</h4>
                <h2><?= $jobCount ?></h2>
            </div>
        </div>

        <h3>Quick Navigation & Directories</h3>
        <nav>
            <ul>
                <li><a href="?action=public_school_search" role="button" class="secondary">Public Schools (<?= $publicCount ?>)</a></li>
                <li><a href="?action=private_school_search" role="button" class="secondary">Private Schools (<?= $privateCount ?>)</a></li>
                <li><a href="?action=international_school_search" role="button" class="secondary">International Schools (<?= $intlCount ?>)</a></li>
                <li><a href="?action=blog_admin" role="button" class="secondary">Manage Blog Articles</a></li>
            </ul>
        </nav>

        <a href="?action=logout" role="button" class="contrast" style="margin-top: 1rem;">Logout</a>
    </div>
</article>
