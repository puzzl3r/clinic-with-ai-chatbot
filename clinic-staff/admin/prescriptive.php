<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/analytics.php';
require_admin();

$pdo = db();
$filters = analytics_filters(['30'], '30');
$branchId = $filters['branchId'];
$branches = $filters['branches'];
$horizon = $_GET['horizon'] ?? '14';
if (!in_array($horizon, ['7', '14', '30'], true)) $horizon = '14';
$horizon = (int)$horizon;
$slotsPerDay = filter_input(INPUT_GET, 'slots', FILTER_VALIDATE_INT);
$slotsPerDay = $slotsPerDay ? max(6, min(16, (int)$slotsPerDay)) : ANALYTICS_SLOT_CAPACITY;
$coverage = filter_input(INPUT_GET, 'coverage', FILTER_VALIDATE_INT);
$coverage = $coverage ? max(50, min(100, (int)$coverage)) : 100;

$rows = analytics_rows($pdo, $filters['where'], $filters['params']);
$summary = analytics_summary($rows);
$slotStats = analytics_slot_stats($rows);
$branchStats = analytics_branch_stats($rows, $branches);
$peakSlot = ['label' => 'No data', 'count' => 0, 'share' => 0];
foreach ($slotStats as $slot) if ($slot['count'] > $peakSlot['count']) $peakSlot = $slot;

$pendingWhere = ' WHERE a.status = ?';
$pendingParams = ['pending'];
if ($branchId !== null) { $pendingWhere .= ' AND a.branch_id = ?'; $pendingParams[] = $branchId; }
$pendingRows = analytics_rows($pdo, $pendingWhere, $pendingParams);
$today = date('Y-m-d');
$upcomingPending = array_values(array_filter($pendingRows, static fn($r) => $r['appointment_date'] >= $today));
$oldPending = count(array_filter($pendingRows, static fn($r) => $r['appointment_date'] < $today || strtotime($r['created_at']) < strtotime('-7 days')));

$forecast = analytics_forecast($pdo, $branchId, $horizon);
$branchCount = $branchId ? 1 : max(1, count($branches));
$capacity = analytics_capacity($forecast['total'], $branchCount, $horizon, $slotsPerDay, $coverage);
$recommendations = analytics_recommendations([
    'summary' => $summary,
    'forecast' => $forecast,
    'capacity' => $capacity,
    'peakSlot' => $peakSlot,
    'branchStats' => $branchStats,
    'oldPending' => $oldPending,
]);
$states = analytics_action_states();
foreach ($recommendations as &$recommendation) {
    $recommendation['state'] = $states[$recommendation['id']]['state'] ?? 'none';
}
unset($recommendation);
$validActionIds = array_column($recommendations, 'id');
$queryParams = ['branch' => $branchId, 'horizon' => $horizon, 'slots' => $slotsPerDay, 'coverage' => $coverage];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = trim((string)($_POST['action_id'] ?? ''));
    $notice = 'invalid_request';
    $extra = [];

    if ($action === 'confirm_appointment') {
        $appointmentId = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
        if ($appointmentId) {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
            $stmt->execute([$appointmentId]);
            $notice = $stmt->rowCount() > 0 ? 'appointment_confirmed' : 'appointment_not_pending';
        }
    } elseif (preg_match('/^[a-z0-9-]+$/', $id) && in_array($id, $validActionIds, true)) {
        if ($action === 'plan_action') { analytics_set_action_state($id, 'planned'); $notice = 'action_planned'; }
        elseif ($action === 'complete_action') { analytics_set_action_state($id, 'done'); $notice = 'action_done'; }
        elseif ($action === 'reopen_action') { analytics_set_action_state($id, 'planned'); $notice = 'action_reopened'; }
        elseif ($action === 'remove_action') { analytics_set_action_state($id, 'none'); $notice = 'action_removed'; }
        elseif ($action === 'dismiss_action') { analytics_set_action_state($id, 'dismissed'); $notice = 'action_dismissed'; $extra = ['undo' => $id]; }
        elseif ($action === 'restore_action') { analytics_set_action_state($id, 'none'); $notice = 'action_restored'; }
    } elseif (preg_match('/^[a-z0-9-]+$/', $id) && $action === 'restore_action') {
        analytics_set_action_state($id, 'none');
        $notice = 'action_restored';
    } elseif ($action === 'plan_all_high') {
        foreach ($recommendations as $recommendation) {
            if ($recommendation['priority'] === 'high' && $recommendation['state'] === 'none') analytics_set_action_state($recommendation['id'], 'planned');
        }
        $notice = 'high_priority_planned';
    }

    header('Location: ' . analytics_url('prescriptive.php', $queryParams + ['notice' => $notice] + $extra));
    exit;
}

