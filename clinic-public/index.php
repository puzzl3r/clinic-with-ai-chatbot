<?php
require_once __DIR__ . '/config/database.php';

$pageTitle = 'Book an appointment';
$activeNav = 'book';

// Load branches using the columns that actually exist in your database.
$branches = db()
    ->query("SELECT id, name, sort_order FROM branches ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/header.php';
?>

<section class="hero wrap">
    <div class="hero-copy">
        <div class="eyebrow">
            <span class="eyebrow-dot"></span>
            YOUR CARE, ON YOUR TIME
        </div>

        <h1>
            A better way to<br>
            <span>book your adjustment.</span>
        </h1>

        <p>
            Choose your clinic branch, find a time that works for you,
            and book your appointment in just a few steps.
        </p>

        <div class="hero-trust">
            <span class="trust-icon">✓</span>
            Simple booking
            <span class="trust-sep">•</span>
            <span class="trust-icon">✓</span>
            Clear scheduling
        </div>
    </div>

    <div class="hero-art" aria-hidden="true">
        <div class="art-orbit orbit-one"></div>
        <div class="art-orbit orbit-two"></div>

        <div class="art-card art-card-main">
            <span class="art-plus">+</span>
            <strong>Care that fits</strong>
            <small>Your schedule, your choice</small>
            <span class="art-check">✓</span>
        </div>

        <div class="art-card art-mini mini-one">
            <span>✓</span>
            <div>
                <strong>Easy booking</strong>
                <small>Choose your time</small>
            </div>
        </div>

        <div class="art-card art-mini mini-two">
            <span>⌖</span>
            <div>
                <strong>Two locations</strong>
                <small>Pick what's closest</small>
            </div>
        </div>
    </div>
</section>

<section class="booking-section wrap" id="branches">
    <div class="section-heading">
        <div>
            <div class="eyebrow">START HERE</div>
            <h2>Choose your branch</h2>
            <p>
                Select the location where you'd like to have your adjustment.
            </p>
        </div>

        <div class="step-indicator">
            <span class="step-current">01</span>
            <span class="step-line"></span>
            <span>02</span>
            <span class="step-line"></span>
            <span>03</span>
        </div>
    </div>

    <?php if (empty($branches)): ?>
        <div class="booking-note">
            <strong>No clinic branches found.</strong>
            Please check the branches table in your database.
        </div>
    <?php else: ?>
        <div class="branch-grid">
            <?php foreach ($branches as $index => $branch): ?>
                <article class="branch-card">
                    <div class="branch-card-top">
                        <div class="branch-icon">
                            <?= $index === 0 ? '⌖' : '✚' ?>
                        </div>

                        <span class="branch-number">
                            0<?= $index + 1 ?>
                        </span>
                    </div>

                    <div class="branch-content">
                        <div class="eyebrow">
                            <?= $index === 0
                                ? 'PRIMARY LOCATION'
                                : 'LAPU-LAPU LOCATION' ?>
                        </div>

                        <h3><?= htmlspecialchars(
                            $branch['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?></h3>

                        <p class="branch-address">
                            Clinic adjustment services
                        </p>

                        <p class="branch-description">
                            Book your appointment at
                            <?= htmlspecialchars(
                                $branch['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>.
                        </p>
                    </div>

                    <a
                        class="button button-primary branch-button"
                        href="<?= htmlspecialchars(
                            rtrim(defined('BASE_URL') ? BASE_URL : '', '/'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>/book.php?branch=<?= (int) $branch['id'] ?>"
                    >
                        Book this branch <span>→</span>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="booking-note">
        <span class="note-icon">i</span>
        <p>
            <strong>Need help?</strong>
            Choose your preferred branch first. You'll be able to select an
            available date and time on the next screen.
        </p>
    </div>
</section>

<section class="how-section">
    <div class="wrap">
        <div class="section-heading">
            <div>
                <div class="eyebrow">HOW IT WORKS</div>
                <h2>Three simple steps</h2>
                <p>Booking your clinic adjustment is straightforward.</p>
            </div>
        </div>

        <div class="how-grid">
            <article class="how-card">
                <span class="how-number">01</span>
                <h3>Choose a branch</h3>
                <p>Select Main Branch or Lapu-Lapu Branch.</p>
            </article>

            <article class="how-card">
                <span class="how-number">02</span>
                <h3>Select a schedule</h3>
                <p>Choose an available appointment date and time.</p>
            </article>

            <article class="how-card">
                <span class="how-number">03</span>
                <h3>Confirm your booking</h3>
                <p>Enter your details and submit your appointment request.</p>
            </article>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
