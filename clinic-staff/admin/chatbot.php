<?php
require_once __DIR__ . '/../config/database.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $conversationId = filter_input(INPUT_POST, 'conversation_id', FILTER_VALIDATE_INT);

    if ($action === 'delete_conversation' && $conversationId) {
        $stmt = $pdo->prepare('DELETE FROM chat_conversations WHERE id = ?');
        $stmt->execute([$conversationId]);
        redirect('/admin/chatbot.php?notice='
            . ($stmt->rowCount() > 0 ? 'conversation_deleted' : 'conversation_not_found'));
    }

    redirect('/admin/chatbot.php?notice=invalid_request');
}

$q = trim($_GET['q'] ?? '');
$from = trim($_GET['from'] ?? '');
$to = trim($_GET['to'] ?? '');
$notice = $_GET['notice'] ?? '';
$viewId = filter_input(INPUT_GET, 'view', FILTER_VALIDATE_INT);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = '';

$transcript = null;
$transcriptMeta = null;

if ($viewId) {
    $stmt = $pdo->prepare('
        SELECT id, visitor_key, started_at, last_message_at
        FROM chat_conversations WHERE id = ?
    ');
    $stmt->execute([$viewId]);
    $transcriptMeta = $stmt->fetch();

    if ($transcriptMeta) {
        $stmt = $pdo->prepare('
            SELECT sender, message, created_at
            FROM chat_messages WHERE conversation_id = ?
            ORDER BY id ASC
        ');
        $stmt->execute([$viewId]);
        $transcript = $stmt->fetchAll();
    }
}

$sql = "
    SELECT
        c.id,
        c.visitor_key,
        c.started_at,
        c.last_message_at,
        COUNT(m.id) AS message_count,
        (SELECT m2.message FROM chat_messages m2
          WHERE m2.conversation_id = c.id AND m2.sender = 'user'
          ORDER BY m2.id DESC LIMIT 1) AS last_user_message
    FROM chat_conversations c
    LEFT JOIN chat_messages m ON m.conversation_id = c.id
";

$where = [];
$params = [];

if ($q !== '') {
    $where[] = '(c.visitor_key LIKE ? OR EXISTS (
        SELECT 1 FROM chat_messages ms
        WHERE ms.conversation_id = c.id AND ms.message LIKE ?
    ))';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($from !== '') {
    $where[] = 'c.last_message_at >= ?';
    $params[] = $from . ' 00:00:00';
}
if ($to !== '') {
    $where[] = 'c.last_message_at <= ?';
    $params[] = $to . ' 23:59:59';
}
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' GROUP BY c.id ORDER BY c.last_message_at DESC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$conversations = $stmt->fetchAll();

$pageTitle = 'AI Chatbot';
$activeNav = 'chatbot';
include __DIR__ . '/../includes/header.php';
?>

