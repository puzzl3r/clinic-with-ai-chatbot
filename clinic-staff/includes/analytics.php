<?php
declare(strict_types=1);

const ANALYTICS_SLOTS = ['09:00','09:30','10:00','10:30','11:00','11:30','13:00','13:30','14:00','14:30','15:00','15:30'];
const ANALYTICS_SLOT_CAPACITY = 12;

function analytics_filters(array $allowedPeriods = ['7','30','90','all'], string $defaultPeriod = '30'): array {
    $pdo = db();
    $period = $_GET['period'] ?? $defaultPeriod;
    if (!in_array($period, $allowedPeriods, true)) $period = $defaultPeriod;

    $branches = $pdo->query("SELECT id, name FROM branches WHERE active = 1 ORDER BY sort_order, id")->fetchAll();
    $branchId = filter_input(INPUT_GET, 'branch', FILTER_VALIDATE_INT);
    $validBranchIds = array_map(static fn($b) => (int)$b['id'], $branches);
    if (!$branchId || !in_array((int)$branchId, $validBranchIds, true)) $branchId = null;

    $today = new DateTimeImmutable('today');
    $end = $today->modify('+1 day');
    $start = $period === 'all' ? null : $today->modify('-' . ((int)$period - 1) . ' days');

    $where = ' WHERE 1 = 1';
    $params = [];
    if ($start !== null) {
        $where .= ' AND a.appointment_date >= ? AND a.appointment_date < ?';
        $params[] = $start->format('Y-m-d');
        $params[] = $end->format('Y-m-d');
    }
    if ($branchId !== null) {
        $where .= ' AND a.branch_id = ?';
        $params[] = $branchId;
    }

    return [
        'period' => $period,
        'branchId' => $branchId,
        'branches' => $branches,
        'where' => $where,
        'params' => $params,
        'start' => $start,
        'end' => $end,
        'today' => $today,
    ];
}

function analytics_rows(PDO $pdo, string $where, array $params): array {
    $stmt = $pdo->prepare(
        "SELECT a.*, p.full_name, p.email, p.phone, b.name AS branch_name
         FROM appointments a
         JOIN patients p ON p.id = a.patient_id
         JOIN branches b ON b.id = a.branch_id" . $where .
        " ORDER BY a.appointment_date DESC, a.appointment_time DESC, a.id DESC"
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function analytics_summary(array $rows): array {
    $statuses = ['pending' => 0, 'confirmed' => 0, 'completed' => 0, 'cancelled' => 0];
    $patients = [];
    foreach ($rows as $row) {
        $statuses[$row['status']] = ($statuses[$row['status']] ?? 0) + 1;
        $patients[$row['patient_id']] = true;
    }
    $total = count($rows);
    $active = $total - $statuses['cancelled'];
    return [
        'total' => $total,
        'statuses' => $statuses,
        'active' => $active,
        'patients' => count($patients),
        'completionRate' => $total ? round($statuses['completed'] / $total * 100, 1) : 0.0,
        'cancellationRate' => $total ? round($statuses['cancelled'] / $total * 100, 1) : 0.0,
        'pendingShare' => $total ? round($statuses['pending'] / $total * 100, 1) : 0.0,
    ];
}

function analytics_daily(array $rows, ?DateTimeImmutable $start, DateTimeImmutable $end, bool $onlyActive = true): array {
    if ($start === null) {
        $dates = array_column($rows, 'appointment_date');
        $start = $dates ? new DateTimeImmutable(min($dates)) : $end->modify('-30 days');
    }
    $counts = [];
    foreach ($rows as $row) {
        if ($onlyActive && $row['status'] === 'cancelled') continue;
        $date = $row['appointment_date'];
        $counts[$date] = ($counts[$date] ?? 0) + 1;
    }
    $labels = $values = $dates = [];
    for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
        $key = $day->format('Y-m-d');
        $dates[] = $key;
        $labels[] = $day->format('M j');
        $values[] = $counts[$key] ?? 0;
    }
    return ['labels' => $labels, 'values' => $values, 'dates' => $dates];
}

function analytics_branch_stats(array $rows, array $branches): array {
    $stats = [];
    foreach ($branches as $branch) {
        $stats[(int)$branch['id']] = [
            'id' => (int)$branch['id'],
            'name' => $branch['name'],
            'bookings' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'active' => 0,
            'share' => 0.0,
        ];
    }
    foreach ($rows as $row) {
        $id = (int)$row['branch_id'];
        if (!isset($stats[$id])) continue;
        $stats[$id]['bookings']++;
        if ($row['status'] === 'completed') $stats[$id]['completed']++;
        if ($row['status'] === 'cancelled') $stats[$id]['cancelled']++;
        if ($row['status'] !== 'cancelled') $stats[$id]['active']++;
    }
    $total = count($rows);
    foreach ($stats as &$stat) {
        $stat['share'] = $total ? round($stat['bookings'] / $total * 100, 1) : 0.0;
    }
    unset($stat);
    return array_values($stats);
}

function analytics_slot_stats(array $rows): array {
    $counts = array_fill_keys(ANALYTICS_SLOTS, 0);
    foreach ($rows as $row) {
        if ($row['status'] === 'cancelled') continue;
        $slot = substr((string)$row['appointment_time'], 0, 5);
        if (isset($counts[$slot])) $counts[$slot]++;
    }
    $total = array_sum($counts);
    $slots = [];
    foreach ($counts as $time => $count) {
        $slots[] = [
            'time' => $time,
            'label' => date('g:i A', strtotime($time)),
            'count' => $count,
            'share' => $total ? round($count / $total * 100, 1) : 0.0,
        ];
    }
    return $slots;
}

function analytics_weekday_stats(array $rows): array {
    $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $counts = array_fill_keys(array_values($names), 0);
    foreach ($rows as $row) {
        if ($row['status'] === 'cancelled') continue;
        $weekday = (int)date('N', strtotime($row['appointment_date']));
        $counts[$names[$weekday]]++;
    }
    return ['labels' => array_values($names), 'values' => array_values($counts)];
}

function analytics_open_days(DateTimeImmutable $start, DateTimeImmutable $end): int {
    $days = 0;
    for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
        if ((int)$day->format('N') !== 7) $days++;
    }
    return $days;
}

