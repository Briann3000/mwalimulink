<?php
// teacher_update.php
require_auth('teacher');

$authUser = auth_user();
$teacher_id = $authUser['user_id'];

// Fetch the teacher's data from the database
$teacher = R::load('teacher', $teacher_id);

// If the teacher doesn't exist, handle the error
if (!$teacher->id) {
    echo "<div class='container'><article class='message -error'>Teacher not found.</article></div>";
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and update the teacher's information
    $teacher->name = $_POST['name'] ?? $teacher->name;
    $teacher->gender = $_POST['gender'] ?? $teacher->gender;
    $teacher->year_of_birth = $_POST['year_of_birth'] ?? $teacher->year_of_birth;
    $teacher->mobile = $_POST['mobile'] ?? $teacher->mobile;
    $teacher->email = $_POST['email'] ?? $teacher->email;

    if (!empty($_POST['password'])) {
        $teacher->password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    }

    $teacher->tsc_number = $_POST['tsc_number'] ?? $teacher->tsc_number;
    $teacher->years_of_experience = $_POST['years_of_experience'] ?? $teacher->years_of_experience;
    $teacher->grade_levels = $_POST['grade_levels'] ?? $teacher->grade_levels;
    $teacher->teaching_subjects = $_POST['teaching_subjects'] ?? $teacher->teaching_subjects;
    $teacher->qualification = $_POST['qualification'] ?? $teacher->qualification;
    $teacher->institutions_attended = $_POST['institutions_attended'] ?? $teacher->institutions_attended;
    $teacher->county = $_POST['county'] ?? $teacher->county;
    $teacher->country = $_POST['country'] ?? $teacher->country;
    $teacher->brief_profile = $_POST['brief_profile'] ?? $teacher->brief_profile;
    $teacher->status = $_POST['status'] ?? $teacher->status; // Update status

    // Save the updated teacher data
    R::store($teacher);

    echo "<div class='container'><article class='message -success'>Your profile has been updated.</article></div>";
}
?>

<div class="container">
    <article>
        <h3>Update Teacher Profile</h3>
        <form method="POST" action="">

            <label for="name">
                Full Name
                <input type="text" id="name" name="name"
                    value="<?php echo htmlspecialchars($teacher->name ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label for="gender">
                Gender
                <select id="gender" name="gender" required>
                    <option value="">-- Select Gender --</option>
                    <option value="Male" <?php if (($teacher->gender ?? '') === 'Male') echo 'selected'; ?>>Male
                    </option>
                    <option value="Female" <?php if (($teacher->gender ?? '') === 'Female') echo 'selected'; ?>>Female
                    </option>
                    <option value="Other" <?php if (($teacher->gender ?? '') === 'Other') echo 'selected'; ?>>Other
                    </option>
                </select>
            </label>

            <label for="year_of_birth">
                Year of Birth
                <input type="number" id="year_of_birth" name="year_of_birth" min="1900" max="<?php echo date('Y'); ?>"
                    value="<?php echo htmlspecialchars($teacher->year_of_birth ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    required>
            </label>

            <label for="mobile">
                Mobile
                <input type="tel" id="mobile" name="mobile"
                    value="<?php echo htmlspecialchars($teacher->mobile ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label for="email">
                Email
                <input type="email" id="email" name="email"
                    value="<?php echo htmlspecialchars($teacher->email ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label for="password">
                Password (leave blank to keep current)
                <input type="password" id="password" name="password">
            </label>

            <label for="tsc_number">
                TSC Number
                <input type="text" id="tsc_number" name="tsc_number"
                    value="<?php echo htmlspecialchars($teacher->tsc_number ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    required>
            </label>

            <label for="years_of_experience">
                Years of Experience
                <input type="number" id="years_of_experience" name="years_of_experience"
                    value="<?php echo htmlspecialchars($teacher->years_of_experience ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    required>
            </label>

            <label for="grade_levels">
                Grade Levels
                <input type="text" id="grade_levels" name="grade_levels"
                    value="<?php echo htmlspecialchars($teacher->grade_levels ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    required>
            </label>

            <label for="teaching_subjects">
                Teaching Subjects
                <input type="text" id="teaching_subjects" name="teaching_subjects"
                    value="<?php echo htmlspecialchars($teacher->teaching_subjects ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    required>
            </label>

            <label for="qualification">
                Qualification
                <select id="qualification" name="qualification" required>
                    <option value="">-- Select Qualification --</option>
                    <option value="Diploma" <?php if (($teacher->qualification ?? '') === 'Diploma') echo 'selected'; ?>>
                        Diploma</option>
                    <option value="Bachelor's Degree"
                        <?php if (($teacher->qualification ?? '') === "Bachelor's Degree") echo 'selected'; ?>>Bachelor's
                        Degree</option>
                    <option value="Postgraduate Diploma"
                        <?php if (($teacher->qualification ?? '') === 'Postgraduate Diploma') echo 'selected'; ?>>
                        Postgraduate Diploma</option>
                    <option value="Masters" <?php if (($teacher->qualification ?? '') === 'Masters') echo 'selected'; ?>>
                        Masters</option>
                    <option value="PhD" <?php if (($teacher->qualification ?? '') === 'PhD') echo 'selected'; ?>>PhD
                    </option>
                    <option value="Other" <?php if (($teacher->qualification ?? '') === 'Other') echo 'selected'; ?>>Other
                    </option>
                </select>
            </label>

            <label for="institutions_attended">
                Institution Attended
                <input type="text" id="institutions_attended" name="institutions_attended"
                    value="<?php echo htmlspecialchars($teacher->institutions_attended ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                    required>
            </label>

            <label for="county">
                County
                <input type="text" id="county" name="county"
                    value="<?php echo htmlspecialchars($teacher->county ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>

            <label for="country">
                Country
                <select id="country" name="country" required>
                    <option value="Kenya" <?php if (($teacher->country ?? '') === 'Kenya') echo 'selected'; ?>>Kenya
                    </option>
                    <option value="Uganda" <?php if (($teacher->country ?? '') === 'Uganda') echo 'selected'; ?>>Uganda
                    </option>
                    <option value="Tanzania" <?php if (($teacher->country ?? '') === 'Tanzania') echo 'selected'; ?>>
                        Tanzania</option>
                    <option value="Rwanda" <?php if (($teacher->country ?? '') === 'Rwanda') echo 'selected'; ?>>Rwanda
                    </option>
                    <option value="Burundi" <?php if (($teacher->country ?? '') === 'Burundi') echo 'selected'; ?>>Burundi
                    </option>
                    <option value="South Sudan" <?php if (($teacher->country ?? '') === 'South Sudan') echo 'selected'; ?>>
                        South Sudan</option>
                </select>
            </label>

            <label for="brief_profile">
                Brief Profile (max 100 words)
                <textarea id="brief_profile" name="brief_profile" maxlength="1000" rows="5"
                    required><?php echo htmlspecialchars($teacher->brief_profile ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            </label>

            <label for="status">
                Availability Status
                <select id="status" name="status" required>
                    <option value="available"
                        <?php if (($teacher->status ?? '') === 'available') echo 'selected'; ?>>Available</option>
                    <option value="unavailable"
                        <?php if (($teacher->status ?? '') === 'unavailable') echo 'selected'; ?>>Unavailable</option>
                </select>
            </label>

            <button type="submit" class="primary">Update Profile</button>

        </form>
    </article>
</div>