<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Authentication / origin check: only accept requests from our own site.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && rtrim($origin, '/') !== rtrim((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? ''), '/')) {
    http_response_code(403);
    echo json_encode(['error' => 'Cross-origin requests are not allowed.']);
    exit;
}

$pdo = db();
$chatSeq = isset($_SESSION['chat_seq']) ? (int)$_SESSION['chat_seq'] : 1;
$visitorKey = hash('sha256', 'chat_visitor_' . session_id() . ':' . $chatSeq);

/**
 * Get (or lazily create) the conversation row for this visitor session.
 */
function conversation_for(PDO $pdo, string $visitorKey): int {
    $stmt = $pdo->prepare('SELECT id FROM chat_conversations WHERE visitor_key = ?');
    $stmt->execute([$visitorKey]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        return (int)$id;
    }
    $stmt = $pdo->prepare('INSERT INTO chat_conversations (visitor_key) VALUES (?)');
    $stmt->execute([$visitorKey]);
    return (int)$pdo->lastInsertId();
}

function save_message(PDO $pdo, int $conversationId, string $sender, string $message): string {
    $stmt = $pdo->prepare('INSERT INTO chat_messages (conversation_id, sender, message) VALUES (?, ?, ?)');
    $stmt->execute([$conversationId, $sender, $message]);
    $stmt = $pdo->prepare('SELECT created_at FROM chat_messages WHERE id = ?');
    $stmt->execute([(int)$pdo->lastInsertId()]);
    return (string)$stmt->fetchColumn();
}

function load_history(PDO $pdo, int $conversationId, int $limit = 30): array {
    $stmt = $pdo->prepare(
        'SELECT sender, message, created_at FROM chat_messages
         WHERE conversation_id = ? ORDER BY id DESC LIMIT ' . (int)$limit
    );
    $stmt->execute([$conversationId]);
    return array_reverse($stmt->fetchAll());
}

function system_prompt(): string {
    return <<<PROMPT
    You are "CarePoint Assistant", the friendly AI chatbot of CarePoint Clinic Adjustment Scheduler in Cebu, Philippines.

    Clinic facts:
    - Branches: "Adjustment with Main Branch" (Main Branch, Cebu) and "Adjustment with Lapu Lapu Branch" (Lapu-Lapu City, Cebu).
    - Appointments run 09:00 to 16:00 in 30-minute slots, with a lunch break at 12:00-12:30.
    - The clinic is closed on Sundays.
    - Patients book adjustments through the booking form on this website and receive a reference code.

    Rules:
    - Answer only questions about this clinic: booking appointments, branches, schedule, slot availability, reference codes, and general clinic information.
    - NEVER provide medical advice, diagnoses, treatment recommendations, or opinions about symptoms. If asked, politely advise the patient to book an appointment or consult a healthcare professional.
    - Do not ask patients for sensitive personal information (medical records, passwords, payment details). If a patient volunteers such information, remind them not to share it in chat.
    - Keep replies short (2-4 sentences), warm, and professional.
    - If a question is unrelated to the clinic, politely redirect to clinic topics.
    PROMPT;
}

/**
 * Rule-based fallback used when the Gemini API is unavailable, so the
 * chatbot stays available (Availability principle).
 */
function fallback_reply(string $message): string {
    $m = mb_strtolower($message);
    if (str_contains($m, 'hour') || str_contains($m, 'open') || str_contains($m, 'schedule') || str_contains($m, 'time')) {
        return 'We are open Monday to Saturday, 09:00 AM to 04:00 PM (lunch break 12:00-12:30), and closed on Sundays. You can pick any available 30-minute slot when booking.';
    }
    if (str_contains($m, 'branch') || str_contains($m, 'location') || str_contains($m, 'where')) {
        return 'We have two branches: the Main Branch in Cebu and the Lapu-Lapu Branch in Lapu-Lapu City. You can choose your preferred branch in the booking form.';
    }
    if (str_contains($m, 'book') || str_contains($m, 'appointment') || str_contains($m, 'reserve')) {
        return 'To book an appointment, choose a branch, date, and available time slot in the booking form on this page, then submit your details. You will receive a reference code for your appointment.';
    }
    if (str_contains($m, 'reference') || str_contains($m, 'code')) {
        return 'Your reference code is shown after you successfully book an appointment. Please keep it handy when you visit the clinic.';
    }
    return 'I am having trouble reaching our full assistant right now. For booking, please use the form on this page — or rephrase your question about our branches, schedule, or appointments, and I will try again.';
}

