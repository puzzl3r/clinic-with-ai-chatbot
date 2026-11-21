<?php
require_once __DIR__ . '/config/database.php';
$branchId = filter_input(INPUT_GET, 'branch', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'branch_id', FILTER_VALIDATE_INT);
$stmt = db()->prepare("SELECT * FROM branches WHERE id = ? AND active = 1");
$stmt->execute([$branchId]);
$branch = $stmt->fetch();
if (!$branch) redirect('/index.php');

$errors = [];
$success = false;
$reference = '';
$selectedDate = $_POST['appointment_date'] ?? date('Y-m-d', strtotime('+1 day'));
$selectedTime = $_POST['appointment_time'] ?? '';
$name = trim($_POST['patient_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$notes = trim($_POST['notes'] ?? '');

function available_slots(int $branchId, string $date): array {
    $slots = [];
    $start = strtotime('09:00');
    $end = strtotime('16:00');
    for ($time = $start; $time <= $end; $time += 1800) {
        if (date('H:i', $time) === '12:00' || date('H:i', $time) === '12:30') continue;
        $slots[] = date('H:i', $time);
    }
    if ($date < date('Y-m-d') || date('N', strtotime($date)) == 7) return [];
    $q = db()->prepare("SELECT appointment_time FROM appointments WHERE branch_id = ? AND appointment_date = ? AND status IN ('pending','confirmed')");
    $q->execute([$branchId, $date]);
    $taken = array_map(static fn($r) => substr((string)$r['appointment_time'], 0, 5), $q->fetchAll());
    return array_values(array_diff($slots, $taken));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $selectedDate = $_POST['appointment_date'] ?? '';
    $selectedTime = $_POST['appointment_time'] ?? '';
    if (mb_strlen($name) < 2) $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) $errors[] = 'Please enter a valid phone number.';
    $dateObj = DateTime::createFromFormat('Y-m-d', $selectedDate);
    if (!$dateObj || $dateObj->format('Y-m-d') !== $selectedDate || $selectedDate < date('Y-m-d')) $errors[] = 'Please choose a valid future date.';
    if ($dateObj && $dateObj->format('N') == 7) $errors[] = 'The clinic is closed on Sundays. Please choose another date.';
    if (!in_array($selectedTime, available_slots((int)$branch['id'], $selectedDate), true)) $errors[] = 'That time is no longer available. Please select another slot.';
    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $patientStmt = $pdo->prepare("INSERT INTO patients (full_name,email,phone) VALUES (?,?,?) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), phone=VALUES(phone)");
            $patientStmt->execute([$name,$email,$phone]);
            $patientId = (int)$pdo->lastInsertId();
            if ($patientId === 0) {
                $findPatient = $pdo->prepare("SELECT id FROM patients WHERE email = ?");
                $findPatient->execute([$email]); $patientId = (int)$findPatient->fetchColumn();
            }
            $reference = 'CP-' . strtoupper(bin2hex(random_bytes(3)));
            $insert = $pdo->prepare("INSERT INTO appointments (reference_code,patient_id,branch_id,appointment_date,appointment_time,status,notes) VALUES (?,?,?,?,?,'pending',?)");
            $insert->execute([$reference,$patientId,$branch['id'],$selectedDate,$selectedTime,$notes]);
            $pdo->commit(); $success = true;
        } catch (PDOException $ex) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = 'We could not complete the booking. That slot may have just been taken. Please try another time.';
        }
    }
}
$slots = available_slots((int)$branch['id'], $selectedDate);
$pageTitle = 'Book ' . $branch['name'];
include __DIR__ . '/includes/header.php';
?>
<section class="booking-page wrap">
  <a class="back-link" href="<?= BASE_URL ?>/index.php">← Back to branches</a>
  <?php if ($success): ?>
    <div class="success-panel">
      <div class="success-check">✓</div><div class="eyebrow">BOOKING RECEIVED</div><h1>You’re on your way to better care.</h1>
      <p>Your appointment request has been saved. Keep your reference code for checking with the clinic.</p>
      <div class="reference-box"><span>YOUR BOOKING REFERENCE</span><strong><?= e($reference) ?></strong><button class="copy-button" type="button" data-copy="<?= e($reference) ?>">Copy</button></div>
      <div class="confirmation-details"><div><span>Branch</span><strong><?= e($branch['name']) ?></strong></div><div><span>Date</span><strong><?= e(date('F j, Y', strtotime($selectedDate))) ?></strong></div><div><span>Time</span><strong><?= e(date('g:i A', strtotime($selectedTime))) ?></strong></div><div><span>Status</span><strong class="status status-pending">Pending confirmation</strong></div></div>
      <a class="button button-primary" href="<?= BASE_URL ?>/index.php">Return to home <span>→</span></a>
    </div>
  <?php else: ?>
  <div class="booking-layout">
    <section class="booking-form-panel">
      <div class="eyebrow">APPOINTMENT BOOKING</div><h1>Book your adjustment</h1><p class="muted">Complete the steps below to request your appointment.</p>
      <?php if ($errors): ?><div class="alert alert-error"><strong>Please check the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      <form method="post" class="appointment-form" id="bookingForm">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="branch_id" value="<?= (int)$branch['id'] ?>">
        <div class="form-step-title"><span>1</span><div><h2>Choose date & time</h2><p>Select an available appointment slot.</p></div></div>
        <label class="field-label" for="appointment_date">Appointment date</label>
        <input class="field" type="date" id="appointment_date" name="appointment_date" min="<?= date('Y-m-d') ?>" value="<?= e($selectedDate) ?>" required>
        <label class="field-label">Available times <small>(30-minute appointments)</small></label>
        <div class="time-grid" id="timeGrid">
          <?php if (!$slots): ?><p class="empty-slots">No available times for this date. Please select another day.</p><?php endif; ?>
          <?php foreach ($slots as $slot): ?><label class="time-option"><input type="radio" name="appointment_time" value="<?= e($slot) ?>" <?= $selectedTime === $slot ? 'checked' : '' ?> required><span><?= e(date('g:i A', strtotime($slot))) ?></span></label><?php endforeach; ?>
        </div>
        <div class="form-step-title form-step-spaced"><span>2</span><div><h2>Your details</h2><p>We’ll use these details for your appointment record.</p></div></div>
        <label class="field-label" for="patient_name">Full name</label><input class="field" id="patient_name" name="patient_name" placeholder="Enter your full name" value="<?= e($name) ?>" autocomplete="name" required>
        <div class="form-two-col"><div><label class="field-label" for="email">Email address</label><input class="field" type="email" id="email" name="email" placeholder="you@example.com" value="<?= e($email) ?>" autocomplete="email" required></div><div><label class="field-label" for="phone">Phone number</label><input class="field" type="tel" id="phone" name="phone" placeholder="+63 9XX XXX XXXX" value="<?= e($phone) ?>" autocomplete="tel" required></div></div>
        <label class="field-label" for="notes">Notes <span class="optional">(optional)</span></label><textarea class="field" id="notes" name="notes" rows="3" placeholder="Anything the clinic should know?"><?= e($notes) ?></textarea>
        <label class="consent-row"><input type="checkbox" required><span>I confirm that the information above is correct.</span></label>
        <button class="button button-primary button-wide" type="submit">Request appointment <span>→</span></button>
        <p class="form-footnote">Your appointment will be marked as pending until clinic staff confirms it.</p>
      </form>
    </section>
    <aside class="booking-summary">
      <div class="summary-label">YOUR SELECTION</div><div class="summary-icon">⌖</div><h3><?= e($branch['name']) ?></h3><p><?= e($branch['address']) ?></p>
      <div class="summary-divider"></div><div class="summary-line"><span>Appointment type</span><strong>Adjustment</strong></div><div class="summary-line"><span>Duration</span><strong>30 minutes</strong></div><div class="summary-line"><span>Clinic hours</span><strong>9:00 AM – 4:00 PM</strong></div>
      <div class="summary-help"><span>i</span><p>Clinic hours are Monday to Saturday. Sundays are unavailable.</p></div>
    </aside>
  </div>
  <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
