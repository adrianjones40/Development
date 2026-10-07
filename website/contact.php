<?php
session_start();
require_once __DIR__ . '/includes/config.php';

$helpOptions = ['PHP/Laravel', 'WordPress', 'API Integration', 'MySQL/Database', 'Bug Fixing', 'Website Maintenance', 'Ongoing Development', 'Other'];
$monthlyOptions = ['Yes', 'No', 'Not sure'];

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }

$errors = [];
$sent = isset($_GET['sent']);
$v = ['name' => '', 'company' => '', 'email' => '', 'url' => '', 'message' => '', 'monthly' => '', 'help' => []];
if (!empty($_GET['plan'])) {
    $v['message'] = 'I am interested in the ' . substr(preg_replace('/[^A-Za-z ]/', '', (string)$_GET['plan']), 0, 20) . " option.\n\n";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clean = fn($k) => trim(preg_replace('/[\r\n]+/', ' ', (string)($_POST[$k] ?? '')));
    $v['name'] = $clean('name');
    $v['company'] = $clean('company');
    $v['email'] = $clean('email');
    $v['url'] = $clean('url');
    $v['monthly'] = in_array($_POST['monthly'] ?? '', $monthlyOptions, true) ? $_POST['monthly'] : '';
    $v['help'] = array_values(array_intersect($helpOptions, (array)($_POST['help'] ?? [])));
    $v['message'] = trim((string)($_POST['message'] ?? ''));

    if (!hash_equals($_SESSION['csrf'], (string)($_POST['csrf'] ?? ''))) { $errors[] = 'Your session expired. Please try again.'; }
    if (!empty($_POST['website_hp'])) { $errors[] = 'Submission rejected.'; } // honeypot
    if ($v['name'] === '') { $errors[] = 'Please enter your name.'; }
    if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Please enter a valid email address.'; }
    if ($v['url'] !== '' && !filter_var($v['url'], FILTER_VALIDATE_URL)) { $errors[] = 'Please enter a valid URL (starting with https://).'; }
    if (strlen($v['message']) < 10) { $errors[] = 'Please tell me a little about what you need.'; }
    if (strlen($v['message']) > 5000) { $errors[] = 'Message is too long.'; }

    if (!$errors) {
        $body = "Name: {$v['name']}\nCompany: {$v['company']}\nEmail: {$v['email']}\nURL: {$v['url']}\n"
              . "Needs: " . implode(', ', $v['help']) . "\nMonthly support: {$v['monthly']}\n\n{$v['message']}\n";
        $headers = "From: " . MAIL_FROM . "\r\nReply-To: {$v['email']}\r\nContent-Type: text/plain; charset=UTF-8";
        if (@mail(CONTACT_EMAIL, 'New consultation request from ' . $v['name'], $body, $headers)) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
            header('Location: contact.php?sent=1#request');
            exit;
        }
        $errors[] = 'Sorry, the message could not be sent. Please email ' . CONTACT_EMAIL . ' directly.';
    }
}

$pageTitle = 'Contact | ' . SITE_NAME;
$pageDesc = 'Book a free 30-minute consultation or tell me about your PHP, Laravel or WordPress project.';
$current = 'contact';
require __DIR__ . '/includes/header.php';
?>
<section class="pagehead">
  <div class="wrap">
    <p class="eyebrow">Contact</p>
    <h1>Let's Discuss Your Project</h1>
    <p class="lead">Tell me what you are currently working on, what problems you are facing and what type of ongoing support you need.</p>
  </div>
</section>

<section class="sec" style="padding-top:0">
  <div class="wrap contact">

    <div style="flex:1 1 560px;min-width:0" id="request">
    <?php if ($sent): ?>
      <div class="alert ok" role="status"><strong>Thanks, your request has been received.</strong><br>I'll reply by email, usually within one business day.</div>
    <?php else: ?>
      <form method="post" action="contact.php#request" novalidate style="display:grid;gap:24px">
        <?php if ($errors): ?>
          <div class="alert err" role="alert"><strong>Please fix the following:</strong><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <div class="hp" aria-hidden="true"><label>Leave empty <input type="text" name="website_hp" tabindex="-1" autocomplete="off"></label></div>
        <div class="row">
          <div class="field"><label for="name">Name</label><input id="name" name="name" type="text" autocomplete="name" required value="<?= e($v['name']) ?>"></div>
          <div class="field"><label for="company">Company</label><input id="company" name="company" type="text" autocomplete="organization" value="<?= e($v['company']) ?>"></div>
        </div>
        <div class="row">
          <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" autocomplete="email" required value="<?= e($v['email']) ?>"></div>
          <div class="field"><label for="url">Website/Application URL</label><input id="url" name="url" type="url" placeholder="https://" value="<?= e($v['url']) ?>"></div>
        </div>
        <fieldset>
          <legend class="legend">What do you need help with?</legend>
          <div class="checks">
            <?php foreach ($helpOptions as $o): ?>
              <label><input type="checkbox" name="help[]" value="<?= e($o) ?>"<?= in_array($o, $v['help'], true) ? ' checked' : '' ?>> <?= e($o) ?></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <fieldset>
          <legend class="legend">Do you need ongoing monthly support?</legend>
          <div class="checks">
            <?php foreach ($monthlyOptions as $o): ?>
              <label><input type="radio" name="monthly" value="<?= e($o) ?>"<?= $v['monthly'] === $o ? ' checked' : '' ?>> <?= e($o) ?></label>
            <?php endforeach; ?>
          </div>
        </fieldset>
        <div class="field"><label for="message">Message</label><textarea id="message" name="message" required><?= e($v['message']) ?></textarea></div>
        <div><button class="btn btn-p" type="submit">Request a Free Consultation</button></div>
      </form>
    <?php endif; ?>
    </div>

    <aside id="book">
      <h2 style="font-size:24px">Prefer to talk?</h2>
      <p class="muted" style="margin-top:8px">Book a free 30-minute consultation.</p>
      <?php if (BOOKING_URL !== ''): ?>
        <a class="btn btn-p" href="<?= e(BOOKING_URL) ?>" style="margin-top:20px;width:100%" target="_blank" rel="noopener"><?= e(CTA_TEXT) ?></a>
      <?php else: ?>
        <p style="margin-top:16px">Send the form and I'll reply with available times. You can also email me directly.</p>
      <?php endif; ?>
      <p style="margin-top:20px;font-size:15px"><a class="link" href="mailto:<?= e(CONTACT_EMAIL) ?>"><?= e(CONTACT_EMAIL) ?></a></p>
    </aside>

  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
