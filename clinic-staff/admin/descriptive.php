<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/analytics.php';
require_admin();

$pdo = db();
$filters = analytics_filters();
$period = $filters['period'];
$branchId = $filters['branchId'];
$branches = $filters['branches'];
$rows = analytics_rows($pdo, $filters['where'], $filters['params']);
$summary = analytics_summary($rows);
$daily = analytics_daily($rows, $filters['start'], $filters['end'], false);
$completedRows = array_values(array_filter($rows, static fn($r) => $r['status'] === 'completed'));
$completedDaily = analytics_daily($completedRows, $filters['start'], $filters['end'], false);
$branchStats = analytics_branch_stats($rows, $branches);
$slotStats = analytics_slot_stats($rows);
$weekdayStats = analytics_weekday_stats($rows);

$peakSlot = ['label' => 'No data', 'count' => 0, 'share' => 0];
foreach ($slotStats as $slot) if ($slot['count'] > $peakSlot['count']) $peakSlot = $slot;
$leadingBranch = ['name' => 'No data', 'bookings' => 0, 'share' => 0];
foreach ($branchStats as $branch) if ($branch['bookings'] > $leadingBranch['bookings']) $leadingBranch = $branch;
$peakDayIndex = $daily['values'] ? array_keys($daily['values'], max($daily['values']))[0] : 0;
$peakDayLabel = $daily['labels'][$peakDayIndex] ?? 'No data';
$peakDayValue = $daily['values'][$peakDayIndex] ?? 0;

$queryParams = ['period' => $period, 'branch' => $branchId];
if (($_GET['export'] ?? '') === 'csv') {
    $csvRows = [];
    foreach ($rows as $row) {
        $csvRows[] = [
            $row['reference_code'], $row['full_name'], $row['email'], $row['phone'],
            $row['branch_name'], $row['appointment_date'], substr($row['appointment_time'], 0, 5),
            ucfirst($row['status']), $row['notes'] ?? '',
        ];
    }
    analytics_csv_download('carepoint-descriptive-' . date('Ymd') . '.csv',
        ['Reference', 'Patient', 'Email', 'Phone', 'Branch', 'Date', 'Time', 'Status', 'Notes'], $csvRows);
}

$recordsForJs = array_map(static fn($r) => [
    'id' => (int)$r['id'],
    'reference' => $r['reference_code'],
    'name' => $r['full_name'],
    'email' => $r['email'],
    'phone' => $r['phone'],
    'branch' => $r['branch_name'],
    'date' => date('M j, Y', strtotime($r['appointment_date'])),
    'time' => date('g:i A', strtotime($r['appointment_time'])),
    'status' => $r['status'],
    'notes' => $r['notes'] ?? '',
    'created' => date('M j, Y g:i A', strtotime($r['created_at'])),
], $rows);

$statusColors = ['pending' => '#F5A623', 'confirmed' => '#1668E3', 'completed' => '#00A88E', 'cancelled' => '#94A3B8'];
$statusTotal = max(1, $summary['total']);
$conicStops = [];
$offset = 0;
foreach ($summary['statuses'] as $status => $count) {
    $share = $count / $statusTotal * 100;
    $conicStops[] = $statusColors[$status] . ' ' . round($offset, 2) . '% ' . round($offset + $share, 2) . '%';
    $offset += $share;
}
$conicGradient = 'conic-gradient(' . implode(', ', $conicStops) . ')';
$maxDaily = max(1, ...$daily['values']);
$maxWeekday = max(1, ...$weekdayStats['values']);
$maxSlot = max(1, ...array_column($slotStats, 'count'));

$payload = [
    'page' => 'descriptive',
    'records' => $recordsForJs,
    'daily' => $daily,
    'completedDaily' => $completedDaily,
    'statuses' => $summary['statuses'],
    'branchStats' => $branchStats,
    'weekday' => $weekdayStats,
    'slots' => $slotStats,
];

