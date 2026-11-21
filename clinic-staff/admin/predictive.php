<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/analytics.php';
require_admin();

$pdo = db();
$branches = $pdo->query("SELECT id, name FROM branches WHERE active = 1 ORDER BY sort_order, id")->fetchAll();
$branchId = filter_input(INPUT_GET, 'branch', FILTER_VALIDATE_INT);
$validBranchIds = array_map(static fn($b) => (int)$b['id'], $branches);
if (!$branchId || !in_array((int)$branchId, $validBranchIds, true)) $branchId = null;

$horizon = $_GET['horizon'] ?? '14';
if (!in_array($horizon, ['7', '14', '30'], true)) $horizon = '14';
$horizon = (int)$horizon;
$shift = filter_input(INPUT_GET, 'shift', FILTER_VALIDATE_INT);
$shift = $shift === false || $shift === null ? 0 : max(-30, min(30, (int)$shift));

$forecast = analytics_forecast($pdo, $branchId, $horizon, $shift);
$branchCount = $branchId ? 1 : max(1, count($branches));
$capacity = analytics_capacity($forecast['total'], $branchCount, $horizon);

$queryParams = ['branch' => $branchId, 'horizon' => $horizon, 'shift' => $shift];
if (($_GET['export'] ?? '') === 'csv') {
    $csvRows = [];
    foreach ($forecast['rows'] as $row) {
        $csvRows[] = [$row['date'], $row['weekday'], $row['closed'] ? 'Closed' : number_format($row['expected'], 1), $row['booked'], $row['closed'] ? '' : number_format($row['expected'] - $row['booked'], 1)];
    }
    analytics_csv_download('carepoint-predictive-' . date('Ymd') . '.csv',
        ['Date', 'Weekday', 'Expected bookings', 'Already booked', 'Unfilled estimate'], $csvRows);
}

