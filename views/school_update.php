<?php
// views/school_update.php - School Profile, Institution Details & Account Security Settings
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];
$school = R::load('school', $school_id);

if (!$school->id) {
    header("Location: /logout");
    exit();
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $section = $_POST['section'] ?? 'all';

        // 1. Institution Identity & Contact Info
        if (in_array($section, ['all', 'profile'])) {
            $school->name = trim($_POST['name'] ?? $school->name);
            $school->county = trim($_POST['county'] ?? $school->county);
            $school->sub_county = trim($_POST['sub_county'] ?? ($school->sub_county ?? ''));
            $school->level = trim($_POST['level'] ?? $school->level);
            $school->curriculum = trim($_POST['curriculum'] ?? ($school->curriculum ?? 'CBC'));
            $school->contact_person = trim($_POST['contact_person'] ?? ($school->contact_person ?? ''));
            $school->about_school = trim($_POST['about_school'] ?? ($school->about_school ?? ''));

            if (isset($_POST['phone_number'])) {
                $rawPhone = trim($_POST['phone_number']);
                $school->phone_number = function_exists('normalize_kenyan_phone') ? normalize_kenyan_phone($rawPhone) : $rawPhone;
            }

            // Email Collision Validation
            $newEmail = trim($_POST['email'] ?? $school->email);
            if (!empty($newEmail) && $newEmail !== $school->email) {
                if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                    $error = "Please provide a valid email address.";
                } else {
                    $dupSchool = R::findOne('school', 'email = ? AND id != ?', [$newEmail, $school->id]);
                    $dupTeacher = R::findOne('teacher', 'email = ?', [$newEmail]);
                    if ($dupSchool || $dupTeacher) {
                        $error = "This email is already associated with another account.";
                    } else {
                        $school->email = $newEmail;
                    }
                }
            }

            // Logo / Crest Upload
            if (empty($error) && !empty($_FILES['school_logo']['name'])) {
                $logoUpload = secure_validate_and_upload(
                    $_FILES['school_logo'],
                    'uploads/photos/',
                    ['jpg', 'jpeg', 'png', 'webp'],
                    5 * 1024 * 1024
                );
                if ($logoUpload['success']) {
                    $school->logo = $logoUpload['relative_path'];
                } else {
                    $error = $logoUpload['error'];
                }
            }
        }

        // 2. Account Security & Password
        if (in_array($section, ['all', 'security']) && empty($error)) {
            if (!empty($_POST['password'])) {
                $newPass = $_POST['password'];
                $confirmPass = $_POST['password_confirm'] ?? '';
                if (strlen($newPass) < 8) {
                    $error = "New password must be at least 8 characters long.";
                } elseif ($newPass !== $confirmPass) {
                    $error = "New password and password confirmation do not match.";
                } else {
                    $school->password = password_hash($newPass, PASSWORD_DEFAULT);
                }
            }
        }

        if (empty($error)) {
            R::store($school);
            $msg = "School profile and account settings updated successfully!";
        }
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane" style="background: #f8fafc; padding: 2rem !important; min-height: 100vh;">
        <div style="max-width: 860px; margin: 0 auto;">

            <!-- Header Strip -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem; background: white; padding: 1.5rem 1.75rem; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="display: flex; gap: 16px; align-items: center;">
                    <div style="width: 56px; height: 56px; border-radius: 10px; background: #0f766e; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; overflow: hidden; font-weight: 800;">
                        <?php if (!empty($school->logo)): ?>
                            <img src="/<?= h($school->logo) ?>" alt="Logo" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <?= strtoupper(substr($school->name ?: 'S', 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2 style="margin: 0; font-size: 1.35rem; color: #0f172a; font-weight: 800;">
                            <?= h($school->name) ?> &mdash; Profile & Settings
                        </h2>
                        <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">
                            <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($school->county ?: 'Kenya') ?> &bull; 
                            <i class="fa fa-envelope" style="color: #0f766e;"></i> <?= h($school->email) ?>
                        </p>
                    </div>
                </div>
                <div>
                    <a href="/school/dashboard" style="background: #f1f5f9; color: #334155; font-size: 0.85rem; font-weight: 600; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0;">
                        <i class="fa fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>

            <?php if ($msg): ?>
                <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 14px 18px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fa fa-check-circle fa-lg"></i>
                    <span><?= h($msg) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fa fa-exclamation-circle fa-lg"></i>
                    <span><?= h($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Form Card -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <form method="POST" action="/school/update" enctype="multipart/form-data" style="margin: 0;">
                    <?= csrf_field() ?>

                    <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-top: 0; margin-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-school" style="color: #0f766e;"></i> 1. School Identity & Location
                    </h3>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.25rem;">
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Official School Name <span style="color: red;">*</span>
                            </label>
                            <input type="text" name="name" value="<?= h($school->name) ?>" required style="width: 100%; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                County <span style="color: red;">*</span>
                            </label>
                            <select name="county" required style="width: 100%; box-sizing: border-box; background: white; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                                <?php foreach (kenyan_counties() as $c): ?>
                                    <option value="<?= h($c) ?>" <?= ($school->county === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Sub-County / Physical Location
                            </label>
                            <input type="text" name="sub_county" value="<?= h($school->sub_county ?? '') ?>" placeholder="e.g. Westlands / Ring Road" style="width: 100%; box-sizing: border-box;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                School Level
                            </label>
                            <select name="level" style="width: 100%; box-sizing: border-box; background: white; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                                <option value="Secondary" <?= ($school->level === 'Secondary') ? 'selected' : '' ?>>Senior Secondary / High School</option>
                                <option value="Junior Secondary" <?= ($school->level === 'Junior Secondary') ? 'selected' : '' ?>>Junior School (JSS)</option>
                                <option value="Primary" <?= ($school->level === 'Primary') ? 'selected' : '' ?>>Primary School</option>
                                <option value="ECD" <?= ($school->level === 'ECD') ? 'selected' : '' ?>>ECD / Kindergarten</option>
                                <option value="Mixed" <?= ($school->level === 'Mixed') ? 'selected' : '' ?>>Comprehensive (ECD to Secondary)</option>
                                <option value="International" <?= ($school->level === 'International') ? 'selected' : '' ?>>International Academy</option>
                                <option value="Tertiary" <?= ($school->level === 'Tertiary') ? 'selected' : '' ?>>Tertiary / College</option>
                            </select>
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Curriculum
                            </label>
                            <select name="curriculum" style="width: 100%; box-sizing: border-box; background: white; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem;">
                                <option value="CBC" <?= (($school->curriculum ?? 'CBC') === 'CBC') ? 'selected' : '' ?>>CBC</option>
                                <option value="8-4-4" <?= (($school->curriculum ?? '') === '8-4-4') ? 'selected' : '' ?>>8-4-4</option>
                                <option value="IGCSE" <?= (($school->curriculum ?? '') === 'IGCSE') ? 'selected' : '' ?>>IGCSE / Cambridge</option>
                                <option value="IB" <?= (($school->curriculum ?? '') === 'IB') ? 'selected' : '' ?>>IB (International Baccalaureate)</option>
                                <option value="British" <?= (($school->curriculum ?? '') === 'British') ? 'selected' : '' ?>>British National Curriculum</option>
                                <option value="American" <?= (($school->curriculum ?? '') === 'American') ? 'selected' : '' ?>>American Curriculum</option>
                            </select>
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                School Crest / Logo
                            </label>
                            <input type="file" name="school_logo" accept=".jpg,.jpeg,.png,.webp" style="font-size: 0.82rem; color: #475569;">
                        </div>
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                            About the School & Mission
                        </label>
                        <textarea name="about_school" rows="3" placeholder="Brief overview of the school, mission, culture, and academic ethos..." style="width: 100%; box-sizing: border-box; font-family: inherit;"><?= h($school->about_school ?? '') ?></textarea>
                    </div>

                    <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-top: 2rem; margin-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-id-card" style="color: #0f766e;"></i> 2. Recruitment Officer & Contacts
                    </h3>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Contact Person / Recruiter Name
                            </label>
                            <input type="text" name="contact_person" value="<?= h($school->contact_person ?? '') ?>" placeholder="e.g. Principal / HR Manager" style="width: 100%; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Official Email Address <span style="color: red;">*</span>
                            </label>
                            <input type="email" name="email" value="<?= h($school->email) ?>" required style="width: 100%; box-sizing: border-box;">
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Contact Phone (M-Pesa / SMS) <span style="color: red;">*</span>
                            </label>
                            <input type="tel" name="phone_number" value="<?= h($school->phone_number ?? '') ?>" required placeholder="07xx xxx xxx" style="width: 100%; box-sizing: border-box;">
                        </div>
                    </div>

                    <h3 style="font-size: 1.05rem; font-weight: 800; color: #0f172a; margin-top: 2rem; margin-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-lock" style="color: #0f766e;"></i> 3. Change Account Password
                    </h3>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                New Password
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="password" id="school_update_password" name="password" placeholder="Leave blank to keep current" minlength="8" style="width: 100%; box-sizing: border-box; padding-right: 42px; margin: 0 !important;">
                                <span role="button" tabindex="0" onclick="togglePasswordVisibility('school_update_password', 'school_eye_pass')" class="password-eye-toggle" title="Show/Hide Password">
                                    <i class="fa fa-eye" id="school_eye_pass"></i>
                                </span>
                            </div>
                        </div>

                        <div>
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Confirm New Password
                            </label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <input type="password" id="school_update_password_confirm" name="password_confirm" placeholder="Confirm new password" minlength="8" style="width: 100%; box-sizing: border-box; padding-right: 42px; margin: 0 !important;">
                                <span role="button" tabindex="0" onclick="togglePasswordVisibility('school_update_password_confirm', 'school_eye_pass_confirm')" class="password-eye-toggle" title="Show/Hide Password">
                                    <i class="fa fa-eye" id="school_eye_pass_confirm"></i>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div style="border-top: 1px solid #f1f5f9; padding-top: 1.5rem; display: flex; justify-content: flex-end; gap: 10px;">
                        <a href="/school/dashboard" style="background: #f1f5f9; color: #475569; padding: 10px 20px; border-radius: 6px; font-weight: 600; text-decoration: none;">
                            Cancel
                        </a>
                        <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 0.9rem;">
                            <i class="fa fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<script>
    function togglePasswordVisibility(inputId, iconId) {
        const input = document.getElementById(inputId);
        const icon = document.getElementById(iconId);
        if (input && icon) {
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    }
</script>
