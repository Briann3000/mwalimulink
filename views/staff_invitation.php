<?php
// views/staff_invitation.php - Accept or Decline Faculty Appointment Invitation

$token = trim($_GET['token'] ?? '');
if (empty($token)) {
    echo "<div class='container' style='max-width: 600px; margin: 3rem auto; padding: 2rem; background: white; border: 1px solid #fee2e2; border-radius: 8px; color: #991b1b;'>
        <h3>Invalid Request</h3>
        <p>No invitation token was provided.</p>
        <a href='/' style='color: #0f766e;'>Return to Home</a>
    </div>";
    exit();
}

$invite = R::findOne('staffinvitation', 'token = ?', [$token]);
if (!$invite) {
    // Try fallback table name if needed
    $invite = R::findOne('staff_invitation', 'token = ?', [$token]);
}

if (!$invite) {
    echo "<div class='container' style='max-width: 600px; margin: 3rem auto; padding: 2rem; background: white; border: 1px solid #fee2e2; border-radius: 8px; color: #991b1b;'>
        <h3>Invitation Not Found</h3>
        <p>This appointment invitation may have expired or been removed by the institution.</p>
        <a href='/' style='color: #0f766e;'>Return to Home</a>
    </div>";
    exit();
}

if ($invite->status === 'accepted') {
    echo "<div class='container' style='max-width: 600px; margin: 3rem auto; padding: 2rem; background: white; border: 1px solid #bbf7d0; border-radius: 8px; color: #166534;'>
        <h3>Already Accepted</h3>
        <p>This faculty invitation has already been accepted.</p>
        <a href='/teacher/dashboard' style='display: inline-block; margin-top: 10px; background: #0f766e; color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none;'>Go to Teacher Dashboard</a>
    </div>";
    exit();
}

$school = R::load('school', $invite->school_id);
if (!$school->id) {
    echo "<div class='container' style='max-width: 600px; margin: 3rem auto; padding: 2rem; background: white; border: 1px solid #fee2e2; border-radius: 8px; color: #991b1b;'>
        <h3>School Not Found</h3>
        <p>The inviting institution could not be verified.</p>
    </div>";
    exit();
}

