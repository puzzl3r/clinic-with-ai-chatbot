<?php
$analyticsActive = $analyticsActive ?? '';
$analyticsParams = $analyticsParams ?? [];
$analyticsTabs = [
    ['key' => 'descriptive', 'label' => 'Descriptive', 'hint' => 'What happened', 'page' => 'descriptive.php', 'icon' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'],
    ['key' => 'predictive', 'label' => 'Predictive', 'hint' => 'What may happen', 'page' => 'predictive.php', 'icon' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>'],
    ['key' => 'prescriptive', 'label' => 'Prescriptive', 'hint' => 'What to do next', 'page' => 'prescriptive.php', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M15.5 8.5l-2 5-5 2 2-5 5-2z"/>'],
];
?>
<nav class="analytics-tabs" aria-label="Analytics views">
    <?php foreach ($analyticsTabs as $tab):
        $params = $analyticsParams;
        if ($tab['key'] === 'descriptive') unset($params['horizon'], $params['shift']);
        if ($tab['key'] !== 'descriptive') unset($params['period']);
        $isActive = $analyticsActive === $tab['key'];
    ?>
        <a class="analytics-tab<?= $isActive ? ' active' : '' ?>"
           href="<?= e(analytics_url($tab['page'], $params)) ?>"
           <?= $isActive ? 'aria-current="page"' : '' ?>>
            <span class="analytics-tab-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $tab['icon'] ?></svg>
            </span>
            <span><b><?= e($tab['label']) ?></b><small><?= e($tab['hint']) ?></small></span>
        </a>
    <?php endforeach; ?>
</nav>