$notice = $_GET['notice'] ?? '';
$undoId = $_GET['undo'] ?? '';
if (!preg_match('/^[a-z0-9-]+$/', (string)$undoId)) $undoId = '';

if (($_GET['export'] ?? '') === 'plan') {
    $csvRows = [];
    foreach ($recommendations as $recommendation) {
        $csvRows[] = [
            $recommendation['title'], ucfirst($recommendation['priority']), ucfirst($recommendation['state']),
            $recommendation['metric'], $recommendation['evidence'], $recommendation['detail'], $recommendation['link'],
        ];
    }
    analytics_csv_download('carepoint-action-plan-' . date('Ymd') . '.csv',
        ['Action', 'Priority', 'Plan state', 'Evidence metric', 'Evidence', 'Recommendation', 'Staff link'], $csvRows);
}

$openActions = count(array_filter($recommendations, static fn($r) => in_array($r['state'], ['none', 'planned'], true)));
$highPriority = count(array_filter($recommendations, static fn($r) => $r['priority'] === 'high' && $r['state'] !== 'dismissed'));
$planItems = array_values(array_filter($recommendations, static fn($r) => in_array($r['state'], ['planned', 'done'], true)));
$doneItems = count(array_filter($planItems, static fn($r) => $r['state'] === 'done'));
$planProgress = $planItems ? round($doneItems / count($planItems) * 100) : 0;
$dismissedItems = array_values(array_filter($recommendations, static fn($r) => $r['state'] === 'dismissed'));

