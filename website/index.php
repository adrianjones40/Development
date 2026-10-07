<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = SITE_NAME . ' | PHP, Laravel & WordPress Development Partner';
$current = '';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
  <div class="wrap hero-grid">
   <div>
    <p class="eyebrow">Remote development partner for US &amp; UK businesses and agencies</p>
    <h1><?= e(SITE_TAGLINE) ?></h1>
    <p class="lead">15+ years of experience helping businesses maintain, improve and develop websites, web applications and API integrations, on a simple monthly retainer.</p>
    <div class="btn-row">
      <a class="btn btn-p" href="<?= e(cta_url()) ?>"><?= e(CTA_TEXT) ?></a>
      <a class="btn btn-s" href="services.php">View Services</a>
    </div>
    <p class="stack">PHP &bull; Laravel &bull; WordPress &bull; MySQL &bull; JavaScript &bull; REST APIs</p>
   </div>
   <img class="fig" src="assets/img/hero-code.svg" width="640" height="480" alt="Code editor showing a Laravel route syncing an order with a CRM">
  </div>
</section>

<section class="sec soft" style="padding:64px 0">
  <div class="wrap">
    <h2 style="font-size:clamp(24px,3vw,30px)">15+ Years of Web Development Experience</h2>
    <div class="grid g4" style="margin-top:28px">
      <div class="card"><div class="stat">15+</div><div class="stat-l">Years Experience</div></div>
      <div class="card"><div class="stat">PHP/Laravel</div><div class="stat-l">Development</div></div>
      <div class="card"><div class="stat">WordPress</div><div class="stat-l">Development</div></div>
      <div class="card"><div class="stat">API</div><div class="stat-l">Integrations</div></div>
    </div>
  </div>
</section>

<section class="sec">
  <div class="wrap">
    <h2 style="max-width:780px">Already Have a Website or Web Application That Needs Ongoing Support?</h2>
    <p class="lead">You don't always need a full-time developer. I provide reliable ongoing technical support for businesses that need someone to maintain, fix and improve their existing websites and web applications.</p>
    <div class="grid g4 mt">
      <div class="card"><h3>Having bugs?</h3><p>I can investigate and fix PHP, Laravel, WordPress and database issues.</p></div>
      <div class="card"><h3>Need new features?</h3><p>I can add new modules and functionality to your existing application.</p></div>
      <div class="card"><h3>Need API integration?</h3><p>I can connect your website or application with third-party APIs and services.</p></div>
      <div class="card hl"><h3>Developer left your project?</h3><p>I can understand and take over an existing PHP, Laravel or WordPress application.</p><p><a class="link" href="monthly-support.php#takeover">Application takeover &rarr;</a></p></div>
    </div>
  </div>
</section>

<section class="sec softer">
  <div class="wrap">
    <p class="eyebrow">Services</p>
    <h2 style="margin-top:12px">What I can help with</h2>
    <div class="grid g3 mt">
      <div class="card">
        <h3>Laravel &amp; PHP Development</h3>
        <p>Development and ongoing maintenance of PHP and Laravel applications.</p>
        <ul class="list" style="margin-top:18px"><li>Existing application maintenance</li><li>Bug fixing and new modules</li><li>Admin panels and authentication</li><li>Legacy PHP maintenance</li></ul>
      </div>
      <div class="card">
        <h3>WordPress Development</h3>
        <p>Professional WordPress development, customization and ongoing maintenance.</p>
        <ul class="list" style="margin-top:18px"><li>Theme and plugin customization</li><li>Custom plugins, Elementor, WooCommerce</li><li>Migration and security updates</li><li>Performance optimization</li></ul>
      </div>
      <div class="card hl">
        <h3>API &amp; Third-Party Integrations</h3>
        <p>Connect your website or application with the tools your business already uses.</p>
        <ul class="list" style="margin-top:18px"><li>Payment gateways, CRM, ERP</li><li>Email, SMS and shipping APIs</li><li>Webhooks and data synchronization</li><li>MySQL and database work</li></ul>
      </div>
    </div>
    <div class="btn-row"><a class="btn btn-s" href="services.php">See all services</a></div>
  </div>
</section>

<section class="sec">
  <div class="wrap split">
    <div>
      <p class="eyebrow">Monthly Retainers</p>
      <h2 style="margin-top:12px">Need a Developer Every Month?</h2>
      <p class="lead">Get an experienced PHP, Laravel and WordPress developer without the cost of hiring a full-time employee. Retainers start from $299/month.</p>
      <div class="btn-row">
        <a class="btn btn-p" href="monthly-support.php">See Monthly Support</a>
        <a class="btn btn-s" href="pricing.php">Compare Pricing</a>
      </div>
    </div>
    <img class="fig" src="assets/img/support-dashboard.svg" width="640" height="420" loading="lazy" alt="Monthly support dashboard with completed and in-progress tasks">
  </div>
</section>

<section class="sec soft" style="padding:72px 0">
  <div class="wrap split">
    <div>
      <h2 style="font-size:clamp(24px,3vw,32px)">Need Someone to Take Over Your Existing Website?</h2>
      <p class="lead" style="font-size:18px">Previous developer disappeared? Old PHP application nobody understands? I review your system, understand the codebase and provide ongoing development and maintenance.</p>
    </div>
    <div>
      <img class="fig" src="assets/img/takeover.svg" width="640" height="360" loading="lazy" alt="Messy legacy files turned into a documented, maintained application">
      <div class="btn-row"><a class="btn btn-p" href="contact.php">Request a System Review</a></div>
    </div>
  </div>
</section>

<section class="sec dark center">
  <div class="wrap">
    <h2>Let's Discuss Your Project</h2>
    <p class="lead">Prefer to talk? Book a free 30-minute consultation and tell me what you're working on.</p>
    <div class="btn-row" style="justify-content:center"><a class="btn btn-w" href="<?= e(cta_url()) ?>"><?= e(CTA_TEXT) ?></a></div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
