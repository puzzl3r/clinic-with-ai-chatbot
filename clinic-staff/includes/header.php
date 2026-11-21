<?php
$pageTitle = $pageTitle ?? APP_NAME;
$activeNav = $activeNav ?? '';
$isAdminPage = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?> | <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>">
</head>
<body<?= $isAdminPage ? ' class="admin-body"' : '' ?>>
<header class="site-header<?= $isAdminPage ? ' admin-site-header' : '' ?>">
  <a class="brand" href="<?= BASE_URL ?>/index.php">
    <span class="brand-mark">+</span>
    <span><strong>CarePoint</strong><small>CLINIC SCHEDULING</small></span>
  </a>
  <?php if (!$isAdminPage): ?>
    <nav class="top-nav">
      <a class="<?= $activeNav === 'book' ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">Book an adjustment</a>
      <a href="<?= BASE_URL ?>/admin/login.php">Staff portal <span class="nav-arrow">↗</span></a>
    </nav>
  <?php endif; ?>
</header>
<main>
