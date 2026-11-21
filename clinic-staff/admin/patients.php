
<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

$pdo = db();
$q = trim($_GET['q'] ?? '');
$notice = $_GET['notice'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $patientId = filter_input(
        INPUT_POST,
        'patient_id',
        FILTER_VALIDATE_INT
    );

    if ($action === 'delete_patient' && $patientId) {
        $check = $pdo->prepare(
            'SELECT COUNT(*) FROM appointments WHERE patient_id = ?'
        );
        $check->execute([$patientId]);

        $appointmentCount = (int)$check->fetchColumn();

        if ($appointmentCount > 0) {
            redirect(
                '/admin/patients.php?notice=patient_has_appointments'
                . ($q !== '' ? '&q=' . urlencode($q) : '')
            );
        }

        $delete = $pdo->prepare("
            DELETE FROM patients
            WHERE id = ?
              AND NOT EXISTS (
                  SELECT 1
                  FROM appointments
                  WHERE appointments.patient_id = patients.id
              )
        ");

        $delete->execute([$patientId]);

        redirect(
            '/admin/patients.php?notice='
            . ($delete->rowCount() > 0
                ? 'patient_deleted'
                : 'patient_not_found')
            . ($q !== '' ? '&q=' . urlencode($q) : '')
        );
    }

    redirect(
        '/admin/patients.php?notice=invalid_request'
        . ($q !== '' ? '&q=' . urlencode($q) : '')
    );
}

$sql = "
    SELECT
        p.id,
        p.full_name,
        p.email,
        p.phone,
        p.created_at,
        COUNT(a.id) AS appointments,
        MAX(a.appointment_date) AS last_visit
    FROM patients p
    LEFT JOIN appointments a ON a.patient_id = p.id
";

$params = [];

if ($q !== '') {
    $sql .= "
        WHERE p.full_name LIKE ?
           OR p.email LIKE ?
           OR p.phone LIKE ?
    ";

    $like = '%' . $q . '%';
    $params = [$like, $like, $like];
}

$sql .= " GROUP BY p.id ORDER BY p.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

$pageTitle = 'Patients';
$activeNav = 'patients';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">

    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <section class="admin-main">

        <div class="admin-topbar">
            <div>
                <span class="breadcrumb">
                    Workspace / <b>Patients</b>
                </span>

                <h1>Patients</h1>

                <p>
                    Search patient contact details and appointment history.
                </p>
            </div>
        </div>

        <div class="dashboard-content">

            <?php if ($notice === 'patient_deleted'): ?>
                <div class="alert alert-success">
                    Patient record deleted successfully.
                </div>

            <?php elseif ($notice === 'patient_has_appointments'): ?>
                <div class="alert alert-error">
                    This patient has appointment history and cannot be
                    deleted. Delete the patient's appointment records first
                    if removal is necessary.
                </div>

            <?php elseif ($notice === 'patient_not_found'): ?>
                <div class="alert alert-error">
                    Patient not found or already deleted.
                </div>

            <?php elseif ($notice === 'invalid_request'): ?>
                <div class="alert alert-error">
                    Invalid request. Please try again.
                </div>
            <?php endif; ?>

            <article class="panel recent-panel">

                <div class="panel-heading">
                    <div>
                        <h3>Patient directory</h3>
                        <p><?= count($patients) ?> patient record(s)</p>
                    </div>

                    <form class="search-form" method="get">
                        <input
                            type="text"
                            name="q"
                            value="<?= e($q) ?>"
                            placeholder="Search name, email, phone"
                            aria-label="Search patient name, email, or phone">

                        <button class="mini-button" type="submit">
                            Search
                        </button>
                    </form>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Phone</th>
                                <th>Appointments</th>
                                <th>Last appointment</th>
                                <th>Patient since</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (!$patients): ?>
                            <tr>
                                <td colspan="6" class="empty-table">
                                    No patient records found.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($patients as $p): ?>
                            <tr>
                                <td>
                                    <b><?= e($p['full_name']) ?></b>
                                    <small><?= e($p['email']) ?></small>
                                </td>

                                <td><?= e($p['phone']) ?></td>

                                <td>
                                    <span class="patient-count">
                                        <?= (int)$p['appointments'] ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($p['last_visit']): ?>
                                        <?= e(date(
                                            'M j, Y',
                                            strtotime($p['last_visit'])
                                        )) ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= e(date(
                                        'M j, Y',
                                        strtotime($p['created_at'])
                                    )) ?>
                                </td>

                                <td>
                                    <form method="post"
                                          onsubmit="return confirm('Delete the patient record for <?= e($p['full_name']) ?>? Patients with appointment history cannot be deleted. This action cannot be undone.');">

                                        <input type="hidden"
                                               name="csrf_token"
                                               value="<?= e(csrf_token()) ?>">

                                        <input type="hidden"
                                               name="action"
                                               value="delete_patient">

                                        <input type="hidden"
                                               name="patient_id"
                                               value="<?= (int)$p['id'] ?>">

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
