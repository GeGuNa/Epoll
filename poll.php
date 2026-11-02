<?php
// poll.php — public poll page with voting + live results
require __DIR__ . '/config.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$poll = $slug !== '' ? get_poll_by_slug($slug) : null;

if (!$poll) {
    http_response_code(404);
    $pageTitle = 'Poll not found';
    include __DIR__ . '/includes/header.php';
    echo '<section class="mx-auto max-w-[720px] px-6 py-32"><h1 class="serif-q text-[32px] leading-[1.1]">This poll link is not valid.</h1>';
    echo '<a class="btn btn-primary mt-8" href="index.php">Back to polls</a></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$user    = current_user();
$state   = poll_state($poll);
$isOwner = $user && (int) $poll['author_id'] === (int) $user['id'];

if ($state === 'draft' && !$isOwner) {
    http_response_code(404);
    $pageTitle = 'Poll not found';
    include __DIR__ . '/includes/header.php';
    echo '<section class="mx-auto max-w-[720px] px-6 py-32"><h1 class="serif-q text-[32px] leading-[1.1]">This poll is not published yet.</h1></section>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

db()->prepare('UPDATE polls SET views = views + 1 WHERE id = ?')->execute([(int) $poll['id']]);

$hash   = viewer_voter_hash();
$voted  = $user ? has_voted((int) $poll['id'], (int) $user['id'], null) : has_voted((int) $poll['id'], null, $hash);
$opts   = poll_options((int) $poll['id']);
$res    = poll_results((int) $poll['id']);
$canVote = $state === 'open' && !$voted;
$needsLogin = $canVote && $poll['require_signin'] && !$user;

$pageTitle = $poll['title'];
include __DIR__ . '/includes/header.php';
?>

<section class="mx-auto max-w-[1440px] px-6 py-12 lg:px-8 lg:py-16">
  <div class="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-6">

    <div class="lg:col-span-7">
      <div class="border border-line-light bg-paper p-6 lg:p-10">
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
          <p class="mono text-ink-muted"><?= e(poll_code($poll)) ?></p>
          <p class="label text-ink-muted"><?= e(ucfirst($poll['category'])) ?></p>
          <?= state_badge($state) ?>
          <p class="mono text-ink-muted" <?= $state === 'open' ? 'data-countdown="' . (int) strtotime($poll['closes_at']) . '"' : '' ?>>
            <?= $state === 'open' ? e(countdown_label($poll['closes_at'])) : 'CLOSED ' . e(date('M j, Y', strtotime($poll['closes_at'] ?? 'now'))) ?>
          </p>
        </div>

        <h1 class="serif-q mt-6 text-[32px] font-medium leading-[1.05] lg:text-[48px]"><?= e($poll['question']) ?></h1>

        <?php if ($poll['description']): ?>
          <p class="mt-5 max-w-[620px] text-[16px] leading-[1.55] text-ink-muted"><?= nl2br(e($poll['description'])) ?></p>
        <?php endif; ?>

        <?php if ($state === 'closed'): ?>
          <div class="mt-8 border-l-4 border-ink border border-line-light p-5">
            <p class="label">This poll is closed.</p>
            <p class="mt-2 text-[15px] text-ink-muted">The result remains public.</p>
          </div>
        <?php endif; ?>

        <?php if ($canVote || $needsLogin): ?>
          <?php if ($needsLogin): ?>
            <div class="mt-8 border border-line-light bg-canvas p-5">
              <p class="label flex items-center gap-2"><?= icon('lock', 'h-5 w-5') ?> Sign in required</p>
              <p class="mt-2 text-[15px] text-ink-muted">This poll allows one vote per account.</p>
              <a class="btn btn-primary mt-4" href="signin.php?next=poll.php">Sign in to vote</a>
            </div>
          <?php else: ?>
            <form method="post" action="api/vote.php" class="mt-8" data-vote-form
                  data-min="1" data-max="<?= $poll['mode'] === 'multiple' ? (int) $poll['max_choices'] : 1 ?>" data-vote-scope>
              <?= csrf_field() ?>
              <input type="hidden" name="poll_id" value="<?= (int) $poll['id'] ?>">
              <input type="hidden" name="back" value="poll.php?slug=<?= e($poll['slug']) ?>">

              <fieldset>
                <legend class="sr-only"><?= e($poll['question']) ?></legend>
                <div class="space-y-3">
                  <?php foreach ($opts as $opt): ?>
                    <label class="answer-row">
                      <input class="sr-only"
                             type="<?= $poll['mode'] === 'multiple' ? 'checkbox' : 'radio' ?>"
                             name="options[]" value="<?= (int) $opt['id'] ?>">
                      <span class="answer-box <?= $poll['mode'] === 'multiple' ? '' : 'round' ?>" aria-hidden="true">
                        <svg viewBox="0 0 20 20" class="h-3.5 w-3.5" fill="none" stroke="#151515" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
                      </span>
                      <span class="text-[16px] font-semibold leading-tight"><?= e($opt['label']) ?></span>
                    </label>
                  <?php endforeach; ?>
                </div>
              </fieldset>

              <p class="field-error-text mt-3 hidden" data-vote-error></p>

              <button type="submit" class="btn btn-voltage mt-6">Submit vote</button>
              <p class="mt-3 text-[13px] text-ink-muted">
                <?= $poll['mode'] === 'multiple'
                    ? 'Choose up to ' . (int) $poll['max_choices'] . ' answers.'
                    : 'Choose one answer. Your vote is recorded once.' ?>
              </p>
            </form>
          <?php endif; ?>
        <?php else: ?>
          <div class="mt-8 border-l-4 border-success border border-line-light p-5">
            <p class="label flex items-center gap-2 text-success"><?= icon('check', 'h-5 w-5') ?> Your vote has been recorded.</p>
            <p class="mt-2 text-[15px] text-ink-muted">You have already voted in this poll.</p>
            <p class="mt-4 text-[13px] text-ink-muted" data-after-vote>Results update live on the right.</p>
          </div>
        <?php endif; ?>

        <!-- meta table -->
        <dl class="mt-10 grid grid-cols-2 gap-px border border-line-light bg-line-light sm:grid-cols-4">
          <?php foreach ([
              'Votes'    => nf($res['total']),
              'Mode'     => $poll['mode'] === 'multiple' ? 'Multiple' : 'Single',
              'Closes'   => $poll['closes_at'] ? date('M j, Y', strtotime($poll['closes_at'])) : 'No closing time',
              'Status'   => ucfirst($state),
          ] as $k => $v): ?>
            <div class="bg-paper p-4">
              <dt class="label text-ink-muted"><?= e($k) ?></dt>
              <dd class="mt-2 text-[15px] font-semibold"><?= e($v) ?></dd>
            </div>
          <?php endforeach; ?>
        </dl>

        <div class="mt-8 flex flex-wrap items-center gap-3">
          <button type="button" class="btn btn-secondary" data-copy="<?= e(APP_URL . '/poll.php?slug=' . $poll['slug']) ?>">
            <?= icon('link', 'h-5 w-5') ?> Copy link
          </button>
          <?php if ($isOwner): ?>
            <a class="btn btn-secondary" href="poll-edit.php?id=<?= (int) $poll['id'] ?>"><?= icon('edit', 'h-5 w-5') ?> Edit poll</a>
            <a class="btn btn-secondary" href="dashboard.php">Your polls</a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="lg:col-span-5">
      <?php if (!$poll['show_results'] && !$voted && $state !== 'closed'): ?>
        <div class="border border-line-light bg-paper p-8">
          <p class="label text-ink-muted">Results</p>
          <p class="serif-q mt-4 text-[24px] leading-[1.25]">Results are hidden until you vote.</p>
          <p class="mt-4 text-[15px] text-ink-muted">The organiser keeps this tally private until a vote comes in.</p>
        </div>
      <?php else: ?>
        <?php render_results_panel($poll, $res, ['title' => 'Live results']); ?>
        <p class="mono mt-3 text-ink-muted">RESULTS UPDATE EVERY 5 SECONDS · ALL TIMESTAMPS UTC</p>
      <?php endif; ?>

      <?php if ($isOwner && $state === 'open'): ?>
        <form method="post" action="poll-action.php" class="mt-6">
          <?= csrf_field() ?>
          <input type="hidden" name="poll_id" value="<?= (int) $poll['id'] ?>">
          <input type="hidden" name="action" value="close">
          <button type="submit" class="btn btn-secondary btn-block">Close poll</button>
        </form>
      <?php endif; ?>
    </div>

  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
