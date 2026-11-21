
<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

$pdo = db();
$allowed = ['pending', 'confirmed', 'completed', 'cancelled'];
$notice = $_GET['notice'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);

    if ($id && $action === 'update_status') {
        $status = $_POST['status'] ?? '';

        if (in_array($status, $allowed, true)) {
            $stmt = $pdo->prepare(
                'UPDATE appointments SET status = ? WHERE id = ?'
            );
            $stmt->execute([$status, $id]);

            redirect('/admin/appointments.php?notice=status_updated');
        }

        redirect('/admin/appointments.php?notice=invalid_request');
    }

    if ($id && $action === 'delete_appointment') {
        $stmt = $pdo->prepare(
            'DELETE FROM appointments WHERE id = ?'
        );
        $stmt->execute([$id]);

        redirect('/admin/appointments.php?notice=' .
            ($stmt->rowCount() > 0
                ? 'appointment_deleted'
                : 'appointment_not_found')
        );
    }

    redirect('/admin/appointments.php?notice=invalid_request');
}

$filter = $_GET['status'] ?? '';

if ($filter && !in_array($filter, $allowed, true)) {
    $filter = '';
}

$sql = "
    SELECT a.*, p.full_name, p.email, p.phone,
           b.name AS branch_name
    FROM appointments a
    JOIN patients p ON p.id = a.patient_id
    JOIN branches b ON b.id = a.branch_id
";

$params = [];

if ($filter) {
    $sql .= " WHERE a.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$pageTitle = 'Appointments';
$activeNav = 'appointments';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">

    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <section class="admin-main">

        <div class="admin-topbar">
            <div>
                <span class="breadcrumb">
                    Workspace / <b>Appointments</b>
                </span>

                <h1>Appointments</h1>

                <p>
                    Review requests and update appointment statuses.
                </p>
            </div>

            <a class="button button-primary"
               href="<?= BASE_URL ?>/index.php">
                ＋ New booking
            </a>
        </div>

        <div class="dashboard-content">

            <?php if ($notice === 'status_updated'): ?>
                <div class="alert alert-success">
                    Appointment status updated.
                </div>

            <?php elseif ($notice === 'appointment_deleted'): ?>
                <div class="alert alert-success">
                    Appointment deleted. The patient record was preserved.
                </div>

            <?php elseif ($notice === 'appointment_not_found'): ?>
                <div class="alert alert-error">
                    Appointment not found or already deleted.
                </div>

            <?php elseif ($notice === 'invalid_request'): ?>
                <div class="alert alert-error">
                    Invalid request. Please try again.
                </div>
            <?php endif; ?>

            <article class="panel recent-panel">

                <div class="panel-heading">
                    <div>
                        <h3>All appointment records</h3>
                        <p><?= count($rows) ?> result(s)</p>
                    </div>

                    <form class="period-form" method="get">
                        <label for="status">Status</label>

                        <select name="status"
                                id="status"
                                onchange="this.form.submit()">
                            <option value="">All statuses</option>

                            <?php foreach ($allowed as $s): ?>
                                <option value="<?= e($s) ?>"
                                    <?= $filter === $s ? 'selected' : '' ?>>
                                    <?= e(ucfirst($s)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Branch</th>
                                <th>Appointment</th>
                                <th>Reference</th>
                                <th>Status</th>
                                <th>Update status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (!$rows): ?>
                            <tr>
                                <td colspan="7" class="empty-table">
                                    No appointment records found.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($rows as $a): ?>
                            <tr>
                                <td>
                                    <b><?= e($a['full_name']) ?></b>
                                    <small><?= e($a['email']) ?></small>
                                    <small><?= e($a['phone']) ?></small>
                                </td>

                                <td><?= e($a['branch_name']) ?></td>

                                <td>
                                    <?= e(date(
                                        'M j, Y',
                                        strtotime($a['appointment_date'])
                                    )) ?>

                                    <small>
                                        <?= e(date(
                                            'g:i A',
                                            strtotime($a['appointment_time'])
                                        )) ?>
                                    </small>
                                </td>

                                <td class="ref-cell">
                                    <?= e($a['reference_code']) ?>
                                </td>

                                <td>
                                    <span class="status status-<?= e($a['status']) ?>">
                                        <?= e(ucfirst($a['status'])) ?>
                                    </span>
                                </td>

                                <td>
                                    <form method="post" class="status-edit">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= e(csrf_token()) ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="update_status">

                                        <input type="hidden"
                                               name="appointment_id"
                                               value="<?= (int)$a['id'] ?>">

                                        <select name="status"
                                                aria-label="Status for <?= e($a['reference_code']) ?>">
                                            <?php foreach ($allowed as $s): ?>
                                                <option value="<?= e($s) ?>"
                                                    <?= $a['status'] === $s ? 'selected' : '' ?>>
                                                    <?= e(ucfirst($s)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <button type="submit" class="mini-button">
                                            Save
                                        </button>
                                    </form>
                                </td>

                                <td>
                                    <form method="post"
                                          onsubmit="return confirm('Delete appointment <?= e($a['reference_code']) ?>? Only this appointment will be deleted. The patient record will remain. This cannot be undone.');">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= e(csrf_token()) ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="delete_appointment">

                                        <input type="hidden"
                                               name="appointment_id"
                                               value="<?= (int)$a['id'] ?>">

                                        <button type="submit"
                                                class="delete-button">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>

            </article>
        </div>
    </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
