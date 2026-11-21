
<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

$pdo = db();
$period = $_GET['period'] ?? '30';
if (!in_array($period, ['7','30','90','all'], true)) $period = '30';

$dateCondition = $period === 'all' ? '' : ' AND a.appointment_date >= DATE_SUB(CURDATE(), INTERVAL ' . (int)$period . ' DAY)';

$totals = $pdo->query("SELECT COUNT(*) total, SUM(status='pending') pending, SUM(status='confirmed') confirmed, SUM(status='completed') completed, SUM(status='cancelled') cancelled FROM appointments")->fetch();

$recent = $pdo->query("SELECT a.*,p.full_name,p.email,p.phone,b.name branch_name FROM appointments a JOIN patients p ON p.id=a.patient_id JOIN branches b ON b.id=a.branch_id ORDER BY a.created_at DESC LIMIT 8")->fetchAll();

$branchStats = $pdo->query("SELECT b.name, COUNT(a.id) bookings, SUM(a.status='completed') completed FROM branches b LEFT JOIN appointments a ON a.branch_id=b.id GROUP BY b.id ORDER BY b.sort_order")->fetchAll();

$dailyStmt = $pdo->query("SELECT DATE_FORMAT(appointment_date,'%b %e') label, COUNT(*) total FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND status != 'cancelled' GROUP BY appointment_date ORDER BY appointment_date");
$dailyRows = $dailyStmt->fetchAll();

$trendLabels = [];
$trendValues = [];
$lookup = [];

foreach ($dailyRows as $r) $lookup[$r['label']] = (int)$r['total'];

for ($i = 13; $i >= 0; $i--) {
    $label = date('M j', strtotime("-$i days"));
    $trendLabels[] = $label;
    $trendValues[] = $lookup[$label] ?? 0;
}

$statusRows = $pdo->query("SELECT status, COUNT(*) total FROM appointments GROUP BY status")->fetchAll();
$statusMap = ['pending'=>0,'confirmed'=>0,'completed'=>0,'cancelled'=>0];

foreach ($statusRows as $r) {
    $statusMap[$r['status']] = (int)$r['total'];
}

$forecastStmt = $pdo->query("SELECT DATE(appointment_date) day, COUNT(*) total FROM appointments WHERE appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND appointment_date < CURDATE() AND status != 'cancelled' GROUP BY DATE(appointment_date) ORDER BY day");
$forecastRows = $forecastStmt->fetchAll();

$observed = array_map(fn($r) => (int)$r['total'], $forecastRows);
$avgDaily = count($observed) ? array_sum($observed) / count($observed) : 0;
$next7Forecast = round($avgDaily * 7);

$bestBranch = $branchStats ? $branchStats[0] : ['name'=>'No data','bookings'=>0];

foreach ($branchStats as $bs) {
    if ((int)$bs['bookings'] > (int)$bestBranch['bookings']) {
        $bestBranch = $bs;
    }
}

$busyHourStmt = $pdo->query("SELECT DATE_FORMAT(appointment_time,'%h:%i %p') slot, COUNT(*) total FROM appointments WHERE status IN ('pending','confirmed','completed') GROUP BY appointment_time ORDER BY total DESC LIMIT 1");
$busyHour = $busyHourStmt->fetch() ?: ['slot'=>'No data','total'=>0];

$util = min(100, round(($next7Forecast / (2 * 12 * 6 * 7)) * 100));

$recommendations = [];

if ((int)$totals['pending'] > 5) {
    $recommendations[] = 'Review the ' . (int)$totals['pending'] . ' pending appointment requests to avoid leaving patients without confirmation.';
}

if ($busyHour['slot'] !== 'No data' && (int)$busyHour['total'] > 3) {
    $recommendations[] = 'The most requested time is ' . $busyHour['slot'] . '. Consider keeping staff coverage available during this period.';
}

if ($util >= 70) {
    $recommendations[] = 'Estimated demand is high relative to the current slot capacity. Consider opening additional slots or extending clinic hours after staff approval.';
}

if (!$recommendations) {
    $recommendations[] = 'Current volume appears manageable. Keep monitoring bookings and review the dashboard weekly.';
}

