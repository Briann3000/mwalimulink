<?php
// school_list_admin.php

//session_start();

// Uncomment and setup RedBeanPHP as needed
// require 'rb.php';
// R::setup('sqlite:mwalimu.db');

// Simple session-based authentication
$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Handle admin login
if (isset($_POST['admin_login'])) {
    if ($_POST['username'] === 'admin' && $_POST['password'] === 'Kenya@254') {
        $_SESSION['is_admin'] = true;
        //header("Location: " . $_SERVER['PHP_SELF']);
        header('Refresh:0');
        exit();
    } else {
        $login_error = "Invalid credentials!";
    }
}

// Handle logout
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Only proceed with CRUD if admin is logged in
if ($is_admin && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_school':
            $school = R::dispense('school');
            $school->school_name = $_POST['school_name'];
            $school->school_email = $_POST['school_email'];
            R::store($school);
            break;

        case 'update_school':
            $school = R::load('school', (int)$_POST['school_id']);
            if ($school->id) {
                $school->school_name = $_POST['school_name'];
                $school->school_email = $_POST['school_email'];
                R::store($school);
            }
            break;

        case 'delete_school':
            $school = R::load('school', (int)$_POST['school_id']);
            if ($school->id) R::trash($school);
            break;
    }
}

// Fetch all schools for admin listing (no pagination for simplicity)
$schools = R::findAll('school', 'ORDER BY school_name');
?>

<article class="container">

    <?php if (!$is_admin): ?>
        <form method="post">
            <fieldset>
                <legend>Admin Login</legend>
                <?php if (isset($login_error)): ?>
                    <small class="error"><?= htmlspecialchars($login_error) ?></small>
                <?php endif; ?>
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit" name="admin_login" class="contrast">Login</button>
            </fieldset>
        </form>
    <?php else: ?>
        <form method="post" style="text-align: right; margin-bottom: 1em;">
            <button type="submit" name="logout" class="contrast">Logout</button>
        </form>

        <form method="post">
            <fieldset>
                <legend>School Management</legend>
                <input type="hidden" name="school_id" id="school_id">
                <input type="text" name="school_name" id="school_name" placeholder="School Name" required>
                <input type="email" name="school_email" id="school_email" placeholder="Email" required>
                <div class="grid">
                    <button type="submit" name="action" value="add_school" class="outline">Add School</button>
                    <button type="submit" name="action" value="update_school" class="outline">Update School</button>
                    <button type="button" onclick="clearForm()" class="secondary">Cancel</button>
                </div>
            </fieldset>
        </form>

        <div class="table-responsive" style="margin-top: 1em;">
            <table class="table striped">
                <?php foreach ($schools as $school): ?>
                <tr>
                    <td><?= htmlspecialchars($school->school_name) ?></td>
                    <td><?= htmlspecialchars($school->school_email) ?></td>
                    <td>
                        <button onclick="editSchool(<?= $school->id ?>, '<?= addslashes($school->school_name) ?>', '<?= addslashes($school->school_email) ?>')"
                                class="small contrast">Edit</button>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="school_id" value="<?= $school->id ?>">
                            <button type="submit" name="action" value="delete_school" 
                                    class="small contrast" 
                                    onclick="return confirm('Delete this school?')">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>

</article>

<script>
function editSchool(id, name, email) {
    document.getElementById('school_id').value = id;
    document.getElementById('school_name').value = name;
    document.getElementById('school_email').value = email;
    window.scrollTo({ top: document.querySelector('form').offsetTop - 20, behavior: 'smooth' });
}
function clearForm() {
    document.getElementById('school_id').value = '';
    document.getElementById('school_name').value = '';
    document.getElementById('school_email').value = '';
}
</script>