function analytics_forecast(PDO $pdo, ?int $branchId, int $horizon, int $shift = 0): array {
    $today = new DateTimeImmutable('today');
    $lookbackStart = $today->modify('-56 days');
    $branchWhere = $branchId ? ' AND branch_id = ?' : '';
    $branchParams = $branchId ? [$branchId] : [];

    $stmt = $pdo->prepare(
        "SELECT appointment_date AS day, COUNT(*) AS total
         FROM appointments
         WHERE status != 'cancelled' AND appointment_date >= ? AND appointment_date < ?" . $branchWhere .
        " GROUP BY appointment_date"
    );
    $stmt->execute(array_merge([$lookbackStart->format('Y-m-d'), $today->format('Y-m-d')], $branchParams));
    $historyCounts = [];
    foreach ($stmt->fetchAll() as $row) $historyCounts[$row['day']] = (int)$row['total'];

    $weekdaySums = array_fill(1, 6, 0);
    $weekdayDays = array_fill(1, 6, 0);
    $dailyValues = [];
    $recentTotal = $previousTotal = 0;
    $recentDays = $previousDays = 0;
    $recentStart = $today->modify('-14 days');
    for ($day = $lookbackStart; $day < $today; $day = $day->modify('+1 day')) {
        $weekday = (int)$day->format('N');
        if ($weekday === 7) continue;
        $count = $historyCounts[$day->format('Y-m-d')] ?? 0;
        $weekdaySums[$weekday] += $count;
        $weekdayDays[$weekday]++;
        $dailyValues[] = $count;
        if ($day >= $recentStart) { $recentTotal += $count; $recentDays++; }
        else { $previousTotal += $count; $previousDays++; }
    }
    $openHistoryDays = count($dailyValues);
    $historyTotal = array_sum($dailyValues);
    $overallAvg = $openHistoryDays ? $historyTotal / $openHistoryDays : 0.0;
    $weekdayAvg = [];
    for ($i = 1; $i <= 6; $i++) {
        $weekdayAvg[$i] = $weekdayDays[$i] ? $weekdaySums[$i] / $weekdayDays[$i] : $overallAvg;
    }
    $recentAvg = $recentDays ? $recentTotal / $recentDays : $overallAvg;
    $previousAvg = $previousDays ? $previousTotal / $previousDays : $overallAvg;
    $trend = $previousAvg > 0 ? $recentAvg / $previousAvg : 1.0;
    $trend = max(0.85, min(1.15, $trend));
    $shift = max(-30, min(30, $shift));
    $multiplier = $trend * (1 + $shift / 100);

    $mean = $overallAvg;
    $variance = 0.0;
    foreach ($dailyValues as $value) $variance += ($value - $mean) ** 2;
    $stdDev = $openHistoryDays > 1 ? sqrt($variance / ($openHistoryDays - 1)) : 0.0;

    $horizonEnd = $today->modify('+' . $horizon . ' days');
    $bookedStmt = $pdo->prepare(
        "SELECT appointment_date AS day, COUNT(*) AS total
         FROM appointments
         WHERE status != 'cancelled' AND appointment_date >= ? AND appointment_date < ?" . $branchWhere .
        " GROUP BY appointment_date"
    );
    $bookedStmt->execute(array_merge([$today->format('Y-m-d'), $horizonEnd->format('Y-m-d')], $branchParams));
    $bookedCounts = [];
    foreach ($bookedStmt->fetchAll() as $row) $bookedCounts[$row['day']] = (int)$row['total'];

    $rows = [];
    $total = 0.0;
    $openDays = 0;
    for ($day = $today; $day < $horizonEnd; $day = $day->modify('+1 day')) {
        $weekday = (int)$day->format('N');
        $key = $day->format('Y-m-d');
        $expected = $weekday === 7 ? 0.0 : $weekdayAvg[$weekday] * $multiplier;
        $total += $expected;
        if ($weekday !== 7) $openDays++;
        $rows[] = [
            'date' => $key,
            'label' => $day->format('M j'),
            'weekday' => $day->format('D'),
            'expected' => round($expected, 1),
            'booked' => $bookedCounts[$key] ?? 0,
            'closed' => $weekday === 7,
        ];
    }
    $totalExpected = (int)round($total);
    $margin = $openHistoryDays ? 1.28 * $stdDev * sqrt(max(1, $openDays)) : max(1, $totalExpected * 0.25);
    $lower = max(0, (int)round($totalExpected - $margin));
    $upper = (int)round($totalExpected + $margin);

    $holdoutStart = $today->modify('-7 days');
    $trainStart = $today->modify('-35 days');
    $trainSums = array_fill(1, 6, 0);
    $trainDays = array_fill(1, 6, 0);
    $trainTotal = 0;
    $trainOpen = 0;
    for ($day = $trainStart; $day < $holdoutStart; $day = $day->modify('+1 day')) {
        $weekday = (int)$day->format('N');
        if ($weekday === 7) continue;
        $count = $historyCounts[$day->format('Y-m-d')] ?? 0;
        $trainSums[$weekday] += $count;
        $trainDays[$weekday]++;
        $trainTotal += $count;
        $trainOpen++;
    }
    $trainAvg = $trainOpen ? $trainTotal / $trainOpen : 0.0;
    $backtestExpected = 0.0;
    $backtestActual = 0;
    for ($day = $holdoutStart; $day < $today; $day = $day->modify('+1 day')) {
        $weekday = (int)$day->format('N');
        if ($weekday === 7) continue;
        $avg = $trainDays[$weekday] ? $trainSums[$weekday] / $trainDays[$weekday] : $trainAvg;
        $backtestExpected += $avg;
        $backtestActual += $historyCounts[$day->format('Y-m-d')] ?? 0;
    }
    $fit = $backtestActual > 0 ? max(0.0, round(100 - abs($backtestExpected - $backtestActual) / $backtestActual * 100, 1)) : null;

    $historyDaily = analytics_daily_from_counts($historyCounts, $today->modify('-28 days'), $today);

    $branchStmt = $pdo->prepare(
        "SELECT b.id, b.name, COUNT(a.id) AS total
         FROM branches b
         LEFT JOIN appointments a ON a.branch_id = b.id AND a.status != 'cancelled' AND a.appointment_date >= ? AND a.appointment_date < ?
         WHERE b.active = 1" . ($branchId ? ' AND b.id = ?' : '') .
        " GROUP BY b.id, b.name ORDER BY b.sort_order, b.id"
    );
    $branchStmt->execute(array_merge([$lookbackStart->format('Y-m-d'), $today->format('Y-m-d')], $branchParams));
    $branchRows = $branchStmt->fetchAll();
    $branchTotal = array_sum(array_map(static fn($r) => (int)$r['total'], $branchRows));
    $branchSplit = [];
    $allocated = 0;
    foreach ($branchRows as $i => $branch) {
        $share = $branchTotal ? (int)$branch['total'] / $branchTotal : 1 / max(1, count($branchRows));
        $expected = $i === count($branchRows) - 1 ? $totalExpected - $allocated : (int)round($totalExpected * $share);
        $allocated += $expected;
        $branchSplit[] = ['name' => $branch['name'], 'expected' => max(0, $expected), 'share' => round($share * 100, 1)];
    }

    $peakWeekday = array_keys($weekdayAvg, max($weekdayAvg))[0] ?? 1;
    $weekdayNames = [1=>'Monday','2'=>'Tuesday','3'=>'Wednesday','4'=>'Thursday','5'=>'Friday','6'=>'Saturday'];

    return [
        'horizon' => $horizon,
        'shift' => $shift,
        'total' => $totalExpected,
        'avgOpenDay' => $openDays ? round($totalExpected / $openDays, 1) : 0.0,
        'lower' => $lower,
        'upper' => $upper,
        'fit' => $fit,
        'backtestActual' => $backtestActual,
        'backtestExpected' => (int)round($backtestExpected),
        'trend' => round(($trend - 1) * 100, 1),
        'rows' => $rows,
        'history' => $historyDaily,
        'branchSplit' => $branchSplit,
        'openDays' => $openDays,
        'historyOpenDays' => $openHistoryDays,
        'historyTotal' => $historyTotal,
        'lookbackStart' => $lookbackStart->format('Y-m-d'),
        'lookbackEnd' => $today->modify('-1 day')->format('Y-m-d'),
        'peakWeekday' => $weekdayNames[$peakWeekday] ?? 'Monday',
        'hasHistory' => $historyTotal > 0,
        'stdDev' => round($stdDev, 2),
    ];
}