<div class="admin-shell">

    <?php include __DIR__ . '/../includes/admin_sidebar.php'; ?>

    <section class="admin-main">

        <div class="admin-topbar">
            <div>
                <span class="breadcrumb">
                    Workspace / <b>AI Chatbot</b>
                </span>

                <h1>AI Chatbot Conversations</h1>

                <p>
                    Review recorded conversations between website visitors
                    and the CarePoint AI assistant.
                </p>
            </div>
        </div>

        <div class="dashboard-content">

            <?php if ($notice === 'conversation_deleted'): ?>
                <div class="alert alert-success">
                    Conversation deleted successfully.
                </div>

            <?php elseif ($notice === 'conversation_not_found'): ?>
                <div class="alert alert-error">
                    Conversation not found or already deleted.
                </div>

            <?php elseif ($notice === 'invalid_request'): ?>
                <div class="alert alert-error">
                    Invalid request. Please try again.
                </div>
            <?php endif; ?>

        <?php if ($viewId && $transcriptMeta): ?>

            <article class="panel recent-panel">
                <div class="panel-heading">
                    <div>
                        <h3>Conversation #<?= (int)$transcriptMeta['id'] ?></h3>
                        <p>
                            Visitor <?= e(substr($transcriptMeta['visitor_key'], 0, 12)) ?>&hellip;
                            &middot; started <?= e(date('M j, Y g:i A', strtotime($transcriptMeta['started_at']))) ?>
                            &middot; <?= count($transcript) ?> message(s)
                        </p>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <form method="post"
                              onsubmit="return confirm('Delete conversation #<?= (int)$transcriptMeta['id'] ?> and all of its messages? This action cannot be undone.');">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_conversation">
                            <input type="hidden" name="conversation_id" value="<?= (int)$transcriptMeta['id'] ?>">
                            <button type="submit" class="delete-button">Delete conversation</button>
                        </form>

                        <a class="mini-button" href="<?= BASE_URL ?>/admin/chatbot.php<?= $q !== '' ? '?q=' . urlencode($q) : '' ?>">
                            &larr; Back to list
                        </a>
                    </div>
                </div>

                <div class="chat-transcript">
                    <?php foreach ($transcript as $msg): ?>
                        <div class="chat-line <?= $msg['sender'] === 'user' ? 'from-user' : 'from-bot' ?>">
                            <div class="chat-bubble">
                                <span class="chat-sender">
                                    <?= $msg['sender'] === 'user' ? 'Visitor' : 'AI Assistant' ?>
                                </span>
                                <div class="chat-text"><?= nl2br(e($msg['message'])) ?></div>
                                <time><?= e(date('M j, Y g:i A', strtotime($msg['created_at']))) ?></time>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

        <?php elseif ($viewId): ?>

            <div class="alert alert-error">Conversation not found.</div>
            <a class="mini-button" href="<?= BASE_URL ?>/admin/chatbot.php">&larr; Back to list</a>

        <?php else: ?>

            <article class="panel recent-panel">

                <div class="panel-heading">
                    <div>
                        <h3>All conversations</h3>
                        <p><?= count($conversations) ?> conversation(s)</p>
                    </div>

                    <form class="search-form chat-search-form" method="get">
                        <input
                            type="text"
                            name="q"
                            value="<?= e($q) ?>"
                            placeholder="Search message text or visitor"
                            aria-label="Search conversations">

                        <input
                            type="date"
                            name="from"
                            value="<?= e($from) ?>"
                            aria-label="From date">

                        <input
                            type="date"
                            name="to"
                            value="<?= e($to) ?>"
                            aria-label="To date">

                        <button class="mini-button" type="submit">Search</button>
                        <a class="mini-button mini-button-ghost"
                           href="<?= BASE_URL ?>/admin/chatbot.php">Reset</a>
                    </form>
                </div>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Visitor</th>
                                <th>Last user message</th>
                                <th>Messages</th>
                                <th>Started</th>
                                <th>Last activity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>

                        <?php if (!$conversations): ?>
                            <tr>
                                <td colspan="7" class="empty-table">
                                    No chatbot conversations recorded yet.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($conversations as $c): ?>
                            <tr>
                                <td><?= (int)$c['id'] ?></td>

                                <td>
                                    <small title="<?= e($c['visitor_key']) ?>">
                                        <?= e(substr($c['visitor_key'], 0, 12)) ?>&hellip;
                                    </small>
                                </td>

                                <td class="chat-preview">
                                    <?php if ($c['last_user_message'] !== null): ?>
                                        <?= e(mb_strimwidth($c['last_user_message'], 0, 80, '…')) ?>
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="patient-count">
                                        <?= (int)$c['message_count'] ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(date('M j, Y g:i A', strtotime($c['started_at']))) ?>
                                </td>

                                <td>
                                    <?= e(date('M j, Y g:i A', strtotime($c['last_message_at']))) ?>
                                </td>

                                <td>
                                    <div style="display:flex;gap:6px;align-items:center">
                                        <a class="mini-button"
                                           href="<?= BASE_URL ?>/admin/chatbot.php?view=<?= (int)$c['id'] ?>">
                                            View transcript
                                        </a>

                                        <form method="post"
                                              onsubmit="return confirm('Delete conversation #<?= (int)$c['id'] ?> and all of its messages? This action cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_conversation">
                                            <input type="hidden" name="conversation_id" value="<?= (int)$c['id'] ?>">
                                            <button type="submit" class="delete-button" aria-label="Delete conversation <?= (int)$c['id'] ?>">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>

            </article>
        <?php endif; ?>

        </div>
    </section>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
