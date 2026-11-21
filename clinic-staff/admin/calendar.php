
<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

$pdo = db();

$view = $_GET['view'] ?? 'month';
if (!in_array($view, ['day', 'week', 'month'], true)) {
    $view = 'month';
}

$dateInput = $_GET['date'] ?? date('Y-m-d');
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateInput);

if (!$date || $date->format('Y-m-d') !== $dateInput) {
    $date = new DateTimeImmutable('today');
}

$branchId = filter_input(INPUT_GET, 'branch', FILTER_VALIDATE_INT);

if ($view === 'day') {
    $start = $date;
    $end = $date->modify('+1 day');
    $heading = $date->format('F j, Y');
} elseif ($view === 'week') {
    $start = $date->modify('monday this week');
    $end = $start->modify('+7 days');
    $heading = $start->format('M j') . ' – ' .
        $end->modify('-1 day')->format('M j, Y');
} else {
    $monthStart = $date->modify('first day of this month');
    $start = $monthStart->modify('monday this week');
    $end = $date->modify('last day of this month')
        ->modify('sunday this week')->modify('+1 day');
    $heading = $date->format('F Y');
}

$branches = $pdo->query(
    "SELECT id, name FROM branches ORDER BY name"
)->fetchAll();

$sql = "
    SELECT a.id, a.reference_code, a.appointment_date,
           a.appointment_time, a.status,
           p.full_name, p.email, p.phone,
           b.name AS branch_name, b.id AS branch_id
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN branches b ON b.id = a.branch_id
    WHERE a.appointment_date >= ?
      AND a.appointment_date < ?
";

$params = [$start->format('Y-m-d'), $end->format('Y-m-d')];

if ($branchId) {
    $sql .= " AND b.id = ?";
    $params[] = $branchId;
}

$sql .= " ORDER BY a.appointment_date, a.appointment_time";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

$eventsByDate = [];
foreach ($appointments as $appointment) {
    $eventsByDate[$appointment['appointment_date']][] = $appointment;
}

$pageTitle = 'Appointment Calendar';
$activeNav = 'calendar';

