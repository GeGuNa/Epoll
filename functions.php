<?php



if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}


function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}


function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function nf(int|string $n): string
{
    return number_format((int) $n);
}

function poll_code(array $poll): string
{
    return 'POLL-' . str_pad((string) $poll['id'], 4, '0', STR_PAD_LEFT);
}


function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flash(): array
{
    $all = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $all;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $sent = (string) ($_POST['csrf'] ?? '');
    if ($sent === '' || !hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Your form token has expired. Go back, refresh the page and try again.');
    }
}


function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $st = db()->prepare(
				'SELECT id, name, email, avatar_url, provider,notify_results, notify_closing, notify_news, created_at
				FROM users WHERE id = ? LIMIT 1'
            );
            $st->execute([(int) $_SESSION['user_id']]);
            $row = $st->fetch();
            if ($row) {
                $user = $row;
            } else {
                unset($_SESSION['user_id']);
            }
        }
    }
    return $user;
}

function require_login(string $next = ''): array
{
    $user = current_user();
    if (!$user) {
        flash('warning', 'Sign in to continue.');
        redirect('signin.php' . ($next !== '' ? '?next=' . urlencode($next) : ''));
    }
    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function log_activity(int $userId, string $action, string $meta = ''): void
{
    $st = db()->prepare('INSERT INTO activity_log (user_id, action, meta) VALUES (?, ?, ?)');
    $st->execute([$userId, $action, mb_substr($meta, 0, 250)]);
}


function poll_state(array $poll): string
{
    if ($poll['status'] === 'draft') {
        return 'draft';
    }
    if ($poll['status'] === 'closed') {
        return 'closed';
    }
    if (!empty($poll['closes_at']) && strtotime($poll['closes_at']) <= time()) {
        return 'closed';
    }
    return 'open';
}

function state_badge(string $state): string
{
    $map = [
        'open'   => ['Open',    'bg-voltage text-ink'],
        'closed' => ['Closed',  'bg-surface-muted text-ink'],
        'draft'  => ['Draft',   'border border-ink text-ink'],
    ];
    [$label, $class] = $map[$state] ?? $map['draft'];
    return '<span class="label inline-flex items-center rounded-full px-2.5 py-1 leading-none ' . $class . '">'
        . e($label) . '</span>';
}

function time_ago(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return '—';
    }
    $s = time() - $ts;
    if ($s < 5) {
        return 'just now';
    }
    if ($s < 60) {
        return $s . ' sec ago';
    }
    if ($s < 3600) {
        return intdiv($s, 60) . ' min ago';
    }
    if ($s < 86400) {
        return intdiv($s, 3600) . ' hr ago';
    }
    if ($s < 604800) {
        return intdiv($s, 86400) . ' days ago';
    }
    return date('M j, Y', $ts);
}

function countdown_label(?string $closesAt): string
{
    if (!$closesAt) {
        return 'NO CLOSING TIME';
    }
    $left = strtotime($closesAt) - time();
    if ($left <= 0) {
        return 'CLOSED';
    }
    $h = intdiv($left, 3600);
    $m = intdiv($left % 3600, 60);
    return sprintf('%02d:%02d REMAINING', $h, $m);
}

function get_poll_by_slug(string $slug): ?array
{
    $st = db()->prepare('SELECT * FROM polls WHERE slug = ? LIMIT 1');
    $st->execute([$slug]);
    return $st->fetch() ?: null;
}

