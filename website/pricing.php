<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Pricing | ' . SITE_NAME;
$pageDesc = 'Simple monthly retainers: Starter $299, Business $599, Dedicated $1,199, Custom from $1,500 per month.';
$current = 'pricing';
require __DIR__ . '/includes/header.php';
?>
<section class="pagehead">
  <div class="wrap">
    <p class="eyebrow">Pricing</p>
    <h1>Simple monthly pricing for experienced support</h1>
    <p class="lead">Experienced remote development support without the cost of a full-time developer.</p>
  </div>
</section>

<section class="sec" style="padding-top:16px">
  <div class="wrap">
    <div class="tablewrap">
      <table>
        <thead><tr><th></th><th>Starter</th><th class="hlc">Business</th><th>Dedicated</th><th>Custom</th></tr></thead>
        <tbody>
          <tr><td><strong>Price</strong></td><td>$299/month</td><td class="hlc"><strong>$599/month</strong></td><td>$1,199/month</td><td>$1,500+/month</td></tr>
          <tr><td><strong>Hours</strong></td><td>Up to 5</td><td class="hlc">Up to 12</td><td>Up to 25</td><td>As agreed</td></tr>
          <tr><td><strong>Best for</strong></td><td>Small business websites</td><td class="hlc">Ongoing development needs</td><td>Businesses and agencies</td><td>Substantial ongoing work</td></tr>
          <tr><td><strong>WordPress</strong></td><td>Maintenance, HTML/CSS</td><td class="hlc">Yes</td><td>Yes</td><td>Yes</td></tr>
          <tr><td><strong>PHP / Laravel</strong></td><td>Minor PHP fixes</td><td class="hlc">Yes</td><td>Yes</td><td>Yes</td></tr>
          <tr><td><strong>MySQL &amp; API integrations</strong></td><td>&mdash;</td><td class="hlc">Yes</td><td>Yes</td><td>Yes</td></tr>
          <tr><td><strong>New features</strong></td><td>&mdash;</td><td class="hlc">Minor features</td><td>Yes</td><td>Yes</td></tr>
          <tr><td><strong>Support</strong></td><td>Email</td><td class="hlc">Priority</td><td>Priority + scheduled calls</td><td>Agreed with you</td></tr>
        </tbody>
      </table>
    </div>
    <div class="btn-row">
      <a class="btn btn-p" href="contact.php#request">Get Started</a>
      <a class="btn btn-s" href="monthly-support.php">See plan details</a>
    </div>
  </div>
</section>

<section class="sec softer">
  <div class="wrap">
    <h2>How it works</h2>
    <div class="grid g3 mt" style="margin-top:32px">
      <div class="card"><h3>1. Talk</h3><p>A free 30-minute consultation to understand your systems and needs.</p></div>
      <div class="card"><h3>2. Choose a plan</h3><p>Pick the smallest plan that covers your work. Change it as your needs change.</p></div>
      <div class="card"><h3>3. Send requests</h3><p>Report bugs and changes by email. Hours used are tracked and shared with you.</p></div>
    </div>
    <p class="muted" style="margin-top:28px;font-size:15px">Prices in US dollars. Hours are included per month. Need something one-off? Ask for a fixed quote.</p>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