$pageTitle = 'Descriptive analytics';
$activeNav = 'descriptive';
$analyticsActive = 'descriptive';
$analyticsParams = $queryParams;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <section class="admin-main">
        <div class="admin-topbar">
            <div>
                <span class="breadcrumb">Analytics / <b>Descriptive</b></span>
                <h1>Descriptive analytics</h1>
                <p>Understand what happened across bookings, branches, and appointment slots.</p>
            </div>
            <a class="button button-primary" href="<?= e(analytics_url('descriptive.php', $queryParams + ['export' => 'csv'])) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>
                Export CSV
            </a>
        </div>

        <div class="dashboard-content analytics-page" data-analytics-page="descriptive">
            <?php include __DIR__ . '/../includes/analytics_tabs.php'; ?>

            <div class="analytics-toolbar">
                <div>
                    <span class="eyebrow">REPORTING WINDOW</span>
                    <h2><?= e(analytics_period_label($period)) ?> · <?= e(analytics_branch_label($branchId, $branches)) ?></h2>
                </div>
                <form method="get" class="analytics-filters">
                    <label for="period">Period</label>
                    <select name="period" id="period" data-auto-submit>
                        <?php foreach (['7'=>'Last 7 days','30'=>'Last 30 days','90'=>'Last 90 days','all'=>'All time'] as $value=>$label): ?>
                            <option value="<?= e($value) ?>" <?= $period === $value ? 'selected' : '' ?>><?= e($label) ?></option>
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

            <section class="analytics-hero descriptive-hero reveal">
                <div class="analytics-hero-copy">
                    <span class="eyebrow light">WHAT HAPPENED</span>
                    <h2><?= $summary['total'] ? e($leadingBranch['name']) . ' leads with ' . (int)$leadingBranch['bookings'] . ' bookings' : 'No bookings in this window yet' ?></h2>
                    <p><?= $summary['total']
                        ? 'The busiest recorded day was ' . e($peakDayLabel) . ' with ' . (int)$peakDayValue . ' bookings. ' . e($peakSlot['label']) . ' is the most requested time slot.'
                        : 'Try a wider period or another branch, or add a booking from the public website.' ?></p>
                </div>
                <div class="analytics-hero-stats">
                    <div><strong><?= (int)$summary['total'] ?></strong><span>bookings</span></div>
                    <div><strong><?= $summary['completionRate'] ?>%</strong><span>completed</span></div>
                    <div><strong><?= (int)$summary['patients'] ?></strong><span>patients</span></div>
                </div>
            </section>

            <div class="metric-grid analytics-metrics">
                <article class="metric-card reveal">
                    <div class="metric-top"><span>Total bookings</span><span class="metric-icon blue">▤</span></div>
                    <strong><?= (int)$summary['total'] ?></strong>
                    <small><?= (int)$summary['active'] ?> non-cancelled · <?= (int)$summary['statuses']['pending'] ?> pending</small>
                </article>
                <article class="metric-card reveal">
                    <div class="metric-top"><span>Completed visits</span><span class="metric-icon green">✓</span></div>
                    <strong><?= (int)$summary['statuses']['completed'] ?></strong>
                    <small><?= $summary['completionRate'] ?>% of all bookings</small>
                </article>
                <article class="metric-card reveal">
                    <div class="metric-top"><span>Cancellation rate</span><span class="metric-icon amber">◷</span></div>
                    <strong><?= $summary['cancellationRate'] ?>%</strong>
                    <small><?= (int)$summary['statuses']['cancelled'] ?> cancelled bookings</small>
                </article>
                <article class="metric-card reveal">
                    <div class="metric-top"><span>Unique patients</span><span class="metric-icon violet">♙</span></div>
                    <strong><?= (int)$summary['patients'] ?></strong>
                    <small>Distinct patient records in period</small>
                </article>
            </div>

            <div class="analytics-grid">
                <article class="panel chart-panel reveal">
                    <div class="panel-heading">
                        <div><h3>Booking activity</h3><p>Daily bookings and completed visits · <?= e(analytics_period_label($period)) ?></p></div>
                        <span class="chart-legend multi"><i class="blue"></i> Bookings <i class="green"></i> Completed</span>
                    </div>
                    <div class="chart-area tall"><canvas id="descriptiveTrend" aria-label="Daily booking activity chart" role="img"></canvas></div>
                    <div class="chart-fallback trend-fallback" aria-hidden="true">
                        <?php foreach ($daily['values'] as $i => $value): ?>
                            <div class="fallback-bar" style="--bar-height:<?= round($value / $maxDaily * 100) ?>%"><span><?= (int)$value ?></span><i></i><small><?= e($daily['labels'][$i]) ?></small></div>
                        <?php endforeach; ?>
                    </div>
                </article>

                <article class="panel status-panel reveal">
                    <div class="panel-heading"><div><h3>Booking status</h3><p>Share of recorded bookings</p></div></div>
                    <div class="donut-wrap">
                        <canvas id="descriptiveStatus" aria-label="Booking status chart" role="img"></canvas>
                        <div class="donut-fallback" style="background:<?= e($conicGradient) ?>"></div>
                        <div class="donut-center"><strong><?= (int)$summary['total'] ?></strong><span>Total</span></div>
                    </div>
                    <div class="status-legend">
                        <?php foreach (['pending'=>'Pending','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled'] as $key=>$label): ?>
                            <div><span class="legend-dot <?= e($key) ?>"></span><?= e($label) ?><b><?= (int)$summary['statuses'][$key] ?></b></div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>

            <div class="analytics-grid equal-grid">
                <article class="panel reveal">
                    <div class="panel-heading"><div><h3>Branch comparison</h3><p>Booking volume by clinic location</p></div></div>
                    <div class="branch-analytics">
                        <?php foreach ($branchStats as $i => $branch): ?>
                            <div class="branch-analytics-row">
                                <div class="bar-label"><span><?= e($branch['name']) ?></span><b><?= (int)$branch['bookings'] ?></b></div>
                                <div class="progress-track"><span class="branch-bar-<?= $i % 4 ?>" style="width:<?= $summary['total'] ? round($branch['bookings'] / $summary['total'] * 100) : 0 ?>%"></span></div>
                                <small><?= $branch['share'] ?>% of bookings · <?= (int)$branch['completed'] ?> completed</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="insight-callout"><span>↗</span><p><b>Leading branch:</b> <?= e($leadingBranch['name']) ?> with <?= (int)$leadingBranch['bookings'] ?> bookings.</p></div>
                </article>

                <article class="panel reveal">
                    <div class="panel-heading"><div><h3>Demand by weekday</h3><p>Non-cancelled bookings across the week</p></div></div>
                    <div class="chart-area"><canvas id="descriptiveWeekday" aria-label="Weekday demand chart" role="img"></canvas></div>
                    <div class="chart-fallback weekday-fallback" aria-hidden="true">
                        <?php foreach ($weekdayStats['values'] as $i => $value): ?>
                            <div class="fallback-bar" style="--bar-height:<?= round($value / $maxWeekday * 100) ?>%"><span><?= (int)$value ?></span><i></i><small><?= e($weekdayStats['labels'][$i]) ?></small></div>
                        <?php endforeach; ?>
                    </div>
                </article>
            </div>

            <article class="panel reveal">
                <div class="panel-heading">
                    <div><h3>Appointment slot heatmap</h3><p>Non-cancelled demand by start time · select a slot to inspect records</p></div>
                    <span class="forecast-chip">PEAK <?= e(strtoupper($peakSlot['label'])) ?></span>
                </div>
                <div class="slot-heatmap">
                    <?php foreach ($slotStats as $slot):
                        $intensity = $maxSlot ? $slot['count'] / $maxSlot : 0;
                        $level = $intensity >= 0.75 ? 'hot' : ($intensity >= 0.45 ? 'warm' : ($intensity > 0 ? 'mild' : 'empty'));
                    ?>
                        <button type="button" class="slot-cell <?= e($level) ?>" data-slot-filter="<?= e($slot['time']) ?>">
                            <b><?= e($slot['label']) ?></b>
                            <strong><?= (int)$slot['count'] ?></strong>
                            <small><?= $slot['share'] ?>%</small>
                        </button>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="panel recent-panel reveal" id="records-panel">
                <div class="panel-heading records-heading">
                    <div><h3>Appointment records</h3><p><span id="recordCount"><?= count($rows) ?></span> matching records · fictional demo data is labeled in notes</p></div>
                    <div class="records-controls">
                        <label class="sr-only" for="recordSearch">Search records</label>
                        <input type="search" id="recordSearch" placeholder="Search patient, reference, branch…" autocomplete="off">
                        <label class="sr-only" for="recordStatus">Filter status</label>
                        <select id="recordStatus">
                            <option value="">All statuses</option>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <label class="sr-only" for="recordSort">Sort records</label>
                        <select id="recordSort">
                            <option value="date-desc">Newest first</option>
                            <option value="date-asc">Oldest first</option>
                            <option value="name-asc">Patient A–Z</option>
                            <option value="branch-asc">Branch A–Z</option>
                        </select>
                    </div>
                </div>
                <div class="table-wrap">
                    <table id="recordsTable">
                        <thead><tr><th>Patient</th><th>Branch</th><th>Appointment</th><th>Status</th><th>Reference</th><th><span class="sr-only">Details</span></th></tr></thead>
                        <tbody>
                            <?php if (!$rows): ?>
                                <tr class="empty-row"><td colspan="6" class="empty-table">No appointments match this period and branch.</td></tr>
                            <?php endif; ?>
                            <?php foreach ($rows as $row): ?>
                                <tr data-record-row="<?= (int)$row['id'] ?>"
                                    data-status="<?= e($row['status']) ?>"
                                    data-branch="<?= e($row['branch_name']) ?>"
                                    data-name="<?= e($row['full_name']) ?>"
                                    data-date="<?= e($row['appointment_date']) ?>"
                                    data-slot="<?= e(substr($row['appointment_time'], 0, 5)) ?>">
                                    <td><b><?= e($row['full_name']) ?></b><small><?= e($row['email']) ?></small></td>
                                    <td><?= e($row['branch_name']) ?></td>
                                    <td><?= e(date('M j, Y', strtotime($row['appointment_date']))) ?><small><?= e(date('g:i A', strtotime($row['appointment_time']))) ?></small></td>
                                    <td><span class="status status-<?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span></td>
                                    <td class="ref-cell"><?= e($row['reference_code']) ?></td>
                                    <td><button type="button" class="mini-button" data-record-details="<?= (int)$row['id'] ?>">View</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-pagination">
                    <button type="button" class="mini-button" id="recordsPrev">← Previous</button>
                    <span id="recordsPageInfo">Page 1</span>
                    <button type="button" class="mini-button" id="recordsNext">Next →</button>
                </div>
            </article>

            <p class="dashboard-disclaimer">Descriptive analytics summarize stored appointment records. Demo records are fictional and labeled for classroom demonstration.</p>
        </div>
    </section>
</div>

<div class="drawer-overlay" id="drawerOverlay" hidden></div>
<aside class="record-drawer" id="recordDrawer" role="dialog" aria-modal="true" aria-labelledby="drawerTitle" hidden>
    <div class="drawer-header">
        <div><span class="eyebrow">APPOINTMENT DETAIL</span><h2 id="drawerTitle">Record</h2></div>
        <button type="button" class="drawer-close" id="drawerClose" aria-label="Close details">×</button>
    </div>
    <div class="drawer-body" id="drawerBody"></div>
    <div class="drawer-footer"><a class="button button-primary" id="drawerManageLink" href="<?= BASE_URL ?>/admin/appointments.php">Manage appointments</a></div>
</aside>

<script id="analyticsData" type="application/json"><?= analytics_json($payload) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/analytics.js?v=<?= filemtime(__DIR__ . '/../assets/js/analytics.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>