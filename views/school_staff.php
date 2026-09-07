<?php
// views/school_staff.php - Employed Faculty & Teaching Staff Management with Invitation Workflow
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];
$school = R::load('school', $school_id);

$msg = '';
$error = '';

// Handle POST actions: Send Invitation, Resend Invite, Cancel Invite, Remove Staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } elseif ($_POST['action_type'] === 'send_invitation') {
        $identifier = trim($_POST['teacher_identifier'] ?? '');
        $employmentType = trim($_POST['employment_type'] ?? 'Permanent');
        $roleTitleSelect = trim($_POST['role_title'] ?? 'Teacher');
        $customRoleTitle = trim($_POST['custom_role_title'] ?? '');
        
        $finalRole = ($roleTitleSelect === 'Other' && !empty($customRoleTitle)) ? $customRoleTitle : $roleTitleSelect;

        if (empty($identifier)) {
            $error = "Please enter the Teacher's Email Address (or registered TSC Number).";
        } else {
            // Find if teacher exists by email or TSC
            $teacher = R::findOne('teacher', 'email = ? OR tsc_number = ?', [$identifier, $identifier]);
            $recipientEmail = $teacher ? $teacher->email : (filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strtolower($identifier) : null);

            if (!$recipientEmail) {
                $error = "Please enter a valid email address so we can deliver the faculty invitation.";
            } else {
                // Check if already active in faculty
                $isAlreadyStaff = false;
                if ($teacher) {
                    $existingStaff = R::findOne('schoolstaff', 'school_id = ? AND teacher_id = ?', [$school_id, $teacher->id]);
                    if ($existingStaff) $isAlreadyStaff = true;
                }

                if ($isAlreadyStaff) {
                    $error = "This educator is already listed on your active faculty roster.";
                } else {
                    // Check if pending invitation already exists for this email
                    $pendingInvite = R::findOne('staffinvitation', 'school_id = ? AND email = ? AND status = ?', [$school_id, $recipientEmail, 'pending']);
                    if ($pendingInvite) {
                        // Refresh token and resend
                        $token = bin2hex(random_bytes(24));
                        $pendingInvite->token = $token;
                        $pendingInvite->role_title = $finalRole;
                        $pendingInvite->employment_type = $employmentType;
                        $pendingInvite->created_at = date('Y-m-d H:i:s');
                        R::store($pendingInvite);

                        send_staff_invitation_email($school, $recipientEmail, $finalRole, $employmentType, $token, !empty($teacher));
                        $msg = "An updated appointment invitation has been resent to {$recipientEmail}.";
                    } else {
                        // Create new invitation
                        $token = bin2hex(random_bytes(24));
                        $invite = R::dispense('staffinvitation');
                        $invite->school_id = $school_id;
                        $invite->email = $recipientEmail;
                        $invite->role_title = $finalRole;
                        $invite->employment_type = $employmentType;
                        $invite->token = $token;
                        $invite->status = 'pending';
                        $invite->created_at = date('Y-m-d H:i:s');
                        R::store($invite);

                        $mailSuccess = send_staff_invitation_email($school, $recipientEmail, $finalRole, $employmentType, $token, !empty($teacher));
                        if ($mailSuccess) {
                            $msg = "Faculty invitation successfully sent to {$recipientEmail}! Once they accept, their verified profile will appear on your active roster.";
                        } else {
                            $msg = "Faculty invitation created for {$recipientEmail}. (Email notification queued/dispatched).";
                        }
                    }
                }
            }
        }
    } elseif ($_POST['action_type'] === 'resend_invite') {
        $inviteId = intval($_POST['invite_id'] ?? 0);
        $invite = R::load('staffinvitation', $inviteId);
        if ($invite->id && $invite->school_id == $school_id && $invite->status === 'pending') {
            $token = bin2hex(random_bytes(24));
            $invite->token = $token;
            $invite->created_at = date('Y-m-d H:i:s');
            R::store($invite);

            $teacher = R::findOne('teacher', 'email = ?', [$invite->email]);
            send_staff_invitation_email($school, $invite->email, $invite->role_title, $invite->employment_type, $token, !empty($teacher));
            $msg = "Invitation resent to {$invite->email}.";
        }
    } elseif ($_POST['action_type'] === 'cancel_invite') {
        $inviteId = intval($_POST['invite_id'] ?? 0);
        $invite = R::load('staffinvitation', $inviteId);
        if ($invite->id && $invite->school_id == $school_id) {
            R::trash($invite);
            $msg = "Invitation cancelled.";
        }
    } elseif ($_POST['action_type'] === 'remove_staff') {
        $staffId = intval($_POST['staff_id'] ?? 0);
        $staff = R::load('schoolstaff', $staffId);
        if ($staff->id && $staff->school_id == $school_id) {
            $tId = $staff->teacher_id;
            R::trash($staff);
            
            // If teacher was linked, revert status to available
            if ($tId) {
                $teacher = R::load('teacher', $tId);
                if ($teacher->id) {
                    $teacher->status = 'available';
                    $teacher->current_school = '';
                    R::store($teacher);
                }
            }
            $msg = "Faculty member removed from roster.";
        }
    }
}

