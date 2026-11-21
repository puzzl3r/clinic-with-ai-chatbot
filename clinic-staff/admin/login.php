
<?php
require_once __DIR__ . '/../config/database.php';

// Redirect to the dashboard if already logged in.
if (is_admin()) {
    redirect('/admin/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Use the actual admin_users table in clinic_scheduler.
    $stmt = db()->prepare(
        'SELECT id, username, password_hash
         FROM admin_users
         WHERE username = ?
         LIMIT 1'
    );

    $stmt->execute([$username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);

        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_name'] = $admin['username'];

        redirect('/admin/index.php');
    }

    $error = 'Incorrect username or password.';
}

$pageTitle = 'Staff Login';

include __DIR__ . '/../includes/header.php';
?>

<section class="login-wrap">
    <div class="login-card">

        <div class="login-emblem">+</div>

        <div class="eyebrow">STAFF ACCESS</div>

        <h1>Welcome back</h1>

        <p class="muted">
            Sign in to manage clinic appointments and view analytics.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e(csrf_token()) ?>"
            >

            <label class="field-label" for="username">
                Username
            </label>

            <input
                class="field"
                id="username"
                name="username"
                type="text"
                autocomplete="username"
                required
            >

            <label class="field-label" for="password">
                Password
            </label>

            <input
                class="field"
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
            >

            <button
                class="button button-primary button-wide"
                type="submit"
            >
                Sign in <span>→</span>
            </button>
        </form>

        <div class="demo-login">
            <strong>Demo login</strong>
            <span>Username: admin</span>
            <span>Password: password</span>

            <small>
                Change the default password before any real deployment.
            </small>
        </div>

        <a
            class="back-link centered"
            href="<?= BASE_URL ?>/index.php"
        >
            ← Return to booking page
        </a>

    </div>
</section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