/**
 * Call the FreeLLMAPI gateway (OpenAI-compatible) with the conversation
 * history and return the reply text. Returns null when unavailable.
 */
function llm_reply(array $history): ?string {
    if (LLM_API_KEY === '') {
        return null;
    }

    $messages = [['role' => 'system', 'content' => system_prompt()]];
    foreach ($history as $row) {
        $messages[] = [
            'role' => $row['sender'] === 'user' ? 'user' : 'assistant',
            'content' => $row['message'],
        ];
    }

    $payload = json_encode([
        'model' => LLM_MODEL,
        'messages' => $messages,
        'temperature' => 0.4,
        'max_tokens' => 1024,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init(rtrim(LLM_BASE_URL, '/') . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . LLM_API_KEY,
        ],
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if (!is_string($response) || $status !== 200) {
        error_log("Chatbot LLM API error (HTTP {$status}): " . (is_string($response) ? substr($response, 0, 500) : 'curl failure'));
        return null;
    }

    $data = json_decode($response, true);
    $text = $data['choices'][0]['message']['content'] ?? null;
    return is_string($text) && trim($text) !== '' ? trim($text) : null;
}

// ---------------------------------------------------------------------------
// GET: return the current session's conversation history (widget restore).
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conversationId = conversation_for($pdo, $visitorKey);
    $history = array_map(static fn(array $r): array => [
        'sender' => $r['sender'],
        'message' => $r['message'],
        'created_at' => $r['created_at'],
    ], load_history($pdo, $conversationId, 100));
    echo json_encode(['conversation_id' => $conversationId, 'messages' => $history]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

// ---------------------------------------------------------------------------
// POST: { "message": "..." } -> save, generate AI reply, save, respond.
// ---------------------------------------------------------------------------
$body = json_decode(file_get_contents('php://input'), true);

// Start a fresh conversation for this visitor session.
if (is_array($body) && ($body['action'] ?? '') === 'new_chat') {
    $_SESSION['chat_seq'] = $chatSeq + 1;
    echo json_encode(['status' => 'ok']);
    exit;
}

$message = is_array($body) ? trim((string)($body['message'] ?? '')) : '';

// Integrity: validate input at the boundary.
if ($message === '' || mb_strlen($message) > 2000) {
    http_response_code(422);
    echo json_encode(['error' => 'Message must be 1-2000 characters.']);
    exit;
}

// Rate limiting (Availability: protect the service and the API quota).
$now = time();
$window = $_SESSION['chat_window'] ?? ['start' => $now, 'count' => 0];
if ($now - $window['start'] > 600) {
    $window = ['start' => $now, 'count' => 0];
}
$window['count']++;
$_SESSION['chat_window'] = $window;
if ($window['count'] > 30) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many messages. Please try again in a few minutes.']);
    exit;
}
if (($now - (int)($_SESSION['chat_last_post'] ?? 0)) < 2) {
    http_response_code(429);
    echo json_encode(['error' => 'You are sending messages too fast. Please wait a moment.']);
    exit;
}
$_SESSION['chat_last_post'] = $now;

$conversationId = conversation_for($pdo, $visitorKey);
$userCreatedAt = save_message($pdo, $conversationId, 'user', $message);

$history = load_history($pdo, $conversationId, 20);
$reply = llm_reply($history) ?? fallback_reply($message);
$botCreatedAt = save_message($pdo, $conversationId, 'bot', $reply);

echo json_encode([
    'conversation_id' => $conversationId,
    'user_message' => ['message' => $message, 'created_at' => $userCreatedAt],
    'reply' => ['message' => $reply, 'created_at' => $botCreatedAt],
]);