function analytics_daily_from_counts(array $counts, DateTimeImmutable $start, DateTimeImmutable $end): array {
    $labels = $values = $dates = [];
    for ($day = $start; $day < $end; $day = $day->modify('+1 day')) {
        $key = $day->format('Y-m-d');
        $dates[] = $key;
        $labels[] = $day->format('M j');
        $values[] = $counts[$key] ?? 0;
    }
    return ['labels' => $labels, 'values' => $values, 'dates' => $dates];
}

function analytics_capacity(float $forecastTotal, int $branchCount, int $horizon, int $slotsPerDay = ANALYTICS_SLOT_CAPACITY, int $coverage = 100): array {
    $today = new DateTimeImmutable('today');
    $openDays = analytics_open_days($today, $today->modify('+' . $horizon . ' days'));
    $branchCount = max(1, $branchCount);
    $slotsPerDay = max(1, $slotsPerDay);
    $coverage = max(10, min(100, $coverage));
    $capacity = (int)round($branchCount * $slotsPerDay * $openDays * $coverage / 100);
    $util = $capacity ? round($forecastTotal / $capacity * 100, 1) : 0.0;
    $requiredSlots = $openDays ? (int)ceil($forecastTotal / ($branchCount * $openDays * $coverage / 100)) : 0;
    return [
        'openDays' => $openDays,
        'capacity' => $capacity,
        'util' => $util,
        'headroom' => (int)round($capacity - $forecastTotal),
        'requiredSlots' => $requiredSlots,
        'slotsPerDay' => $slotsPerDay,
        'coverage' => $coverage,
    ];
}

