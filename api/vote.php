<?php
require __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method Not Allowed');
}
csrf_verify();

$user      = current_user();
$wantsJson = (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json')
    || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

$pollId = (int) ($_POST['poll_id'] ?? 0);
$poll   = $pollId > 0 ? get_poll($pollId) : null;

$back = (string) ($_POST['back'] ?? 'index.php');
if (!preg_match('#^[a-z0-9\-]+\.php(\?[a-z0-9=&\-\.%]*)?$#i', $back)) {
    $back = 'index.php';
}

$fail = function (string $message, int $code = 422) use ($wantsJson, $back) {
    if ($wantsJson) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    } else {
        flash('error', $message);
        redirect($back);
    }
    exit;
};

if (!$poll) {
    $fail('This poll is no longer available.', 404);
}
if (poll_state($poll) !== 'open') {
    $fail('This poll is no longer accepting votes.', 409);
}
if ($poll['require_signin'] && !$user) {
    $fail('Sign in to vote in this poll.', 401);
}

$raw = (array) ($_POST['options'] ?? []);
$optionIds = array_values(array_unique(array_filter(array_map('intval', $raw), fn ($i) => $i > 0)));

if (!$optionIds) {
    $fail('Choose at least one answer before voting.');
}

$max = $poll['mode'] === 'multiple' ? max(1, (int) $poll['max_choices']) : 1;
if (count($optionIds) > $max) {
    $fail('Choose no more than ' . $max . ' answer' . ($max > 1 ? 's' : '') . '.');
}

$validIds = array_map(fn ($o) => (int) $o['id'], poll_options($pollId));
foreach ($optionIds as $optionId) {
    if (!in_array($optionId, $validIds, true)) {
        $fail('One of the answers is not part of this poll.');
    }
}


$userId = $user ? (int) $user['id'] : null;
$voterHash = viewer_voter_hash();
if (!$userId && !$voterHash) {
    $voterHash = bin2hex(random_bytes(16));
    setcookie('bl_voter', $voterHash, [
        'expires'  => time() + 86400 * 365,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$pdo = db();
$already = $pdo->prepare(
    $userId
        ? 'SELECT COUNT(*) AS n FROM votes WHERE poll_id = ? AND user_id = ?'
        : 'SELECT COUNT(*) AS n FROM votes WHERE poll_id = ? AND voter_hash = ?'
);
$already->execute([$pollId, $userId ?? $voterHash]);
if ((int) $already->fetch()['n'] > 0) {
    $fail('You have already voted in this poll.', 409);
}

try {
    $pdo->beginTransaction();
    $insert = $pdo->prepare(
        'INSERT INTO votes (poll_id, option_id, user_id, voter_hash, created_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP())'
    );
    foreach ($optionIds as $optionId) {
        $insert->execute([$pollId, $optionId, $userId, $userId ? null : $voterHash]);
    }
    $pdo->commit();
} catch (PDOException $ex) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($ex->getCode() === '23000') {
        $fail('You have already voted in this poll.', 409);
    }
    $fail('We could not record your vote. Try again.', 500);
}

if ($user) {
    log_activity((int) $user['id'], 'vote.recorded', poll_code($poll) . ' · ' . $poll['title']);
}

$payload = poll_results($pollId);

if ($wantsJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'voted' => true, 'message' => 'Your vote has been recorded.'] + $payload, JSON_UNESCAPED_UNICODE);
    exit;
}

flash('success', 'Your vote has been recorded.');
redirect($back);


?>
