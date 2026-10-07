<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'About | ' . SITE_NAME;
$pageDesc = 'Experienced web developer with 15+ years in PHP, Laravel, WordPress, MySQL and API integrations, working remotely with businesses and agencies.';
$current = 'about';
require __DIR__ . '/includes/header.php';
?>
<section class="sec">
  <div class="wrap about">
    <div class="txt">
      <p class="eyebrow">About</p>
      <h1 style="margin-top:16px;font-size:clamp(32px,4.6vw,48px)">Experienced Web Developer with 15+ Years of Experience</h1>
      <p style="color:var(--ink)">I am a web developer specializing in PHP, Laravel, WordPress, MySQL and third-party API integrations.</p>
      <p>Over the years, I have worked on business websites, custom web applications, workflow systems, database-driven applications and integrations.</p>
      <p>My focus today is helping businesses and agencies with ongoing development and technical support.</p>
      <p>Rather than simply delivering a project and moving on, I work with clients who need a reliable technical partner who can understand their existing systems, solve problems and continuously improve their applications.</p>
      <p style="font-size:16px;color:var(--ink);padding-top:24px;border-top:1px solid var(--line)">Based in <?= e(LOCATION) ?>, working remotely with businesses and agencies internationally.</p>
      <div class="btn-row"><a class="btn btn-p" href="<?= e(cta_url()) ?>"><?= e(CTA_TEXT) ?></a></div>
    </div>
    <!-- Replace the placeholder with: <img src="assets/img/profile.jpg" alt="Your Name"> -->
    <div class="photo">Add your professional profile photo<br>(assets/img/profile.jpg)</div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