$authUser = auth_user();
$existingTeacher = R::findOne('teacher', 'email = ?', [$invite->email]);

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please refresh and try again.";
    } else {
        $action = trim($_POST['action'] ?? '');
        
        if ($action === 'accept') {
            if (!$authUser || $authUser['role'] !== 'teacher') {
                $error = "You must be logged in with your teacher account to accept this invitation.";
            } else {
                $teacher = R::load('teacher', $authUser['user_id']);
                if ($teacher->id) {
                    // Check if already in staff roster
                    $existingStaff = R::findOne('schoolstaff', 'school_id = ? AND teacher_id = ?', [$school->id, $teacher->id]);
                    if (!$existingStaff) {
                        $staff = R::dispense('schoolstaff');
                        $staff->school_id = $school->id;
                        $staff->teacher_id = $teacher->id;
                        $staff->teacher_name = $teacher->name ?: 'Educator';
                        $staff->tsc_number = $teacher->tsc_number ?? '';
                        $staff->subjects = $teacher->teaching_subjects ?? '';
                        $staff->employment_type = $invite->employment_type ?: 'Permanent';
                        $staff->role_title = $invite->role_title ?: 'Teacher';
                        $staff->date_joined = date('Y-m-d');
                        $staff->status = 'active';
                        R::store($staff);
                    }

                    // Update teacher profile
                    $teacher->status = 'employed';
                    $teacher->current_school = $school->name;
                    R::store($teacher);

                    // Update invitation status
                    $invite->status = 'accepted';
                    $invite->accepted_at = date('Y-m-d H:i:s');
                    $invite->teacher_id = $teacher->id;
                    R::store($invite);

                    // Redirect to dashboard with confirmation
                    header("Location: /teacher/dashboard?msg=" . urlencode("Congratulations! You have successfully joined the faculty of " . $school->name));
                    exit();
                }
            }
        } elseif ($action === 'reject') {
            $invite->status = 'rejected';
            R::store($invite);
            
            if ($authUser && $authUser['role'] === 'teacher') {
                header("Location: /teacher/dashboard?msg=" . urlencode("You declined the invitation from " . $school->name));
                exit();
            } else {
                header("Location: /?msg=" . urlencode("You declined the faculty invitation."));
                exit();
            }
        }
    }
}
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; background: #f8fafc;">
    <div style="max-width: 580px; width: 100%; background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);">
        
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="width: 60px; height: 60px; background: #e6fffa; color: #0f766e; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.6rem; margin-bottom: 1rem;">
                <i class="fa fa-school"></i>
            </div>
            <h2 style="margin: 0 0 6px; color: #0f172a; font-size: 1.45rem;">Official Faculty Appointment Invitation</h2>
            <p style="margin: 0; color: #64748b; font-size: 0.9rem;">
                <strong><?= h($school->name) ?></strong> has invited you to join their teaching roster on MwalimuLink.
            </p>
        </div>

        <?php if ($error): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 10px 14px; border-radius: 6px; margin-bottom: 1.25rem; font-size: 0.88rem;">
                <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- Appointment Overview Card -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; font-size: 0.88rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: #64748b;">Institution:</span>
                <strong style="color: #0f172a;"><?= h($school->name) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: #64748b;">County:</span>
                <strong style="color: #1e293b;"><?= h($school->county ?: 'Kenya') ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: #64748b;">Role / Title:</span>
                <strong style="color: #0f766e;"><?= h($invite->role_title) ?></strong>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #64748b;">Employment Type:</span>
                <strong style="color: #1e293b;"><?= h($invite->employment_type) ?></strong>
            </div>
        </div>

        <?php if (!$authUser): ?>
            <!-- User is not logged in -->
            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 1.25rem; text-align: center; margin-bottom: 1.5rem;">
                <p style="margin: 0 0 12px; font-size: 0.88rem; color: #1e40af;">
                    <?= $existingTeacher ? "Please log in to your MwalimuLink teacher account to accept this invitation." : "Create your educator account to accept this appointment and activate your profile." ?>
                </p>
                <?php if ($existingTeacher): ?>
                    <a href="/login/teacher" style="background: #0f766e; color: white !important; font-weight: 700; font-size: 0.92rem; padding: 10px 22px; border-radius: 6px; text-decoration: none; display: inline-block;">
                        <i class="fa fa-sign-in-alt"></i> Log In to Accept Invitation
                    </a>
                <?php else: ?>
                    <a href="/register/teacher?invite_token=<?= urlencode($token) ?>&email=<?= urlencode($invite->email) ?>" style="background: #0f766e; color: white !important; font-weight: 700; font-size: 0.92rem; padding: 10px 22px; border-radius: 6px; text-decoration: none; display: inline-block;">
                        <i class="fa fa-user-plus"></i> Create Teacher Account & Accept
                    </a>
                <?php endif; ?>
            </div>

        <?php elseif ($authUser['role'] === 'teacher'): ?>
            <!-- Logged in as Teacher -->
            <form method="POST" style="margin: 0;">
                <?= csrf_field() ?>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="submit" name="action" value="reject" onclick="return confirm('Decline this faculty appointment invitation?');" style="background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; padding: 11px 20px; border-radius: 6px; font-weight: 600; font-size: 0.9rem; cursor: pointer;">
                        Decline
                    </button>
                    <button type="submit" name="action" value="accept" style="background: #0f766e; color: white; border: none; padding: 11px 24px; border-radius: 6px; font-weight: 700; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-check"></i> Accept Appointment
                    </button>
                </div>
            </form>

        <?php else: ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 1rem; border-radius: 6px; font-size: 0.88rem; text-align: center;">
                You are currently logged in as a school administrator. Please log out and sign in with your teacher account to accept this appointment.
            </div>
        <?php endif; ?>

    </div>
</div>
