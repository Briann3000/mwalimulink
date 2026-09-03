<?php
// views/pay_subscription.php

// Check if the school is logged in or just registered
if (!isset($_COOKIE['school_id']) && !isset($_COOKIE['new_school_id'])) {
    header('Location: index.php?action=school_login');
    exit();
}

// Determine the school ID
$school_id = isset($_COOKIE['school_id']) ? $_COOKIE['school_id'] : $_COOKIE['new_school_id'];

// Fetch the school details
$school = R::load('school', $school_id);
if (!$school->id) {
    echo "<div class='w3-container w3-red'><h2>Error</h2><p>School not found.</p></div>";
    exit();
}

// If the form is submitted, process the payment request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // IntaSend API credentials
    $public_key = "ISPubKey_live_40f25458-716c-47c5-b049-786fd1f3a1ce";

    // Payment details
    $amount = 12; // Subscription amount (KES 12)
    $currency = "KES";
    $host = "http://localhost/mwalimu.info/"; // Your base URL
    $redirect_url = $host . "/index.php?action=subscription_callback"; // Callback URL
    $api_ref = "MwalimuLink_School_Subscription_" . $school->id;  // Unique reference

    // Sanitize the school name to remove invalid characters
    $school_name = preg_replace('/[^a-zA-Z0-9\\s\\-\\_]/', '', $school->name);

    // API request payload
    $data = [
        "public_key" => $public_key,
        "amount" => $amount,
        "currency" => $currency,
        "api_ref" => $api_ref,
        "redirect_url" => $redirect_url,
        "first_name" => $school_name, // Use the sanitized name
        "last_name" => "", // You can leave this blank
        "email" => $school->email,
        "phone" => $school->phone_number,
        "country" => "KE"
    ];

    // Initialize cURL session
    $ch = curl_init("https://payment.intasend.com/api/v1/checkout/");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    // Execute the request
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Decode JSON response
    $responseData = json_decode($response, true);

    // Redirect if successful
    if ($http_code == 201 && isset($responseData['url'])) {
        header("Location: " . $responseData['url']);
        exit();
    } else {
        echo "<div class='w3-container w3-red'><h2>Payment Error</h2><p>HTTP $http_code - " . json_encode($responseData) . "</p></div>";
    }
}
?>


    <style>
        body {
            background-color: #f1f1f1; /* Light grey background */
        }

        .payment-card {
            max-width: 400px;
            margin: auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.2);
        }

        .w3-label {
            display: block;
            margin-top: 10px;
            font-weight: bold;
            color: black;
        }

        .w3-green {
            background-color: #4CAF50 !important; /* Green theme color */
        }
    </style>


    <div class="w3-container w3-padding-32 w3-light-grey">
        <div class="w3-card payment-card">
            <div class="w3-container w3-green">
                <h2 class="w3-center">School Subscription Payment</h2>
            </div>
            <p class="w3-center">Please pay the subscription fee to activate your account.</p>
            <form method="post" class="w3-container">
                <button class="w3-button w3-block w3-round w3-green w3-margin-top" type="submit">
                    <i class="fas fa-credit-card"></i> Pay Now (KES 12,000)
                </button>
            </form>
        </div>
    </div>

