<?php
require 'class.phpmailer.php';
require 'class.smtp.php';

$msg = '';
if (array_key_exists('email', $_POST)) {
    date_default_timezone_set('Etc/UTC');

    $mail = new PHPMailer();
    $mail->isSMTP();
    $mail->SMTPDebug = 0;  // 0 = off (for production), 1 = client messages, 2 = client and server messages
    $mail->Host = 'smtp.gmail.com';
    $mail->Port = 587;
    $mail->SMTPSecure = 'tls';
    $mail->SMTPAuth = true;
    $mail->Username = 'themwalimulink@gmail.com'; // Your Gmail address
    $mail->Password = 'mfix kcub okqw aipu'; // Your Gmail app password
    $mail->setFrom($_POST['email'], $_POST['name']); // Form submission email and name
    $mail->addReplyTo($_POST['email'], $_POST['name']); // Reply to the submitter
    $mail->addAddress('themwalimulink@gmail.com', 'Mwalimu Link'); // Recipient email and name

    // Always BCC to the same address
    $mail->addBCC('themwalimulink@gmail.com');

    $mail->Subject = 'MwalimuLink Contact Form Submission';
    $mail->msgHTML(
        "Name: " . htmlspecialchars($_POST['name']) . "<br>" .
        "Email: " . htmlspecialchars($_POST['email']) . "<br>" .
        "Message: " . nl2br(htmlspecialchars($_POST['message']))
    ); // HTML message
    $mail->AltBody = "Name: " . $_POST['name'] . "\nEmail: " . $_POST['email'] . "\nMessage: " . $_POST['message']; // Plain Text message

    // Handle file attachment if uploaded
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
        // Optional: Validate file size and type here for security
        $uploadFilePath = $_FILES['attachment']['tmp_name'];
        $uploadFileName = $_FILES['attachment']['name'];

        // Attach the uploaded file to the email
        $mail->addAttachment($uploadFilePath, $uploadFileName);
    }

    if (!$mail->send()) {
        $msg = "Mailer Error: " . $mail->ErrorInfo;
    } else {
        $msg = "Message sent! Thank you for contacting us.";
    }
}
?>

<div class="container">
    <article class="card" style="margin: 2rem auto; max-width: 800px; padding: 1.5rem;">
        <h3>Contact Us</h3>
        <?php if ($msg): ?>
            <p style="color: <?php echo (strpos($msg, 'Error') !== false) ? 'red' : 'green'; ?>;">
                <hr/><?php echo $msg; ?> One of our representatives will get back to you ASAP.<hr/>
            </p>
        <?php endif; ?>
        <form method="post" action="" enctype="multipart/form-data" style="margin-top: 1rem;">
            <div style="margin-bottom: 1rem;">
                <label for="name">Name:</label>
                <input type="text" id="name" name="name" required style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label for="message">Message:</label>
                <textarea id="message" name="message" rows="4" required style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;"></textarea>
            </div>
            <div style="margin-bottom: 1rem;">
                <label for="attachment">Attachment (optional):</label>
                <input type="file" id="attachment" name="attachment" style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 4px;">
            </div>
            <button type="submit" style="background-color: #4CAF50; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;">Send Message</button>
        </form>
    </article>
</div>
