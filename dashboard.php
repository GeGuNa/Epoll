<?php
require __DIR__ . '/config.php';

$user = require_login('dashboard.php');

$filter = (string) ($_GET['filter'] ?? 'all');
if (!in_array($filter, ['all', 'open', 'draft', 'closed'], true)) {
    $filter = 'all';
}

$sql = 'SELECT p.*, (SELECT COUNT(*) FROM votes v WHERE v.poll_id = p.id) AS vote_count FROM polls p WHERE p.author_id = ?';
$params = [(int) $user['id']];
if ($filter !== 'all') {
    $sql .= ' AND p.status = ?';
    $params[] = $filter;
}
$sql .= ' ORDER BY p.updated_at DESC';
$st = db()->prepare($sql);
$st->execute($params);
$polls = $st->fetchAll();

$stats = dashboard_stats((int) $user['id']);

$pageTitle = 'Your polls';
$navActive = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<section class="mx-auto max-w-[1440px] px-6 py-12 lg:px-8 lg:py-16">
  <div class="flex flex-wrap items-end justify-between gap-6">
    <div>
      <p class="label text-ink-muted">Dashboard</p>
      <h1 class="mt-3 font-display text-[32px] font-bold leading-[1.05] lg:text-[40px]">Your polls</h1>
      <p class="mt-3 text-[16px] text-ink-muted">Create, manage, and watch every answer arrive.</p>
    </div>
    <a class="btn btn-voltage" href="poll-edit.php"><?= icon('plus', 'h-5 w-5') ?> Create poll</a>
  </div>

  <div class="mt-10 grid grid-cols-2 gap-y-8 border-y border-ink py-8 lg:grid-cols-4">
    <?php
    $cards = [
        ['Active polls', nf($stats['open_polls']), 'accepting votes right now'],
        ['Total votes', nf($stats['votes']), 'across all of your polls'],
        ['Response rate', $stats['response_rate'] . '%', 'votes ÷ poll views'],
        ['Live now', nf($stats['live']), 'open polls still counting'],
    ];
    foreach ($cards as $i => [$label, $value, $note]): ?>
      <div class="pl-6 first:pl-0 <?= $i > 0 ? 'border-l border-line-light' : '' ?>">
        <div class="flex items-start gap-3">
          <?php if ($i === 0): ?>
            <svg width="40" height="16" viewBox="0 0 40 16" class="mt-1" aria-hidden="true">
              <circle cx="8" cy="8" r="7" fill="none" stroke="#151515"></circle>
              <line x1="16" y1="8" x2="40" y2="8" stroke="#151515"></line>
            </svg>
          <?php endif; ?>
          <div>
            <p class="label text-ink-muted"><?= e($label) ?></p>
            <p class="metric-num mt-3 text-[26px] lg:text-[32px]"><?= e($value) ?></p>
            <p class="mono mt-2 text-ink-muted"><?= e(strtoupper($note)) ?></p>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="mt-10 grid grid-cols-1 gap-10 lg:grid-cols-12">
    <div class="lg:col-span-9">
      <div class="flex flex-wrap items-center gap-2">
        <p class="label mr-2 text-ink-muted">Filter</p>
        <?php foreach (['all' => 'All', 'open' => 'Open', 'draft' => 'Drafts', 'closed' => 'Closed'] as $key => $lab): ?>
          <button type="button" data-filter="<?= e($key) ?>"
                  class="label rounded-full border px-3 py-2 leading-none <?= $filter === $key ? 'bg-ink text-paper border-ink' : 'border-ink' ?>">
            <?= e($lab) ?>
          </button>
        <?php endforeach; ?>
      </div>

      <?php if (!$polls): ?>
        <div class="mt-6 border border-line-light bg-paper p-12 text-center">
          <p class="serif-q text-[24px] leading-[1.25]">No polls yet. Make the first question count.</p>
          <a class="btn btn-primary mt-8" href="poll-edit.php">Create poll</a>
        </div>
      <?php else: ?>
        <div class="mt-6 border border-line-light bg-paper">
          <div class="hidden grid-cols-12 gap-4 border-b border-line-light px-6 py-4 md:grid">
            <p class="label col-span-5 text-ink-muted">Poll</p>
            <p class="label col-span-2 text-ink-muted">Category</p>
            <p class="label col-span-1 text-ink-muted">Status</p>
            <p class="label col-span-1 text-ink-muted">Votes</p>
            <p class="label col-span-1 text-ink-muted">Closes</p>
            <p class="label col-span-2 text-ink-muted">Actions</p>
          </div>

          <?php foreach ($polls as $poll):
              $state = poll_state($poll);
              $code  = poll_code($poll);
          ?>
            <div class="table-row grid grid-cols-1 items-center gap-4 border-b border-line-light px-6 py-5 last:border-b-0 md:grid-cols-12 md:py-0"
                 data-poll-id="<?= (int) $poll['id'] ?>"
                 data-poll-slug="<?= e($poll['slug']) ?>"
                 data-poll-code="<?= e($code) ?>"
                 data-poll-title="<?= e($poll['title']) ?>"
                 data-poll-status="<?= e(ucfirst($state)) ?>"
                 data-poll-votes="<?= e(nf($poll['vote_count'])) ?>"
                 data-state="<?= e($state) ?>"
                 tabindex="0" role="button" aria-label="Select <?= e($poll['title']) ?>">

              <div class="md:col-span-5">
                <p class="mono text-ink-muted"><?= e($code) ?></p>
                <a class="mt-1 block text-[16px] font-semibold leading-tight hover:underline"
                   href="poll.php?slug=<?= e($poll['slug']) ?>"><?= e($poll['title']) ?></a>
                <p class="mono mt-1 text-ink-muted md:hidden"><?= e(strtoupper($poll['category'])) ?> · <?= e(nf($poll['vote_count'])) ?> VOTES</p>
              </div>

              <p class="hidden text-[14px] text-ink-muted md:col-span-2 md:block"><?= e(ucfirst($poll['category'])) ?></p>
              <div class="md:col-span-1"><?= state_badge($state) ?></div>
              <p class="hidden text-[14px] font-semibold md:col-span-1 md:block"><?= e(nf($poll['vote_count'])) ?></p>
              <p class="mono hidden text-ink-muted md:col-span-1 md:block">
                <?= $poll['closes_at'] ? e(date('M j', strtotime($poll['closes_at']))) : '—' ?>
              </p>

              <div class="flex items-center gap-1 md:col-span-2">
                <a class="icon-btn" href="poll-edit.php?id=<?= (int) $poll['id'] ?>" title="Edit poll">
                  <span class="sr-only">Edit <?= e($poll['title']) ?></span><?= icon('edit') ?>
                </a>
                <a class="icon-btn" href="poll.php?slug=<?= e($poll['slug']) ?>" title="Preview poll">
                  <span class="sr-only">Preview <?= e($poll['title']) ?></span><?= icon('eye') ?>
                </a>
                <button type="button" class="icon-btn" title="Duplicate poll" data-copy="poll-action.php">
                  <span class="sr-only">Duplicate <?= e($poll['title']) ?></span><?= icon('copy') ?>
                </button>
                <button type="button" class="icon-btn icon-btn-danger" title="Delete poll"
                        data-confirm-open
                        data-confirm-poll-id="<?= (int) $poll['id'] ?>"
                        data-confirm-action="delete"
                        data-confirm-copy="Delete “<?= e($poll['title']) ?>”? This cannot be undone.">
                  <span class="sr-only">Delete <?= e($poll['title']) ?></span><?= icon('trash') ?>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>


    <aside class="lg:col-span-3">
      <div id="action-panel" class="sticky top-[96px] border border-ink bg-paper">
        <div class="border-b border-line-light p-6">
          <p class="label text-ink-muted">Selected poll</p>
          <p class="mono mt-3" data-panel="code">POLL-0048</p>
          <h2 class="mt-2 text-[18px] font-semibold leading-tight" data-panel="title"><?= e($polls[0]['title'] ?? 'No poll selected') ?></h2>
          <div class="mt-4 flex items-center gap-3">
            <span class="label text-ink-muted" data-panel="status">Open</span>
            <span class="mono text-ink-muted"><span data-panel="votes">0</span> VOTES</span>
          </div>
        </div>

        <div class="flex flex-col gap-2 p-6">
          <a class="btn btn-primary btn-block" href="poll-edit.php" data-panel-link="poll-edit.php?id=__ID__">Edit poll</a>
          <a class="btn btn-secondary btn-block" href="poll.php?slug=__SLUG__" data-panel-link="poll.php?slug=__SLUG__">Preview poll</a>
          <form method="post" action="poll-action.php" data-panel-post>
            <?= csrf_field() ?>
            <input type="hidden" name="poll_id" value="">
            <input type="hidden" name="action" value="close">
            <button type="submit" class="btn btn-secondary btn-block"><span data-post-label>Close poll</span></button>
          </form>
          <form method="post" action="poll-action.php">
            <?= csrf_field() ?>
            <input type="hidden" name="poll_id" value="<?= (int) ($polls[0]['id'] ?? 0) ?>">
            <input type="hidden" name="action" value="duplicate">
            <button type="submit" class="btn btn-secondary btn-block">Duplicate</button>
          </form>
          <button type="button" class="btn btn-danger btn-block"
                  data-confirm-open
                  data-confirm-poll-id="<?= (int) ($polls[0]['id'] ?? 0) ?>"
                  data-confirm-action="delete"
                  data-confirm-copy="Delete this poll? This cannot be undone.">
            Delete poll
          </button>
        </div>

        <div class="border-t border-line-light p-6">
          <p class="label text-ink-muted">Result visibility</p>
          <p class="mt-3 text-[14px] leading-[1.55] text-ink-muted">
            Results stay public after closing unless you turn them off while editing the poll.
          </p>
        </div>
      </div>
    </aside>
  </div>
</section>


<div id="confirm-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-6"
     style="background:rgba(21,21,21,.6)" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
  <div class="w-full max-w-[520px] border border-ink bg-paper">
    <div class="p-8">
      <div class="flex h-10 w-10 items-center justify-center border border-danger text-danger"><?= icon('warning') ?></div>
      <h2 id="confirm-title" class="mt-5 font-display text-[22px] font-bold leading-[1.15]">Delete this poll?</h2>
      <p class="mt-3 text-[15px] leading-[1.55] text-ink-muted" data-confirm-copy>
        This cannot be undone.
      </p>
    </div>
    <div class="flex flex-wrap gap-3 border-t border-line-light p-6">
      <form method="post" action="poll-action.php" class="flex gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="poll_id" value="">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn-danger">Delete</button>
      </form>
      <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
