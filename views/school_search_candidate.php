<?php
// school_search_candidate.php - Modern Candidate Search
require_auth('school');

$school = R::load('school', auth_user()['user_id']);
if ($school->status !== 'active') {
    header('Location: /school/pay');
    exit();
}

// Helper function to sanitize GET parameters
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data ?? '')));
}

// Pagination setup
$limit = 12; // Number of records per page
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Search filters
$country = isset($_GET['country']) ? sanitize_input($_GET['country']) : '';
$county = isset($_GET['county']) ? sanitize_input($_GET['county']) : '';
$subject = isset($_GET['subject']) ? sanitize_input($_GET['subject']) : '';
$experience = isset($_GET['experience']) ? (int)$_GET['experience'] : 0;
$grade = isset($_GET['grade']) ? sanitize_input($_GET['grade']) : '';

// Build safe query with filters
$conditions = ["1=1"];  // Base condition
$params = [];

if (!empty($county)) {
    $conditions[] = "county LIKE ?";
    $params[] = "%$county%";
}

if (!empty($subject)) {
    $conditions[] = "teaching_subjects LIKE ?";
    $params[] = "%$subject%";
}

if ($experience > 0) {
    $conditions[] = "years_of_experience >= ?";
    $params[] = $experience;
}

if (!empty($grade)) {
    $conditions[] = "grade_levels LIKE ?";
    $params[] = "%$grade%";
}

// Combine conditions
$query = implode(' AND ', $conditions);

// Fetch filtered results with pagination
$totalTeachers = R::count('teacher', $query, $params);
$teachers = R::find('teacher', "$query ORDER BY (status = 'available') DESC, id DESC LIMIT ? OFFSET ?", 
    array_merge($params, [$limit, $offset])
);

// Calculate total pages
$totalPages = ceil($totalTeachers / $limit);
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane">
        <div style="margin-bottom: 2rem;">
            <h2 style="margin: 0 0 4px; font-size: 1.4rem; color: #0f172a;">Search & Recruit Teachers</h2>
            <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                Browse over <?= number_format($totalTeachers) ?> verified educators across Kenya.
            </p>
        </div>

        <!-- Filter Search Bar -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <form method="GET" action="/school/search-candidates" style="margin: 0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; align-items: flex-end;">
                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">County</label>
                        <select name="county" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; margin: 0; box-sizing: border-box;">
                            <option value="">All Counties</option>
                            <?php foreach (kenyan_counties() as $c): ?>
                                <option value="<?= h($c) ?>" <?= ($county === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Grade Level</label>
                        <select name="grade" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; margin: 0; box-sizing: border-box;">
                            <option value="">All Grade Levels</option>
                            <?php foreach (kenyan_grade_levels() as $gl): ?>
                                <option value="<?= h($gl) ?>" <?= ($grade === $gl) ? 'selected' : '' ?>><?= h($gl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Teaching Subject</label>
                        <input type="text" name="subject" value="<?= h($subject) ?>" placeholder="e.g. Physics, Kiswahili" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin: 0; box-sizing: border-box;">
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Min. Experience</label>
                        <input type="number" name="experience" value="<?= $experience > 0 ? h($experience) : '' ?>" placeholder="Years" min="0" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin: 0; box-sizing: border-box;">
                    </div>

                    <div>
                        <button type="submit" class="btn-primary" style="height: 38px; width: 100%; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; gap: 6px;">
                            <i class="fa fa-search"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Candidate Results Grid -->
        <?php if (empty($teachers)): ?>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; padding: 3rem 1.5rem;">
                <i class="fa fa-search fa-3x" style="color: #cbd5e1; margin-bottom: 1rem;"></i>
                <h4 style="color: #0f172a; margin: 0 0 4px;">No Candidates Matching Criteria</h4>
                <p style="color: #64748b; font-size: 0.85rem;">Try adjusting your filters or resetting the search.</p>
                <a href="/school/search-candidates" style="color: #0f766e; font-weight: 600; font-size: 0.85rem;">Reset All Filters &rarr;</a>
            </div>
        <?php else: ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                <?php foreach ($teachers as $teacher): ?>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between;">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                <h4 style="margin: 0; font-size: 1.05rem; color: #0f172a;">
                                    <a href="/teacher/profile?teacher_id=<?= $teacher->id ?>" style="color: #0f172a; text-decoration: none;"><?= h($teacher->name) ?></a>
                                </h4>
                                <?php if (!empty($teacher->tsc_number)): ?>
                                    <span style="background: #dcfce7; color: #166534; font-size: 0.7rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">
                                        ✓ TSC
                                    </span>
                                <?php endif; ?>
                            </div>

                            <p style="margin: 0 0 6px; font-size: 0.82rem; color: #64748b;">
                                <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($teacher->county ?: 'Kenya') ?> &bull; 
                                <?= h($teacher->qualification ?: 'Teacher') ?>
                            </p>

                            <div style="background: #f8fafc; border: 1px solid #f1f5f9; border-radius: 6px; padding: 6px 10px; margin-bottom: 12px; font-size: 0.82rem;">
                                <strong style="color: #475569;">Subjects:</strong> <?= h($teacher->teaching_subjects ?: 'General') ?><br>
                                <strong style="color: #475569;">Exp:</strong> <?= intval($teacher->years_of_experience) ?> Years &bull; 
                                <strong style="color: #475569;">Grades:</strong> <?= h($teacher->grade_levels ?: 'All') ?>
                            </div>
                        </div>

                        <div style="border-top: 1px solid #f1f5f9; padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                            <?php if (!empty($teacher->mobile)): ?>
                                <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $teacher->mobile) ?>?text=<?= urlencode("Hello {$teacher->name}, we are contacting you from {$school->name} regarding teaching opportunities.") ?>" target="_blank" style="background: #25d366; color: white !important; font-size: 0.78rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa-brands fa-whatsapp"></i> WhatsApp
                                </a>
                            <?php endif; ?>

                            <a href="/teacher/profile?teacher_id=<?= $teacher->id ?>" style="font-size: 0.82rem; font-weight: 700; color: #0f766e; text-decoration: none;">
                                Full CV &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <?php
                $queryParams = $_GET;
                unset($queryParams['page']);
                ?>
                <div style="display: flex; justify-content: center; gap: 6px; margin-top: 2rem;">
                    <?php if ($page > 1): ?>
                        <a href="/school/search-candidates?page=<?= $page - 1 ?>&<?= http_build_query($queryParams) ?>" style="padding: 6px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; color: #334155;">&laquo; Prev</a>
                    <?php endif; ?>

                    <span style="padding: 6px 12px; background: #0f766e; color: white; border-radius: 6px; font-size: 0.82rem; font-weight: 700;">
                        Page <?= $page ?> of <?= $totalPages ?>
                    </span>

                    <?php if ($page < $totalPages): ?>
                        <a href="/school/search-candidates?page=<?= $page + 1 ?>&<?= http_build_query($queryParams) ?>" style="padding: 6px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; color: #334155;">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    </main>
</div>