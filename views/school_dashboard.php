<?php
// Enforce school authentication
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];
$school_name = $authUser['name'];

// Load school details from the database
$school = R::load('school', $school_id);

// Check if the subscription is active and not expired
$now = new DateTime();

// Validate subscription_expiry before creating a DateTime object
if (!empty($school->subscription_expiry)) {
    $expiry_date = new DateTime($school->subscription_expiry);
} else {
    // Handle null or empty subscription_expiry (e.g., set a default or redirect)
    $expiry_date = new DateTime('1970-01-01'); // Default to an old date for safety
}

// Check subscription status and expiry date
if ($school->status !== 'active' || $expiry_date < $now) {
    // Subscription is inactive or expired - redirect to payment page
    header('Location: index.php?action=pay_subscription');
    exit();
}
?>

<article class="card" style="max-width: 80%; margin: 0 auto;">
    <header>
        <h2>School Dashboard</h2>
    </header>

    <div class="grid gap-2">
        <h3>Welcome, <?php echo htmlspecialchars($school_name); ?>!</h3>
    </div>

    <div class="grid gap-2">
         <button type="button" onclick="location.href='index.php?action=school_search_candidate'" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center; background-color: #f7f7f2;color:black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-search fa-3x"></i>
            <br>
            Search Teacher(s)
        </button>
        <button type="button" onclick="location.href='index.php?action=school_post_job'" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center; background-color: #f7f7f2;color:black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-briefcase fa-3x"></i>
            <br>
            Post a Job
        </button>

       <button type="button" onclick="location.href='index.php?action=logout'" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center; background-color: #cccccc;color:black; border: 1px solid #333; box-shadow: 0 0 10px rgba(128, 128, 128, 0.5);">
            <i class="fa fa-sign-out-alt fa-3x"></i>
            <br>
            Logout
        </button>
    </div>
</article>


<!--<article class="card" style="max-width: 80%; margin: 0 auto;">
    <header>
        <h2>School Dashboard</h2>
    </header>

    <div class="grid gap-2">
        <h3>Welcome, <?php echo htmlspecialchars($school_name); ?>!</h3>
    </div>

    <div class="grid gap-2">
        <a href="index.php?action=school_post_job" class="btn btn-primary" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center;">
            <i class="fa fa-briefcase fa-3x"></i>
            <br>
            Post a Job
        </a>

        <a href="index.php?action=school_search_candidate" class="btn btn-primary" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center;">
            <i class="fa fa-search fa-3x"></i>
            <br>
            Search Candidates
        </a>

        <a href="index.php?action=logout" class="btn btn-danger" style="width: 10rem; height: 10rem; display: flex; justify-content: center; align-items: center;">
            <i class="fa fa-sign-out-alt fa-3x"></i>
            <br>
            Logout
        </a>
    </div>
</article>-->
