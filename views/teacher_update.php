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
        $section = $_POST['section'] ?? 'all';

        // 1. Personal & Contact Details
        if (in_array($section, ['all', 'personal'])) {
            $teacher->name = trim($_POST['name'] ?? $teacher->name);
            $teacher->gender = trim($_POST['gender'] ?? $teacher->gender);
            if (isset($_POST['year_of_birth'])) {
                $teacher->year_of_birth = intval($_POST['year_of_birth']);
            }
            $teacher->mobile = trim($_POST['mobile'] ?? $teacher->mobile);
            $teacher->email = trim($_POST['email'] ?? $teacher->email);
            $teacher->county = trim($_POST['county'] ?? $teacher->county);
            $teacher->country = trim($_POST['country'] ?? 'Kenya');
        }

        // 2. Academic & Teaching Qualifications
        if (in_array($section, ['all', 'academic'])) {
            $teacher->tsc_number = trim($_POST['tsc_number'] ?? $teacher->tsc_number);
            $teacher->qualification = trim($_POST['qualification'] ?? $teacher->qualification);
            $teacher->teaching_subjects = trim($_POST['teaching_subjects'] ?? $teacher->teaching_subjects);
            if (isset($_POST['years_of_experience'])) {
                $teacher->years_of_experience = intval($_POST['years_of_experience']);
            }
            $teacher->institutions_attended = trim($_POST['institutions_attended'] ?? $teacher->institutions_attended);
            $teacher->grade_levels = trim($_POST['grade_levels'] ?? $teacher->grade_levels);
        }

        // 3. Bio & Pitch
        if (in_array($section, ['all', 'bio'])) {
            $teacher->brief_profile = trim($_POST['brief_profile'] ?? $teacher->brief_profile);
        }

        // 4. Availability & Security
        if (in_array($section, ['all', 'security'])) {
            $teacher->status = trim($_POST['status'] ?? $teacher->status);
            if (!empty($_POST['password'])) {
                $teacher->password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
        }

        // 5. Safeguarding & Good Conduct
        if (in_array($section, ['all', 'compliance'])) {
            // Handle Reset Clearance action
            if (isset($_POST['compliance_action']) && $_POST['compliance_action'] === 'reset') {
                $teacher->verification_status = 'none';
                $teacher->verification_ref = null;
                $teacher->verified_at = null;
                $teacher->good_conduct_doc = null;
                $teacher->good_conduct_cert_no = null;
                $teacher->good_conduct_issue_date = null;
                $teacher->good_conduct_expiry_date = null;
                $msg = "Background clearance details have been reset. You can now submit fresh credentials.";
                $teacher->good_conduct_cert_no = trim($_POST['good_conduct_cert_no'] ?? ($teacher->good_conduct_cert_no ?? ''));
                $teacher->good_conduct_issue_date = trim($_POST['good_conduct_issue_date'] ?? ($teacher->good_conduct_issue_date ?? ''));
                $teacher->good_conduct_expiry_date = trim($_POST['good_conduct_expiry_date'] ?? ($teacher->good_conduct_expiry_date ?? ''));

                // Auto-calculate expiry if issue date is provided and expiry is empty (Kenyan Police Clearance is valid for 12 months)
                if (!empty($teacher->good_conduct_issue_date) && empty($teacher->good_conduct_expiry_date)) {
                    $teacher->good_conduct_expiry_date = date('Y-m-d', strtotime('+1 year', strtotime($teacher->good_conduct_issue_date)));
                }

                // Warn if certificate is already expired
                if (!empty($teacher->good_conduct_expiry_date) && strtotime($teacher->good_conduct_expiry_date) < time()) {
                    $error = "The provided Good Conduct certificate appears to be expired. DCI clearance certificates are valid for 12 months. Please provide an active certificate.";
                }

                // Document Upload with Deep Inspection
                if (empty($error) && !empty($_FILES['good_conduct_doc']['name'])) {
                    $uploadRes = secure_validate_and_upload($_FILES['good_conduct_doc'], 'uploads/documents/', ['pdf', 'jpg', 'jpeg', 'png'], 5 * 1024 * 1024);
                    if ($uploadRes['success']) {
                        $teacher->good_conduct_doc = $uploadRes['relative_path'];
                    } else {
                        $error = $uploadRes['error'];
                    }
                }

                // Screening request trigger (or Re-verification)
                if (isset($_POST['compliance_action']) && $_POST['compliance_action'] === 'reverify') {
                    if (empty($teacher->good_conduct_doc) && empty($teacher->good_conduct_cert_no)) {
                        $error = "Please upload your Certificate of Good Conduct or provide a Certificate Serial Number to request verification.";
                    } else {
                        $teacher->verification_status = 'pending';
                        $teacher->verification_ref = 'REQ-' . strtoupper(substr(md5(uniqid()), 0, 8));
                        $msg = "Your renewal credentials have been submitted. Processing typically takes 24–48 business hours. Once verified, the 'Verified Educator ✓' badge will automatically appear on your public profile.";
                    }
                } elseif (!empty($_POST['request_screening'])) {
                    if (empty($teacher->good_conduct_doc) && empty($teacher->good_conduct_cert_no)) {
                        $error = "Please upload your Certificate of Good Conduct or provide a Certificate Serial Number before submitting for screening.";
                    } else {
                        $teacher->verification_status = 'pending';
                        $teacher->verification_ref = $teacher->verification_ref ?? ('REQ-' . strtoupper(substr(md5(uniqid()), 0, 8)));
                        $msg = "Your credentials have been submitted. Processing typically takes 24–48 business hours. Once verified, the 'Verified Educator ✓' badge will automatically appear on your public profile.";
                    }
                } elseif (!empty($teacher->good_conduct_doc) && (empty($teacher->verification_status) || $teacher->verification_status === 'none')) {
                    $teacher->verification_status = 'pending';
                    $teacher->verification_ref = $teacher->verification_ref ?? ('REQ-' . strtoupper(substr(md5(uniqid()), 0, 8)));
                    $msg = "Your credentials have been submitted. Processing typically takes 24–48 business hours. Once verified, the 'Verified Educator ✓' badge will automatically appear on your public profile.";
                }
            }
        }

        if (empty($error)) {
            R::store($teacher);
            $sectionLabels = [
                'personal' => 'Personal & Contact Details',
                'academic' => 'Academic & Qualifications',
                'bio' => 'Professional Bio',
                'compliance' => 'Safeguarding & Clearance',
                'security' => 'Availability & Security'
            ];
            $savedName = $sectionLabels[$section] ?? 'Profile';
            if (empty($msg)) {
                $msg = "{$savedName} successfully updated!";
            }
        }
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane">
        <div style="max-width: 1200px; margin: 0 auto;">
            
            <!-- Top Header & Profile Link -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin: 0; font-size: 1.4rem; color: #0f172a;">Educator Profile & Document Hub</h2>
                    <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">
                        Update your professional details, credentials, and safeguarding clearance independently.
                    </p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <a href="/teacher/profile?teacher_id=<?= $teacher->id ?>" style="background: #0f766e; color: white !important; font-size: 0.85rem; font-weight: 600; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-eye"></i> View Public Profile
                    </a>
                </div>
            </div>

            <?php if ($msg): ?>
                <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-check-circle"></i> <?= h($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <!-- Interactive Section Navigation Tabs -->
            <div class="profile-section-tabs" style="display: flex; gap: 8px; margin-bottom: 1.5rem; overflow-x: auto; padding-bottom: 4px; border-bottom: 2px solid #e2e8f0;">
                <button type="button" class="tab-btn active" onclick="switchSection('personal', this)" style="background: transparent; border: none; border-bottom: 3px solid #0f766e; color: #0f766e; font-weight: 700; font-size: 0.9rem; padding: 10px 16px; border-radius: 0; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap; margin-bottom: -2px;">
                    <i class="fa fa-user-circle"></i> 1. Personal & Contact
                </button>
                <button type="button" class="tab-btn" onclick="switchSection('academic', this)" style="background: transparent; border: none; border-bottom: 3px solid transparent; color: #64748b; font-weight: 600; font-size: 0.9rem; padding: 10px 16px; border-radius: 0; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap; margin-bottom: -2px;">
                    <i class="fa fa-graduation-cap"></i> 2. Qualifications & Subjects
                </button>
                <button type="button" class="tab-btn" onclick="switchSection('bio', this)" style="background: transparent; border: none; border-bottom: 3px solid transparent; color: #64748b; font-weight: 600; font-size: 0.9rem; padding: 10px 16px; border-radius: 0; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap; margin-bottom: -2px;">
                    <i class="fa fa-book-open"></i> 3. Bio & Pitch
                </button>
                <button type="button" class="tab-btn" onclick="switchSection('compliance', this)" style="background: transparent; border: none; border-bottom: 3px solid transparent; color: #64748b; font-weight: 600; font-size: 0.9rem; padding: 10px 16px; border-radius: 0; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap; margin-bottom: -2px;">
                    <i class="fa fa-shield-halved"></i> 4. Good Conduct & Clearance
                </button>
                <button type="button" class="tab-btn" onclick="switchSection('security', this)" style="background: transparent; border: none; border-bottom: 3px solid transparent; color: #64748b; font-weight: 600; font-size: 0.9rem; padding: 10px 16px; border-radius: 0; cursor: pointer; display: flex; align-items: center; gap: 8px; white-space: nowrap; margin-bottom: -2px;">
                    <i class="fa fa-lock"></i> 5. Status & Security
                </button>
            </div>

            <!-- TAB PANE 1: Personal & Contact Details -->
            <div id="section-personal" class="profile-tab-pane" style="display: block;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <form method="POST" action="/teacher/update" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="personal">

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                            <div>
                                <h3 style="margin: 0; font-size: 1.2rem; color: #0f172a;">Personal & Contact Details</h3>
                                <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">Basic profile identifying information and primary communication contacts.</p>
                            </div>
                            <span style="background: #e0f2fe; color: #0369a1; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 4px;">Step 1 of 5</span>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Full Name</label>
                                <input type="text" name="name" value="<?= h($teacher->name) ?>" required style="width: 100%; box-sizing: border-box; margin: 0;">
                            </div>

                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Email Address</label>
                                <input type="email" name="email" value="<?= h($teacher->email) ?>" required style="width: 100%; box-sizing: border-box; margin: 0;">
                            </div>

                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">WhatsApp Mobile Number</label>
                                <input type="text" name="mobile" value="<?= h($teacher->mobile) ?>" placeholder="e.g. 0712345678" required style="width: 100%; box-sizing: border-box; margin: 0;">
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div>
                                    <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Gender</label>
                                    <select name="gender" style="width: 100%; box-sizing: border-box; background: white; margin: 0;">
                                        <option value="Male" <?= ($teacher->gender === 'Male') ? 'selected' : '' ?>>Male</option>
                                        <option value="Female" <?= ($teacher->gender === 'Female') ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Birth Year</label>
                                    <input type="number" name="year_of_birth" value="<?= $teacher->year_of_birth ? intval($teacher->year_of_birth) : '' ?>" placeholder="e.g. 1995" min="1950" max="2010" style="width: 100%; box-sizing: border-box; margin: 0;">
                                </div>
                            </div>

                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">County Location</label>
                                <select name="county" style="width: 100%; box-sizing: border-box; background: white; margin: 0;">
                                    <?php foreach (kenyan_counties() as $c): ?>
                                        <option value="<?= h($c) ?>" <?= ($teacher->county === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <span style="font-size: 0.82rem; color: #94a3b8;"><i class="fa fa-info-circle"></i> Changes to this section take effect immediately on your profile.</span>
                            <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 0.9rem;">
                                <i class="fa fa-save"></i> Save Personal Details
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB PANE 2: Academic & Qualifications -->
            <div id="section-academic" class="profile-tab-pane" style="display: none;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <form method="POST" action="/teacher/update" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="academic">

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                            <div>
                                <h3 style="margin: 0; font-size: 1.2rem; color: #0f766e;">Academic & Teaching Qualifications</h3>
                                <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">Specify your subject specialization, education level, and TSC credentials.</p>
                            </div>
                            <span style="background: #e0f2fe; color: #0369a1; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 4px;">Step 2 of 5</span>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">TSC Registration Number</label>
                                <input type="text" name="tsc_number" value="<?= h($teacher->tsc_number) ?>" placeholder="e.g. 976775 (leave blank if non-TSC)" style="width: 100%; box-sizing: border-box; margin: 0;">
                            </div>

                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Highest Qualification</label>
                                <select name="qualification" style="width: 100%; box-sizing: border-box; background: white; margin: 0;">
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
                                <input type="text" name="teaching_subjects" value="<?= h($teacher->teaching_subjects) ?>" placeholder="e.g. Mathematics / Physics, English / Lit" required style="width: 100%; box-sizing: border-box; margin: 0;">
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <div>
                                    <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Years of Experience</label>
                                    <input type="number" name="years_of_experience" value="<?= intval($teacher->years_of_experience) ?>" min="0" max="50" style="width: 100%; box-sizing: border-box; margin: 0;">
                                </div>
                                <div>
                                    <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Target Grade Level</label>
                                    <select name="grade_levels" style="width: 100%; box-sizing: border-box; background: white; margin: 0;">
                                        <option value="">Select Level</option>
                                        <?php foreach (kenyan_grade_levels() as $gl): ?>
                                            <option value="<?= h($gl) ?>" <?= ($teacher->grade_levels === $gl) ? 'selected' : '' ?>><?= h($gl) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div style="grid-column: 1 / -1;">
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">University / College Attended</label>
                                <input type="text" name="institutions_attended" value="<?= h($teacher->institutions_attended) ?>" placeholder="e.g. Kenyatta University / Kagumo TTC" style="width: 100%; box-sizing: border-box; margin: 0;">
                            </div>
                        </div>

                        <div style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <span style="font-size: 0.82rem; color: #94a3b8;"><i class="fa fa-search"></i> Accurate subject combinations help schools match your profile to vacancies.</span>
                            <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 0.9rem;">
                                <i class="fa fa-save"></i> Save Qualifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB PANE 3: Professional Bio & Summary -->
            <div id="section-bio" class="profile-tab-pane" style="display: none;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <form method="POST" action="/teacher/update" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="bio">

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                            <div>
                                <h3 style="margin: 0; font-size: 1.2rem; color: #0f766e;">Professional Summary & Educator Bio</h3>
                                <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">Pitch yourself to school principals, headteachers, and recruitment panels.</p>
                            </div>
                            <span style="background: #e0f2fe; color: #0369a1; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 4px;">Step 3 of 5</span>
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 6px; display: block;">Executive Bio / Cover Statement</label>
                            <textarea name="brief_profile" rows="8" placeholder="Highlight your teaching methodology (CBC/IGCSE/8-4-4), student achievements, extracurricular clubs (Drama, Sports, Scouts, Debate), and professional aspirations..." style="width: 100%; box-sizing: border-box; font-family: inherit; margin: 0; padding: 12px;"><?= h($teacher->brief_profile) ?></textarea>
                        </div>

                        <div style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <span style="font-size: 0.82rem; color: #94a3b8;"><i class="fa fa-lightbulb"></i> Tip: Highlight CBC classroom experience and co-curricular leadership.</span>
                            <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 0.9rem;">
                                <i class="fa fa-save"></i> Save Bio & Pitch
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB PANE 4: Safeguarding & Clearance -->
            <div id="section-compliance" class="profile-tab-pane" style="display: none;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <form method="POST" action="/teacher/update" enctype="multipart/form-data" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="compliance">

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <h3 style="margin: 0; font-size: 1.2rem; color: #0f766e;">Safeguarding & Police Clearance (Good Conduct)</h3>
                                <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">Upload your official DCI clearance certificate to gain verified educator status.</p>
                            </div>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <?php if (($teacher->verification_status ?? '') === 'verified'): ?>
                                    <span style="background: #dcfce7; color: #166534; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: 1px solid #86efac; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa fa-check-circle"></i> Verified Educator ✓
                                    </span>
                                <?php elseif (in_array($teacher->verification_status ?? '', ['failed', 'rejected', 'flagged'])): ?>
                                    <span style="background: #fee2e2; color: #991b1b; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: 1px solid #fca5a5; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa fa-times-circle"></i> Verification Action Required
                                    </span>
                                <?php elseif (!empty($teacher->good_conduct_doc) || ($teacher->verification_status ?? '') === 'pending'): ?>
                                    <span style="background: #fef3c7; color: #92400e; font-size: 0.78rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: 1px solid #fde68a;">
                                        <i class="fa fa-clock"></i> Document on File (Pending Audit)
                                    </span>
                                <?php else: ?>
                                    <span style="background: #f1f5f9; color: #64748b; font-size: 0.78rem; font-weight: 600; padding: 4px 10px; border-radius: 6px;">
                                        Not Verified
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- TSC Registration Cross-Check Banner -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <i class="fa fa-id-card" style="color: #0f766e; font-size: 1.1rem;"></i>
                                <span style="font-size: 0.85rem; color: #334155;">
                                    <?php if (!empty($teacher->tsc_number)): ?>
                                        TSC Registration Number: <strong style="color: #0f172a;"><?= h($teacher->tsc_number) ?></strong> (Linked)
                                    <?php else: ?>
                                        TSC Registration: <span style="color: #64748b;">Not configured (You can add your TSC number in Tab 2)</span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <span style="font-size: 0.75rem; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-weight: 700;">
                                <i class="fa fa-lock"></i> Safeguarding Verified
                            </span>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Certificate Serial Number</label>
                                <input type="text" name="good_conduct_cert_no" id="good_conduct_cert_no" value="<?= h($teacher->good_conduct_cert_no ?? '') ?>" placeholder="e.g. PCC-2026-XXXXX" style="width: 100%; box-sizing: border-box; margin: 0;">
                            </div>

                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                    Issue Date <span style="color: #64748b; font-weight: 400; font-size: 0.75rem;">(Auto-sets 1-yr expiry)</span>
                                </label>
                                <input type="date" name="good_conduct_issue_date" id="good_conduct_issue_date" onchange="autoCalculateExpiry(this.value)" value="<?= h($teacher->good_conduct_issue_date ?? '') ?>" style="width: 100%; box-sizing: border-box; background: #ffffff !important; color: #0f172a !important; border: 1px solid #cbd5e1 !important; margin: 0;">
                            </div>

                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                    Expiry Date <span id="expiry_warning" style="display: none; color: #dc2626; font-size: 0.75rem;">(Expired)</span>
                                </label>
                                <input type="date" name="good_conduct_expiry_date" id="good_conduct_expiry_date" onchange="checkExpiryDate(this.value)" value="<?= h($teacher->good_conduct_expiry_date ?? '') ?>" style="width: 100%; box-sizing: border-box; background: #ffffff !important; color: #0f172a !important; border: 1px solid #cbd5e1 !important; margin: 0;">
                            </div>
                        </div>

                        <div style="margin-bottom: 1.5rem;">
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Upload Certificate Document (PDF or Image, Max 5MB)</label>
                            <input type="file" name="good_conduct_doc" id="good_conduct_file_input" accept=".pdf,.jpg,.jpeg,.png" onchange="handleFileSelected(this)" style="width: 100%; box-sizing: border-box; background: white; padding: 6px; margin: 0;">
                            <div id="file_selected_preview" style="display: none; margin-top: 6px; font-size: 0.82rem; color: #0f766e; font-weight: 600;">
                                <i class="fa fa-file-arrow-up"></i> Ready to upload: <span id="selected_file_name"></span>
                            </div>
                            <?php if (!empty($teacher->good_conduct_doc)): ?>
                                <p style="margin: 6px 0 0; font-size: 0.82rem; color: #0f766e;">
                                    <i class="fa fa-file-pdf"></i> Current Document: <a href="/<?= h($teacher->good_conduct_doc) ?>" target="_blank" style="font-weight: 700; text-decoration: underline;">View Uploaded Clearance PDF</a>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Official Background Clearance & Verification Status -->
                        <?php if (($teacher->verification_status ?? '') === 'verified'): ?>
                            <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: #22c55e; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem;">
                                            <i class="fa fa-check"></i>
                                        </div>
                                        <div>
                                            <h4 style="margin: 0; color: #166534; font-size: 1rem; font-weight: 700;">Verified Educator Status: Active ✓</h4>
                                            <span style="font-size: 0.8rem; color: #15803d;">Your background credentials and certificate of good conduct have been authenticated.</span>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <button type="submit" name="compliance_action" value="reverify" onclick="return confirm('Submit renewal verification check for updated certificate?')" style="background: #0f766e; color: white !important; font-size: 0.78rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                                            <i class="fa fa-rotate-right"></i> Verify Again / Renew
                                        </button>
                                        <button type="submit" name="compliance_action" value="reset" onclick="return confirm('Are you sure you want to reset your clearance status and remove current certificate?')" style="background: #fee2e2; color: #991b1b !important; font-size: 0.78rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; border: 1px solid #fca5a5; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                                            <i class="fa fa-trash"></i> Reset Clearance
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php elseif (($teacher->verification_status ?? '') === 'pending'): ?>
                            <div style="background: #eff6ff; border: 1px solid #93c5fd; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                                <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                    <div style="display: flex; align-items: flex-start; gap: 12px; flex: 1; min-width: 260px;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: #3b82f6; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; margin-top: 2px;">
                                            <i class="fa fa-clock"></i>
                                        </div>
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                                <h4 style="margin: 0; color: #1e40af; font-size: 0.95rem; font-weight: 700;">Screening in Progress</h4>
                                                <span style="background: #3b82f6; color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;">PROCESSING</span>
                                            </div>
                                            <p style="margin: 0; font-size: 0.84rem; color: #1e3a8a; line-height: 1.5;">
                                                Your credentials have been submitted. Processing typically takes 24–48 business hours. Once verified, the 'Verified Educator ✓' badge will automatically appear on your public profile.
                                            </p>
                                        </div>
                                    </div>
                                    <div>
                                        <button type="submit" name="compliance_action" value="reset" onclick="return confirm('Cancel screening and reset uploaded documents?')" style="background: #fee2e2; color: #991b1b !important; font-size: 0.78rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; border: 1px solid #fca5a5; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                                            <i class="fa fa-times"></i> Cancel & Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php elseif (in_array($teacher->verification_status ?? '', ['failed', 'rejected', 'flagged'])): ?>
                            <div style="background: #fef2f2; border: 1px solid #fca5a5; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                                <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                                    <div style="display: flex; align-items: flex-start; gap: 12px; flex: 1; min-width: 260px;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: #ef4444; color: white; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0; margin-top: 2px;">
                                            <i class="fa fa-triangle-exclamation"></i>
                                        </div>
                                        <div>
                                            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                                <h4 style="margin: 0; color: #991b1b; font-size: 0.95rem; font-weight: 700;">Clearance Verification Unsuccessful</h4>
                                                <span style="background: #ef4444; color: white; font-size: 0.7rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;">ACTION REQUIRED</span>
                                            </div>
                                            <p style="margin: 0; font-size: 0.84rem; color: #7f1d1d; line-height: 1.5;">
                                                We were unable to authenticate the submitted clearance details. Please ensure your Certificate Serial Number matches your official records or upload an updated, clear copy of your Police Clearance Certificate.
                                            </p>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <button type="submit" name="compliance_action" value="reverify" style="background: #0f766e; color: white !important; font-size: 0.78rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                                            <i class="fa fa-rotate-right"></i> Re-submit Clearance
                                        </button>
                                        <button type="submit" name="compliance_action" value="reset" onclick="return confirm('Reset and clear current certificate details?')" style="background: #fee2e2; color: #991b1b !important; font-size: 0.78rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; border: 1px solid #fca5a5; cursor: pointer; display: inline-flex; align-items: center; gap: 5px;">
                                            <i class="fa fa-trash"></i> Reset
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                                    <div>
                                        <strong style="font-size: 0.95rem; color: #0f172a; display: block;">Official Background Screening & Educator Verification</strong>
                                        <span style="font-size: 0.82rem; color: #64748b;">Submit your Good Conduct Certificate to earn verified educator standing and priority candidate ranking.</span>
                                    </div>
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 0.85rem; font-weight: 700; color: #0f766e; cursor: pointer; margin: 0;">
                                        <input type="checkbox" name="request_screening" value="1" <?= (!empty($teacher->good_conduct_doc) ? 'checked' : '') ?> style="margin: 0;">
                                        Request Official Background Screening
                                    </label>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <span style="font-size: 0.82rem; color: #94a3b8;"><i class="fa fa-shield"></i> Verified teachers receive priority placement in school candidate searches.</span>
                            <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 0.9rem;">
                                <i class="fa fa-shield-check"></i> Save & Submit Clearance
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- TAB PANE 5: Status & Security -->
            <div id="section-security" class="profile-tab-pane" style="display: none;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <form method="POST" action="/teacher/update" style="margin: 0;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="section" value="security">

                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                            <div>
                                <h3 style="margin: 0; font-size: 1.2rem; color: #0f766e;">Employment Status & Account Security</h3>
                                <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">Control your hiring visibility and update account credentials.</p>
                            </div>
                            <span style="background: #e0f2fe; color: #0369a1; font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 4px;">Step 5 of 5</span>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Employment Availability Status</label>
                                <select name="status" style="width: 100%; box-sizing: border-box; background: white; margin: 0;">
                                    <option value="available" <?= ($teacher->status === 'available') ? 'selected' : '' ?>>🟢 Available for Immediate Hire</option>
                                    <option value="open_to_offers" <?= ($teacher->status === 'open_to_offers') ? 'selected' : '' ?>>🟡 Employed (Open to Better Offers)</option>
                                    <option value="employed" <?= ($teacher->status === 'employed') ? 'selected' : '' ?>>🔴 Currently Employed (Not Available)</option>
                                </select>
                            </div>

                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Change Account Password</label>
                                <input type="password" name="password" placeholder="Leave blank to keep current password" style="width: 100%; box-sizing: border-box; margin: 0;">
                            </div>
                        </div>

                        <div style="border-top: 1px solid #f1f5f9; padding-top: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <span style="font-size: 0.82rem; color: #94a3b8;"><i class="fa fa-lock"></i> Use a strong password containing letters and numbers.</span>
                            <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 0.9rem;">
                                <i class="fa fa-save"></i> Save Status & Security
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
function switchSection(sectionId, btn) {
    // Hide all panes
    document.querySelectorAll('.profile-tab-pane').forEach(function(el) {
        el.style.display = 'none';
    });

    // Show target pane
    const targetPane = document.getElementById('section-' + sectionId);
    if (targetPane) {
        targetPane.style.display = 'block';
    }

    // Update active tab buttons
    document.querySelectorAll('.tab-btn').forEach(function(b) {
        b.style.borderBottom = '3px solid transparent';
        b.style.color = '#64748b';
        b.style.fontWeight = '600';
    });

    if (btn) {
        btn.style.borderBottom = '3px solid #0f766e';
        btn.style.color = '#0f766e';
        btn.style.fontWeight = '700';
    }

    // Save active tab in session/localStorage
    localStorage.setItem('mwalimu_active_profile_tab', sectionId);
}

// Restore last open tab on reload
document.addEventListener('DOMContentLoaded', function() {
    const savedTab = localStorage.getItem('mwalimu_active_profile_tab');
    if (savedTab) {
        const matchingBtn = document.querySelector(".tab-btn[onclick*='" + savedTab + "']");
        if (matchingBtn) {
            switchSection(savedTab, matchingBtn);
        }
    }

    // Initial check on load for existing expiry date
    const expiryInput = document.getElementById('good_conduct_expiry_date');
    if (expiryInput && expiryInput.value) {
        checkExpiryDate(expiryInput.value);
    }
});

// Auto-calculate +1 year expiry date from issue date
function autoCalculateExpiry(issueDateStr) {
    if (!issueDateStr) return;
    const issueDate = new Date(issueDateStr);
    if (isNaN(issueDate.getTime())) return;

    // Add 1 year
    const expiryDate = new Date(issueDate);
    expiryDate.setFullYear(expiryDate.getFullYear() + 1);

    const year = expiryDate.getFullYear();
    const month = String(expiryDate.getMonth() + 1).padStart(2, '0');
    const day = String(expiryDate.getDate()).padStart(2, '0');
    const formattedExpiry = `${year}-${month}-${day}`;

    const expiryInput = document.getElementById('good_conduct_expiry_date');
    if (expiryInput) {
        expiryInput.value = formattedExpiry;
        checkExpiryDate(formattedExpiry);
    }
}

// Check if certificate date is in the past
function checkExpiryDate(expiryDateStr) {
    const warningEl = document.getElementById('expiry_warning');
    if (!warningEl || !expiryDateStr) return;

    const expiryDate = new Date(expiryDateStr);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (expiryDate < today) {
        warningEl.style.display = 'inline';
    } else {
        warningEl.style.display = 'none';
    }
}

// File selected preview
function handleFileSelected(input) {
    const previewDiv = document.getElementById('file_selected_preview');
    const nameSpan = document.getElementById('selected_file_name');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (nameSpan) nameSpan.textContent = file.name + ' (' + (file.size / (1024 * 1024)).toFixed(2) + ' MB)';
        if (previewDiv) previewDiv.style.display = 'block';
    }
}
</script>