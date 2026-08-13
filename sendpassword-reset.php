<?php
$email = $_POST["email"];
$token = bin2hex(random_bytes(16));
$token_hash = hash("sha256", $token);
$expiry = date("Y-m-d H:i:s", time() + 60 * 30);
$mysqli = require __DIR__ . "/database.php";
$sql = "UPDATE user
        SET reset_token_hash = ?,
            reset_token_expires_at = ?
        WHERE email = ?";
$stmt = $mysqli->prepare($sql);     
$stmt->bind_param("sss", $token_hash, $expiry, $email);
$stmt->execute();

if ($mysqli->affected_rows) {
    $mail = require __DIR__ . "/mailer.php";
    $mail->setFrom("noreply@rainbowforestparadise.com", "Rainbow Forest Paradise Resort And Campsite");
    $mail->addAddress($email);
    $mail->Subject = "Password Reset - Rainbow Forest Paradise Resort And Campsite";
    
    // Get site domain for links
    $domain = "https://rainbowforestparadiseresortandcampsite.com";
    
    // Correctly embed the token in the email body
    $resetLink = "{$domain}/reset-password.php?token=" . urlencode($token);
    
    // HTML email with logo and styling
    $mail->isHTML(true);
    $mail->Body = <<<END
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Password Reset</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333333;
                margin: 0;
                padding: 0;
            }
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                border: 1px solid #e4e4e4;
                border-radius: 5px;
                overflow: hidden;
            }
            .email-header {
                background-color: #2b5142;
                padding: 20px;
                text-align: center;
            }
            .email-logo {
                max-width: 150px;
                height: auto;
                border-radius: 50px;
            }
            .email-title {
                color: #ffffff;
                font-size: 22px;
                margin: 10px 0;
                font-weight: bold;
            }
            .email-content {
                padding: 30px;
                background-color: #ffffff;
            }
            .email-greeting {
                font-size: 18px;
                margin-bottom: 20px;
                font-weight: bold;
            }
            .email-message {
                margin-bottom: 30px;
                font-size: 16px;
            }
            .email-button {
                display: inline-block;
                background-color: #2b5142;
                color: #ffffff !important;
                text-decoration: none;
                padding: 12px 30px;
                border-radius: 4px;
                font-weight: bold;
                font-size: 16px;
                margin: 15px 0;
            }
            .email-instructions {
                margin-top: 25px;
                font-size: 14px;
                color: #666666;
            }
            .email-expiry {
                font-style: italic;
                color: #777777;
                font-size: 14px;
            }
            .email-footer {
                background-color: #f7f7f7;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #666666;
            }
            .email-help {
                margin-top: 15px;
            }
            @media screen and (max-width: 480px) {
                .email-content {
                    padding: 20px 15px;
                }
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <img src="https://rainbowforestparadiseresortandcampsite.com/images/rainbow-logo.png" alt="Rainbow Forest Paradise Logo" class="email-logo">
                <h1 class="email-title">Rainbow Forest Paradise Resort And Campsite</h1>
            </div>
            <div class="email-content">
                <p class="email-greeting">Password Reset Request</p>
                <div class="email-message">
                    <p>We received a request to reset your password for your Rainbow Forest Paradise account. To complete the password reset process, please click the button below:</p>
                </div>
                <div style="text-align: center;">
                    <a href="{$resetLink}" class="email-button">Reset Password</a>
                </div>
                <div class="email-instructions">
                    <p>If you did not request a password reset, please ignore this email or contact customer support if you have concerns about your account.</p>
                    <p class="email-expiry">This password reset link will expire in 30 minutes.</p>
                </div>
            </div>
            <div class="email-footer">
                <p>&copy; 2025 Rainbow Forest Paradise Resort And Campsite. All rights reserved.</p>
                <p class="email-help">If you need assistance, please contact our support team at <a href="mailto:support@rainbowforestparadise.com">support@rainbowforestparadise.com</a></p>
            </div>
        </div>
    </body>
    </html>
    END;
    
    // Plain text alternative
    $mail->AltBody = "Password Reset - Rainbow Forest Paradise Resort And Campsite\n\n".
                    "We received a request to reset your password. Please visit this link to reset your password:\n".
                    "{$resetLink}\n\n".
                    "This link will expire in 30 minutes.\n\n".
                    "If you did not request a password reset, please ignore this email.";
    
    try {
        $mail->send();
        echo "Message sent, Please check your email address inbox.";
    } catch (Exception $e) {
        echo "Message can't be sent. Mailer error: {$mail->ErrorInfo}";
    }
} else {
    echo "No user found with that email address.";
}
?>