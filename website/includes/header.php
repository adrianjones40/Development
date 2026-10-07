<?php
require_once __DIR__ . '/config.php';
$pageTitle = $pageTitle ?? SITE_NAME;
$pageDesc  = $pageDesc ?? 'Ongoing PHP, Laravel and WordPress development, maintenance and API integration for US & UK businesses and agencies.';
$current   = $current ?? '';
$nav = [
    'services'  => ['services.php', 'Services'],
    'support'   => ['monthly-support.php', 'Monthly Support'],
    'cases'     => ['case-studies.php', 'Case Studies'],
    'about'     => ['about.php', 'About'],
    'pricing'   => ['pricing.php', 'Pricing'],
    'contact'   => ['contact.php', 'Contact'],
];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($pageDesc) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($pageDesc) ?>">
<meta property="og:type" content="website">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;700;800&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<header class="site-header">
  <div class="wrap bar">
    <a class="logo" href="index.php"><?= e(SITE_NAME) ?></a>
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Toggle menu">
      <span></span><span></span><span></span>
    </button>
    <nav id="primary-nav" class="nav" aria-label="Main">
      <?php foreach ($nav as $key => [$href, $label]): ?>
        <a href="<?= $href ?>"<?= $current === $key ? ' class="on" aria-current="page"' : '' ?>><?= $label ?></a>
      <?php endforeach; ?>
      <a class="btn btn-p btn-sm nav-cta" href="<?= e(cta_url()) ?>">Book a Consultation</a>
    </nav>
  </div>
</header>
<main id="main">
