<?php)
require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$pollId = (int) ($_GET['poll_id'] ?? 0);
$poll   = $pollId > 0 ? get_poll($pollId) : null;

if (!$poll) {
    http_response_code(404);
    echo json_encode(['error' => 'Poll not found']);
    exit;
}

$user = current_user();
if (poll_state($poll) === 'draft' && !($user && (int) $poll['author_id'] === (int) $user['id'])) {
    http_response_code(404);
    echo json_encode(['error' => 'Poll not found']);
    exit;
}

echo json_encode(poll_results($pollId), JSON_UNESCAPED_UNICODE);

?>
