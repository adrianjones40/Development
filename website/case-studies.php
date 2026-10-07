<?php
require_once __DIR__ . '/includes/config.php';
$pageTitle = 'Case Studies | ' . SITE_NAME;
$pageDesc = 'Examples of Laravel applications, WordPress maintenance and API integrations: challenge, solution, technology and role.';
$current = 'cases';
require __DIR__ . '/includes/header.php';

// Edit or add case studies here.
$cases = [
  [
    'title' => 'Custom Laravel Business Application',
    'meta' => ['Client' => 'Confidential', 'Industry' => 'Publishing / Operations'],
    'challenge' => 'The client required a web-based workflow system to manage projects, users, departments and production stages.',
    'solution' => 'Developed and maintained a custom PHP/Laravel application with MySQL database, role-based access, workflow management and reporting.',
    'tech' => ['Laravel', 'PHP', 'MySQL', 'JavaScript', 'REST APIs'],
    'role' => ['Application development', 'Database design', 'API integration', 'Bug fixing', 'Workflow development', 'Production support'],
  ],
  [
    'title' => 'WordPress Website Maintenance',
    'meta' => ['Client' => 'Confidential'],
    'challenge' => 'Existing WordPress website required ongoing content updates, accessibility improvements, troubleshooting and technical maintenance.',
    'solution' => 'Provided ongoing WordPress/PHP maintenance and implemented required changes without disrupting the live website.',
    'tech' => ['WordPress', 'PHP', 'MySQL'],
    'role' => [],
  ],
  [
    'title' => 'Third-Party API Integration',
    'meta' => ['Client' => 'Confidential'],
    'challenge' => 'Business needed to exchange data between its website and an external service.',
    'solution' => 'Designed and implemented API communication, authentication, data processing and error handling.',
    'tech' => ['REST APIs', 'PHP', 'Webhooks'],
    'role' => [],
  ],
];
?>
<section class="pagehead">
  <div class="wrap">
    <p class="eyebrow">Case Studies</p>
    <h1>Problems solved, not just projects delivered</h1>
    <p class="lead">Client details are kept confidential. Each study shows the challenge, the solution and my role.</p>
  </div>
</section>

<section class="sec" style="padding-top:24px">
  <div class="wrap grid">
  <?php foreach ($cases as $c): ?>
    <article class="card case">
      <div>
        <h2 style="font-size:clamp(24px,3vw,30px)"><?= e($c['title']) ?></h2>
        <div class="meta"><?php foreach ($c['meta'] as $k => $v): ?><span><strong><?= e($k) ?>:</strong> <?= e($v) ?></span><?php endforeach; ?></div>
      </div>
      <div class="cols2">
        <div><div class="lbl">Challenge</div><p><?= e($c['challenge']) ?></p></div>
        <div><div class="lbl">Solution</div><p><?= e($c['solution']) ?></p></div>
      </div>
      <div class="cols2">
        <div><div class="lbl">Technology</div><?php foreach ($c['tech'] as $t): ?><span class="tag"><?= e($t) ?></span><?php endforeach; ?></div>
        <?php if ($c['role']): ?>
        <div><div class="lbl">My Role</div><ul class="list"><?php foreach ($c['role'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
  </div>
</section>

<section class="sec soft" style="padding:72px 0">
  <div class="wrap split">
    <h2 style="font-size:clamp(24px,3vw,32px)">Have a similar problem?</h2>
    <div><a class="btn btn-p" href="<?= e(cta_url()) ?>"><?= e(CTA_TEXT) ?></a></div>
  </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
