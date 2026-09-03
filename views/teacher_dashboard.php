<?php
// Enforce teacher authentication
require_auth('teacher');

$authUser = auth_user();
$teacher_id = $authUser['user_id'];
$teacher_name = $authUser['name'];
?>

<article class="card" style="max-width: 80%; margin: 0 auto;">
    <header>
        <h2>Teacher Dashboard</h2>
    </header>

    <div class="grid gap-2">
        <h4>Welcome, <?php echo $teacher_name; ?>!</h4>
       

        <!-- Display Teacher Information -->
        <!--<h4>Your Information</h4>-->
        <span style="align-items: right;">
        <p><b>Teacher Data ID:</b> <?php echo $teacher_id; ?></p>
        <p><b>Name:</b> <?php echo $teacher_name; ?></p>
        </span>
    </div>

    <div class="grid gap-2">
        
        <button type="button" onclick="location.href='index.php?action=public_school_search'" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center; background-color: #f7f7f2; color: black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-school fa-3x"></i>
            <br>
            <font color="green">Free</font> Public School Search
        </button>

        <button type="button" onclick="location.href='index.php?action=teacher_update&teacher_id=<?php echo urlencode($teacher_id); ?>'" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center; background-color: #f7f7f2; color: black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-edit fa-3x"></i>
            <br>
            Update My Profile
        </button>
        
        <button type="button" onclick="location.href='index.php?action=teacher_job_search'" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center; background-color: #f7f7f2; color: black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-search fa-3x"></i>
            <br>
            Search Job Postings
        </button>

        <button type="button" onclick="location.href='index.php?action=blog'" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center; background-color: #f7f7f2; color: black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-newspaper-o fa-3x"></i>
            <br>
            &nbsp;Resources
        </button>
    </div>
    <!-- SECOND ROW OF DASHBOARD BUTTONS -->
      <div class="grid gap-2">
        
        <button type="button" onclick="location.href='index.php?action=private_school_search'" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center; background-color: #f7f7f2; color: black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-home fa-3x"></i>
            <br>
            Private School Search
        </button>

        <button type="button" onclick="location.href='index.php?action=international_school_search'"  style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center; background-color: #f7f7f2; color: black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-globe fa-3x"></i>
            <br/>
             International<br/>School Search
        </button>
        
        <button type="button" onclick="location.href='index.php?action=get_in_touch'" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center; background-color: #f7f7f2; color: black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-comment fa-3x"></i>
            <br>
            Get In Touch
        </button>

        <button type="button" onclick="location.href='index.php?action=logout'" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center; background-color: #cccccc; color: black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-sign-out-alt fa-3x"></i>
            <br>
            Logout
        </button>
    </div>
</article>

<?php
// Handle logout functionality
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Clear cookies by setting them with an expiration time in the past
    setcookie('teacher_id', '', time() - 3600, '/');
    setcookie('teacher_name', '', time() - 3600, '/');

    // Redirect to the landing page
    header('Location: index.php?action=landing');
    exit();
}
?>
