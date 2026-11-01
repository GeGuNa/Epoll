<?php

declare(strict_types=1);

require __DIR__ . '/config.php';

if (php_sapi_name() !== 'cli' && ($_GET['run'] ?? '') !== 'yes') {
    exit("Run from the command line (php install.php) or request with ?run=yes\n");
}

$ddl = <<<'SQL'
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL, password_hash VARCHAR(255) NOT NULL, avatar_url VARCHAR(255) NULL,
  provider VARCHAR(32) NOT NULL DEFAULT 'local',
  notify_results TINYINT(1) NOT NULL DEFAULT 1, notify_closing TINYINT(1) NOT NULL DEFAULT 1,
  notify_news TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS polls (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, author_id INT UNSIGNED NOT NULL,
  slug VARCHAR(190) NOT NULL, code VARCHAR(16) NOT NULL, title VARCHAR(160) NOT NULL,
  question TEXT NOT NULL, description TEXT NULL, category VARCHAR(48) NOT NULL DEFAULT 'community',
  mode ENUM('single','multiple') NOT NULL DEFAULT 'single',
  status ENUM('draft','open','closed') NOT NULL DEFAULT 'open',
  require_signin TINYINT(1) NOT NULL DEFAULT 0, show_results TINYINT(1) NOT NULL DEFAULT 1,
  max_choices TINYINT UNSIGNED NOT NULL DEFAULT 3, minutes_open INT UNSIGNED NOT NULL DEFAULT 10080,
  closes_at DATETIME NULL, views INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_polls_slug (slug), KEY idx_polls_author (author_id), KEY idx_polls_status (status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS poll_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, poll_id INT UNSIGNED NOT NULL,
  label VARCHAR(180) NOT NULL, position TINYINT UNSIGNED NOT NULL DEFAULT 0,
  KEY idx_options_poll (poll_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS votes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, poll_id INT UNSIGNED NOT NULL,
  option_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NULL, voter_hash CHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_vote_user (poll_id, user_id), UNIQUE KEY uq_vote_hash (poll_id, voter_hash),
  KEY idx_votes_option (option_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_reset_token (token_hash)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS contacts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL, topic VARCHAR(60) NOT NULL DEFAULT 'general',
  message TEXT NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id INT UNSIGNED NOT NULL,
  action VARCHAR(64) NOT NULL, meta VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_activity_user (user_id, created_at)
) ENGINE=InnoDB;
SQL;

$pdo = db();
foreach (array_filter(array_map('trim', explode(';', $ddl))) as $statement) {
    $pdo->exec($statement);
}

foreach (['activity_log','password_resets','contacts','votes','poll_options','polls','users'] as $t) {
    $pdo->exec("DROP TABLE IF EXISTS $t");
}
foreach (array_filter(array_map('trim', explode(';', $ddl))) as $statement) {
    $pdo->exec($statement);
}
echo "Schema installed.\n";


$pdo->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)')->execute(['Ada Moreno', 'demo@gege.com', password_hash('demo1234', PASSWORD_DEFAULT)]);
$userId = (int) $pdo->lastInsertId();

$pdo->prepare('INSERT INTO activity_log (user_id, action, meta) VALUES (?, ?, ?)')->execute([$userId, 'account.created', 'Signed up for Ballot / Live']);
$pdo->prepare('INSERT INTO activity_log (user_id, action, meta) VALUES (?, ?, ?)')->execute([$userId, 'poll.published', 'POLL-0048 · Community transport']);


$polls = [
    [
        'title' => 'What should the city improve next?',
        'question' => 'What should the city improve next?',
        'description' => 'One answer per person. The count updates as the room answers, and the result stays public after the poll closes.',
        'category' => 'community', 'mode' => 'single', 'status' => 'open',
        'views' => 1720, 'minutes' => 272, 'total' => 1284, 'window' => 30,
        'options' => [
            ['Public transport', 38], ['Neighbourhood safety', 24], ['Green spaces', 18],
            ['Cycle lanes', 12], ['More affordable housing', 8],
        ],
    ],
    [
        'title' => 'Where should the new community fund go?',
        'question' => 'Where should the new community fund go?',
        'description' => 'The fund opens next quarter. Pick the single strongest priority.',
        'category' => 'city', 'mode' => 'single', 'status' => 'open',
        'views' => 1060, 'minutes' => 2880, 'total' => 800, 'window' => 48,
        'options' => [
            ['Youth programs', 34], ['Small business grants', 28],
            ['Parks and play areas', 22], ['Emergency relief', 16],
        ],
    ],
    [
        'title' => 'When should the new library open?',
        'question' => 'When should the new library open?',
        'description' => 'This poll is closed. The result remains public.',
        'category' => 'education', 'mode' => 'single', 'status' => 'closed',
        'views' => 380, 'minutes' => 1440, 'total' => 300, 'window' => 72,
        'options' => [
            ['Weekday mornings', 42], ['Weekday evenings', 23],
            ['Saturday', 26], ['Sunday', 9],
        ],
    ],
    [
        'title' => 'Which public service needs the most attention?',
        'question' => 'Which public service needs the most attention?',
        'description' => 'Results from this poll go straight to the monthly council briefing.',
        'category' => 'city', 'mode' => 'single', 'status' => 'open',
        'views' => 309, 'minutes' => 4320, 'total' => 240, 'window' => 60,
        'options' => [
            ['Waste collection', 31], ['Street lighting', 27],
            ['Road repairs', 25], ['Public Wi-Fi', 17],
        ],
    ],
    [
        'title' => 'Which format should the local newsletter take?',
        'question' => 'Which format should the local newsletter take?',
        'description' => 'Draft — not published yet.',
        'category' => 'product', 'mode' => 'single', 'status' => 'draft',
        'views' => 220, 'minutes' => 10080, 'total' => 180, 'window' => 40,
        'options' => [
            ['Email digest', 47], ['Printed sheet', 19], ['Mobile alerts', 34],
        ],
    ],
];

$code = 40;
$now = time();

foreach ($polls as $data) {
    $code++;
    $slug = slugify($data['title']);
    $closes = $data['status'] === 'open'
        ? date('Y-m-d H:i:s', $now + $data['minutes'] * 60)
        : date('Y-m-d H:i:s', $now - 3600);

    $pdo->prepare(
        'INSERT INTO polls (author_id, slug, code, title, question, description, category, mode, status,
                            require_signin, show_results, max_choices, minutes_open, closes_at, views)
         VALUES (?,?,?,?,?,?,?,?,?,0,1,3,?,?,?)'
    )->execute([
        $userId, unique_slug($slug), 'POLL-' . str_pad((string) $code, 4, '0', STR_PAD_LEFT),
        $data['title'], $data['question'], $data['description'], $data['category'],
        $data['mode'], $data['status'], $data['minutes'], $closes, $data['views'],
    ]);
    $pollId = (int) $pdo->lastInsertId();

    $optionIds = [];
    foreach ($data['options'] as $i => [$label, $share]) {
        $pdo->prepare('INSERT INTO poll_options (poll_id, label, position) VALUES (?, ?, ?)')
            ->execute([$pollId, $label, $i]);
        $optionIds[$i] = (int) $pdo->lastInsertId();
    }

    
    $ins = $pdo->prepare('INSERT INTO votes (poll_id, option_id, user_id, voter_hash, created_at) VALUES (?,?,?,?,?)');
    $window = $data['window'] * 3600;
    $start = $now - $window;
    $end = $now - 12;

    $pdo->beginTransaction();
    foreach ($data['options'] as $i => [$label, $share]) {
        $count = (int) round($data['total'] * $share / 100);
        for ($k = 0; $k < $count; $k++) {
            $ratio = $count > 1 ? $k / ($count - 1) : 1.0;
            $ts = (int) ($start + ($end - $start) * $ratio);
            $ins->execute([$pollId, $optionIds[$i], null, bin2hex(random_bytes(16)), date('Y-m-d H:i:s', $ts)]);
        }
    }
    $pdo->commit();
    echo "Seeded {$data['title']} ({$data['total']} votes)\n";
}

echo "Done. Sign in with demo@gege.com / demo1234\n";
