<?php
// teacher_update.php - Modern Profile Editor
require_auth('teacher');

$authUser = auth_user();
$teacher_id = $authUser['user_id'];
$teacher = R::load('teacher', $teacher_id);

if (!$teacher->id) {
    header("Location: /logout");
    exit();
}

$msg = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $teacher->name = trim($_POST['name'] ?? $teacher->name);
        $teacher->gender = trim($_POST['gender'] ?? $teacher->gender);
        $teacher->year_of_birth = intval($_POST['year_of_birth'] ?? $teacher->year_of_birth);
        $teacher->mobile = trim($_POST['mobile'] ?? $teacher->mobile);
        $teacher->email = trim($_POST['email'] ?? $teacher->email);

        if (!empty($_POST['password'])) {
            $teacher->password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        $teacher->tsc_number = trim($_POST['tsc_number'] ?? $teacher->tsc_number);
        $teacher->years_of_experience = intval($_POST['years_of_experience'] ?? $teacher->years_of_experience);
        $teacher->grade_levels = trim($_POST['grade_levels'] ?? $teacher->grade_levels);
        $teacher->teaching_subjects = trim($_POST['teaching_subjects'] ?? $teacher->teaching_subjects);
        $teacher->qualification = trim($_POST['qualification'] ?? $teacher->qualification);
        $teacher->institutions_attended = trim($_POST['institutions_attended'] ?? $teacher->institutions_attended);
        $teacher->county = trim($_POST['county'] ?? $teacher->county);
        $teacher->country = trim($_POST['country'] ?? 'Kenya');
        $teacher->brief_profile = trim($_POST['brief_profile'] ?? $teacher->brief_profile);
        $teacher->status = trim($_POST['status'] ?? $teacher->status);

        R::store($teacher);
        $msg = "Your profile and CV details have been successfully updated!";
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane">
        <div style="max-width: 860px; margin: 0 auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin: 0; font-size: 1.4rem; color: #0f172a;">Edit Educator Profile & CV</h2>
                    <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">
                        Keep your academic credentials, teaching subjects, and contact details up to date.
                    </p>
                </div>
                <div>
                    <a href="/teacher/profile?teacher_id=<?= $teacher->id ?>" style="background: #0f766e; color: white !important; font-size: 0.85rem; font-weight: 600; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-eye"></i> View Public Profile
                    </a>
                </div>
            </div>

            <?php if ($msg): ?>
                <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem;">
                    <i class="fa fa-check-circle"></i> <?= h($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem;">
                    <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <form method="POST" action="/teacher/update" style="margin: 0;">
                    <?= csrf_field() ?>

                    <h4 style="margin: 0 0 1.25rem; font-size: 1rem; color: #0f766e; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                        <i class="fa fa-user"></i> Personal & Contact Information
                    </h4>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Full Name</label>
                            <input type="text" name="name" value="<?= h($teacher->name) ?>" required style="width: 100%; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Email Address</label>
                            <input type="email" name="email" value="<?= h($teacher->email) ?>" required style="width: 100%; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Mobile Phone (WhatsApp)</label>
                            <input type="text" name="mobile" value="<?= h($teacher->mobile) ?>" required style="width: 100%; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Gender</label>
                            <select name="gender" style="width: 100%; box-sizing: border-box; background: white;">
                                <option value="Male" <?= ($teacher->gender === 'Male') ? 'selected' : '' ?>>Male</option>
                                <option value="Female" <?= ($teacher->gender === 'Female') ? 'selected' : '' ?>>Female</option>
                            </select>
                        </div>
                    </div>

                    <h4 style="margin: 1.5rem 0 1.25rem; font-size: 1rem; color: #0f766e; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.5rem;">
                        <i class="fa fa-graduation-cap"></i> Professional & Academic Qualifications
                    </h4>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">TSC Registration Number</label>
                            <input type="text" name="tsc_number" value="<?= h($teacher->tsc_number) ?>" placeholder="e.g. 976775 (leave blank if non-TSC)" style="width: 100%; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Highest Qualification</label>
                            <select name="qualification" style="width: 100%; box-sizing: border-box; background: white;">
                                <option value="Certificate" <?= ($teacher->qualification === 'Certificate') ? 'selected' : '' ?>>Certificate</option>
                                <option value="Diploma" <?= ($teacher->qualification === 'Diploma') ? 'selected' : '' ?>>Diploma</option>
                                <option value="Bachelor's Degree" <?= ($teacher->qualification === "Bachelor's Degree" || $teacher->qualification === 'Degree') ? 'selected' : '' ?>>Bachelor's Degree</option>
                                <option value="Postgraduate Diploma" <?= ($teacher->qualification === 'Postgraduate Diploma') ? 'selected' : '' ?>>Postgraduate Diploma</option>
                                <option value="Master's Degree" <?= ($teacher->qualification === "Master's Degree") ? 'selected' : '' ?>>Master's Degree</option>
                                <option value="PhD / Doctorate" <?= ($teacher->qualification === 'PhD / Doctorate') ? 'selected' : '' ?>>PhD / Doctorate</option>
                            </select>
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Teaching Subject Combination</label>
                            <input type="text" name="teaching_subjects" value="<?= h($teacher->teaching_subjects) ?>" placeholder="e.g. Mathematics / Physics, English / Lit" required style="width: 100%; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Years of Experience</label>
                            <input type="number" name="years_of_experience" value="<?= intval($teacher->years_of_experience) ?>" min="0" max="50" style="width: 100%; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">County Location</label>
                            <select name="county" style="width: 100%; box-sizing: border-box; background: white;">
                                <?php foreach (kenyan_counties() as $c): ?>
                                    <option value="<?= h($c) ?>" <?= ($teacher->county === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Institutions Attended</label>
                            <input type="text" name="institutions_attended" value="<?= h($teacher->institutions_attended) ?>" placeholder="e.g. Kenyatta University" style="width: 100%; box-sizing: border-box;">
                        </div>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Professional Bio / Summary</label>
                        <textarea name="brief_profile" rows="4" style="width: 100%; box-sizing: border-box; font-family: inherit;"><?= h($teacher->brief_profile) ?></textarea>
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <a href="/teacher/dashboard" style="background: #f1f5f9; color: #475569; padding: 10px 18px; border-radius: 6px; font-weight: 600; text-decoration: none;">Cancel</a>
                        <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 0.9rem;">
                            <i class="fa fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>