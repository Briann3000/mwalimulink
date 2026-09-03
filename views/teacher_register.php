<?php
// teacher_register.php

$message = '';
$showForm = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    
    // Check if email already exists
    $email = strtolower(trim($_POST['email'] ?? ''));
    $existing_teacher = R::findOne('teacher', 'email = ?', [$email]);
    if ($existing_teacher) {
        $message = '⚠️ This email is already registered. <a href="index.php?action=reset_teacher_password&email=' . urlencode($email) . '">Reset password?</a>';
        $showForm = false;
    } else {
        if (!isset($_POST['terms']) || $_POST['terms'] != '1') {
            $message = "You must accept the Terms and Conditions and Privacy Policy.";
            $showForm = true;
        } else {
            // Validate & normalize phone number
            $normalizedPhone = normalize_kenyan_phone($_POST['mobile'] ?? '');
            if (!$normalizedPhone) {
                $message = "Please enter a valid Kenyan phone number (e.g. 0712345678 or 0112345678).";
                $showForm = true;
            } else {
                // Validate TSC Number if provided
                $tscCheck = validate_tsc_number($_POST['tsc_number'] ?? '');
                if (!$tscCheck['valid']) {
                    $message = $tscCheck['error'];
                    $showForm = true;
                } else {
                    $teacher = R::dispense('teacher');

                    $teacher->name = trim($_POST['name'] ?? '');
                    $teacher->gender = $_POST['gender'] ?? '';
                    $teacher->year_of_birth = intval($_POST['year_of_birth'] ?? 0);
                    $teacher->mobile = $normalizedPhone;
                    $teacher->email = $email;
                    $teacher->password = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
                    $teacher->tsc_number = $tscCheck['formatted']; // Saved formatted digits or null
                    $teacher->years_of_experience = intval($_POST['years_of_experience'] ?? 0);
                    
                    // Grade levels can be array of checkboxes or single selection
                    $gradeLevels = $_POST['grade_levels'] ?? [];
                    $teacher->grade_levels = is_array($gradeLevels) ? implode(', ', $gradeLevels) : trim($gradeLevels);
                    
                    $qualification = trim($_POST['qualification'] ?? '');
                    if ($qualification === 'Other' && !empty($_POST['qualification_other'])) {
                        $qualification = trim($_POST['qualification_other']);
                    }
                    $teacher->qualification = $qualification;
                    $teacher->county = trim($_POST['county'] ?? '');
                    $teacher->country = trim($_POST['country'] ?? 'Kenya');
                    $teacher->brief_profile = trim($_POST['brief_profile'] ?? '');
                    $teacher->responsibility = trim($_POST['responsibility'] ?? ''); // Role/Responsibility
                    $teacher->status = 'available';
                    $teacher->created_at = date('Y-m-d H:i:s');

                    R::store($teacher);
                    $message = '🎉 Registration successful! Thank you for registering on MwalimuLink&trade;. <a href="./index.php?action=teacher_login">Click here to Login</a>.';
                    $showForm = false;
                }
            }
        }
    }
}
?>

