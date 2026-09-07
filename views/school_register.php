<?php
// school_register.php

$message = '';
$showForm = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validate_csrf();
        
        if (empty($_POST['name'])) throw new Exception("School Name is required.");
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) throw new Exception("Invalid email address.");
        
        // Normalize Kenyan phone numbers (supports 07xx, 01xx, +254xx, 254xx)
        $normalizedPhone = normalize_kenyan_phone($_POST['phone_number'] ?? '');
        if (!$normalizedPhone) {
            throw new Exception("Please enter a valid Kenyan phone number (e.g. 0712345678 or 0112345678).");
        }

        if (empty($_POST['county'])) throw new Exception("County is required.");
        if (strlen($_POST['password'] ?? '') < 8) throw new Exception("Password must be at least 8 characters.");
        if (!isset($_POST['terms']) || $_POST['terms'] != '1') throw new Exception("You must accept the Terms and Conditions and Privacy Policy.");

        // Check if email already exists
        $existing_school = R::findOne('school', 'email = ?', [$_POST['email']]);
        if ($existing_school) {
            $message = '⚠️ This email is already registered. <a href="/reset-password/school?email=' . urlencode($_POST['email']) . '">Reset password?</a>';
            $showForm = false;
        } else {
            $school = R::dispense('school');
            $school->name = trim($_POST['name']);
            $school->email = strtolower(trim($_POST['email']));
            $school->password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $school->phone_number = $normalizedPhone;
            $school->county = trim($_POST['county']);
            $school->category = trim($_POST['category'] ?? 'Private School');
            $school->contact_person = trim($_POST['contact_person'] ?? '');
            $school->address = trim($_POST['address'] ?? '');
            $school->status = 'active';
            $school->plan = 'free';
            $school->subscription_expiry = null;

            $school_id = R::store($school);
            auth_login($school, 'school');

            header('Location: /school/dashboard');
            exit;
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>

<article class="card" style="max-width: 65%; margin: 2rem auto;">
    <header>
        <h3 style="text-align: center;">School Registration</h3>
        <p style="text-align: center; margin-bottom: 0;">Register your institution to search teachers and post job vacancies.</p>
    </header>

    <?php if ($message): ?>
        <div class="alert alert-warning" style="margin: 1rem;">
            <p><?= $message ?></p>
        </div>
    <?php endif; ?>

    <?php if ($showForm): ?>
    <!-- Step Progress Indicator -->
    <div class="wizard-steps" style="display: flex; justify-content: space-around; margin: 1.5rem 1rem; border-bottom: 1px solid #e0e0e0; padding-bottom: 1rem;">
        <div id="school-tab-1" class="step-tab active" style="font-weight: bold; color: #007bff; display: flex; align-items: center; gap: 6px;">
            <span style="display: inline-block; width: 26px; height: 26px; line-height: 26px; text-align: center; border-radius: 50%; background: #007bff; color: white;">1</span>
            <span>Institution Details</span>
        </div>
        <div id="school-tab-2" class="step-tab" style="color: #888; display: flex; align-items: center; gap: 6px;">
            <span style="display: inline-block; width: 26px; height: 26px; line-height: 26px; text-align: center; border-radius: 50%; background: #ddd; color: #555;">2</span>
            <span>Contact & Account</span>
        </div>
    </div>

    <form id="schoolRegForm" method="POST" style="padding: 1rem;">
        <?= csrf_field() ?>
        
        <!-- STEP 1: Institution Details -->
        <div id="school-step-1" class="step-section">
            <h4 style="margin-bottom: 1rem; color: #333;"><i class="fa fa-school"></i> Step 1: Institution Information</h4>

            <label for="name">School Name <span style="color: red;">*</span>
                <input type="text" id="name" placeholder="e.g. St. Austin's Academy" name="name" required>
            </label>

            <div class="grid">
                <label for="category">School Category <span style="color: red;">*</span>
                    <select id="category" name="category" required>
                        <option value="">Select Category</option>
                        <?php foreach (kenyan_school_categories() as $sc): ?>
                            <option value="<?= h($sc) ?>"><?= h($sc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label for="county">County <span style="color: red;">*</span>
                    <select id="county" name="county" required>
                        <option value="">Select County</option>
                        <?php foreach (kenyan_counties() as $c): ?>
                            <option value="<?= h($c) ?>"><?= h($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <label for="address">Location / Sub-County / Physical Address
                <input type="text" id="address" placeholder="e.g. Westlands, off Waiyaki Way" name="address">
            </label>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="button" class="primary" onclick="goToSchoolStep(2)" style="width: auto; padding: 0.6rem 2rem;">Next: Contact & Security &rarr;</button>
            </div>
        </div>

        <!-- STEP 2: Contact Person & Account Security -->
        <div id="school-step-2" class="step-section" style="display: none;">
            <h4 style="margin-bottom: 1rem; color: #333;"><i class="fa fa-lock"></i> Step 2: Contact Person & Security</h4>

            <div class="grid">
                <label for="email">Official School Email <span style="color: red;">*</span>
                    <input type="email" id="email" placeholder="e.g. info@staustins.ac.ke" name="email" required>
                </label>

                <label for="phone_number">Official Phone Number (M-Pesa) <span style="color: red;">*</span>
                    <input type="tel" id="phone_number" placeholder="e.g. 0712345678 or 0112345678" name="phone_number" required>
                </label>
            </div>

            <label for="contact_person">Contact Person & Designation <small style="color: #666;">(e.g. Principal, Headteacher, Director)</small>
                <input type="text" id="contact_person" placeholder="e.g. Mrs. Mary Kariuki - Headteacher" name="contact_person">
            </label>

            <div class="grid">
                <label for="password">Create Password <span style="color: red;">*</span>
                    <div class="password-input">
                        <input type="password" id="password" placeholder="At least 8 characters" name="password" minlength="8" required>
                        <span class="password-toggle" onclick="togglePassword('password')">👁</span>
                    </div>
                    <small id="err_school_password" class="field-error" style="color: #dc3545; display: none;">Password must be at least 8 characters.</small>
                </label>

                <label for="password_confirm">Confirm Password <span style="color: red;">*</span>
                    <div class="password-input">
                        <input type="password" id="password_confirm" placeholder="Re-type password" name="password_confirm" minlength="8" required>
                        <span class="password-toggle" onclick="togglePassword('password_confirm')">👁</span>
                    </div>
                    <small id="err_school_password_confirm" class="field-error" style="color: #dc3545; display: none;">Passwords do not match.</small>
                </label>
            </div>

            <label for="terms" style="text-align: left; margin-top: 1rem;">
                <input type="checkbox" id="terms" name="terms" value="1" required>
                I have read and agree to the <a href="#privacyPolicyModal" data-toggle="modal">Privacy Policy</a> and <a href="#termsConditionsModal" data-toggle="modal">Terms & Conditions</a>
                <small id="err_school_terms" class="field-error" style="color: #dc3545; display: none;">You must accept the terms to continue.</small>
            </label>

            <div style="display: flex; justify-content: space-between; margin-top: 1.5rem;">
                <button type="button" class="secondary" onclick="goToSchoolStep(1)" style="width: auto; padding: 0.6rem 1.5rem;">&larr; Back</button>
                <button type="submit" class="primary" style="width: auto; padding: 0.6rem 2.5rem; background: #28a745; border-color: #28a745;">Register & Proceed to Subscription</button>
            </div>
        </div>
        
        <p style="text-align: center; margin-top: 1.5rem;">Already registered? <a href="/login/school">Login here</a></p>
    </form>
    <?php endif; ?>

    <script>
    function togglePassword(inputId) {
        const input = document.getElementById(inputId || 'password');
        if (input.type === 'password') {
            input.type = 'text';
        } else {
            input.type = 'password';
        }
    }

    function setSchoolFieldError(fieldId, errorMsg) {
        const field = document.getElementById(fieldId);
        let errSpan = document.getElementById('err_school_' + fieldId);
        if (field) {
            field.style.borderColor = '#dc3545';
            field.style.boxShadow = '0 0 0 2px rgba(220, 53, 69, 0.2)';
        }
        if (errSpan) {
            if (errorMsg) errSpan.innerText = errorMsg;
            errSpan.style.display = 'block';
        }
    }

    function clearSchoolFieldError(fieldId) {
        const field = document.getElementById(fieldId);
        let errSpan = document.getElementById('err_school_' + fieldId);
        if (field) {
            field.style.borderColor = '';
            field.style.boxShadow = '';
        }
        if (errSpan) {
            errSpan.style.display = 'none';
        }
    }

    // Attach real-time clear handlers
    ['name', 'category', 'county', 'email', 'phone_number', 'password', 'password_confirm'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => clearSchoolFieldError(id));
            el.addEventListener('change', () => clearSchoolFieldError(id));
        }
    });

    function goToSchoolStep(stepNumber) {
        let hasError = false;

        if (stepNumber === 2) {
            const name = document.getElementById('name');
            const category = document.getElementById('category');
            const county = document.getElementById('county');

            if (!name.value.trim()) { setSchoolFieldError('name'); hasError = true; } else { clearSchoolFieldError('name'); }
            if (!category.value) { setSchoolFieldError('category'); hasError = true; } else { clearSchoolFieldError('category'); }
            if (!county.value) { setSchoolFieldError('county'); hasError = true; } else { clearSchoolFieldError('county'); }

            if (hasError) return;
        }

        document.querySelectorAll('.step-section').forEach(el => el.style.display = 'none');
        document.getElementById('school-step-' + stepNumber).style.display = 'block';

        for (let i = 1; i <= 2; i++) {
            const tab = document.getElementById('school-tab-' + i);
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
