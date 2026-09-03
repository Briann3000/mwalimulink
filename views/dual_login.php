<?php
// dual_login.php
$school_error_message = null;
$teacher_error_message = null;

// Auto redirect if already logged in
if (is_logged_in()) {
    if (has_role('school')) {
        header('Location: index.php?action=school_dashboard');
        exit();
    } elseif (has_role('teacher')) {
        header('Location: index.php?action=teacher_dashboard');
        exit();
    }
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $login_type = $_POST['login_type'] ?? '';

    if ($login_type === 'school') {
        try {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            $school = R::findOne('school', 'email = ?', [$email]);
            if (!$school) {
                throw new Exception("School does not exist.");
            }

            if (!password_verify($password, $school->password)) {
                throw new Exception("Incorrect password.");
            }

            auth_login($school, 'school');
            header('Location: index.php?action=school_dashboard');
            exit();
        } catch (Exception $e) {
            $school_error_message = $e->getMessage();
        }
    } elseif ($login_type === 'teacher') {
        try {
            $email = strtolower(trim($_POST['email'] ?? ''));
            $password = $_POST['password'] ?? '';

            $teacher = R::findOne('teacher', 'email = ?', [$email]);

            if ($teacher && password_verify($password, $teacher->password)) {
                auth_login($teacher, 'teacher');
                header('Location: index.php?action=teacher_dashboard');
                exit();
            } else {
                $teacher_error_message = "Invalid email or password.";
            }
        } catch (Exception $e) {
            $teacher_error_message = $e->getMessage();
        }
    }
}
?>

<article class="card" style="max-width: 85%; margin: 2rem auto;">
    <header>
        <h2 style="text-align: center;">MwalimuLink&trade; Portal Login</h2>
        <p style="text-align: center; margin-bottom: 0;">Sign in to your School or Teacher dashboard.</p>
    </header>

    <div class="grid" style="padding: 1rem;">
        <!-- School Login Card -->
        <article class="card" style="margin: 0.5rem;">
            <header>
                <h3 style="text-align: center;"><i class="fa fa-school"></i> School Login</h3>
            </header>

            <?php if (isset($school_error_message)): ?>
                <div class="alert alert-error">
                    <p><?= h($school_error_message) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="login_type" value="school">
                
                <label for="school_email">Official School Email
                    <input type="email" id="school_email" placeholder="e.g. info@school.ac.ke" name="email" required>
                </label>

                <label for="school_password">Password
                    <input type="password" placeholder="Password" id="school_password" name="password" required>
                </label>

                <button type="submit" class="primary" style="width: 100%; margin-top: 1rem;">School Login</button>
            </form>

            <div style="display: flex; justify-content: space-between; margin-top: 1rem; font-size: 0.9rem;">
                <p>New School? <a href="index.php?action=school_register">Register</a></p>
                <p><a href="index.php?action=reset_school_password" style="color: #666;">Forgot password?</a></p>
            </div>
        </article>

        <!-- Teacher Login Card -->
        <article class="card" style="margin: 0.5rem;">
            <header>
                <h3 style="text-align: center;"><i class="fa fa-chalkboard-teacher"></i> Teacher Login</h3>
            </header>

            <?php if (isset($teacher_error_message)): ?>
                <div class="alert alert-error">
                    <p><?= h($teacher_error_message) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="login_type" value="teacher">
                
                <label for="teacher_email">Teacher Email
                    <input type="email" id="teacher_email" placeholder="e.g. teacher@gmail.com" name="email" required>
                </label>

                <label for="teacher_password">Password
                    <input type="password" placeholder="Password" id="teacher_password" name="password" required>
                </label>

                <button type="submit" class="primary" style="width: 100%; margin-top: 1rem;">Teacher Login</button>
            </form>
            
            <div style="display: flex; justify-content: space-between; margin-top: 1rem; font-size: 0.9rem;">
                <p>New Teacher? <a href="index.php?action=teacher_register">Register Free</a></p>
                <p><a href="index.php?action=reset_teacher_password" style="color: #666;">Forgot password?</a></p>
            </div>
        </article>
    </div>
</article>