function get_poll(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM polls WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

function poll_options(int $pollId): array
{
    $st = db()->prepare('SELECT * FROM poll_options WHERE poll_id = ? ORDER BY position, id');
    $st->execute([$pollId]);
    return $st->fetchAll();
}


function poll_results(int $pollId): array
{
    $st = db()->prepare(
        'SELECT o.id, o.label,
                COUNT(v.id) AS votes
           FROM poll_options o
           LEFT JOIN votes v ON v.option_id = o.id
          WHERE o.poll_id = ?
          GROUP BY o.id, o.label, o.position
          ORDER BY o.position, o.id'
    );
    $st->execute([$pollId]);
    $rows = $st->fetchAll();

    $total = 0;
    $max = 0;
    foreach ($rows as $row) {
        $total += (int) $row['votes'];
        $max = max($max, (int) $row['votes']);
    }

    $options = [];
    foreach ($rows as $row) {
        $votes = (int) $row['votes'];
        $options[] = [
            'id'       => (int) $row['id'],
            'label'    => (string) $row['label'],
            'votes'    => $votes,
            'percent'  => $total > 0 ? round($votes * 100 / $total, 1) : 0.0,
            'leading'  => $total > 0 && $max > 0 && $votes === $max,
        ];
    }

    $st = db()->prepare('SELECT MAX(created_at) AS last_vote FROM votes WHERE poll_id = ?');
    $st->execute([$pollId]);
    $last = $st->fetch();

    return [
        'poll_id'      => $pollId,
        'total'        => $total,
        'options'      => $options,
        'last_vote_at' => $last['last_vote'] ?? null,
        'updated_at'   => gmdate('c'),
    ];
}

function has_voted(int $pollId, ?int $userId, ?string $voterHash): bool
{
    $sql = 'SELECT COUNT(*) AS n FROM votes WHERE poll_id = ? AND ';
    $sql .= $userId ? 'user_id = ?' : 'voter_hash = ?';
    $st = db()->prepare($sql);
    $st->execute([$pollId, $userId ?? $voterHash]);
    return (int) $st->fetch()['n'] > 0;
}

function viewer_voter_hash(): ?string
{
    return isset($_COOKIE['bl_voter']) && preg_match('/^[a-f0-9]{32}$/', $_COOKIE['bl_voter'])
        ? $_COOKIE['bl_voter']
        : null;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? mb_substr($text, 0, 60) : 'poll';
}

function unique_slug(string $base): string
{
    $slug = $base;
    $i = 1;
    while (get_poll_by_slug($slug) !== null) {
        $slug = $base . '-' . (++$i);
    }
    return $slug;
}


function dashboard_stats(int $userId): array
{
    $st = db()->prepare(
        "SELECT
            COUNT(*) AS polls,
            SUM(status = 'open') AS open_polls,
            SUM(status = 'draft') AS draft_polls,
            SUM(status = 'closed') AS closed_polls,
            SUM(views) AS views
           FROM polls WHERE author_id = ?"
    );
    $st->execute([$userId]);
    $row = $st->fetch();

    $st = db()->prepare(
        'SELECT COUNT(*) AS n FROM votes v
           JOIN polls p ON p.id = v.poll_id
          WHERE p.author_id = ?'
    );
    $st->execute([$userId]);
    $votes = (int) $st->fetch()['n'];

    $st = db()->prepare(
        "SELECT COUNT(*) AS n FROM polls
          WHERE author_id = ? AND status = 'open'
            AND (closes_at IS NULL OR closes_at > UTC_TIMESTAMP())"
    );
    $st->execute([$userId]);
    $live = (int) $st->fetch()['n'];

    $views = (int) ($row['views'] ?? 0);

    return [
        'polls'         => (int) ($row['polls'] ?? 0),
        'open_polls'    => (int) ($row['open_polls'] ?? 0),
        'draft_polls'   => (int) ($row['draft_polls'] ?? 0),
        'closed_polls'  => (int) ($row['closed_polls'] ?? 0),
        'votes'         => $votes,
        'live'          => $live,
        'response_rate' => $views > 0 ? min(100, (int) round($votes * 100 / $views)) : 0,
    ];
}


function icon(string $name, string $class = 'h-5 w-5'): string
{
    $paths = [
        'plus'      => '<path d="M10 4v12M4 10h12"/>',
        'minus'     => '<path d="M4 10h12"/>',
        'check'     => '<path d="M4 10.5l4 4 8-9"/>',
        'edit'      => '<path d="M4 16h3.5l8-8-3.5-3.5-8 8V16z"/><path d="M11.5 5.5L14.5 8.5"/>',
        'trash'     => '<path d="M4 6h12M8 6V4h4v2M6 6l1 10h6l1-10"/>',
        'copy'      => '<rect x="7" y="7" width="9" height="9"/><path d="M13 4H4v9"/>',
        'settings'  => '<circle cx="10" cy="10" r="2.5"/><path d="M10 2.5v2M10 15.5v2M2.5 10h2M15.5 10h2M4.7 4.7l1.4 1.4M13.9 13.9l1.4 1.4M15.3 4.7l-1.4 1.4M6.1 13.9l-1.4 1.4"/>',
        'chart'     => '<path d="M3 16V8M8 16V4M13 16v-6M18 16H2"/>',
        'clock'     => '<circle cx="10" cy="10" r="7"/><path d="M10 6v4l3 2"/>',
        'lock'      => '<rect x="4" y="9" width="12" height="8"/><path d="M7 9V6.5a3 3 0 016 0V9"/>',
        'mail'      => '<rect x="3" y="5" width="14" height="10"/><path d="M3 6l7 5 7-5"/>',
        'arrow'     => '<path d="M3 10h13M11 5l5 5-5 5"/>',
        'menu'      => '<path d="M3 7h14M3 13h14"/>',
        'close'     => '<path d="M5 5l10 10M15 5L5 15"/>',
        'up'        => '<path d="M10 16V5M5 10l5-5 5 5"/>',
        'down'      => '<path d="M10 4v11M5 10l5 5 5-5"/>',
        'eye'       => '<path d="M2 10s3-5 8-5 8 5 8 5-3 5-8 5-8-5-8-5z"/><circle cx="10" cy="10" r="2"/>',
        'user'      => '<circle cx="10" cy="7" r="3"/><path d="M4 17c0-3 2.7-5 6-5s6 2 6 5"/>',
        'link'      => '<path d="M8 12a3 3 0 004 0l2-2a3 3 0 10-4-4l-1 1"/><path d="M12 8a3 3 0 00-4 0l-2 2a3 3 0 104 4l1-1"/>',
        'warning'   => '<path d="M10 3l7 13H3l7-13z"/><path d="M10 8v3M10 13.5v.5"/>',
    ];
    $body = $paths[$name] ?? '';
    return '<svg class="' . e($class) . '" viewBox="0 0 20 20" fill="none" stroke="currentColor" '
        . 'stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $body . '</svg>';
}


function render_results_panel(array $poll, array $results, array $opts = []): void
{
    $compact  = (bool) ($opts['compact'] ?? false);
    $title    = (string) ($opts['title'] ?? 'LIVE RESULTS');
    $showMeta = (bool) ($opts['showMeta'] ?? true);
    $totalLabel = nf($results['total']) . ' votes';
    include __DIR__ . '/includes/results-panel.php';
}

function render_toasts(): void
{
    $flashes = pull_flash();
    if (!$flashes) {
        return;
    }
    echo '<div class="pointer-events-none fixed bottom-6 right-6 z-50 flex w-[320px] flex-col gap-2">';
    foreach ($flashes as $f) {
        $border = in_array($f['type'], ['success'], true) ? 'border-success'
            : (in_array($f['type'], ['error', 'warning'], true) ? 'border-danger' : 'border-ink');
        echo '<div data-toast class="pointer-events-auto border ' . $border . ' border-l-4 bg-paper p-4 text-[14px] shadow-none">'
            . '<p>' . e($f['message']) . '</p></div>';
    }
    echo '</div>';
}
