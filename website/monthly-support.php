<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Monthly Development & Maintenance Retainers | ' . SITE_NAME;
$pageDesc = 'Monthly PHP, Laravel and WordPress development and maintenance retainers from $299/month. An experienced developer without the cost of a full-time hire.';
$current = 'support';
require __DIR__ . '/includes/header.php';
?>
<section class="pagehead">
  <div class="wrap">
    <p class="eyebrow">Monthly Support</p>
    <h1>Need a Developer Every Month?</h1>
    <p class="lead">Get an experienced PHP, Laravel and WordPress developer without the cost of hiring a full-time employee.</p>
  </div>
</section>

<section class="sec" style="padding-top:32px">
  <div class="wrap">
    <div class="plans">
      <div class="card plan">
        <h3>Starter</h3><div class="price">$299<small>/month</small></div>
        <p class="for">For small business websites.</p>
        <ul class="list"><li>Up to 5 hours/month</li><li>WordPress maintenance</li><li>HTML/CSS changes</li><li>Minor PHP fixes</li><li>Basic troubleshooting</li><li>Email support</li></ul>
        <a class="btn btn-s" href="contact.php?plan=Starter#request">Get Started</a>
      </div>
      <div class="card plan hl">
        <span class="badge">Most popular</span>
        <h3>Business</h3><div class="price">$599<small>/month</small></div>
        <p class="for">For businesses with ongoing development requirements.</p>
        <ul class="list"><li>Up to 12 hours/month</li><li>PHP/Laravel</li><li>WordPress</li><li>MySQL</li><li>API integrations</li><li>Bug fixing</li><li>Minor feature development</li><li>Priority support</li></ul>
        <a class="btn btn-p" href="contact.php?plan=Business#request">Get Started</a>
      </div>
      <div class="card plan">
        <h3>Dedicated</h3><div class="price">$1,199<small>/month</small></div>
        <p class="for">For businesses and agencies requiring regular development support.</p>
        <ul class="list"><li>Up to 25 hours/month</li><li>Laravel/PHP</li><li>WordPress</li><li>MySQL</li><li>API integrations</li><li>New features</li><li>Application maintenance</li><li>Priority support</li><li>Scheduled development calls</li></ul>
        <a class="btn btn-s" href="contact.php?plan=Dedicated#request">Get Started</a>
      </div>
      <div class="card plan" style="background:var(--softer)">
        <h3>Custom</h3><div class="price">$1,500+<small>/month</small></div>
        <p class="for">For agencies or businesses requiring substantial ongoing development.</p>
        <p style="font-size:15px;margin:0 0 28px">Scope, hours and communication rhythm designed around your team.</p>
        <a class="btn btn-s" href="contact.php?plan=Custom#request">Discuss Your Needs</a>
      </div>
    </div>
  </div>
</section>

<section class="sec dark" id="takeover">
  <div class="wrap split">
    <div>
      <p class="eyebrow">Application takeover</p>
      <h2 style="margin-top:12px">Need Someone to Take Over Your Existing Website?</h2>
      <ul class="list" style="margin-top:24px;color:#CBD5E1"><li style="color:#CBD5E1;font-size:18px">Has your previous developer disappeared?</li><li style="color:#CBD5E1;font-size:18px">Is your website difficult to maintain?</li><li style="color:#CBD5E1;font-size:18px">Do you have an old PHP application that nobody understands?</li></ul>
      <p class="lead">I can review your existing system, understand the codebase and provide ongoing development and maintenance.</p>
      <div class="btn-row"><a class="btn btn-w" href="contact.php?plan=Takeover#request">Request a System Review</a></div>
    </div>
    <div>
    <img class="fig" src="assets/img/takeover.svg" width="640" height="360" loading="lazy" alt="Messy legacy files turned into a documented, maintained application" style="margin-bottom:24px">
    <div class="card" style="background:transparent;border-color:#334155;padding:32px">
      <p style="color:#CBD5E1;margin:0">Typical starting point</p>
      <ol style="margin:16px 0 0;padding-left:20px;display:grid;gap:12px;color:#fff"><li>Review code, database and hosting</li><li>Document what exists and what is risky</li><li>Fix urgent issues, then agree a monthly plan</li></ol>
    </div>
    </div>
  </div>
</section>

<section class="sec center">
  <div class="wrap">
    <h2>Not sure which plan fits?</h2>
    <p class="lead">Book a 30-minute call. I'll recommend the smallest plan that covers what you need.</p>
    <div class="btn-row" style="justify-content:center"><a class="btn btn-p" href="<?= e(cta_url()) ?>"><?= e(CTA_TEXT) ?></a></div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
