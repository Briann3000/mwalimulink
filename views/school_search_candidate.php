<?php
require_auth('school');

$school = R::load('school', auth_user()['user_id']);
if ($school->status !== 'active') {
    header('Location: index.php?action=pay_subscription');
    exit();
}

// Helper function to sanitize GET parameters
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

// Pagination setup
$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
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

if (!empty($country)) {
    $conditions[] = "country LIKE ?";
    $params[] = "%$country%";
}

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
$teachers = R::find('teacher', "$query LIMIT ? OFFSET ?", 
    array_merge($params, [$limit, $offset])
);

// Calculate total pages
$totalPages = ceil($totalTeachers / $limit);
?>


<article class="card" style="max-width: 80%; margin: 0 auto;">
    <header>
        <h2>Search Teacher</h2>
    </header>

    <!-- Search Form -->
  

<form method="get" action="./index.php" style="margin-bottom: 2rem;">
    <input type="hidden" name="action" value="school_search_candidate">

    <div class="grid">
        <!-- COUNTY DROPDOWN -->
        <select id="county" name="county">
            <option value="">All Counties</option>
            <?php foreach (kenyan_counties() as $c): ?>
                <option value="<?= h($c) ?>" <?= ($county === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
            <?php endforeach; ?>
        </select>
        
        <!-- GRADE LEVEL DROPDOWN -->
        <select id="grade" name="grade">
            <option value="">All Grade Levels</option>
            <?php foreach (kenyan_grade_levels() as $gl): ?>
                <option value="<?= h($gl) ?>" <?= ($grade === $gl) ? 'selected' : '' ?>><?= h($gl) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- SUBJECT -->
        <input type="text" id="subject" name="subject" placeholder="Subject (e.g. Maths, Kiswahili)" value="<?= h($subject) ?>">
        
        <!-- EXPERIENCE -->
        <input type="number" id="experience" name="experience" placeholder="Min. Exp (Years)" value="<?= $experience > 0 ? h($experience) : '' ?>" min="0">

        <button type="submit" class="primary" style="margin-bottom: 0;">Search Teachers</button>
    </div>
</form>


    <!-- Results Table -->
    <?php if (empty($teachers)): ?>
        <p>No teachers found.</p>
    <?php else: ?>
        <table role="grid">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Subjects</th>
                    <th>Experience</th>
                    <th>Grades</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teachers as $teacher): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($teacher->name); ?></td>
                        <td><?php echo htmlspecialchars($teacher->teaching_subjects); ?></td>
                        <td><?php echo htmlspecialchars($teacher->years_of_experience); ?> years</td>
                        <td><?php echo htmlspecialchars($teacher->grade_levels); ?></td>
                        <td><a href="?action=teacher_profile&id=<?php echo $teacher->id; ?>">View Profile</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <!-- Pagination Links -->
<div class="grid gap-2">
    <?php
    // Remove 'page' from query parameters to prevent duplication
    $queryParams = $_GET;
    unset($queryParams['page']);

    // Display "Page X of Y (Total Results)"
    echo "<p>Page $page of $totalPages (Total Results: $totalTeachers)</p>";

    // First Page Link
    if ($page > 1) {
        echo "<a href='?page=1&" . http_build_query($queryParams) . "'>First</a>";
    }

    // Previous Page Link
    if ($page > 1) {
        $prevPage = $page - 1;
        echo "<a href='?page=$prevPage&" . http_build_query($queryParams) . "'>Previous</a>";
    }

    // Next Page Link
    if ($page < $totalPages) {
        $nextPage = $page + 1;
        echo "<a href='?page=$nextPage&" . http_build_query($queryParams) . "'>Next</a>";
    }

    // Last Page Link
    if ($page < $totalPages) {
        echo "<a href='?page=$totalPages&" . http_build_query($queryParams) . "'>Last</a>";
    }
    ?>
</div>
    <?php endif; ?>
</article>