// Fetch all active staff and pending invitations
$staffList = R::find('schoolstaff', 'school_id = ? ORDER BY id DESC', [$school_id]);
$totalStaff = count($staffList);
$tscRegisteredStaff = 0;
foreach ($staffList as $s) {
    if (!empty($s->tsc_number)) $tscRegisteredStaff++;
}
$tscComplianceRate = $totalStaff > 0 ? round(($tscRegisteredStaff / $totalStaff) * 100) : 100;

$pendingInvites = R::find('staffinvitation', 'school_id = ? AND status = ? ORDER BY id DESC', [$school_id, 'pending']);
$totalInvites = count($pendingInvites);
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Content Pane -->
    <main class="content-pane" style="padding-top: 1.5rem !important;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0 0 4px; font-size: 1.4rem; color: #0f172a; font-weight: 800;">Employed Faculty & Teaching Staff</h2>
                <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                    Manage active educators, assign institutional roles, and invite new staff members to <?= h($school->name) ?>.
                </p>
            </div>
            <div>
                <button onclick="document.getElementById('addStaffModal').style.display='flex'" style="background: #0f766e; color: white; border: none; padding: 9px 18px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(15,118,110,0.2);">
                    <i class="fa fa-paper-plane"></i> Invite / Add Faculty Member
                </button>
            </div>
        </div>

        <?php if ($msg): ?>
            <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-check-circle"></i> <?= h($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- Faculty KPI Metrics -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">Total Active Teachers</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a;"><?= $totalStaff ?></div>
            </div>

            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">TSC Compliance Rate</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #16a34a;"><?= $tscComplianceRate ?>%</div>
            </div>

            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">TSC Registered Staff</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a;"><?= $tscRegisteredStaff ?> / <?= $totalStaff ?></div>
            </div>

            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">Pending Invitations</div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #d97706;"><?= $totalInvites ?></div>
            </div>
        </div>

        <!-- Active Staff Roster Table -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 2rem;">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h4 style="margin: 0; font-size: 1rem; font-weight: 700; color: #0f172a;">Active Faculty Roster</h4>
                <span style="font-size: 0.8rem; color: #64748b;"><?= $totalStaff ?> confirmed members</span>
            </div>

            <?php if (!empty($staffList)): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                <th style="padding: 10px 14px; font-weight: 600;">Teacher Name</th>
                                <th style="padding: 10px 14px; font-weight: 600;">TSC Number</th>
                                <th style="padding: 10px 14px; font-weight: 600;">Teaching Subjects</th>
                                <th style="padding: 10px 14px; font-weight: 600;">Role / Title</th>
                                <th style="padding: 10px 14px; font-weight: 600;">Type</th>
                                <th style="padding: 10px 14px; font-weight: 600; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staffList as $s): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 12px 14px; font-weight: 600; color: #0f172a;">
                                        <?php if (!empty($s->teacher_id)): ?>
                                            <a href="/teacher/profile?teacher_id=<?= $s->teacher_id ?>" style="color: #0f766e; text-decoration: none; font-weight: 700;"><?= h($s->teacher_name) ?></a>
                                        <?php else: ?>
                                            <?= h($s->teacher_name) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <?php if (!empty($s->tsc_number)): ?>
                                            <span style="background: #dcfce7; color: #15803d; font-size: 0.75rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">
                                                ✓ <?= h($s->tsc_number) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-size: 0.8rem;">Non-TSC / Tutor</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 14px; color: #475569;"><?= h($s->subjects ?: 'General') ?></td>
                                    <td style="padding: 12px 14px; color: #0f172a; font-weight: 600;"><?= h($s->role_title ?: 'Teacher') ?></td>
                                    <td style="padding: 12px 14px;">
                                        <span style="background: #e0f2fe; color: #0369a1; font-size: 0.75rem; font-weight: 600; padding: 2px 8px; border-radius: 12px;">
                                            <?= h($s->employment_type) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 12px 14px; text-align: right;">
                                        <form method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Remove <?= addslashes($s->teacher_name) ?> from staff roster?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action_type" value="remove_staff">
                                            <input type="hidden" name="staff_id" value="<?= $s->id ?>">
                                            <button type="submit" style="background: none; border: none; color: #ef4444; font-size: 0.8rem; font-weight: 600; cursor: pointer; padding: 0;">
                                                <i class="fa fa-trash"></i> Remove
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem 1rem;">
                    <i class="fa fa-user-friends fa-3x" style="color: #cbd5e1; margin-bottom: 1rem;"></i>
                    <h4 style="color: #0f172a; margin: 0 0 4px;">No Employed Teachers Listed Yet</h4>
                    <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 1.25rem;">
                        Send an invitation to your teachers by their email address.
                    </p>
                    <button onclick="document.getElementById('addStaffModal').style.display='flex'" style="background: #0f766e; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">
                        <i class="fa fa-paper-plane"></i> Send First Invitation
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pending Invitations Section -->
        <?php if (!empty($pendingInvites)): ?>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fffdf5;">
                    <div>
                        <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #92400e;">
                            <i class="fa fa-clock"></i> Pending Staff Invitations
                        </h4>
                        <span style="font-size: 0.78rem; color: #b45309;">Waiting for teachers to accept via email link</span>
                    </div>
                    <span style="background: #fef3c7; color: #92400e; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 12px; border: 1px solid #fde68a;">
                        <?= $totalInvites ?> awaiting response
                    </span>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                <th style="padding: 10px 14px; font-weight: 600;">Recipient Email</th>
                                <th style="padding: 10px 14px; font-weight: 600;">Invited Role</th>
                                <th style="padding: 10px 14px; font-weight: 600;">Classification</th>
                                <th style="padding: 10px 14px; font-weight: 600;">Sent Date</th>
                                <th style="padding: 10px 14px; font-weight: 600; text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingInvites as $inv): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 12px 14px; font-weight: 600; color: #0f172a;">
                                        <i class="fa fa-envelope" style="color: #64748b; margin-right: 4px;"></i> <?= h($inv->email) ?>
                                    </td>
                                    <td style="padding: 12px 14px; color: #0f766e; font-weight: 700;"><?= h($inv->role_title) ?></td>
                                    <td style="padding: 12px 14px; color: #475569;"><?= h($inv->employment_type) ?></td>
                                    <td style="padding: 12px 14px; color: #64748b; font-size: 0.8rem;"><?= date('M d, Y H:i', strtotime($inv->created_at)) ?></td>
                                    <td style="padding: 12px 14px; text-align: right;">
                                        <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                            <form method="POST" style="margin: 0;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action_type" value="resend_invite">
                                                <input type="hidden" name="invite_id" value="<?= $inv->id ?>">
                                                <button type="submit" style="background: #f1f5f9; border: 1px solid #cbd5e1; color: #334155; font-size: 0.78rem; font-weight: 600; padding: 4px 8px; border-radius: 4px; cursor: pointer;">
                                                    <i class="fa fa-redo"></i> Resend
                                                </button>
                                            </form>
                                            <form method="POST" style="margin: 0;" onsubmit="return confirm('Cancel invitation for <?= addslashes($inv->email) ?>?');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action_type" value="cancel_invite">
                                                <input type="hidden" name="invite_id" value="<?= $inv->id ?>">
                                                <button type="submit" style="background: none; border: none; color: #ef4444; font-size: 0.78rem; font-weight: 600; cursor: pointer; padding: 0;">
                                                    <i class="fa fa-times"></i> Cancel
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- Modal: Invite Faculty Member -->
<div id="addStaffModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: white; border-radius: 10px; max-width: 500px; width: 100%; padding: 1.75rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h4 style="margin: 0; font-size: 1.15rem; color: #0f172a;"><i class="fa fa-paper-plane" style="color: #0f766e;"></i> Invite Faculty Member</h4>
            <button type="button" onclick="document.getElementById('addStaffModal').style.display='none'" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b; line-height: 1;">&times;</button>
        </div>

        <p style="margin: 0 0 1.25rem; font-size: 0.84rem; color: #64748b; line-height: 1.5;">
            An appointment invitation email will be dispatched to the teacher. Existing MwalimuLink members will be able to accept directly into their dashboard, while new teachers will be prompted to create their verified profile.
        </p>

        <form method="POST" action="/school/staff">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="send_invitation">

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">
                Teacher's Email Address <span style="color: red;">*</span>
            </label>
            <input type="email" name="teacher_identifier" placeholder="e.g. teacher@example.com" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">
                Institutional Role / Title <span style="color: red;">*</span>
            </label>
            <select name="role_title" id="roleSelect" onchange="handleRoleChange(this.value)" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; background: white; box-sizing: border-box;">
                <option value="Subject Teacher">Subject Teacher</option>
                <option value="Class Teacher">Class Teacher</option>
                <option value="Head of Department (HOD)">Head of Department (HOD)</option>
                <option value="Senior Teacher / Master">Senior Teacher / Master</option>
                <option value="Deputy Principal / Deputy Headteacher">Deputy Principal / Deputy Headteacher</option>
                <option value="Principal / Headteacher">Principal / Headteacher</option>
                <option value="Guidance & Counseling Master">Guidance & Counseling Master</option>
                <option value="Games & Sports Master">Games & Sports Master</option>
                <option value="Boarding Master / Mistress">Boarding Master / Mistress</option>
                <option value="Teaching Practice (TP) Intern">Teaching Practice (TP) Intern</option>
                <option value="Other">Other (Specify Custom Title)</option>
            </select>

            <!-- Custom Role Input (revealed when 'Other' selected) -->
            <div id="customRoleWrapper" style="display: none; margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 600; color: #0f766e; margin-bottom: 4px; display: block;">
                    Specify Custom Role Title <span style="color: red;">*</span>
                </label>
                <input type="text" id="customRoleInput" name="custom_role_title" placeholder="e.g. Laboratory Technician, IT Coordinator, Music Director" style="width: 100%; padding: 9px 12px; border: 1px solid #0f766e; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box;">
            </div>

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">
                Employment Classification <span style="color: red;">*</span>
            </label>
            <select name="employment_type" required style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1.5rem; background: white; box-sizing: border-box;">
                <option value="Permanent (TSC / Ministry)">Permanent (TSC / Ministry)</option>
                <option value="BOG / Board Contract">BOG / Board Contract</option>
                <option value="Teaching Practice (TP) Intern">Teaching Practice (TP) Intern</option>
                <option value="Relief / Term Cover">Relief / Term Cover</option>
                <option value="Part-time / Tutor">Part-time / Tutor</option>
            </select>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('addStaffModal').style.display='none'" style="background: #f1f5f9; color: #475569; border: none; padding: 9px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" style="background: #0f766e; color: white; border: none; padding: 9px 20px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">
                    <i class="fa fa-paper-plane"></i> Send Invitation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function handleRoleChange(val) {
    const customWrapper = document.getElementById('customRoleWrapper');
    const customInput = document.getElementById('customRoleInput');
    if (val === 'Other') {
        customWrapper.style.display = 'block';
        customInput.setAttribute('required', 'required');
        customInput.focus();
    } else {
        customWrapper.style.display = 'none';
        customInput.removeAttribute('required');
    }
}
</script>