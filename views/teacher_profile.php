<?php
// teacher_profile.php - Clean Educator Profile & CV
require_auth();

$authUser = auth_user();
$isTeacher = ($authUser['role'] === 'teacher');
$isSchool = ($authUser['role'] === 'school');

$teacher_id = intval($_GET['teacher_id'] ?? ($_GET['id'] ?? 0));
if (!$teacher_id && $isTeacher) {
    $teacher_id = $authUser['user_id'];
}

$teacher = R::load('teacher', $teacher_id);
if (!$teacher->id) {
    echo "<div class='container' style='padding: 2rem;'><div style='background: #fee2e2; padding: 1.5rem; border-radius: 8px; color: #991b1b;'>Teacher profile not found.</div></div>";
    exit();
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane">
        <div style="max-width: 860px; margin: 0 auto;">
            
            <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <?php if ($isSchool): ?>
                        <a href="/school/search-candidates" style="color: #64748b; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-arrow-left"></i> Back to Candidate Search
                        </a>
                    <?php else: ?>
                        <a href="/teacher/dashboard" style="color: #64748b; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-arrow-left"></i> Back to Dashboard
                        </a>
                    <?php endif; ?>
                </div>

                <div style="display: flex; gap: 8px;">
                    <?php if (!empty($teacher->mobile)): ?>
                        <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $teacher->mobile) ?>?text=<?= urlencode("Hello {$teacher->name}, we saw your educator profile on MwalimuLink and would like to get in touch.") ?>" target="_blank" style="background: #25d366; color: white !important; font-size: 0.85rem; font-weight: 600; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa-brands fa-whatsapp"></i> WhatsApp Candidate
                        </a>
                    <?php endif; ?>

                    <?php if ($isTeacher && $teacher->id == $authUser['user_id']): ?>
                        <a href="/teacher/update" class="btn-primary" style="font-size: 0.85rem; padding: 8px 16px;">
                            <i class="fa fa-edit"></i> Edit Profile
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Header Card -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 1.5rem;">
                <div style="display: flex; gap: 1.5rem; align-items: center; flex-wrap: wrap;">
                    <div style="width: 76px; height: 76px; border-radius: 50%; background: #0f766e; color: white; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 800;">
                        <?= strtoupper(substr($teacher->name, 0, 1)) ?>
                    </div>
                    <div>
                        <h2 style="margin: 0 0 4px; font-size: 1.4rem; color: #0f172a;"><?= h($teacher->name) ?></h2>
                        <p style="margin: 0 0 8px; font-size: 0.88rem; color: #64748b;">
                            <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($teacher->county ?: 'Kenya') ?> &bull; 
                            <?= h($teacher->qualification ?: 'Educator') ?>
                        </p>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <?php if (!empty($teacher->tsc_number)): ?>
                                <span style="background: #dcfce7; color: #166534; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 4px;">
                                    ✓ TSC Registered: <?= h($teacher->tsc_number) ?>
                                </span>
                            <?php else: ?>
                                <span style="background: #f1f5f9; color: #64748b; font-size: 0.75rem; font-weight: 600; padding: 3px 8px; border-radius: 4px;">
                                    Candidate / Tutor
                                </span>
                            <?php endif; ?>

                            <span style="background: #e0f2fe; color: #0369a1; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 12px;">
                                🟢 <?= ucfirst($teacher->status ?: 'Available') ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Details Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                
                <!-- Academic & Teaching Details -->
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                    <h4 style="margin: 0 0 1rem; font-size: 1rem; color: #0f766e; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                        <i class="fa fa-graduation-cap"></i> Teaching Specialization
                    </h4>
                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 0.88rem;">
                        <div>
                            <strong style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; display: block;">Teaching Subjects:</strong>
                            <span style="color: #0f172a; font-weight: 600;"><?= h($teacher->teaching_subjects ?: 'General') ?></span>
                        </div>
                        <div>
                            <strong style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; display: block;">Highest Qualification:</strong>
                            <span style="color: #0f172a;"><?= h($teacher->qualification ?: 'N/A') ?></span>
                        </div>
                        <div>
                            <strong style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; display: block;">Institutions Attended:</strong>
                            <span style="color: #0f172a;"><?= h($teacher->institutions_attended ?: 'N/A') ?></span>
                        </div>
                        <div>
                            <strong style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; display: block;">Years of Experience:</strong>
                            <span style="color: #0f172a;"><?= intval($teacher->years_of_experience) ?> Years</span>
                        </div>
                    </div>
                </div>

                <!-- Contact & Bio Details -->
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                    <h4 style="margin: 0 0 1rem; font-size: 1rem; color: #0f766e; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                        <i class="fa fa-address-card"></i> Contact Information
                    </h4>
                    <div style="display: flex; flex-direction: column; gap: 10px; font-size: 0.88rem;">
                        <div>
                            <strong style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; display: block;">Phone Number:</strong>
                            <span style="color: #0f172a;"><?= h($teacher->mobile) ?></span>
                        </div>
                        <div>
                            <strong style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; display: block;">Email Address:</strong>
                            <a href="mailto:<?= h($teacher->email) ?>" style="color: #0f766e;"><?= h($teacher->email) ?></a>
                        </div>
                        <div>
                            <strong style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; display: block;">County of Residence:</strong>
                            <span style="color: #0f172a;"><?= h($teacher->county ?: 'Kenya') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Professional Bio -->
            <?php if (!empty($teacher->brief_profile)): ?>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                    <h4 style="margin: 0 0 0.75rem; font-size: 1rem; color: #0f766e; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                        <i class="fa fa-book-open"></i> Professional Summary & Background
                    </h4>
                    <p style="margin: 0; color: #334155; font-size: 0.88rem; line-height: 1.7;">
                        <?= nl2br(h($teacher->brief_profile)) ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