$pageTitle = 'Analytics dashboard';
$activeNav = 'overview';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">

    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <section class="admin-main">

        <div class="admin-topbar">
            <div>
                <span class="breadcrumb">Workspace / <b>Overview</b></span>
                <h1>Dashboard overview</h1>
                <p>Monitor clinic activity and make data-informed scheduling decisions.</p>
            </div>
            <div class="topbar-date"><?= date('D, M j, Y') ?></div>
        </div>

        <div class="dashboard-content">

            <div class="dashboard-toolbar">
                <div>
                    <span class="eyebrow">AT A GLANCE</span>
                    <h2>Clinic performance</h2>
                </div>

                <form method="get" class="period-form">
                    <label for="period">Period</label>
                    <select name="period" id="period" onchange="this.form.submit()">
                        <option value="7" <?= $period === '7' ? 'selected' : '' ?>>Last 7 days</option>
                        <option value="30" <?= $period === '30' ? 'selected' : '' ?>>Last 30 days</option>
                        <option value="90" <?= $period === '90' ? 'selected' : '' ?>>Last 90 days</option>
                        <option value="all" <?= $period === 'all' ? 'selected' : '' ?>>All time</option>
                    </select>
                </form>
            </div>

            <div class="metric-grid">
                <article class="metric-card">
                    <div class="metric-top"><span>Total appointments</span><span class="metric-icon blue">▤</span></div>
                    <strong><?= (int)$totals['total'] ?></strong>
                    <small>All recorded bookings</small>
                </article>

                <article class="metric-card">
                    <div class="metric-top"><span>Awaiting review</span><span class="metric-icon amber">◷</span></div>
                    <strong><?= (int)$totals['pending'] ?></strong>
                    <small>Pending confirmation</small>
                </article>

                <article class="metric-card">
                    <div class="metric-top"><span>Confirmed</span><span class="metric-icon green">✓</span></div>
                    <strong><?= (int)$totals['confirmed'] ?></strong>
                    <small>Ready for appointment</small>
                </article>

                <article class="metric-card">
                    <div class="metric-top"><span>Completed</span><span class="metric-icon violet">✦</span></div>
                    <strong><?= (int)$totals['completed'] ?></strong>
                    <small>Completed visits</small>
                </article>
            </div>

            <div class="analytics-grid">

                <article class="panel chart-panel">
                    <div class="panel-heading">
                        <div>
                            <h3>Appointment activity</h3>
                            <p>Daily non-cancelled bookings · last 14 days</p>
                        </div>
                        <span class="chart-legend"><i></i> Appointments</span>
                    </div>
                    <div class="chart-area">
                        <canvas id="trendChart" aria-label="Appointment activity chart"></canvas>
                    </div>
                </article>

                <article class="panel status-panel">
                    <div class="panel-heading">
                        <div>
                            <h3>Booking status</h3>
                            <p>All appointment records</p>
                        </div>
                    </div>

                    <div class="donut-wrap">
                        <canvas id="statusChart" aria-label="Booking status chart"></canvas>
                        <div class="donut-center">
                            <strong><?= (int)$totals['total'] ?></strong>
                            <span>Total</span>
                        </div>
                    </div>

                    <div class="status-legend">
                        <?php foreach (['pending'=>'Pending','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled'] as $key => $label): ?>
                            <div>
                                <span class="legend-dot <?= e($key) ?>"></span>
                                <?= e($label) ?>
                                <b><?= $statusMap[$key] ?></b>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </article>

            </div>

            <div class="analytics-grid lower-grid">

                <article class="panel">
                    <div class="panel-heading">
                        <div>
                            <h3>Branch comparison</h3>
                            <p>Booking volume by clinic location</p>
                        </div>
                    </div>

                    <div class="branch-analytics">
                        <?php foreach ($branchStats as $bs):
                            $percent = $totals['total']
                                ? round($bs['bookings'] / $totals['total'] * 100)
                                : 0;
                        ?>
                            <div class="branch-analytics-row">
                                <div class="bar-label">
                                    <span><?= e($bs['name']) ?></span>
                                    <b><?= (int)$bs['bookings'] ?></b>
                                </div>
                                <div class="progress-track">
                                    <span style="width:<?= $percent ?>%"></span>
                                </div>
                                <small><?= $percent ?>% of all bookings</small>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="insight-callout">
                        <span>↗</span>
                        <p><b>Leading branch:</b> <?= e($bestBranch['name']) ?> with <?= (int)$bestBranch['bookings'] ?> recorded booking(s).</p>
                    </div>
                </article>

                <article class="panel forecast-panel">
                    <div class="panel-heading">
                        <div>
                            <h3>Demand forecast</h3>
                            <p>Simple baseline estimate for next 7 days</p>
                        </div>
                        <span class="forecast-chip">PREDICTIVE</span>
                    </div>

                    <div class="forecast-number">
                        <?= $next7Forecast ?><small> estimated bookings</small>
                    </div>

                    <p class="forecast-explain">
                        Based on the average daily volume in the previous 30 days with recorded activity (excluding cancelled appointments).
                    </p>

                    <div class="forecast-stats">
                        <div><span>Peak time</span><b><?= e($busyHour['slot']) ?></b></div>
                        <div><span>Estimated slot use</span><b><?= $util ?>%</b></div>
                    </div>

                    <p class="method-note">
                        Method: historical daily average × 7. This is a classroom baseline, not a guaranteed prediction.
                    </p>
                </article>

            </div>

            <article class="panel recommendation-panel">
                <div class="panel-heading">
                    <div>
                        <h3>Scheduling recommendations</h3>
                        <p>Prescriptive analytics · suggested actions from current data</p>
                    </div>
                    <span class="recommendation-icon">✦</span>
                </div>

                <div class="recommendation-list">
                    <?php foreach ($recommendations as $i => $rec): ?>
                        <div class="recommendation-item">
                            <span><?= $i + 1 ?></span>
                            <p><?= e($rec) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="panel recent-panel">
                <div class="panel-heading">
                    <div>
                        <h3>Recent appointments</h3>
                        <p>Latest booking activity across both branches</p>
                    </div>
                    <a class="text-link" href="<?= BASE_URL ?>/admin/appointments.php">View all →</a>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Branch</th>
                                <th>Date & time</th>
                                <th>Status</th>
                                <th>Reference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$recent): ?>
                                <tr>
                                    <td colspan="5" class="empty-table">
                                        No appointments yet. Make a test booking from the public website.
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($recent as $a): ?>
                                <tr>
                                    <td>
                                        <b><?= e($a['full_name']) ?></b>
                                        <small><?= e($a['email']) ?></small>
                                    </td>
                                    <td><?= e($a['branch_name']) ?></td>
                                    <td>
                                        <?= e(date('M j, Y', strtotime($a['appointment_date']))) ?>
                                        <small><?= e(date('g:i A', strtotime($a['appointment_time']))) ?></small>
                                    </td>
                                    <td>
                                        <span class="status status-<?= e($a['status']) ?>">
                                            <?= e(ucfirst($a['status'])) ?>
                                        </span>
                                    </td>
                                    <td class="ref-cell"><?= e($a['reference_code']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <p class="dashboard-disclaimer">
                Analytics are generated from the records stored in your local MySQL database. Forecasts and recommendations are decision-support estimates for academic demonstration.
            </p>

        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trendLabels = <?= json_encode($trendLabels) ?>;
const trendValues = <?= json_encode($trendValues) ?>;
const statusValues = <?= json_encode(array_values($statusMap)) ?>;

if (window.Chart) {
    Chart.defaults.font.family = "'DM Sans', sans-serif";

    new Chart(document.getElementById('trendChart'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Appointments',
                data: trendValues,
                borderColor: '#2876c8',
                backgroundColor: 'rgba(40,118,200,.12)',
                fill: true,
                tension: .36,
                pointRadius: 3,
                pointBackgroundColor: '#2876c8',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    grid: { color: '#edf1f7' }
                },
                x: { grid: { display: false } }
            }
        }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Confirmed', 'Completed', 'Cancelled'],
            datasets: [{
                data: statusValues,
                backgroundColor: ['#e8a33e', '#2876c8', '#36a782', '#a0a9bb'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '76%',
            plugins: { legend: { display: false } }
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
