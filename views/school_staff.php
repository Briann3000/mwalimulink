<?php
// views/school_staff.php - Employed Faculty & Teaching Staff Management
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];
$school = R::load('school', $school_id);

$msg = '';
$error = '';

// Handle linking/adding an employed teacher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } elseif ($_POST['action_type'] === 'link_teacher') {
        $identifier = trim($_POST['teacher_identifier'] ?? '');
        $employmentType = trim($_POST['employment_type'] ?? 'Permanent');
        $roleTitle = trim($_POST['role_title'] ?? 'Teacher');

        if (empty($identifier)) {
            $error = "Please enter a Teacher's Email or TSC Number.";
        } else {
            // Find teacher by email or TSC
            $teacher = R::findOne('teacher', 'email = ? OR tsc_number = ?', [$identifier, $identifier]);
            if (!$teacher) {
                $error = "No registered teacher found matching '{$identifier}'.";
            } else {
                // Check if already in staff
                $existingStaff = R::findOne('school_staff', 'school_id = ? AND teacher_id = ?', [$school_id, $teacher->id]);
                if ($existingStaff) {
                    $error = "{$teacher->name} is already listed on your staff roster.";
                } else {
                    $staff = R::dispense('school_staff');
                    $staff->school_id = $school_id;
                    $staff->teacher_id = $teacher->id;
                    $staff->teacher_name = $teacher->name;
                    $staff->tsc_number = $teacher->tsc_number ?? '';
                    $staff->subjects = $teacher->teaching_subjects ?? '';
                    $staff->employment_type = $employmentType;
                    $staff->role_title = $roleTitle;
                    $staff->date_joined = date('Y-m-d');
                    $staff->status = 'active';
                    R::store($staff);

                    // Update teacher's current status and school
                    $teacher->status = 'employed';
                    $teacher->current_school = $school->name;
                    R::store($teacher);

                    $msg = "Successfully added {$teacher->name} to your employed faculty!";
                }
            }
        }
    } elseif ($_POST['action_type'] === 'remove_staff') {
        $staffId = intval($_POST['staff_id'] ?? 0);
        $staff = R::load('school_staff', $staffId);
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

// Fetch all staff for this school
$staffList = R::find('school_staff', 'school_id = ? ORDER BY id DESC', [$school_id]);
$totalStaff = count($staffList);
$tscRegisteredStaff = 0;
foreach ($staffList as $s) {
    if (!empty($s->tsc_number)) $tscRegisteredStaff++;
}
$tscComplianceRate = $totalStaff > 0 ? round(($tscRegisteredStaff / $totalStaff) * 100) : 100;
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Content Pane -->
    <main class="content-pane">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0; font-size: 1.4rem; color: #0f172a;">Employed Faculty & Teaching Staff</h2>
                <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">
                    Manage active teachers employed at <?= h($school->name) ?>.
                </p>
            </div>
            <div>
                <button onclick="document.getElementById('addStaffModal').style.display='flex'" style="background: #2271b1; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-user-plus"></i> Link / Add Teacher
                </button>
            </div>
        </div>

        <?php if ($msg): ?>
            <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem;">
                <i class="fa fa-check-circle"></i> <?= h($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 6px; margin-bottom: 1.5rem; font-size: 0.9rem;">
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
        </div>

        <!-- Staff Roster Table -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                <h4 style="margin: 0; font-size: 1rem; font-weight: 700; color: #0f172a;">Active Faculty Roster</h4>
                <span style="font-size: 0.8rem; color: #64748b;"><?= $totalStaff ?> records</span>
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
                                            <a href="/teacher/profile?teacher_id=<?= $s->teacher_id ?>"><?= h($s->teacher_name) ?></a>
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
                                    <td style="padding: 12px 14px; color: #475569;"><?= h($s->role_title ?: 'Teacher') ?></td>
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
                        Link your currently employed teachers by their Email or TSC Number.
                    </p>
                    <button onclick="document.getElementById('addStaffModal').style.display='flex'" style="background: #2271b1; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">
                        <i class="fa fa-plus-circle"></i> Link First Teacher
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Modal: Link / Add Employed Teacher -->
<div id="addStaffModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: white; border-radius: 10px; max-width: 480px; width: 100%; padding: 1.5rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h4 style="margin: 0; font-size: 1.1rem; color: #0f172a;"><i class="fa fa-user-plus" style="color: #2271b1;"></i> Link Employed Teacher</h4>
            <button type="button" onclick="document.getElementById('addStaffModal').style.display='none'" style="background: none; border: none; font-size: 1.2rem; cursor: pointer; color: #64748b;">&times;</button>
        </div>

        <form method="POST" action="/school/staff">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="link_teacher">

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">
                Teacher's Email or TSC Number <span style="color: red;">*</span>
            </label>
            <input type="text" name="teacher_identifier" placeholder="e.g. brian@example.com or 976775" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">
                Role / Leadership Title
            </label>
            <input type="text" name="role_title" placeholder="e.g. Head of Science, Class Teacher, Senior Master" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">
                Employment Classification
            </label>
            <select name="employment_type" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1.5rem; background: white; box-sizing: border-box;">
                <option value="Permanent & Pensionable">Permanent (TSC / Ministry)</option>
                <option value="BOG / Board Contract">BOG / Board Contract</option>
                <option value="Teaching Practice (TP) Intern">Teaching Practice (TP) Intern</option>
                <option value="Relief / Term Cover">Relief / Term Cover</option>
                <option value="Part-time / Tutor">Part-time / Tutor</option>
            </select>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('addStaffModal').style.display='none'" style="background: #f1f5f9; color: #475569; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">
                    Cancel
                </button>
                <button type="submit" style="background: #2271b1; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">
                    Add to Roster
                </button>
            </div>
        </form>
    </div>
</div>