include __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">

    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <section class="admin-main">

        <div class="admin-topbar">
            <div>
                <span class="breadcrumb">Workspace / <b>Calendar</b></span>
                <h1>Appointment Calendar</h1>
                <p>View and organize appointments by day, week, or month.</p>
            </div>

            <a class="button button-primary"
               href="<?= BASE_URL ?>/admin/appointments.php">
                Manage appointments →
            </a>
        </div>

        <div class="dashboard-content">
            <article class="panel calendar-panel">

                <div class="calendar-toolbar">
                    <div class="calendar-navigation">
                        <button type="button" id="previousPeriod">←</button>
                        <strong><?= e($heading) ?></strong>
                        <button type="button" id="nextPeriod">→</button>
                        <button type="button" id="todayButton">Today</button>
                    </div>

                    <div class="calendar-view-switch">
                        <?php foreach (['day', 'week', 'month'] as $option): ?>
                            <button type="button"
                                    data-view="<?= e($option) ?>"
                                    class="<?= $view === $option ? 'active' : '' ?>">
                                <?= e(ucfirst($option)) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>

                    <form method="get">
                        <input type="hidden" name="date"
                               value="<?= e($date->format('Y-m-d')) ?>">
                        <input type="hidden" name="view"
                               value="<?= e($view) ?>">

                        <select name="branch"
                                class="calendar-branch-filter"
                                aria-label="Filter branch"
                                onchange="this.form.submit()">
                            <option value="">All branches</option>
                            <?php foreach ($branches as $branch): ?>
                                <option value="<?= (int)$branch['id'] ?>"
                                    <?= $branchId === (int)$branch['id']
                                        ? 'selected' : '' ?>>
                                    <?= e($branch['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <div class="calendar-legend">
                    <span><i class="calendar-dot pending"></i> Pending</span>
                    <span><i class="calendar-dot confirmed"></i> Confirmed</span>
                    <span><i class="calendar-dot completed"></i> Completed</span>
                    <span><i class="calendar-dot cancelled"></i> Cancelled</span>
                </div>

                <?php if ($view === 'month'): ?>
                    <div class="calendar-grid">
                        <?php foreach (
                            ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
                            as $dayName
                        ): ?>
                            <div class="calendar-weekday">
                                <?= e($dayName) ?>
                            </div>
                        <?php endforeach; ?>

                        <?php
                        $today = date('Y-m-d');

                        for ($day = $start; $day < $end;
                             $day = $day->modify('+1 day')):
                            $dateKey = $day->format('Y-m-d');
                            $dayEvents = $eventsByDate[$dateKey] ?? [];
                        ?>
                            <div class="calendar-day
                                <?= $day->format('m') !== $date->format('m')
                                    ? 'muted' : '' ?>
                                <?= $dateKey === $today ? 'today' : '' ?>">

                                <div class="calendar-day-number">
                                    <span><?= $day->format('j') ?></span>
                                </div>

                                <?php foreach (array_slice($dayEvents, 0, 3) as $event): ?>
                                    <button type="button"
                                        class="calendar-event <?= e($event['status']) ?>"
                                        data-name="<?= e($event['full_name']) ?>"
                                        data-email="<?= e($event['email']) ?>"
                                        data-phone="<?= e($event['phone']) ?>"
                                        data-branch="<?= e($event['branch_name']) ?>"
                                        data-date="<?= e(date('M j, Y', strtotime($event['appointment_date']))) ?>"
                                        data-time="<?= e(date('g:i A', strtotime($event['appointment_time']))) ?>"
                                        data-reference="<?= e($event['reference_code']) ?>"
                                        data-status="<?= e($event['status']) ?>">
                                        <?= e(date('g:i A', strtotime($event['appointment_time']))) ?>
                                        · <?= e($event['full_name']) ?>
                                    </button>
                                <?php endforeach; ?>

                                <?php if (count($dayEvents) > 3): ?>
                                    <div class="calendar-more">
                                        +<?= count($dayEvents) - 3 ?> more
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>

                <?php else: ?>
                    <div class="calendar-list">
                        <?php
                        $found = false;

                        for ($day = $start; $day < $end;
                             $day = $day->modify('+1 day')):
                            $dateKey = $day->format('Y-m-d');
                            $dayEvents = $eventsByDate[$dateKey] ?? [];

                            if ($view === 'week'):
                        ?>
                            <h3 class="calendar-date-heading">
                                <?= e($day->format('l, M j')) ?>
                            </h3>
                        <?php endif; ?>

                        <?php foreach ($dayEvents as $event):
                            $found = true;
                        ?>
                            <button type="button"
                                class="calendar-list-item"
                                data-name="<?= e($event['full_name']) ?>"
                                data-email="<?= e($event['email']) ?>"
                                data-phone="<?= e($event['phone']) ?>"
                                data-branch="<?= e($event['branch_name']) ?>"
                                data-date="<?= e(date('M j, Y', strtotime($event['appointment_date']))) ?>"
                                data-time="<?= e(date('g:i A', strtotime($event['appointment_time']))) ?>"
                                data-reference="<?= e($event['reference_code']) ?>"
                                data-status="<?= e($event['status']) ?>">

                                <span class="calendar-list-time">
                                    <?= e(date('g:i A', strtotime($event['appointment_time']))) ?>
                                </span>

                                <span class="calendar-list-details">
                                    <b><?= e($event['full_name']) ?></b>
                                    <small>
                                        <?= e($event['branch_name']) ?>
                                        · <?= e($event['reference_code']) ?>
                                    </small>
                                    <span class="status status-<?= e($event['status']) ?>">
                                        <?= e(ucfirst($event['status'])) ?>
                                    </span>
                                </span>
                            </button>
                        <?php endforeach; ?>
                        <?php endfor; ?>

                        <?php if (!$found): ?>
                            <div class="calendar-empty">
                                No appointments in this period.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="calendar-details" id="appointmentDetails" hidden>
                    <h3 id="detailName"></h3>
                    <p id="detailDate"></p>
                    <p id="detailBranch"></p>
                    <p id="detailContact"></p>
                    <p id="detailReference"></p>
                    <p id="detailStatus"></p>
                </div>

            </article>
        </div>
    </section>
</div>

<script>
(() => {
    const currentDate = <?= json_encode($date->format('Y-m-d')) ?>;
    const currentView = <?= json_encode($view) ?>;
    const currentBranch = <?= json_encode($branchId ?: '') ?>;

    function makeUrl(date, view) {
        const params = new URLSearchParams({date, view});
        if (currentBranch) params.set('branch', currentBranch);
        return 'calendar.php?' + params.toString();
    }

    function parseDate(value) {
        const [year, month, day] = value.split('-').map(Number);
        return new Date(year, month - 1, day, 12);
    }

    function formatDate(date) {
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, '0'),
            String(date.getDate()).padStart(2, '0')
        ].join('-');
    }

    function moveDate(amount, unit) {
        const date = parseDate(currentDate);
        if (unit === 'month') {
            date.setDate(1);
            date.setMonth(date.getMonth() + amount);
        } else {
            date.setDate(date.getDate() + amount);
        }
        return formatDate(date);
    }

    document.getElementById('todayButton').addEventListener('click', () => {
        window.location.href = makeUrl(formatDate(new Date()), currentView);
    });

    document.getElementById('previousPeriod').addEventListener('click', () => {
        const amount = currentView === 'month' ? -1 :
            currentView === 'week' ? -7 : -1;
        window.location.href = makeUrl(moveDate(amount, currentView), currentView);
    });

    document.getElementById('nextPeriod').addEventListener('click', () => {
        const amount = currentView === 'month' ? 1 :
            currentView === 'week' ? 7 : 1;
        window.location.href = makeUrl(moveDate(amount, currentView), currentView);
    });

    document.querySelectorAll('[data-view]').forEach(button => {
        button.addEventListener('click', () => {
            window.location.href = makeUrl(currentDate, button.dataset.view);
        });
    });

    document.querySelectorAll('[data-reference]').forEach(event => {
        event.addEventListener('click', () => {
            const details = document.getElementById('appointmentDetails');

            document.getElementById('detailName').textContent = event.dataset.name;
            document.getElementById('detailDate').textContent =
                event.dataset.date + ' at ' + event.dataset.time;
            document.getElementById('detailBranch').textContent =
                'Branch: ' + event.dataset.branch;
            document.getElementById('detailContact').textContent =
                event.dataset.email + ' · ' + event.dataset.phone;
            document.getElementById('detailReference').textContent =
                'Reference: ' + event.dataset.reference;
            document.getElementById('detailStatus').textContent =
                'Status: ' + event.dataset.status;

            details.hidden = false;
            details.scrollIntoView({behavior: 'smooth', block: 'nearest'});
        });
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