$payload = ['page' => 'predictive', 'forecast' => $forecast, 'capacity' => $capacity, 'branchCount' => $branchCount];
$pageTitle = 'Predictive analytics';
$activeNav = 'predictive';
$analyticsActive = 'predictive';
$analyticsParams = $queryParams;
include __DIR__ . '/../includes/header.php';
?>
<div class="admin-shell">
    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>
    <section class="admin-main">
        <div class="admin-topbar">
            <div>
                <span class="breadcrumb">Analytics / <b>Predictive</b></span>
                <h1>Predictive analytics</h1>
                <p>Estimate upcoming appointment demand from recent booking patterns.</p>
            </div>
            <a class="button button-primary" id="forecastExport" href="<?= e(analytics_url('predictive.php', $queryParams + ['export' => 'csv'])) ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>
                Export forecast
            </a>
        </div>

        <div class="dashboard-content analytics-page" data-analytics-page="predictive">
            <?php include __DIR__ . '/../includes/analytics_tabs.php'; ?>

            <div class="analytics-toolbar">
                <div>
                    <span class="eyebrow">FORECAST WINDOW</span>
                    <h2>Next <?= $horizon ?> days · <?= e(analytics_branch_label($branchId, $branches)) ?></h2>
                </div>
                <form method="get" class="analytics-filters">
                    <input type="hidden" name="shift" value="<?= (int)$shift ?>" id="shiftHidden">
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

            <section class="analytics-hero predictive-hero reveal">
                <div class="analytics-hero-copy">
                    <span class="eyebrow light">WHAT MAY HAPPEN</span>
                    <h2><span id="heroForecastTotal"><?= (int)$forecast['total'] ?></span> estimated bookings</h2>
                    <p>Planning range <b id="heroForecastRange"><?= (int)$forecast['lower'] ?>–<?= (int)$forecast['upper'] ?></b> across <?= (int)$forecast['openDays'] ?> open days. This is a transparent baseline estimate, not a guaranteed prediction.</p>
                </div>
                <div class="analytics-hero-stats">
                    <div><strong id="heroAvgDay"><?= $forecast['avgOpenDay'] ?></strong><span>per open day</span></div>
                    <div><strong id="heroFit"><?= $forecast['fit'] === null ? '—' : $forecast['fit'] . '%' ?></strong><span>backtest fit</span></div>
                    <div><strong><?= e($forecast['peakWeekday']) ?></strong><span>strongest weekday</span></div>
                </div>
            </section>

            <div class="metric-grid analytics-metrics">
                <article class="metric-card reveal">
                    <div class="metric-top"><span>Expected demand</span><span class="metric-icon blue">↗</span></div>
                    <strong id="kpiExpected"><?= (int)$forecast['total'] ?></strong>
                    <small>Next <?= $horizon ?> days · shift <span id="kpiShift"><?= $shift > 0 ? '+' : '' ?><?= $shift ?></span>%</small>
                </article>
                <article class="metric-card reveal">
                    <div class="metric-top"><span>Planning range</span><span class="metric-icon violet">≈</span></div>
                    <strong id="kpiRange"><?= (int)$forecast['lower'] ?>–<?= (int)$forecast['upper'] ?></strong>
                    <small>Historical variability band</small>
                </article>
                <article class="metric-card reveal">
                    <div class="metric-top"><span>Projected slot use</span><span class="metric-icon green">▦</span></div>
                    <strong id="kpiUtil"><?= $capacity['util'] ?>%</strong>
                    <small><?= (int)$capacity['capacity'] ?> modeled slots available</small>
                </article>
                <article class="metric-card reveal">
                    <div class="metric-top"><span>Recent trend</span><span class="metric-icon amber">◴</span></div>
                    <strong><?= $forecast['trend'] > 0 ? '+' : '' ?><?= $forecast['trend'] ?>%</strong>
                    <small>Last 14 open days vs previous 14</small>
                </article>
            </div>

            <article class="panel chart-panel reveal">
                <div class="panel-heading">
                    <div><h3>Historical demand and forecast</h3><p>28 days of history · <?= $horizon ?>-day outlook · Sundays closed</p></div>
                    <span class="chart-legend multi"><i class="blue"></i> Recorded <i class="violet"></i> Expected <i class="green"></i> Booked</span>
                </div>
                <div class="chart-area tall"><canvas id="predictiveChart" aria-label="Historical demand and forecast chart" role="img"></canvas></div>
                <div class="chart-fallback forecast-fallback">
                    <table>
                        <thead><tr><th>Date</th><th>Expected</th><th>Booked</th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($forecast['rows'], 0, 10) as $row): ?>
                                <tr><td><?= e($row['label']) ?> · <?= e($row['weekday']) ?></td><td><?= $row['closed'] ? 'Closed' : e((string)$row['expected']) ?></td><td><?= (int)$row['booked'] ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <div class="analytics-grid equal-grid">
                <article class="panel scenario-panel reveal">
                    <div class="panel-heading">
                        <div><h3>Scenario lab</h3><p>Adjust expected demand and see the outlook update live</p></div>
                        <span class="forecast-chip">LIVE</span>
                    </div>
                    <label class="slider-label" for="demandShift">Demand change <output id="demandShiftValue"><?= $shift > 0 ? '+' : '' ?><?= $shift ?>%</output></label>
                    <input type="range" id="demandShift" min="-30" max="30" step="5" value="<?= (int)$shift ?>" class="analytics-slider">
                    <div class="slider-scale"><span>−30%</span><span>Baseline</span><span>+30%</span></div>
                    <div class="scenario-readout">
                        <div><span>Adjusted total</span><b id="scenarioTotal"><?= (int)$forecast['total'] ?></b></div>
                        <div><span>Adjusted slot use</span><b id="scenarioUtil"><?= $capacity['util'] ?>%</b></div>
                    </div>
                    <button type="button" class="mini-button" id="resetScenario">Reset to baseline</button>
                    <p class="method-note">The scenario multiplier is applied to the weekday baseline. Export includes the current shift.</p>
                </article>

                <article class="panel reveal">
                    <div class="panel-heading"><div><h3>Branch outlook</h3><p>Expected demand split by recent branch share</p></div></div>
                    <div class="branch-outlook">
                        <?php foreach ($forecast['branchSplit'] as $i => $branch): ?>
                            <div class="branch-outlook-row">
                                <div class="bar-label"><span><?= e($branch['name']) ?></span><b><?= (int)$branch['expected'] ?></b></div>
                                <div class="progress-track"><span class="branch-bar-<?= $i % 4 ?>" style="width:<?= min(100, $branch['share']) ?>%"></span></div>
                                <small><?= $branch['share'] ?>% of recent non-cancelled demand</small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="insight-callout"><span>◎</span><p><b>Capacity headroom:</b> <span id="headroomText"><?= (int)$capacity['headroom'] ?></span> modeled slots remain at the current scenario.</p></div>
                </article>
            </div>

            <article class="panel reveal">
                <div class="panel-heading"><div><h3>Daily forecast</h3><p>Expected bookings compared with appointments already on the calendar</p></div></div>
                <div class="table-wrap">
                    <table id="forecastTable">
                        <thead><tr><th>Date</th><th>Weekday</th><th>Expected</th><th>Already booked</th><th>Unfilled estimate</th><th>Clinic</th></tr></thead>
                        <tbody>
                            <?php foreach ($forecast['rows'] as $row): ?>
                                <tr data-forecast-row data-base="<?= e((string)$row['expected']) ?>">
                                    <td><b><?= e($row['label']) ?></b><small><?= e($row['date']) ?></small></td>
                                    <td><?= e($row['weekday']) ?></td>
                                    <td class="forecast-expected"><?= $row['closed'] ? '—' : e((string)$row['expected']) ?></td>
                                    <td><?= (int)$row['booked'] ?></td>
                                    <td class="forecast-gap"><?= $row['closed'] ? '—' : e((string)round($row['expected'] - $row['booked'], 1)) ?></td>
                                    <td><span class="status <?= $row['closed'] ? 'status-cancelled' : 'status-completed' ?>"><?= $row['closed'] ? 'Closed' : 'Open' ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <details class="model-details reveal" open>
                <summary>How this estimate is built</summary>
                <div class="model-details-body">
                    <div>
                        <h4>Method</h4>
                        <p>Weekday averages from the prior 56 days of non-cancelled bookings are adjusted by a capped recent-trend factor, then multiplied by the scenario shift. Sundays are modeled as closed.</p>
                    </div>
                    <div>
                        <h4>Planning range</h4>
                        <p>The band uses historical day-to-day variability (±1.28 standard deviations scaled by the number of open days). It is a planning aid, not a statistical guarantee.</p>
                    </div>
                    <div>
                        <h4>Backtest</h4>
                        <p>The model was trained on the 28 days before the last 7 days and compared with <?= (int)$forecast['backtestActual'] ?> actual bookings. Fit: <?= $forecast['fit'] === null ? 'not enough history' : $forecast['fit'] . '%' ?>.</p>
                    </div>
                    <div>
                        <h4>Limitations</h4>
                        <p> Holidays, marketing, weather, staffing changes, and no-shows are not modeled. Review the forecast alongside the calendar before making staffing decisions.</p>
                    </div>
                </div>
            </details>

            <p class="dashboard-disclaimer">Predictive analytics are decision-support estimates for academic demonstration. They do not predict individual patient behavior or clinical outcomes.</p>
        </div>
    </section>
</div>

<script id="analyticsData" type="application/json"><?= analytics_json($payload) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/analytics.js?v=<?= filemtime(__DIR__ . '/../assets/js/analytics.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>