function analytics_recommendations(array $context): array {
    $summary = $context['summary'];
    $forecast = $context['forecast'];
    $capacity = $context['capacity'];
    $peakSlot = $context['peakSlot'];
    $branchStats = $context['branchStats'];
    $pending = $summary['statuses']['pending'];
    $recommendations = [];

    if ($pending > 5) {
        $recommendations[] = [
            'id' => 'pending-queue',
            'priority' => 'high',
            'title' => 'Clear the pending confirmation queue',
            'metric' => $pending . ' pending',
            'evidence' => $pending . ' appointment requests are still waiting for a staff decision.',
            'detail' => 'Unconfirmed requests leave patients uncertain and can hide real demand. Review the oldest requests first, confirm available slots, and contact patients whose requested time is no longer open.',
            'steps' => ['Open the pending appointment list', 'Confirm or reschedule the oldest requests', 'Record the outcome in the appointment status'],
            'link' => BASE_URL . '/admin/appointments.php?status=pending',
            'linkLabel' => 'Review pending',
        ];
    }
    if ($context['oldPending'] > 0) {
        $recommendations[] = [
            'id' => 'aging-pending',
            'priority' => 'high',
            'title' => 'Follow up aging requests',
            'metric' => $context['oldPending'] . ' over 7 days old',
            'evidence' => $context['oldPending'] . ' pending requests have appointment dates more than 7 days away or were created over 7 days ago.',
            'detail' => 'Aging pending records are easy to overlook. Assign one staff member to contact these patients and either confirm, reschedule, or cancel the request with a clear note.',
            'steps' => ['Filter the pending queue', 'Contact each patient', 'Update the status and notes'],
            'link' => BASE_URL . '/admin/appointments.php?status=pending',
            'linkLabel' => 'Open queue',
        ];
    }
    if ($summary['cancellationRate'] >= 10) {
        $recommendations[] = [
            'id' => 'cancellation-rate',
            'priority' => 'medium',
            'title' => 'Reduce avoidable cancellations',
            'metric' => $summary['cancellationRate'] . '% cancelled',
            'evidence' => $summary['statuses']['cancelled'] . ' of ' . $summary['total'] . ' bookings in this period were cancelled.',
            'detail' => 'A cancellation rate above 10% can indicate reminder gaps or mismatched slot expectations. Add a confirmation reminder before the visit and review cancellation notes for recurring causes.',
            'steps' => ['Review cancelled appointment notes', 'Send reminders before upcoming visits', 'Track the cancellation rate weekly'],
            'link' => BASE_URL . '/admin/appointments.php?status=cancelled',
            'linkLabel' => 'Review cancellations',
        ];
    }
    if ($peakSlot['count'] > 0 && $peakSlot['share'] >= 15) {
        $recommendations[] = [
            'id' => 'peak-coverage',
            'priority' => 'medium',
            'title' => 'Protect coverage at ' . $peakSlot['label'],
            'metric' => $peakSlot['share'] . '% of demand',
            'evidence' => $peakSlot['label'] . ' is the busiest recorded slot with ' . $peakSlot['count'] . ' non-cancelled bookings.',
            'detail' => 'Concentrated demand can create front-desk and practitioner bottlenecks. Keep staff coverage available at this time and offer nearby quieter slots when patients are flexible.',
            'steps' => ['Check the calendar for the peak slot', 'Assign coverage before the visit', 'Offer adjacent slots when appropriate'],
            'link' => BASE_URL . '/admin/calendar.php',
            'linkLabel' => 'Open calendar',
        ];
    }
    $leadingShare = $branchStats ? max(array_column($branchStats, 'share')) : 0;
    if (count($branchStats) > 1 && $leadingShare >= 65) {
        $leading = $branchStats[0];
        foreach ($branchStats as $branch) if ($branch['share'] > $leading['share']) $leading = $branch;
        $recommendations[] = [
            'id' => 'branch-balance',
            'priority' => 'medium',
            'title' => 'Balance demand across branches',
            'metric' => $leadingShare . '% at ' . $leading['name'],
            'evidence' => $leading['name'] . ' handles ' . $leadingShare . '% of bookings in the selected period.',
            'detail' => 'One branch is carrying most of the demand. When patients are flexible, present the quieter branch as an option and review whether staffing should follow the booking mix.',
            'steps' => ['Compare branch calendars', 'Offer the quieter branch when suitable', 'Review staffing allocation weekly'],
            'link' => BASE_URL . '/admin/descriptive.php',
            'linkLabel' => 'Compare branches',
        ];
    }
    if ($capacity['util'] >= 85) {
        $recommendations[] = [
            'id' => 'capacity-high',
            'priority' => 'high',
            'title' => 'Plan additional approved capacity',
            'metric' => $capacity['util'] . '% projected use',
            'evidence' => 'Forecast demand uses ' . $capacity['util'] . '% of the modeled slot capacity over the next ' . $forecast['horizon'] . ' days.',
            'detail' => 'Projected demand is close to the available capacity. Consider opening additional approved slots or adjusting staff coverage before the schedule fills. Do not change clinic hours without approval.',
            'steps' => ['Review the capacity planner', 'Confirm staff availability', 'Open only approved additional slots'],
            'link' => BASE_URL . '/admin/prescriptive.php#capacity-planner',
            'linkLabel' => 'Open planner',
        ];
    } elseif ($capacity['util'] >= 70) {
        $recommendations[] = [
            'id' => 'capacity-watch',
            'priority' => 'medium',
            'title' => 'Watch the filling schedule',
            'metric' => $capacity['util'] . '% projected use',
            'evidence' => 'Forecast demand uses ' . $capacity['util'] . '% of modeled capacity over the next ' . $forecast['horizon'] . ' days.',
            'detail' => 'Demand is manageable but leaving limited headroom. Monitor new bookings daily and keep a short list of flexible patients who could move to quieter slots.',
            'steps' => ['Check new bookings each morning', 'Keep quieter slots visible', 'Escalate if projected use passes 85%'],
            'link' => BASE_URL . '/admin/calendar.php',
            'linkLabel' => 'View schedule',
        ];
    }
    if (count($recommendations) < 3) {
        $recommendations[] = [
            'id' => 'weekly-review',
            'priority' => 'low',
            'title' => 'Keep a weekly analytics review',
            'metric' => 'Current volume manageable',
            'evidence' => 'No urgent rule threshold is breached in the selected data window.',
            'detail' => 'Stable periods are the right time to build a review habit. Compare descriptive results, check the forecast, and confirm that the pending queue stays small.',
            'steps' => ['Review descriptive trends weekly', 'Check the next forecast horizon', 'Confirm pending requests promptly'],
            'link' => BASE_URL . '/admin/descriptive.php',
            'linkLabel' => 'Review trends',
        ];
    }

    $order = ['high' => 0, 'medium' => 1, 'low' => 2];
    usort($recommendations, static fn($a, $b) => $order[$a['priority']] <=> $order[$b['priority']]);
    return $recommendations;
}

