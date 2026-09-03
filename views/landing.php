<style>
  #splash-container {
    width: 100%;
    min-height: 100vh;
   /*  min-width: 100vh; 22 apr 2025*/
    background-color: #002326;
    background-image: url('prof-smile.png');
    background-size: cover;
    /* This makes the background image cover the entire container */
    background-position: center;
    /* Centers the image within the container */
    display: flex;
    flex-wrap: wrap; /* ADDED THIS LINE 22 aprl 2025*/
    align-items: flex-start;
    justify-content: space-between;
    /* Space out the link container and impact factor buttons */
    padding: 0px;
    /* Remove padding */
    margin: 0px;
    /* Remove margin */
     box-sizing: border-box; /* ADDED THIS LINE 22apr2025*/
  }

  .link-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-left: 20px;
    /* Add some left margin for spacing */
    margin-top: 20px;
    /* Add some top margin for spacing */
    box-sizing: border-box; /* ADD THIS LINE 22apr2025 */
  }

  .link-container a {
    color: #ccc;
    padding: 14px 16px;
    text-decoration: none;
    background-color: #002326;
    border-radius: 5px;
    font-family: 'Exo 2', sans-serif;
    display: flex;
    /* Align icon and text */
    align-items: center;
    /* Vertically align icon and text */
     box-sizing: border-box; /* ADDED THIS LINE 22apr2025 */
  }

  .link-container a i {
    margin-right: 8px;
    /* Space between icon and text */
  }

  .link-container a:hover {
    background-color: #185947;
    color: #00ff00;
    border: 1px solid black;
  }

  .impact-container {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-right: 20px;
    /* Add some right margin for spacing */
    margin-top: 20px;
    /* Add some top margin for spacing */
    box-sizing: border-box; /* ADDED THIS LINE 22apr2025 */
  }


  .impact-button {
    margin-right: 0px;
    color: #003300;
    padding: 7px 0px;
    text-decoration: none;
    background-color: #feffca/* #002326 / # f1f1f7*/;
    border-radius: 30px;
    font-family: 'Exo 2', sans-serif;
    display: flex;
    /* Align icon and text */
    align-items: center;
    /* Vertically align icon and text */
    /*ADDED 22apr2025*/
    cursor: pointer;
  box-sizing: border-box;
  }

  .impact-button i {
    margin-right: 8px;
    /* Space between icon and text */
  }

  .impact-button:hover {
    background-color: #ddd/* #185947 */;
    color: #003300;
    border: 1px solid black;
    cursor: pointer;
    /* Add a pointer cursor on hover */
  }
</style>
<div id="splash-container">
  <div class="link-container">
    <a href="/register/school"><i class="fa fa-school"></i> School Register</a>
    <a href="/schools/public"><i class="fa fa-user-graduate"></i> Search Schools</a>
    <a href="/login/school"><i class="fa fa-clipboard-check"></i> School Login</a>
    <a href="/register/teacher"><i class="fa fa-user-plus"></i> Teacher Register Free</a>
    <a href="/login/teacher"><i class="fa fa-sign-in-alt"></i> Teacher Login</a>
    <h3 style="color:#ffffff;">Why Mwalimu Link &trade; ?</h3>
    <span style="color:#ffffff;">
        <strong> 
      &raquo; Linking Schools to teachers<br />
      &raquo; Linking Teachers with schools<br />
      &raquo; Linking Teacher students with TP<br />
      &raquo; Access to thousands of school contacts<br />
      &raquo; Access to thousands of e-Learning resources<br />
      + And much more ...
      </strong>
    </span>
    <a href="/login/teacher" class="impact-button"><i class="fa fa-chart-bar"></i> + Over 100,000 Teachers</a>
    <a href="/schools/public" class="impact-button"><i class="fa fa-line-chart"></i> + Over 40,000 Schools</a>
    <a href="/login/teacher" class="impact-button"><i class="fa fa-area-chart"></i> + Over 100 Jobs Posted Daily</a>
    <a href="/schools/international" class="impact-button"><i class="fa fa-globe"></i> + Over 6000 international opportunities</a>

    <!--<button class="impact-button"><i class="fa fa-chart-bar"></i> + Over 100,000 Teachers </button>
    <button class="impact-button"><i class="fa fa-line-chart"></i> + Over 40,000 Schools</button>
    <button class="impact-button"><i class="fa fa-area-chart"></i> + Over 100 Jobs Posted Daily</button>-->
  </div>
  <div class="impact-container">
    <!--<h3>Why Mwalimu Link &trade;</h3>
          &raquo; Schools linking with teachers<br/>
          &raquo; Teacher linkage to schools<br/>
          &raquo; Teacher student(UT) placement in school<br/>
          &raquo; Teacher placement opportunities<br/>
          &raquo; Teacher student internship opportunities<br/>
          &raquo; Access to thousand of school contacts<br/>
          &raquo; Access to thousands of e-Learning resources.<br/>
          + Much more ...

    <button class="impact-button"><i class="fa fa-chart-bar"></i> + 100,000 Teachers </button>
    <button class="impact-button"><i class="fa fa-line-chart"></i> + Over 27,000 Schools</button>
    <button class="impact-button"><i class="fa fa-area-chart"></i> + Over 76 Jobs Posted Daily</button>-->
  </div>
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Exo+2:wght@400;700&display=swap">
