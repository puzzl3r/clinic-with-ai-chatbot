<?php
$pageTitle = $pageTitle ?? APP_NAME;
$activeNav = $activeNav ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header">
  <a class="brand" href="<?= BASE_URL ?>/index.php">
    <span class="brand-mark">+</span>
    <span><strong>CarePoint</strong><small>CLINIC SCHEDULING</small></span>
  </a>
  <nav class="top-nav">
    <a class="<?= $activeNav === 'book' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php#branches">Book an adjustment</a>
  </nav>
</header>
<main>