function analytics_action_states(): array {
    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    return $_SESSION['analytics_actions'][$adminId] ?? [];
}

function analytics_set_action_state(string $id, string $state): void {
    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    if (!isset($_SESSION['analytics_actions'][$adminId])) $_SESSION['analytics_actions'][$adminId] = [];
    $_SESSION['analytics_actions'][$adminId][$id] = ['state' => $state, 'updated' => time()];
}

function analytics_csv_cell($value): string {
    $text = (string)$value;
    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $text;
    }
    return $text;
}

function analytics_csv_download(string $filename, array $headers, array $rows): never {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, array_map('analytics_csv_cell', $headers));
    foreach ($rows as $row) fputcsv($out, array_map('analytics_csv_cell', $row));
    fclose($out);
    exit;
}

function analytics_url(string $page, array $params = []): string {
    $query = http_build_query(array_filter($params, static fn($v) => $v !== null && $v !== ''));
    return BASE_URL . '/admin/' . $page . ($query ? '?' . $query : '');
}

function analytics_json(array $data): string {
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
}

function analytics_period_label(string $period): string {
    return $period === 'all' ? 'All time' : 'Last ' . $period . ' days';
}

function analytics_branch_label(?int $branchId, array $branches): string {
    if ($branchId === null) return 'All branches';
    foreach ($branches as $branch) if ((int)$branch['id'] === $branchId) return $branch['name'];
    return 'All branches';
}