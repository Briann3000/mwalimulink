<?php
// teacher_profile.php
if (!is_logged_in()) {
    header("Location: index.php?action=landing");
    exit();
}

$authUser = auth_user();

// Teacher viewing own profile or School/Admin viewing a candidate
if (isset($_GET['id']) && (has_role('school') || has_role('admin'))) {
    $teacher_id = intval($_GET['id']);
} elseif (has_role('teacher')) {
    $teacher_id = $authUser['user_id'];
} else {
    header("Location: index.php?action=landing");
    exit();
}

// Fetch the teacher's data from the database
$teacher = R::load('teacher', $teacher_id);

if (!$teacher->id) {
    echo "<div class='container'><article class='message -error'>Teacher profile not found.</article></div>";
    exit();
}
?>

<article class="card" style="max-width: 80%; margin: 0 auto;">
    <header>
        <h2>Teacher Profile: <?php echo htmlspecialchars($teacher->name ?? ''); ?></h2>
    </header>

    <div style="padding: 1rem;">
        <h3>Personal Details</h3>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($teacher->name ?? ''); ?></p>
        <p><strong>Gender:</strong> <?php echo htmlspecialchars($teacher->gender ?? ''); ?></p>
        <p><strong>Year of Birth:</strong> <?php echo htmlspecialchars($teacher->year_of_birth ?? ''); ?></p>
        <p><strong>Mobile:</strong> <?php echo htmlspecialchars($teacher->mobile ?? ''); ?></p>
        <p><strong>Email:</strong> <a
                href="mailto:<?php echo htmlspecialchars($teacher->email ?? ''); ?>"><?php echo htmlspecialchars($teacher->email ?? ''); ?></a>
        </p>

        <h3>Professional Details</h3>
        <p><strong>TSC Status:</strong> 
            <?php if (!empty($teacher->tsc_number)): ?>
                <span style="display: inline-block; background: #e8f5e9; color: #2e7d32; padding: 2px 8px; border-radius: 4px; font-weight: bold;">
                    ✓ TSC Registered (#<?= h($teacher->tsc_number) ?>)
                </span>
            <?php else: ?>
                <span style="color: #666;">Pending / Private Tutor</span>
            <?php endif; ?>
        </p>
        <p><strong>Years of Experience:</strong> <?= h($teacher->years_of_experience ?? 0) ?> years</p>
        <p><strong>Grade Levels:</strong> <?= h($teacher->grade_levels ?? '') ?></p>
        <p><strong>Teaching Subjects:</strong> <?= h($teacher->teaching_subjects ?? '') ?></p>
        <p><strong>Qualification:</strong> <?= h($teacher->qualification ?? '') ?></p>
        <?php if (!empty($teacher->institutions_attended)): ?>
            <p><strong>Institution Attended:</strong> <?= h($teacher->institutions_attended) ?></p>
        <?php endif; ?>
        <?php if (!empty($teacher->responsibility)): ?>
            <p><strong>Roles / Responsibilities:</strong> <?= h($teacher->responsibility) ?></p>
        <?php endif; ?>

        <h3>Location & Availability</h3>
        <p><strong>County:</strong> <?= h($teacher->county ?? '') ?></p>
        <p><strong>Country:</strong> <?= h($teacher->country ?? 'Kenya') ?></p>
        <p><strong>Availability:</strong> <span style="text-transform: capitalize; color: #28a745; font-weight: bold;"><?= h($teacher->status ?? 'available') ?></span></p>

        <h3>Brief Profile</h3>
        <p><?php echo htmlspecialchars($teacher->brief_profile ?? ''); ?></p>

        <button type="button" onclick="location.href='mailto:<?php echo htmlspecialchars($teacher->email ?? ''); ?>'"
            style="width: 10rem; height: 3rem; display: flex; justify-content: center; align-items: center; background-color: #f7f7f2; color: black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-envelope fa-lg"></i>
            <span>Email Teacher</span>
        </button>
    </div>
</article>
