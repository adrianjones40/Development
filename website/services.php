<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Services | ' . SITE_NAME;
$pageDesc = 'Laravel and PHP development, WordPress development, API integrations, MySQL database work, legacy PHP maintenance and application takeover.';
$current = 'services';
require __DIR__ . '/includes/header.php';
?>
<section class="pagehead">
  <div class="wrap">
    <p class="eyebrow">Services</p>
    <h1>Development and support for the systems your business runs on</h1>
    <p class="lead">Focused areas, one experienced developer who understands your whole stack.</p>
  </div>
</section>

<section class="sec" style="padding-top:24px">
  <div class="wrap grid">

    <div class="card svc">
      <div><h2 style="font-size:28px">Laravel &amp; PHP Development</h2><p class="muted">Development and ongoing maintenance of PHP and Laravel applications.</p></div>
      <ul class="list cols"><li>Laravel development</li><li>Custom PHP</li><li>Existing application maintenance</li><li>Bug fixing</li><li>New modules</li><li>Admin panels</li><li>Database development</li><li>Authentication</li><li>REST APIs</li><li>Performance improvements</li></ul>
    </div>

    <div class="card svc">
      <div><h2 style="font-size:28px">WordPress Development</h2><p class="muted">Professional WordPress development, customization and ongoing maintenance.</p></div>
      <ul class="list cols"><li>WordPress maintenance</li><li>Theme customization</li><li>Plugin customization</li><li>Custom plugin development</li><li>Elementor</li><li>WooCommerce</li><li>PHP customization</li><li>Website migration</li><li>Security updates</li><li>Performance optimization</li></ul>
    </div>

    <div class="card svc hl">
      <div><p class="eyebrow">Core strength</p><h2 style="font-size:28px;margin-top:8px">API &amp; Third-Party Integrations</h2><p class="muted">Connect your website or application with the tools your business already uses.</p></div>
      <ul class="list cols"><li>REST APIs</li><li>Payment gateways</li><li>CRM</li><li>ERP</li><li>Email services</li><li>SMS</li><li>Shipping APIs</li><li>Authentication</li><li>Webhooks</li><li>Data synchronization</li><li>Third-party platforms</li></ul>
    </div>

    <div class="card svc">
      <div><h2 style="font-size:28px">Legacy PHP Maintenance</h2><p class="muted">Keeping older PHP applications running, secure and understandable.</p></div>
      <ul class="list cols"><li>Code review and documentation</li><li>PHP version upgrades</li><li>Security fixes</li><li>Bug fixing</li><li>Gradual refactoring</li><li>Hosting and deployment help</li></ul>
    </div>

    <div class="grid g2">
      <div class="card" style="padding:32px"><h2 style="font-size:24px">MySQL / Database Work</h2><p>Schema design, query optimization, data cleanup, migrations and reporting for database-driven applications.</p></div>
      <div class="card" style="padding:32px"><h2 style="font-size:24px">Application Takeover</h2><p>Previous developer gone? I review your code, document it and take over ongoing development. <a class="link" href="monthly-support.php#takeover">Learn more</a></p></div>
    </div>
  </div>
</section>

<section class="sec soft" style="padding:72px 0">
  <div class="wrap split">
    <div><h2 style="font-size:clamp(24px,3vw,32px)">Not sure which service you need?</h2><p class="lead" style="font-size:18px">Describe the problem and I'll tell you honestly what it will take.</p></div>
    <div><a class="btn btn-p" href="<?= e(cta_url()) ?>"><?= e(CTA_TEXT) ?></a></div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