$payload = [
    'page' => 'prescriptive',
    'forecastTotal' => $forecast['total'],
    'branchCount' => $branchCount,
    'horizon' => $horizon,
    'openDays' => $capacity['openDays'],
    'capacity' => $capacity,
];
$pageTitle = 'Prescriptive analytics';
$activeNav = 'prescriptive';
$analyticsActive = 'prescriptive';
$analyticsParams = $queryParams;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <section class="admin-main">
        <div class="admin-topbar">
            <div>
                <span class="breadcrumb">Analytics / <b>Prescriptive</b></span>
                <h1>Prescriptive analytics</h1>
                <p>Turn booking patterns into a practical, staff-approved action plan.</p>
            </div>
            <a class="button button-primary" id="planExport" href="<?= e(analytics_url('prescriptive.php', $queryParams + ['export' => 'plan'])) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>
                Export action plan
            </a>
        </div>

        <div class="dashboard-content analytics-page" data-analytics-page="prescriptive">
            <?php include __DIR__ . '/../includes/analytics_tabs.php'; ?>

            <?php if ($notice): ?>
                <div class="alert <?= in_array($notice, ['invalid_request','appointment_not_pending'], true) ? 'alert-error' : 'alert-success' ?> action-notice" role="status">
                    <?php
                    $messages = [
                        'action_planned' => 'Added to your action plan.',
                        'action_done' => 'Action marked complete.',
                        'action_reopened' => 'Action reopened in your plan.',
                        'action_removed' => 'Action removed from your plan.',
                        'action_dismissed' => 'Recommendation dismissed.',
                        'action_restored' => 'Recommendation restored.',
                        'high_priority_planned' => 'High-priority recommendations added to your plan.',
                        'appointment_confirmed' => 'Appointment confirmed.',
                        'appointment_not_pending' => 'That appointment is no longer pending.',
                        'invalid_request' => 'Invalid request. Please try again.',
                    ];
                    echo e($messages[$notice] ?? 'Updated.');
                    ?>
                    <?php if ($notice === 'action_dismissed' && $undoId): ?>
                        <form method="post" class="inline-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="restore_action">
                            <input type="hidden" name="action_id" value="<?= e($undoId) ?>">
                            <button type="submit" class="text-link undo-button">Undo</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="analytics-toolbar">
                <div>
                    <span class="eyebrow">DECISION WINDOW</span>
                    <h2>Next <?= $horizon ?> days · <?= e(analytics_branch_label($branchId, $branches)) ?></h2>
                </div>
                <form method="get" class="analytics-filters">
                    <input type="hidden" name="slots" id="slotsHidden" value="<?= (int)$slotsPerDay ?>">
                    <input type="hidden" name="coverage" id="coverageHidden" value="<?= (int)$coverage ?>">
                    <label for="horizon">Horizon</label>
                    <select name="horizon" id="horizon" data-auto-submit>
                        <?php foreach ([7=>'Next 7 days',14=>'Next 14 days',30=>'Next 30 days'] as $value=>$label): ?>
                            <option value="<?= $value ?>" <?= $horizon === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="branch">Branch</label>
                    <select name="branch" id="branch" data-auto-submit>
                        <option value="">All branches</option>
                        <?php foreach ($branches as $branch): ?>
                            <option value="<?= (int)$branch['id'] ?>" <?= $branchId === (int)$branch['id'] ? 'selected' : '' ?>><?= e($branch['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="mini-button">Apply</button>
                </form>
            </div>

            <section class="analytics-hero prescriptive-hero reveal">
                <div class="analytics-hero-copy">
                    <span class="eyebrow light">WHAT TO DO NEXT</span>
                    <h2><?= $openActions ?> open recommendation<?= $openActions === 1 ? '' : 's' ?> ready for review</h2>
                    <p><?= $highPriority ?> high-priority · <?= count($upcomingPending) ?> upcoming pending requests · <?= $capacity['util'] ?>% projected slot use. Recommendations support staff decisions and never change clinic hours automatically.</p>
                </div>
                <div class="analytics-hero-stats">
                    <div><strong><?= count($planItems) ?></strong><span>in your plan</span></div>
                    <div><strong><?= $doneItems ?></strong><span>completed</span></div>
                    <div><strong><?= $planProgress ?>%</strong><span>plan progress</span></div>
                </div>
            </section>

            <div class="metric-grid analytics-metrics">
                <article class="metric-card reveal">
                    <div class="metric-top"><span>Open actions</span><span class="metric-icon blue">✦</span></div>
                    <strong><?= $openActions ?></strong>
                    <small>Not dismissed or completed</small>
                </article>
                <article class="metric-card reveal">
                    <div class="metric-top"><span>High priority</span><span class="metric-icon red">!</span></div>
                    <strong><?= $highPriority ?></strong>
                    <small>Review before the schedule fills</small>
                </article>
                <article class="metric-card reveal">
                    <div class="metric-top"><span>Pending requests</span><span class="metric-icon amber">◷</span></div>
                    <strong><?= count($pendingRows) ?></strong>
                    <small><?= $oldPending ?> aging · <?= count($upcomingPending) ?> upcoming</small>
                </article>
                <article class="metric-card reveal">
                    <div class="metric-top"><span>Projected slot use</span><span class="metric-icon green">▦</span></div>
                    <strong id="plannerUtilKpi"><?= $capacity['util'] ?>%</strong>
                    <small>Live from the capacity planner</small>
                </article>
            </div>

            <div class="analytics-grid equal-grid">
                <article class="panel action-plan-panel reveal">
                    <div class="panel-heading">
                        <div><h3>Your action plan</h3><p>Saved for this staff session · <?= $doneItems ?>/<?= count($planItems) ?> complete</p></div>
                        <?php if ($highPriority > 0): ?>
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="plan_all_high">
                                <button type="submit" class="mini-button">Plan all high</button>
                            </form>
                        <?php endif; ?>
                    </div>
                    <div class="plan-progress"><span style="width:<?= $planProgress ?>%"></span></div>
                    <?php if (!$planItems): ?>
                        <div class="plan-empty">
                            <span>✦</span>
                            <p>Add recommendations below to build a focused plan. Your progress stays available while you work in this staff session.</p>
                        </div>
                    <?php else: ?>
                        <ul class="plan-list">
                            <?php foreach ($planItems as $item): ?>
                                <li class="<?= $item['state'] === 'done' ? 'done' : '' ?>">
                                    <form method="post" class="plan-toggle">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="<?= $item['state'] === 'done' ? 'reopen_action' : 'complete_action' ?>">
                                        <input type="hidden" name="action_id" value="<?= e($item['id']) ?>">
                                        <button type="submit" class="plan-check" aria-label="<?= $item['state'] === 'done' ? 'Reopen' : 'Complete' ?> <?= e($item['title']) ?>"><?= $item['state'] === 'done' ? '✓' : '' ?></button>
                                    </form>
                                    <div><b><?= e($item['title']) ?></b><small><?= e($item['metric']) ?></small></div>
                                    <form method="post">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="remove_action">
                                        <input type="hidden" name="action_id" value="<?= e($item['id']) ?>">
                                        <button type="submit" class="drawer-close small" aria-label="Remove <?= e($item['title']) ?> from plan">×</button>
                                    </form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </article>

                <article class="panel capacity-panel reveal" id="capacity-planner">
                    <div class="panel-heading">
                        <div><h3>Capacity planner</h3><p>Model staffed slot capacity against forecast demand</p></div>
                        <span class="forecast-chip">LIVE</span>
                    </div>
                    <div class="capacity-gauge">
                        <div class="gauge-ring" id="gaugeRing" style="--gauge:<?= min(100, $capacity['util']) ?>%"><div><strong id="gaugeUtil"><?= $capacity['util'] ?>%</strong><span>projected use</span></div></div>
                        <div class="gauge-facts">
                            <div><span>Modeled capacity</span><b id="gaugeCapacity"><?= (int)$capacity['capacity'] ?> slots</b></div>
                            <div><span>Headroom</span><b id="gaugeHeadroom"><?= (int)$capacity['headroom'] ?> slots</b></div>
                            <div><span>Slots needed / branch / day</span><b id="gaugeRequired"><?= (int)$capacity['requiredSlots'] ?></b></div>
                        </div>
                    </div>
                    <label class="slider-label" for="slotsPerDay">Bookable slots per branch per day <output id="slotsValue"><?= (int)$slotsPerDay ?></output></label>
                    <input type="range" id="slotsPerDay" class="analytics-slider" min="6" max="16" step="1" value="<?= (int)$slotsPerDay ?>">
                    <label class="slider-label" for="staffCoverage">Staffed coverage <output id="coverageValue"><?= (int)$coverage ?>%</output></label>
                    <input type="range" id="staffCoverage" class="analytics-slider teal" min="50" max="100" step="5" value="<?= (int)$coverage ?>">
                    <div class="planner-actions">
                        <button type="button" class="mini-button" id="resetPlanner">Reset planner</button>
                        <span class="planner-status" id="plannerStatus"><?= $capacity['util'] >= 85 ? 'Near capacity — review staffing' : ($capacity['util'] >= 70 ? 'Filling — monitor daily' : 'Headroom available') ?></span>
                    </div>
                    <p class="method-note">Planner changes are a scenario only. They do not open slots or change clinic hours.</p>
                </article>
            </div>

            <article class="panel recommendations-panel reveal">
                <div class="panel-heading records-heading">
                    <div><h3>Recommended actions</h3><p>Rule-based guidance from current bookings and the forecast</p></div>
                    <div class="records-controls">
                        <label class="sr-only" for="actionSearch">Search recommendations</label>
                        <input type="search" id="actionSearch" placeholder="Search actions…" autocomplete="off">
                        <label class="sr-only" for="actionPriority">Filter priority</label>
                        <select id="actionPriority">
                            <option value="">All priorities</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                        <label class="sr-only" for="actionState">Filter plan state</label>
                        <select id="actionState">
                            <option value="">All states</option>
                            <option value="none">Not planned</option>
                            <option value="planned">In plan</option>
                            <option value="done">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="recommendation-cards" id="recommendationCards">
                    <?php foreach ($recommendations as $recommendation): if ($recommendation['state'] === 'dismissed') continue; ?>
                        <article class="recommendation-card priority-<?= e($recommendation['priority']) ?>"
                                 data-priority="<?= e($recommendation['priority']) ?>"
                                 data-state="<?= e($recommendation['state']) ?>"
                                 data-search="<?= e(strtolower($recommendation['title'] . ' ' . $recommendation['evidence'] . ' ' . $recommendation['metric'])) ?>">
                            <div class="recommendation-card-top">
                                <span class="priority-chip"><?= e(ucfirst($recommendation['priority'])) ?> priority</span>
                                <span class="state-chip state-<?= e($recommendation['state']) ?>"><?= $recommendation['state'] === 'none' ? 'Not planned' : ($recommendation['state'] === 'done' ? 'Completed' : 'In plan') ?></span>
                            </div>
                            <h3><?= e($recommendation['title']) ?></h3>
                            <div class="recommendation-metric"><?= e($recommendation['metric']) ?></div>
                            <p><?= e($recommendation['evidence']) ?></p>
                            <details class="recommendation-details">
                                <summary>Why this matters & next steps</summary>
                                <p><?= e($recommendation['detail']) ?></p>
                                <ol><?php foreach ($recommendation['steps'] as $step): ?><li><?= e($step) ?></li><?php endforeach; ?></ol>
                            </details>
                            <div class="recommendation-card-actions">
                                <?php if ($recommendation['state'] === 'none'): ?>
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="plan_action"><input type="hidden" name="action_id" value="<?= e($recommendation['id']) ?>"><button type="submit" class="mini-button">Add to plan</button></form>
                                <?php elseif ($recommendation['state'] === 'planned'): ?>
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="complete_action"><input type="hidden" name="action_id" value="<?= e($recommendation['id']) ?>"><button type="submit" class="mini-button success">Mark done</button></form>
                                <?php else: ?>
                                    <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="reopen_action"><input type="hidden" name="action_id" value="<?= e($recommendation['id']) ?>"><button type="submit" class="mini-button">Reopen</button></form>
                                <?php endif; ?>
                                <a class="text-link" href="<?= e($recommendation['link']) ?>"><?= e($recommendation['linkLabel']) ?> →</a>
                                <form method="post" class="dismiss-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="dismiss_action"><input type="hidden" name="action_id" value="<?= e($recommendation['id']) ?>"><button type="submit" class="text-link muted-link">Dismiss</button></form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="empty-table" id="noActionsMessage" hidden>No recommendations match these filters.</p>
                <?php if ($dismissedItems): ?>
                    <details class="dismissed-details">
                        <summary>Dismissed recommendations (<?= count($dismissedItems) ?>)</summary>
                        <ul>
                            <?php foreach ($dismissedItems as $item): ?>
                                <li><span><?= e($item['title']) ?></span>
                                    <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="restore_action"><input type="hidden" name="action_id" value="<?= e($item['id']) ?>"><button type="submit" class="text-link">Restore</button></form>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </details>
                <?php endif; ?>
            </article>

            <article class="panel recent-panel reveal">
                <div class="panel-heading">
                    <div><h3>Upcoming pending requests</h3><p>Confirm available slots directly from the decision queue</p></div>
                    <a class="text-link" href="<?= e(analytics_url('appointments.php', ['status' => 'pending'])) ?>">View all pending →</a>
                </div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Patient</th><th>Branch</th><th>Appointment</th><th>Reference</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php if (!$upcomingPending): ?>
                                <tr><td colspan="5" class="empty-table">No upcoming pending requests for this branch.</td></tr>
                            <?php endif; ?>
                            <?php foreach (array_slice($upcomingPending, 0, 8) as $row): ?>
                                <tr>
                                    <td><b><?= e($row['full_name']) ?></b><small><?= e($row['email']) ?></small></td>
                                    <td><?= e($row['branch_name']) ?></td>
                                    <td><?= e(date('M j, Y', strtotime($row['appointment_date']))) ?><small><?= e(date('g:i A', strtotime($row['appointment_time']))) ?></small></td>
                                    <td class="ref-cell"><?= e($row['reference_code']) ?></td>
                                    <td>
                                        <form method="post" class="inline-form">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="confirm_appointment">
                                            <input type="hidden" name="appointment_id" value="<?= (int)$row['id'] ?>">
                                            <button type="submit" class="mini-button success">Confirm</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <p class="dashboard-disclaimer">Prescriptive recommendations are rule-based decision support for academic demonstration. Staff must approve any scheduling or staffing change.</p>
        </div>
    </section>
</div>

<script id="analyticsData" type="application/json"><?= analytics_json($payload) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/analytics.js?v=<?= filemtime(__DIR__ . '/../assets/js/analytics.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>