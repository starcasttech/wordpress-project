<?php
/**
 * Brevo Email Test Script
 */

define('WP_USE_THEMES', false);
require(__DIR__ . '/wp-load.php');

$to = 'info@starcast.co.za';
$subject = '🧪 Brevo Email Test - ' . date('H:i:s');

$message = '
<html>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <h2 style="color: #d67d3e;">✅ Email Test Successful!</h2>
    <p>This is a test email sent from your <strong>local WordPress</strong> via <strong>Brevo (Sendinblue)</strong>.</p>

    <div style="background: #f5f5f5; padding: 15px; border-left: 4px solid #d67d3e; margin: 20px 0;">
        <strong>Test Details:</strong><br>
        Time: ' . date('Y-m-d H:i:s') . '<br>
        From: Local WordPress (localhost:8081)<br>
        Via: Brevo API<br>
        Sender: info@starcast.co.za
    </div>

    <p>If you received this email, your <strong>signup notifications are working correctly!</strong></p>

    <p style="color: #666; font-size: 12px;">
        Sent by Starcast Technologies Email System
    </p>
</body>
</html>
';

$headers = array(
    'Content-Type: text/html; charset=UTF-8',
    'From: Starcast Technologies <info@starcast.co.za>'
);

echo "Sending test email to: {$to}\n";
echo "Subject: {$subject}\n\n";

$result = wp_mail($to, $subject, $message, $headers);

if ($result) {
    echo "✅ SUCCESS: Test email sent!\n";
    echo "Check your inbox at info@starcast.co.za\n";
    echo "\nNote: It may take 1-2 minutes to arrive.\n";
} else {
    echo "❌ FAILED: Could not send email\n";
    $error = error_get_last();
    if ($error) {
        echo "Error: " . print_r($error, true) . "\n";
    }
}
