<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dashboard.php');
}
csrf_verify();
$user = require_login('dashboard.php');

$pollId = (int) ($_POST['poll_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$poll   = get_poll($pollId);

if (!$poll || (int) $poll['author_id'] !== (int) $user['id']) {
    flash('error', 'That poll is not yours to change.');
    redirect('dashboard.php');
}

switch ($action) {
    case 'close':
        db()->prepare("UPDATE polls SET status = 'closed', updated_at = UTC_TIMESTAMP() WHERE id = ?")
            ->execute([$pollId]);
        log_activity((int) $user['id'], 'poll.closed', poll_code($poll) . ' · ' . $poll['title']);
        flash('success', '“' . $poll['title'] . '” is closed. The result remains public.');
        break;

    case 'reopen':
        db()->prepare("UPDATE polls SET status = 'open', closes_at = ?, updated_at = UTC_TIMESTAMP() WHERE id = ?")
            ->execute([date('Y-m-d H:i:s', time() + 7 * 86400), $pollId]);
        log_activity((int) $user['id'], 'poll.reopened', poll_code($poll) . ' · ' . $poll['title']);
        flash('success', '“' . $poll['title'] . '” is open again for 7 days.');
        break;

    case 'publish':
        db()->prepare("UPDATE polls SET status = 'open', updated_at = UTC_TIMESTAMP() WHERE id = ?")
            ->execute([$pollId]);
        flash('success', 'Poll published. The link is live.');
        break;

    case 'duplicate':
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare(
            "INSERT INTO polls (author_id, slug, code, title, question, description, category, mode, status,
                                require_signin, show_results, max_choices, minutes_open, closes_at, views)
             SELECT author_id, ?, ?, ?, question, description, category, mode, 'draft',
                    require_signin, show_results, max_choices, minutes_open, NULL, 0
               FROM polls WHERE id = ?"
        )->execute([
            unique_slug($poll['slug'] . '-copy'),
            'POLL-' . str_pad((string) (9000 + random_int(100, 999)), 4, '0', STR_PAD_LEFT),
            $poll['title'] . ' (copy)', $pollId,
        ]);
        $newId = (int) $pdo->lastInsertId();
        $pdo->prepare('INSERT INTO poll_options (poll_id, label, position) SELECT ?, label, position FROM poll_options WHERE poll_id = ?')
            ->execute([$newId, $pollId]);
        $pdo->commit();
        log_activity((int) $user['id'], 'poll.duplicated', $poll['title']);
        flash('success', 'Poll duplicated as a draft. Votes are not copied.');
        redirect('poll-edit.php?id=' . $newId);

    case 'delete':
        db()->prepare('DELETE FROM polls WHERE id = ?')->execute([$pollId]);
        log_activity((int) $user['id'], 'poll.deleted', poll_code($poll) . ' · ' . $poll['title']);
        flash('success', '“' . $poll['title'] . '” was deleted.');
        break;

    default:
        flash('error', 'Unknown action.');
}

redirect('dashboard.php');

?>
