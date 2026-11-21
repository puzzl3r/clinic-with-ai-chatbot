<?php
$activeNav = $activeNav ?? '';
$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminInitial = mb_substr(trim($adminName) !== '' ? trim($adminName) : 'A', 0, 1);
$sidebarIcon = static function (string $paths): string {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths . '</svg>';
};
$sidebarItems = [
    ['key' => 'overview', 'label' => 'Overview', 'href' => '/admin/index.php', 'icon' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>'],
    ['key' => 'appointments', 'label' => 'Appointments', 'href' => '/admin/appointments.php', 'icon' => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12h6M9 16h4"/>'],
    ['key' => 'calendar', 'label' => 'Calendar', 'href' => '/admin/calendar.php', 'icon' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 11h18"/>'],
    ['key' => 'patients', 'label' => 'Patients', 'href' => '/admin/patients.php', 'icon' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'],
    ['key' => 'chatbot', 'label' => 'AI Chatbot', 'href' => '/admin/chatbot.php', 'icon' => '<path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/>'],
];
$analyticsItems = [
    ['key' => 'descriptive', 'label' => 'Descriptive', 'href' => '/admin/descriptive.php', 'icon' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'],
    ['key' => 'predictive', 'label' => 'Predictive', 'href' => '/admin/predictive.php', 'icon' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>'],
    ['key' => 'prescriptive', 'label' => 'Prescriptive', 'href' => '/admin/prescriptive.php', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5 5-2z"/>'],
];
?>
<aside class="admin-sidebar">
    <a class="admin-brand" href="<?= BASE_URL ?>/admin/index.php">
        <span class="brand-mark">+</span>
        <span><b>CarePoint</b><small>ADMIN CONSOLE</small></span>
    </a>

    <div class="sidebar-label">WORKSPACE</div>
    <?php foreach ($sidebarItems as $item): ?>
        <a class="sidebar-link<?= $activeNav === $item['key'] ? ' selected' : '' ?>"
           href="<?= BASE_URL . e($item['href']) ?>"
           <?= $activeNav === $item['key'] ? 'aria-current="page"' : '' ?>>
            <span><?= $sidebarIcon($item['icon']) ?></span><?= e($item['label']) ?>
        </a>
    <?php endforeach; ?>

    <div class="sidebar-label sidebar-label-analytics">ANALYTICS</div>
    <?php foreach ($analyticsItems as $item): ?>
        <a class="sidebar-link sidebar-link-analytics<?= $activeNav === $item['key'] ? ' selected' : '' ?>"
           href="<?= BASE_URL . e($item['href']) ?>"
           <?= $activeNav === $item['key'] ? 'aria-current="page"' : '' ?>>
            <span><?= $sidebarIcon($item['icon']) ?></span><?= e($item['label']) ?>
        </a>
    <?php endforeach; ?>

    <div class="sidebar-label sidebar-label-lower">SYSTEM</div>
    <a class="sidebar-link" href="<?= BASE_URL ?>/index.php">
        <span><?= $sidebarIcon('<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6M10 14L21 3"/>') ?></span>Booking website
    </a>
    <a class="sidebar-link" href="<?= BASE_URL ?>/admin/logout.php">
        <span><?= $sidebarIcon('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>') ?></span>Sign out
    </a>

    <div class="sidebar-bottom">
        <div class="admin-avatar"><?= e($adminInitial) ?></div>
        <div>
            <b><?= e($adminName) ?></b>
            <small>Clinic administrator</small>
        </div>
    </div>
</aside>