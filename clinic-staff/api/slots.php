<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');
$branchId = filter_input(INPUT_GET, 'branch', FILTER_VALIDATE_INT);
$date = $_GET['date'] ?? '';
if (!$branchId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400); echo json_encode(['error' => 'Invalid branch or date']); exit;
}
$q = db()->prepare('SELECT id FROM branches WHERE id = ? AND active = 1');
$q->execute([$branchId]);
if (!$q->fetch()) { http_response_code(404); echo json_encode(['error' => 'Branch not found']); exit; }
$slots = [];
if ($date >= date('Y-m-d') && date('N', strtotime($date)) != 7) {
    for ($t = strtotime('09:00'); $t <= strtotime('16:00'); $t += 1800) {
        $slot = date('H:i:s', $t);
        if (in_array(substr($slot,0,5), ['12:00','12:30'], true)) continue;
        $slots[] = $slot;
    }
    $q = db()->prepare("SELECT appointment_time FROM appointments WHERE branch_id=? AND appointment_date=? AND status IN ('pending','confirmed')");
    $q->execute([$branchId,$date]);
    $taken = array_map(fn($r) => substr($r['appointment_time'],0,5), $q->fetchAll());
    $slots = array_values(array_filter($slots, fn($s) => !in_array(substr($s,0,5), $taken, true)));
}
echo json_encode(['slots' => $slots]);
