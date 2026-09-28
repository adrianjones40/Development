<?php
/**
 * Contact form handler for Dr. KBS Pharma.
 * Validates input server-side, then sends a notification email using PHP's mail().
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// ---- Configuration -------------------------------------------------------

$recipientEmail = 'drkbspharma@gmail.com';
$recipientName  = 'Dr. KBS Pharma';
$siteName       = 'Dr. KBS Pharma Website';

// ---- Helpers ---------------------------------------------------------------

function respond(bool $success, string $message, array $errors = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'errors'  => $errors,
    ]);
    exit;
}

function cleanInput(string $value): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $value));
}

// ---- Method / request checks ------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.', [], 405);
}

// ---- Collect & validate input ------------------------------------------------

$name    = isset($_POST['name']) ? cleanInput((string) $_POST['name']) : '';
$email   = isset($_POST['email']) ? cleanInput((string) $_POST['email']) : '';
$phone   = isset($_POST['phone']) ? cleanInput((string) $_POST['phone']) : '';
$service = isset($_POST['service']) ? cleanInput((string) $_POST['service']) : '';
$message = isset($_POST['message']) ? trim((string) $_POST['message']) : '';

$errors = [];

if ($name === '' || mb_strlen($name) < 2) {
    $errors['name'] = 'Please enter your name (at least 2 characters).';
} elseif (mb_strlen($name) > 80) {
    $errors['name'] = 'Name must be under 80 characters.';
}

if ($email === '') {
    $errors['email'] = 'Email is required.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

if ($phone === '') {
    $errors['phone'] = 'Phone number is required.';
} elseif (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
    $errors['phone'] = 'Please enter a valid phone number.';
}

if ($service === '' || mb_strlen($service) < 2) {
    $errors['service'] = 'Please tell us which service you need.';
} elseif (mb_strlen($service) > 120) {
    $errors['service'] = 'Service must be under 120 characters.';
}

if ($message === '' || mb_strlen($message) < 10) {
    $errors['message'] = 'Please leave us a message (at least 10 characters).';
} elseif (mb_strlen($message) > 2000) {
    $errors['message'] = 'Message must be under 2000 characters.';
}

// Simple honeypot support: if a hidden "website" field is filled, silently pretend success.
if (!empty($_POST['website'])) {
    respond(true, 'Thank you! Your message has been sent.');
}

if (!empty($errors)) {
    respond(false, 'Please correct the errors below and try again.', $errors, 422);
}

// ---- Build email -------------------------------------------------------------

$safeName    = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail   = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safePhone   = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');
$safeService = htmlspecialchars($service, ENT_QUOTES, 'UTF-8');
$safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));

$subject = 'New Contact Form Submission - ' . $siteName;

$htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>New Contact Form Submission</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f4; font-family:Arial, Helvetica, sans-serif;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4; padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:6px; overflow:hidden;">
          <tr>
            <td style="background-color:#339933; padding:20px 30px;">
              <h1 style="color:#ffffff; font-size:20px; margin:0;">{$siteName}</h1>
              <p style="color:#ffffff; font-size:14px; margin:4px 0 0;">New Contact Form Submission</p>
            </td>
          </tr>
          <tr>
            <td style="padding:30px;">
              <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px; color:#333333;">
                <tr>
                  <td style="padding:8px 0; width:120px; font-weight:bold;">Name</td>
                  <td style="padding:8px 0;">{$safeName}</td>
                </tr>
                <tr>
                  <td style="padding:8px 0; font-weight:bold;">Email</td>
                  <td style="padding:8px 0;">{$safeEmail}</td>
                </tr>
                <tr>
                  <td style="padding:8px 0; font-weight:bold;">Phone</td>
                  <td style="padding:8px 0;">{$safePhone}</td>
                </tr>
                <tr>
                  <td style="padding:8px 0; font-weight:bold;">Service</td>
                  <td style="padding:8px 0;">{$safeService}</td>
                </tr>
                <tr>
                  <td style="padding:8px 0; font-weight:bold; vertical-align:top;">Message</td>
                  <td style="padding:8px 0;">{$safeMessage}</td>
                </tr>
              </table>
            </td>
          </tr>
          <tr>
            <td style="background-color:#f0f0f0; padding:16px 30px; font-size:12px; color:#888888;">
              This email was sent from the contact form on {$siteName}.
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

$plainBody = "New contact form submission\n\n"
    . "Name: {$name}\n"
    . "Email: {$email}\n"
    . "Phone: {$phone}\n"
    . "Service: {$service}\n"
    . "Message:\n{$message}\n";

$boundary = md5((string) microtime());

$headers  = "From: {$siteName} <no-reply@" . ($_SERVER['SERVER_NAME'] ?? 'localhost') . ">\r\n";
$headers .= "Reply-To: {$name} <{$email}>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$body  = "--{$boundary}\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$body .= $plainBody . "\r\n\r\n";
$body .= "--{$boundary}\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n";
$body .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
$body .= $htmlBody . "\r\n\r\n";
$body .= "--{$boundary}--";

// ---- Send email --------------------------------------------------------------

try {
    $sent = @mail($recipientEmail, $subject, $body, $headers);

    if (!$sent) {
        error_log('Contact form: mail() failed to send message from ' . $email);
        respond(false, 'Sorry, we could not send your message right now. Please try again later or email us directly.', [], 500);
    }

    respond(true, 'Thank you! Your message has been sent. We will get back to you shortly.');
} catch (Throwable $e) {
    error_log('Contact form exception: ' . $e->getMessage());
    respond(false, 'An unexpected error occurred. Please try again later.', [], 500);
}
