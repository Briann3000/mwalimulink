<?php
// Only process if the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Basic input sanitization
    $name = isset($_POST['name']) ? strip_tags($_POST['name']) : '';
    $email = isset($_POST['email']) ? strip_tags($_POST['email']) : '';
    $department = isset($_POST['department']) ? strip_tags($_POST['department']) : '';
    $subject = isset($_POST['subject']) ? strip_tags($_POST['subject']) : '';
    $message = isset($_POST['message']) ? strip_tags($_POST['message']) : '';

    require 'class.phpmailer.php';
    require 'class.smtp.php';

    $mail = new PHPMailer();
    $mail->IsSMTP();
    $mail->SMTPAuth = true;
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPSecure = 'tls';
    $mail->Username = 'infomwalimulink@gmail.com';
    $mail->Password = 'mfix kcub okqw aipu';

    $mail->SetFrom('infomwalimulink@gmail.com', 'MwalimuLink Portal');
    $mail->AddAddress('infomwalimulink@gmail.com');
    $mail->AddReplyTo($email, $name);
    $mail->Sender = 'infomwalimulink@gmail.com'; // Return-Path

    // Department prefix in subject
    $mail->Subject = "{$department}: Message from Mwalimu.info Member - {$subject}";

    // Email body
    $mail->Body = "Name: {$name}\nEmail: {$email}\nDepartment: {$department}\n\nMessage:\n{$message}";

    // Handle attachment
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == UPLOAD_ERR_OK) {
        $mail->AddAttachment($_FILES['attachment']['tmp_name'], $_FILES['attachment']['name']);
    }

    // Send email
    if(!$mail->Send()) {
        $feedback = '<p style="color:red;">Mailer Error: ' . $mail->ErrorInfo . '</p>';
    } else {
        $feedback = '<p style="color:green;">Message sent successfully! We\'ll respond within 24 hours.</p>';
    }
}
?>



    
    <div class="container" style="max-width: 600px; margin: 3rem auto;">
            
      <article class="card">
        <h2>Contact Form</h2>
        <h4>You can use the form provided below to get in touch with us and a representative will get back to you as soon as possible.</h4>
        <?php if (!empty($feedback)) echo $feedback; ?>
        <form action="?action=get_in_touch" method="POST" enctype="multipart/form-data">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" required>

            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>

            <label for="department">Department:</label>
            <select id="department" name="department" required>
                <option value="">Choose...</option>
                <option value="Finance">Finance</option>
                <option value="Accounts">Accounts</option>
                <option value="Registration">Registration</option>
                <option value="Technical Support">Technical Support</option>
            </select>

            <label for="subject">Subject:</label>
            <input type="text" id="subject" name="subject" required>

            <label for="message">Message:</label>
            <textarea id="message" name="message" rows="5" required></textarea>

            <label for="attachment">Attachment (optional):</label>
            <input type="file" id="attachment" name="attachment">

            <button type="submit">Send Message</button>
        </form>
    </article>
</div>
    
    
    <!--    <form action="?action=get_in_touch" method="POST" enctype="multipart/form-data">
        <div>
            <label>Name:</label>
            <input type="text" name="name" required>
        </div>
        
        <div>
            <label>Email:</label>
            <input type="email" name="email" required>
        </div>

        <div>
            <label>Department:</label>
            <select name="department" required>
                <option value="">Choose...</option>
                <option value="Finance">Finance</option>
                <option value="Accounts">Accounts</option>
                <option value="Registration">Registration</option>
                <option value="Technical Support">Technical Support</option>
            </select>
        </div>

        <div>
            <label>Subject:</label>
            <input type="text" name="subject" required>
        </div>

        <div>
            <label>Message:</label>
            <textarea name="message" rows="5" required></textarea>
        </div>

        <div>
            <label>Attachment (optional):</label>
            <input type="file" name="attachment">
        </div>

        <button type="submit">Send Message</button>
    </form>
-->
