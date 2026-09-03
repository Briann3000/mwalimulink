<?php
// Start the session only if it's not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Mwalimu.info connects teachers and schools across Kenya, offering job opportunities, educational resources, and a professional networking platform for educators.">
<meta name="keywords" content="teachers, schools, Kenya, teaching jobs, education, mwalimu, teacher resources, school vacancies, educators, employment">
<meta name="author" content="Mwalimu.info">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="robots" content="index, follow">
  <title>Mwalimu Link&trade;</title>

  <!-- Favicon & Icons -->
  <link rel="icon" href="favicon.ico" type="image/x-icon">
  <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">

  <!-- Pico CSS -->
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <!-- Exo 2 Font -->
  <link href="https://fonts.googleapis.com/css2?family=Exo+2:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <!-- Quicksant Font by Kesh -->
  <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;500;600;700&display=swap" rel="stylesheet">


  <!-- Custom Styles -->
      <style>
    html, body {
    margin: 0;
    padding: 0;
    overflow-x: hidden; /* Prevent horizontal scroll */
    box-sizing: border-box;
    max-width: 100vw; /*ADDED to be more sure*/
    font-family: 'Exo 2','Quicksand', Tahoma, Verdana, 'Calibri', sans-serif; /* ADDED alternative fonts */
     /* font-size: 1vw; "/" /* ADDED font size - Dynamic font size */
      background-color:'white';
    }
    
    h1,h2,h3,h4,h5,h6 {
  
    font-family: 'Exo 2', Tahoma, Verdana, 'Calibri', sans-serif; /* ADDED alternative fonts */
     /* font-size: 1vw; "/" /* ADDED font size - Dynamic font size */
    
    }
    a {
  
    font-family: 'Exo 2', Tahoma, Verdana, 'Calibri', sans-serif; /* ADDED alternative fonts */
    font-weight:bold;
    text-decoration: underline dashed;

    
    /* font-size: 1vw;  //ADDED font size - Dynamic font size */
    
    }
    a:hover {
    text-decoration: underline double;
    font-weight:bold;
    color:green;
            }
    /*
    body {
      font-family: 'Exo 2', sans-serif;
      background-color:'white';
    }
    */

    /* Custom styles for the hamburger menu */
    .hamburger {
      display: none;
      cursor: pointer;
    }

    .hamburger span {
      display: block;
      width: 25px;
      height: 3px;
      margin: 5px auto;
      background-color: black;
    }

    .nav-menu {
      display: flex;
      background-color: Sand; /* Jade Navbar background color */
    }

    .nav-menu li a, .dropdown-toggle {
      color: #333; /* Text color */
      padding: 14px 16px;
      text-decoration: none;
      background-color: #f1f1f1; /* Light grey background */
      border-radius: 5px;
      margin: 0 5px;
      border: none;
      font-family: 'Exo 2', sans-serif;

    }

    .nav-menu li a:hover, .dropdown-toggle:hover {
      background-color: #185947; /* Greenish background on hover */
      color: #00ff00; /* Text color remains dark */
      border: 1px solid black; /* Black border on hover */
      font-weight: bold;
    }

    .dropdown {
      position: relative;
      display: inline-block;
    }

    .dropdown-content {
      display: none;
      position: absolute;
      background-color: #f9f9f9;
      min-width: 160px;
      box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
      z-index: 1;
    }

    .dropdown-content a {
      color: #333; /* Dropdown link color */
      padding: 12px 16px;
      text-decoration: none;
      display: block;
      font-family: 'Exo 2', sans-serif;
    }

    .dropdown-content a:hover {
      background-color: #ccc; /* Greyish background on hover */
      color: #333; /* Text color remains dark */
    }

    .dropdown:hover .dropdown-content {
      display: block;
    }

    @media (max-width: 768px) {
      .hamburger {
        display: block;
      }

      .nav-menu {
        display: none; /* This is key: Start hidden */
        flex-direction: column;
        position: absolute;
        top: 50px;
        left: 0;
        background-color: white;
        width: 100%;
        padding: 1rem;
        z-index: 100; /* ADD THIS: Ensure it's on top */
      }

      .nav-menu.active {
        display: flex; /* This is the main fix: Make it flex when active */
        flex-direction: column;
      }

      .nav-menu.active li a {
        color: #333; /* Ensure links are visible */
        background-color: #f1f1f1; /* Light grey background */
        padding: 0.5rem;
        border-radius: 5px;
        margin: 0.5rem 0;
       font-family: 'Exo 2', Tahoma, Verdana, Calibri, sans-serif; /* ADDED alternative fonts */
      
      }

      .nav-menu.active li a:hover {
        background-color: #185947; /* Greenish background on hover */
        color: #00ff00; /* Text color remains dark */
        border: 1px solid black; /* Black border on hover */
        font-weight: bold;
      }

      .dropdown-content {
        position: static;
        background-color: transparent;
        box-shadow: none;
        padding: 0;
      }

      .hamburger span {
        background-color: black;
      }
    }

    /* Logo Styles */
    .logo-container {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    .logo img {
      max-width: 100%;
      height: auto;
    }
  </style>
<script>
   /** if (window.location.protocol == "http:") {
      console.log("You are not connected with a secure connection.")
      console.log("Reloading the page to a Secure Connection...")
      window.location = document.URL.replace("http://", "https://");
    }

    if (window.location.protocol == "https:") {
      console.log("You are connected with a secure connection.")
    }*/
</script>
<script src="//instant.page/5.2.0" type="module" integrity="sha384-jnZyxPjiipYXnSU0ygqeac2q7CVYMbh84q0uHVRRxEtvFPiQYbXWUorga2aqZJ0z"></script>
</head>
<body style="margin-top: 0px;">
  <header>
    <?php
    $authUser = auth_user();
    if ($authUser) {
        $roleLabel = ucfirst($authUser['role']);
        $dashRoute = ($authUser['role'] === 'school') ? 'school_dashboard' : (($authUser['role'] === 'admin') ? 'admin_dashboard' : 'teacher_dashboard');
        echo '<sup style="position: absolute; top: 5px; right: 5px; font-size: 0.75rem; background-color: #f8f9fa; padding: 5px 10px; border-radius: 5px; border: 1px solid #ddd; z-index: 100;">
            <i class="fa fa-user" style="color: #007bff;"></i> ' . h($authUser['name']) . ' (' . $roleLabel . ') | 
            <a href="./index.php?action=' . $dashRoute . '"><i class="fa fa-tachometer-alt"></i> Dashboard</a> | 
            <a href="./index.php?action=logout" style="color: #dc3545;"><i class="fa fa-sign-out-alt"></i> Logout</a>
        </sup>';
    }
    ?>
    <nav class="container">
      <h1><a href="index.php?action=landing" style="color: white;"><img src="mwalimu-link-logo-clean.png" alt="Logo" class="logo"></a></h1>

      <div class="hamburger" id="hamburger">
        <span></span>
        <span></span>
        <span></span>
      </div>
      <ul class="nav-menu" id="nav-menu">
        <li><a href="index.php?action=landing"><i class="fa fa-home" style="color: #333;"></i> Home</a></li>
        
        <li><a href="index.php?action=about_us"><i class="fa fa-question-circle" style="color: #333;"></i> About</a></li>
        
        <li class="dropdown">
          <a href="#" class="dropdown-toggle"><i class="fa fa-school" style="color: #333;"></i> Schools</a>
          <div class="dropdown-content">
            <a href="index.php?action=school_register">Register</a>
            <a href="index.php?action=subscribe_link">Subscribe</a>
            <a href="index.php?action=school_login">Login</a>
          </div>
        </li>
        
        <li class="dropdown">
          <a href="#" class="dropdown-toggle"><i class="fa fa-graduation-cap" style="color: #333;"></i> Teachers</a>
          <div class="dropdown-content">
            <a href="index.php?action=teacher_register">Register</a>
            <a href="index.php?action=teacher_login">Login</a>
          </div>
        </li>
        
        
        <li class="dropdown">
          <a href="#" class="dropdown-toggle"><i class="fa fa-newspaper" style="color: #333;"></i> Resources</a>
          <div class="dropdown-content">
            <a href="index.php?action=faqs">FAQs</a>
            <a href="index.php?action=faqs-on-teaching-overseas">FAQs on Teaching Overseas </a>
           <!-- <a href="https://schoolsnetkenya.com" target="_blank">SNK&trade;</a>
            <a href="https://esoma.info" target="_blank">eSoma&trade;</a>
            <a href="https://hialinstitute.com" target="_blank">HIAL&trade;</a>-->
            <a href="index.php?action=contacts"><i class="fa fa-address-book" style="color: #333;"></i> Contacts</a>
          </div>
        </li>
        <li class="dropdown">
        <!--<li>...</li>-->
        <li>
          <?php if ($schoolName || $teacherName): ?>
          <div class="dropdown">
            <a href="#" class="dropdown-toggle"><i class="fa-solid fa-circle-info" style="color: #333;"></i></a>
            <div class="dropdown-content">
              <a href="index.php?action=logout"><i class="fa-solid fa-circle-xmark"></i>Logout</a>
            </div>
          </div>
          <?php else: ?>
          <a href="index.php?action=dual_login"><i class="fa fa-sign-in-alt" style="color: #333;"></i> Login</a>
          <?php endif; ?>
        </li>
      </ul>
    </nav>

    <div style="background-color:#185947; height: 2px; width: 100%;"></div>
  </header>

  <script>
    // JavaScript for toggling the hamburger menu
    const hamburger = document.getElementById('hamburger');
    const navMenu = document.getElementById('nav-menu');

    hamburger.addEventListener('click', () => {
      navMenu.classList.toggle('active');
    });
  </script>

  <main style="padding: 0px; margin: 0px; width:100%;">