<article class="card" style="max-width: 65%; margin: 2rem auto;">
    <header>
        <h2 style="text-align: center;">Free Teacher Registration</h2>
        <p style="text-align: center; margin-bottom: 0;">Connect directly with hiring schools across Kenya.</p>
    </header>

    <?php if ($message): ?>
    <div class="alert alert-<?= ($showForm ? 'warning' : 'success') ?>" style="margin: 1rem;">
        <h4><?= $message ?></h4>
    </div>
    <?php endif; ?>

    <?php if ($showForm): ?>
    <!-- Step Progress Indicator -->
    <div class="wizard-steps" style="display: flex; justify-content: space-around; margin: 1.5rem 1rem; border-bottom: 1px solid #e0e0e0; padding-bottom: 1rem;">
        <div id="step-tab-1" class="step-tab active" style="font-weight: bold; color: #007bff; display: flex; align-items: center; gap: 6px;">
            <span style="display: inline-block; width: 26px; height: 26px; line-height: 26px; text-align: center; border-radius: 50%; background: #007bff; color: white;">1</span>
            <span>Account Basics</span>
        </div>
        <div id="step-tab-2" class="step-tab" style="color: #888; display: flex; align-items: center; gap: 6px;">
            <span style="display: inline-block; width: 26px; height: 26px; line-height: 26px; text-align: center; border-radius: 50%; background: #ddd; color: #555;">2</span>
            <span>Teaching Profile</span>
        </div>
        <div id="step-tab-3" class="step-tab" style="color: #888; display: flex; align-items: center; gap: 6px;">
            <span style="display: inline-block; width: 26px; height: 26px; line-height: 26px; text-align: center; border-radius: 50%; background: #ddd; color: #555;">3</span>
            <span>Experience & Bio</span>
        </div>
    </div>

    <form id="teacherRegForm" method="post" style="padding: 1rem;">
        <?= csrf_field() ?>
        
        <!-- STEP 1: Account Basics & Personal Details -->
        <div id="step-1" class="step-section">
            <h4 style="margin-bottom: 1rem; color: #333;"><i class="fa fa-user"></i> Step 1: Personal & Account Details</h4>
            
            <label for="name">Full Name <span style="color: red;">*</span>
                <input type="text" id="name" placeholder="e.g. Jane Wanjiku Mwangi" name="name" required>
            </label>

            <div class="grid">
                <label for="gender">Gender <span style="color: red;">*</span>
                    <select id="gender" name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </label>

                <label for="year_of_birth">Year of Birth <span style="color: red;">*</span>
                    <input type="number" id="year_of_birth" placeholder="e.g. 1996" name="year_of_birth" min="1940" max="<?= date('Y') - 18 ?>" required>
                </label>
            </div>

            <div class="grid">
                <label for="mobile">Mobile Number (M-Pesa) <span style="color: red;">*</span>
                    <input type="tel" id="mobile" placeholder="e.g. 0712345678 or 0112345678" name="mobile" required>
                </label>

                <label for="email">Email Address <span style="color: red;">*</span>
                    <input type="email" id="email" placeholder="e.g. jane.mwangi@gmail.com" name="email" required>
                </label>
            </div>

            <div class="grid">
                <label for="password">Create Password <span style="color: red;">*</span>
                    <div class="password-input">
                        <input type="password" id="password" placeholder="At least 8 characters" name="password" minlength="8" required>
                        <span class="password-toggle" onclick="togglePassword('password')">👁</span>
                    </div>
                    <small id="err_password" class="field-error" style="color: #dc3545; display: none;">Password must be at least 8 characters.</small>
                </label>

                <label for="password_confirm">Confirm Password <span style="color: red;">*</span>
                    <div class="password-input">
                        <input type="password" id="password_confirm" placeholder="Re-type password" name="password_confirm" minlength="8" required>
                        <span class="password-toggle" onclick="togglePassword('password_confirm')">👁</span>
                    </div>
                    <small id="err_password_confirm" class="field-error" style="color: #dc3545; display: none;">Passwords do not match.</small>
                </label>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" class="primary" onclick="goToStep(2)" style="width: auto; padding: 0.6rem 2rem;">Next: Teaching Profile &rarr;</button>
            </div>
        </div>

        <!-- STEP 2: Teaching Specialization & Qualifications -->
        <div id="step-2" class="step-section" style="display: none;">
            <h4 style="margin-bottom: 1rem; color: #333;"><i class="fa fa-graduation-cap"></i> Step 2: Teaching Specialization</h4>

            <div class="grid">
                <label for="county">Preferred / Current County <span style="color: red;">*</span>
                    <select id="county" name="county" required>
                        <option value="">Select County</option>
                        <?php foreach (kenyan_counties() as $c): ?>
                            <option value="<?= h($c) ?>"><?= h($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small id="err_county" class="field-error" style="color: #dc3545; display: none;">Please select a county.</small>
                </label>

                <label for="qualification">Highest Academic Qualification <span style="color: red;">*</span>
                    <select id="qualification" name="qualification" onchange="toggleOtherQualification(this.value)" required>
                        <option value="">Select Qualification</option>
                        <option value="Certificate in ECDE">Certificate in ECDE</option>
                        <option value="Diploma in Education">Diploma in Education</option>
                        <option value="Bachelor of Education (B.Ed)">Bachelor of Education (B.Ed)</option>
                        <option value="Bachelor's Degree (Other Fields)">Bachelor's Degree (Other Fields)</option>
                        <option value="Postgraduate Diploma in Education (PGDE)">Postgraduate Diploma in Education (PGDE)</option>
                        <option value="Master's in Education (M.Ed)">Master's in Education (M.Ed)</option>
                        <option value="PhD">PhD</option>
                        <option value="Other">Other (Specify below)</option>
                    </select>
                    <small id="err_qualification" class="field-error" style="color: #dc3545; display: none;">Please select your qualification.</small>
                </label>
            </div>

            <!-- DYNAMIC OTHER QUALIFICATION INPUT -->
            <div id="other_qualification_container" style="display: none; margin-bottom: 1rem;">
                <label for="qualification_other">Please specify your qualification <span style="color: red;">*</span>
                    <input type="text" id="qualification_other" name="qualification_other" placeholder="e.g. Higher National Diploma (HND), Certified Montessori">
                </label>
                <small id="err_qualification_other" class="field-error" style="color: #dc3545; display: none;">Please specify your qualification.</small>
            </div>

            <label>Primary Teaching Curriculum & Level <span style="color: red;">*</span>
                <select name="grade_levels" id="grade_levels" required>
                    <option value="">Select Teaching Level</option>
                    <?php foreach (kenyan_grade_levels() as $gl): ?>
                        <option value="<?= h($gl) ?>"><?= h($gl) ?></option>
                    <?php endforeach; ?>
                </select>
                <small id="err_grade_levels" class="field-error" style="color: #dc3545; display: none;">Please select a teaching level.</small>
            </label>

            <label for="teaching_subjects">Teaching Subjects <span style="color: red;">*</span>
                <input type="text" id="teaching_subjects" placeholder="e.g. Mathematics / Physics, Biology / Chemistry, English / Literature, CBC All Subjects" name="teaching_subjects" required>
                <small id="err_teaching_subjects" class="field-error" style="color: #dc3545; display: none;">Please enter your teaching subject(s).</small>
            </label>

            <label for="tsc_number">TSC Number <small style="color: #666;">(Optional if not yet registered)</small>
                <input type="text" id="tsc_number" placeholder="e.g. 123456 or 987654 (Digits only)" name="tsc_number">
                <small id="err_tsc_number" class="field-error" style="color: #dc3545; display: none;">Invalid TSC Number. Must be between 4 and 8 digits (or leave blank if not registered).</small>
            </label>

            <div style="display: flex; justify-content: space-between; margin-top: 1.5rem;">
                <button type="button" class="secondary" onclick="goToStep(1)" style="width: auto; padding: 0.6rem 1.5rem;">&larr; Back</button>
                <button type="button" class="primary" onclick="goToStep(3)" style="width: auto; padding: 0.6rem 2rem;">Next: Experience & Bio &rarr;</button>
            </div>
        </div>

        <!-- STEP 3: Experience, Roles & Final Agreement -->
        <div id="step-3" class="step-section" style="display: none;">
            <h4 style="margin-bottom: 1rem; color: #333;"><i class="fa fa-briefcase"></i> Step 3: Experience & Background</h4>

            <div class="grid">
                <label for="years_of_experience">Teaching Experience (Years) <span style="color: red;">*</span>
                    <input type="number" id="years_of_experience" placeholder="0 for fresh graduates" name="years_of_experience" min="0" max="45" value="0" required>
                </label>

                <label for="institutions_attended">College / University Attended
                    <input type="text" id="institutions_attended" placeholder="e.g. Kenyatta University, Moi University" name="institutions_attended">
                </label>
            </div>

            <label for="responsibility">Leadership / Roles Held <small style="color: #666;">(Optional)</small>
                <input type="text" id="responsibility" placeholder="e.g. Head of Department, Class Teacher, Games Master, Deputy Head" name="responsibility">
            </label>

            <label for="brief_profile">Professional Bio / Summary <small style="color: #666;">(Optional)</small>
                <textarea id="brief_profile" placeholder="Brief statement about your teaching philosophy and core strengths." name="brief_profile" rows="3"></textarea>
            </label>

            <input type="hidden" name="country" value="Kenya">

            <label for="terms" style="text-align: left; margin-top: 1rem;">
                <input type="checkbox" id="terms" name="terms" value="1" required>
                I have read and agree to the <a href="#privacyPolicyModal" data-toggle="modal">Privacy Policy</a> and <a href="#termsConditionsModal" data-toggle="modal">Terms & Conditions</a>
                <small id="err_terms" class="field-error" style="color: #dc3545; display: block; display: none;">You must accept the terms to register.</small>
            </label>

            <div style="display: flex; justify-content: space-between; margin-top: 1.5rem;">
                <button type="button" class="secondary" onclick="goToStep(2)" style="width: auto; padding: 0.6rem 1.5rem;">&larr; Back</button>
                <button type="submit" class="primary" style="width: auto; padding: 0.6rem 2.5rem; background: #28a745; border-color: #28a745;">Complete Registration</button>
            </div>
        </div>
        
        <p style="text-align: center; margin-top: 1.5rem;">Already registered? <a href="index.php?action=teacher_login">Login here</a></p>
    </form>
    <?php endif; ?>

    <script>
    function toggleOtherQualification(val) {
        const otherBox = document.getElementById('other_qualification_container');
        const otherInput = document.getElementById('qualification_other');
        if (val === 'Other') {
            otherBox.style.display = 'block';
            otherInput.required = true;
            otherInput.focus();
        } else {
            otherBox.style.display = 'none';
            otherInput.required = false;
        }
    }

    function togglePassword(inputId) {
        const input = document.getElementById(inputId || 'password');
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }

    function setFieldError(fieldId, errorMsg) {
        const field = document.getElementById(fieldId);
        let errSpan = document.getElementById('err_' + fieldId);
        if (field) {
            field.style.borderColor = '#dc3545';
            field.style.boxShadow = '0 0 0 2px rgba(220, 53, 69, 0.2)';
        }
        if (errSpan) {
            if (errorMsg) errSpan.innerText = errorMsg;
            errSpan.style.display = 'block';
        }
    }

    function clearFieldError(fieldId) {
        const field = document.getElementById(fieldId);
        let errSpan = document.getElementById('err_' + fieldId);
        if (field) {
            field.style.borderColor = '';
            field.style.boxShadow = '';
        }
        if (errSpan) {
            errSpan.style.display = 'none';
        }
    }

    // Attach real-time clear handlers
    ['name', 'gender', 'year_of_birth', 'mobile', 'email', 'password', 'password_confirm', 'county', 'qualification', 'qualification_other', 'grade_levels', 'teaching_subjects', 'tsc_number'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => clearFieldError(id));
            el.addEventListener('change', () => clearFieldError(id));
        }
    });

    function goToStep(stepNumber) {
        let hasError = false;

        if (stepNumber === 2) {
            const name = document.getElementById('name');
            const gender = document.getElementById('gender');
            const yob = document.getElementById('year_of_birth');
            const mobile = document.getElementById('mobile');
            const email = document.getElementById('email');
            const pass = document.getElementById('password');
            const passConfirm = document.getElementById('password_confirm');

            if (!name.value.trim()) { setFieldError('name'); hasError = true; } else { clearFieldError('name'); }
            if (!gender.value) { setFieldError('gender'); hasError = true; } else { clearFieldError('gender'); }
            if (!yob.value || yob.value < 1940 || yob.value > (new Date().getFullYear() - 18)) { setFieldError('year_of_birth'); hasError = true; } else { clearFieldError('year_of_birth'); }
            
            // Clean phone regex check (07/01/254...)
            const phoneClean = mobile.value.replace(/[^0-9]/g, '');
            if (!phoneClean || (phoneClean.length !== 10 && phoneClean.length !== 12 && phoneClean.length !== 9)) {
                setFieldError('mobile');
                hasError = true;
            } else {
                clearFieldError('mobile');
            }

            if (!email.checkValidity() || !email.value.trim()) { setFieldError('email'); hasError = true; } else { clearFieldError('email'); }
            
            if (!pass.value || pass.value.length < 8) {
                setFieldError('password', 'Password must be at least 8 characters.');
                hasError = true;
            } else {
                clearFieldError('password');
            }

            if (pass.value !== passConfirm.value) {
                setFieldError('password_confirm', 'Passwords do not match.');
                hasError = true;
            } else {
                clearFieldError('password_confirm');
            }

            if (hasError) return;
        } else if (stepNumber === 3) {
            const county = document.getElementById('county');
            const qualification = document.getElementById('qualification');
            const qualificationOther = document.getElementById('qualification_other');
            const gradeLevels = document.getElementById('grade_levels');
            const subjects = document.getElementById('teaching_subjects');
            const tsc = document.getElementById('tsc_number');

            if (!county.value) { setFieldError('county'); hasError = true; } else { clearFieldError('county'); }
            if (!qualification.value) { setFieldError('qualification'); hasError = true; } else { clearFieldError('qualification'); }
            
            if (qualification.value === 'Other' && !qualificationOther.value.trim()) {
                setFieldError('qualification_other');
                hasError = true;
            } else {
                clearFieldError('qualification_other');
            }

            if (!gradeLevels.value) { setFieldError('grade_levels'); hasError = true; } else { clearFieldError('grade_levels'); }
            if (!subjects.value.trim()) { setFieldError('teaching_subjects'); hasError = true; } else { clearFieldError('teaching_subjects'); }

            // TSC Validation: If filled, MUST be 4 to 8 digits
            if (tsc.value.trim() !== '') {
                const digits = tsc.value.replace(/[^0-9]/g, '');
                // If contains invalid non-numeric chars or invalid digit count
                const tscPattern = /^(TSC\/?)?\d{4,8}$/i;
                if (!tscPattern.test(tsc.value.trim()) || digits.length < 4 || digits.length > 8) {
                    setFieldError('tsc_number', 'Invalid TSC Number (e.g. 123456). Numbers only, or leave blank if pending.');
                    hasError = true;
                } else {
                    clearFieldError('tsc_number');
                }
            } else {
                clearFieldError('tsc_number');
            }

            if (hasError) return;
        }

        // Hide all step sections
        document.querySelectorAll('.step-section').forEach(el => el.style.display = 'none');
        document.getElementById('step-' + stepNumber).style.display = 'block';

        // Update tabs styling
        for (let i = 1; i <= 3; i++) {
            const tab = document.getElementById('step-tab-' + i);
            const circle = tab.querySelector('span:first-child');
            if (i === stepNumber) {
                tab.style.color = '#007bff';
                tab.style.fontWeight = 'bold';
                circle.style.background = '#007bff';
                circle.style.color = 'white';
            } else if (i < stepNumber) {
                tab.style.color = '#28a745';
                tab.style.fontWeight = 'normal';
                circle.style.background = '#28a745';
                circle.style.color = 'white';
                circle.innerHTML = '✓';
            } else {
                tab.style.color = '#888';
                tab.style.fontWeight = 'normal';
                circle.style.background = '#ddd';
                circle.style.color = '#555';
                circle.innerHTML = i;
            }
        }
        
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    </script>

    <style>
    .password-input {
        position: relative;
        display: flex;
        align-items: center;
    }

    .password-input input[type="password"],
    .password-input input[type="text"] {
        padding-right: 30px;
        /* Space for the eye icon */
        width: 100%;
    }

    .password-toggle {
        position: absolute;
        right: 5px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        user-select: none;
    }

    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 1000;
    }

    .modal-content {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background-color: white;
        padding: 20px;
        border-radius: 5px;
        max-width: 80%;
        max-height: 80%;
        overflow: auto;
    }

    .close {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 20px;
        cursor: pointer;
    }
    </style>

    <!-- Modals -->
    <div id="privacyPolicyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('privacyPolicyModal')">&times;</span>
            <?php include 'privacy_policy.php'; ?>
        </div>
    </div>

    <div id="termsConditionsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('termsConditionsModal')">&times;</span>
            <?php include 'terms_and_conditions.php'; ?>
        </div>
    </div>

    <script>
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
    }

    // Modal Functions
    function openModal(modalId) {
        document.getElementById(modalId).style.display = "block";
    }

    function closeModal(modalId) {
        document.getElementById(modalId).style.display = "none";
    }

    // Event listeners for modal links
    document.querySelector('a[href="#privacyPolicyModal"]').addEventListener('click', function(event) {
        event.preventDefault(); // Prevent default link behavior
        openModal('privacyPolicyModal');
    });

    document.querySelector('a[href="#termsConditionsModal"]').addEventListener('click', function(event) {
        event.preventDefault(); // Prevent default link behavior
        openModal('termsConditionsModal');
    });

    // Close modal if clicked outside the content
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = "none";
        }
    }
    </script>
</article>
