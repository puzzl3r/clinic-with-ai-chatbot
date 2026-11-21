<?php
require_once __DIR__ . '/config/database.php';
$pageTitle = 'Book an appointment';
$activeNav = 'book';
$branches = db()->query("SELECT id, name, address, description FROM branches WHERE active = 1 ORDER BY sort_order")->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<section class="hero wrap">
  <div class="hero-copy">
    <div class="eyebrow"><span class="eyebrow-dot"></span> YOUR CARE, ON YOUR TIME</div>
    <h1>A better way to<br><span>book your adjustment.</span></h1>
    <p>Choose your clinic branch, find a time that works for you, and book your appointment in just a few steps.</p>
    <div class="hero-trust"><span class="trust-icon">✓</span> Simple booking <span class="trust-sep">·</span><span class="trust-icon">✓</span> Clear scheduling <span class="trust-sep">·</span><span class="trust-icon">✓</span> Secure records</div>
  </div>
  <div class="hero-art" aria-hidden="true">
    <div class="art-orbit orbit-one"></div><div class="art-orbit orbit-two"></div>
    <div class="art-card art-card-main"><span class="art-plus">+</span><div><strong>Care that fits</strong><small>your schedule</small></div><span class="art-check">✓</span></div>
    <div class="art-mini mini-one"><span>◷</span><div><b>Easy booking</b><small>Choose your time</small></div></div>
    <div class="art-mini mini-two"><span>✦</span><div><b>Two locations</b><small>Pick what’s closest</small></div></div>
    <div class="art-spark spark-one">✦</div><div class="art-spark spark-two">✧</div>
  </div>
</section>
<section class="booking-section wrap" id="branches">
  <div class="section-heading">
    <div><div class="eyebrow">START HERE</div><h2>Choose your branch</h2><p>Select the location where you’d like to have your adjustment.</p></div>
    <div class="step-indicator"><span class="step-current">01</span><span class="step-line"></span><span>02</span><span class="step-line"></span><span>03</span></div>
  </div>
  <div class="branch-grid">
    <?php foreach ($branches as $index => $branch): ?>
      <article class="branch-card">
        <div class="branch-card-top"><div class="branch-icon"><?= $index === 0 ? '⌂' : '⌖' ?></div><span class="branch-number">0<?= $index + 1 ?></span></div>
        <div class="branch-content">
          <div class="branch-tag"><?= $index === 0 ? 'PRIMARY LOCATION' : 'LAPU-LAPU LOCATION' ?></div>
          <h3><?= e($branch['name']) ?></h3>
          <p class="branch-address"><span>⌖</span> <?= e($branch['address']) ?></p>
          <p class="branch-description"><?= e($branch['description']) ?></p>
        </div>
        <a class="button button-primary branch-button" href="<?= BASE_URL ?>/book.php?branch=<?= (int)$branch['id'] ?>">Book this branch <span>→</span></a>
      </article>
    <?php endforeach; ?>
  </div>
  <div class="booking-note"><span class="note-icon">i</span><p><strong>Need help?</strong> Choose the branch you prefer first. You’ll be able to select an available date and time on the next screen.</p></div>
</section>
<section class="how-section">
  <div class="wrap">
    <div class="eyebrow">A SIMPLE PROCESS</div><h2>Three steps to your appointment</h2>
    <div class="how-grid">
      <div class="how-item"><span class="how-number">01</span><div class="how-icon">⌖</div><h3>Choose a branch</h3><p>Select Main Branch or Lapu Lapu Branch.</p></div>
      <div class="how-item"><span class="how-number">02</span><div class="how-icon">▦</div><h3>Pick a time</h3><p>Choose a date and an available appointment slot.</p></div>
      <div class="how-item"><span class="how-number">03</span><div class="how-icon">✓</div><h3>Confirm booking</h3><p>Enter your details and keep your booking reference.</p></div>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
