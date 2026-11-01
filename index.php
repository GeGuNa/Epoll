<?php
require __DIR__ . '/config.php';

$pageTitle = 'Live polls with results you can watch';
$navActive = 'Polls';

$st = db()->query("SELECT * FROM polls WHERE status = 'open' ORDER BY id DESC LIMIT 6");
$openPolls = $st->fetchAll();

$featured = null;
foreach ($openPolls as $p) {
    if ($p['category'] === 'community') { $featured = $p; break; }
}
$featured = $featured ?: ($openPolls[0] ?? null);

$featuredResults = $featured ? poll_results((int) $featured['id']) : null;
if ($featured) {
    db()->prepare('UPDATE polls SET views = views + 1 WHERE id = ?')->execute([(int) $featured['id']]);
}

$st = db()->query('SELECT COUNT(*) AS n FROM votes');
$totalVotes = (int) $st->fetch()['n'];
$st = db()->query('SELECT COUNT(*) AS n FROM polls');
$totalPolls = (int) $st->fetch()['n'];

include __DIR__ . '/includes/header.php';

$heroOptions = $featured ? array_slice(poll_options((int) $featured['id']), 0, 3) : [];
$viewerHash  = viewer_voter_hash();
$alreadyVoted = $featured && current_user()
    ? has_voted((int) $featured['id'], (int) current_user()['id'], null)
    : ($featured ? has_voted((int) $featured['id'], null, $viewerHash) : false);
?>

<?php if ($featured): ?>

<section class="relative overflow-hidden">
  <div class="mx-auto max-w-[1440px] px-6 pb-24 pt-12 lg:px-8 lg:pb-32 lg:pt-16">
    <div class="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:gap-6">

      <!-- question sheet -->
      <div class="reveal lg:col-span-6 lg:col-start-2">
        <div class="border border-line-light bg-paper p-6 lg:p-8">
          <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
            <p class="label flex items-center gap-2 text-ink">
              <span class="live-dot" aria-hidden="true"></span>Live poll
            </p>
            <p class="mono text-ink-muted" data-countdown="<?= (int) strtotime($featured['closes_at']) ?>">
              <?= e(countdown_label($featured['closes_at'])) ?>
            </p>
            <p class="mono text-ink-muted"><?= e(poll_code($featured)) ?></p>
          </div>

          <h1 class="serif-q mt-6 text-[32px] font-medium leading-[1.05] sm:text-[40px] lg:text-[48px]">
            <?= e($featured['question']) ?>
          </h1>
          <p class="mt-5 max-w-[520px] text-[16px] leading-[1.55] text-ink-muted">
            Vote once. See what everyone else thinks. Results update as the room answers.
          </p>

          <form method="post" action="api/vote.php" class="mt-8" data-vote-form data-min="1" data-max="1" data-vote-scope>
            <?= csrf_field() ?>
            <input type="hidden" name="poll_id" value="<?= (int) $featured['id'] ?>">
            <input type="hidden" name="back" value="index.php">

            <fieldset <?= $alreadyVoted ? 'disabled' : '' ?>>
              <legend class="sr-only"><?= e($featured['question']) ?></legend>
              <div class="space-y-3">
                <?php foreach ($heroOptions as $opt): ?>
                  <label class="answer-row">
                    <input class="sr-only" type="radio" name="options[]" value="<?= (int) $opt['id'] ?>">
                    <span class="answer-box round" aria-hidden="true">
                      <svg viewBox="0 0 20 20" class="h-3.5 w-3.5" fill="none" stroke="#151515" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M4 10.5l4 4 8-9"/></svg>
                    </span>
                    <span class="text-[16px] font-semibold leading-tight"><?= e($opt['label']) ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </fieldset>

            <p class="field-error-text mt-3 hidden" data-vote-error></p>

            <div class="mt-6 flex flex-wrap items-center gap-3">
              <?php if ($alreadyVoted): ?>
                <p class="label flex items-center gap-2 text-success">
                  <?= icon('check', 'h-5 w-5') ?> Your vote has been recorded.
                </p>
              <?php else: ?>
                <button type="submit" class="btn btn-voltage">Submit vote</button>
              <?php endif; ?>
              <a class="btn btn-secondary" href="poll.php?slug=<?= e($featured['slug']) ?>">See all answers</a>
            </div>

            <p class="mt-4 text-[13px] text-ink-muted" data-after-vote hidden>
              Your vote has been recorded. <a class="font-semibold underline" href="poll.php?slug=<?= e($featured['slug']) ?>">See all answers</a>
            </p>
          </form>
        </div>
      </div>

 
      <div class="relative z-10 lg:col-span-5 lg:col-start-8 lg:-mt-12">
        <?php render_results_panel($featured, $featuredResults, ['title' => 'Live results']); ?>
        <div class="mt-3 flex items-center justify-between">
          <p class="mono text-ink-muted">RESULTS UPDATE EVERY 5 SECONDS</p>
          <a href="poll.php?slug=<?= e($featured['slug']) ?>" class="label flex items-center gap-2 hover:text-ink-muted">
            Open poll <?= icon('arrow', 'h-4 w-4') ?>
          </a>
        </div>
      </div>

    </div>
  </div>
</section>

<section class="border-y border-line-light bg-paper">
  <div class="mx-auto grid max-w-[1440px] grid-cols-2 gap-y-10 px-6 py-14 lg:grid-cols-4 lg:px-8">
    <?php
    $metrics = [
        ['Votes counted', nf($totalVotes) . '+', 'Across every public poll on the platform'],
        ['Active polls', nf($totalPolls), 'Open right now and accepting votes'],
        ['Average response time', '4 min 18 sec', 'From first vote to a stable leading answer'],
        ['Countries represented', '184', 'Votes come in from every time zone'],
    ];
    foreach ($metrics as $i => [$label, $value, $note]): ?>
      <div class="reveal border-l border-line-light pl-6">
        <p class="label text-ink-muted"><?= e($label) ?></p>
        <p class="metric-num mt-3 text-[26px] lg:text-[32px]"><?= e($value) ?></p>
        <p class="mt-2 max-w-[220px] text-[13px] leading-[1.5] text-ink-muted"><?= e($note) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mx-auto max-w-[1440px] px-6 py-24 lg:px-8">
  <div class="grid grid-cols-1 gap-10 lg:grid-cols-12">
    <div class="reveal lg:col-span-5">
      <h2 class="font-display text-[28px] font-bold leading-[1.15] lg:text-[40px]">
        Make a poll /<br>send a link /<br>watch the room answer.
      </h2>
    </div>
    <div class="reveal lg:col-span-5 lg:col-start-8">
      <p class="serif-q text-[24px] leading-[1.3] lg:text-[28px]">
        No complicated setup. No hidden results.
      </p>
      <p class="mt-5 text-[16px] leading-[1.55] text-ink-muted">
        Write the question, add the answers, publish. The link is short enough to post anywhere,
        and the count is public from the first vote. Editing an open poll keeps every vote already cast.
      </p>
      <a class="btn btn-primary mt-8" href="poll-edit.php">Make a poll <?= icon('arrow', 'h-5 w-5') ?></a>
    </div>
  </div>

  <div class="mt-16 grid grid-cols-1 gap-px border border-line-light bg-line-light sm:grid-cols-3">
    <?php
    $steps = [
        ['chart', 'Live results', 'Bars and counts refresh every five seconds without reloading the page.'],
        ['link', 'Shareable poll links', 'Each poll gets a short public address and a stable poll code.'],
        ['lock', 'Account required, optional', 'Set sign-in per poll when you need one vote per person.'],
    ];
    foreach ($steps as [$ic, $title, $copy]): ?>
      <div class="reveal bg-paper p-8">
        <div class="flex h-10 w-10 items-center justify-center border border-ink"><?= icon($ic, 'h-5 w-5') ?></div>
        <h3 class="mt-5 text-[18px] font-semibold"><?= e($title) ?></h3>
        <p class="mt-2 text-[15px] leading-[1.55] text-ink-muted"><?= e($copy) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section id="public-record" class="on-dark bg-ink text-paper">
  <div class="mx-auto max-w-[1440px] px-6 py-24 lg:px-8">
    <div class="flex flex-wrap items-end justify-between gap-6">
      <div class="reveal">
        <p class="label flex items-center gap-2 text-voltage">
          <span class="live-dot" aria-hidden="true"></span>Live results
        </p>
        <h2 class="mt-4 font-display text-[28px] font-bold leading-[1.15] lg:text-[40px]">The public record</h2>
      </div>
      <p class="reveal max-w-[380px] text-[15px] leading-[1.55] text-paper/70">
        Every open poll is visible here. One poll is shown in detail — the rest keep a single
        leading bar so the eye goes straight to the answer that is winning.
      </p>
    </div>

    <div class="mt-12 grid grid-cols-1 gap-px bg-line-dark lg:grid-cols-3">
      <?php foreach (array_slice($openPolls, 0, 3) as $i => $pollItem):
          $res = poll_results((int) $pollItem['id']);
          $max = 0; foreach ($res['options'] as $o) { $max = max($max, $o['votes']); }
          $detail = ($i === 0);
      ?>
        <article class="reveal bg-ink p-6 lg:p-8 <?= $detail ? 'lg:col-span-2' : '' ?>">
          <div class="flex items-center justify-between">
            <p class="mono text-paper/50"><?= e(poll_code($pollItem)) ?> · <?= e(strtoupper($pollItem['category'])) ?></p>
            <p class="mono text-paper/50"><?= e(time_ago($res['last_vote_at'])) ?></p>
          </div>

          <h3 class="serif-q mt-4 text-[22px] leading-[1.15] lg:text-[26px]"><?= e($pollItem['question']) ?></h3>

          <div class="mt-6 space-y-4">
            <?php foreach (array_slice($res['options'], 0, $detail ? 5 : 3) as $opt): ?>
              <div>
                <div class="flex items-baseline justify-between gap-4">
                  <p class="text-[14px] font-semibold"><?= e($opt['label']) ?></p>
                  <p class="display text-[16px] font-bold"><?= e(number_format((float) $opt['percent'])) ?>%</p>
                </div>
                <div class="bar-track mt-2">
                  <div class="bar-fill <?= ($detail && $opt['votes'] === $max) ? 'leading' : '' ?>"
                       style="--pct: <?= e(number_format((float) $opt['percent'], 1)) ?>%; width: <?= e(number_format((float) $opt['percent'], 1)) ?>%"></div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="mt-6 flex items-center justify-between border-t border-line-dark pt-4">
            <p class="mono text-paper/50"><?= e(nf($res['total'])) ?> votes</p>
            <a class="label flex items-center gap-2 text-voltage" href="poll.php?slug=<?= e($pollItem['slug']) ?>">
              See results <?= icon('arrow', 'h-4 w-4') ?>
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<section class="mx-auto max-w-[1440px] px-6 py-24 lg:px-8">
  <h2 class="reveal label text-ink-muted">How it works</h2>
  <div class="mt-10 space-y-px border-t border-ink">
    <?php
    $flow = [
        ['01', 'Write the question', 'Ask one thing at a time. A question you can answer in three seconds gets answered.'],
        ['02', 'Add answers', 'Two to twelve options, drag-free ordering, and a choice of single or multiple answers.'],
        ['03', 'Share the link', 'Send it out and watch the tally fill in live. Close the poll whenever the room is done.'],
    ];
    foreach ($flow as [$n, $title, $copy]): ?>
      <div class="reveal flex flex-col gap-4 border-b border-line-light py-8 md:flex-row md:items-start md:gap-12">
        <p class="mono w-12 shrink-0 text-[14px] text-ink-muted"><?= e($n) ?></p>
        <h3 class="font-display text-[22px] font-bold leading-[1.15] md:w-[320px]"><?= e($title) ?></h3>
        <p class="max-w-[520px] text-[16px] leading-[1.55] text-ink-muted"><?= e($copy) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- 6. Bottom CTA -->
<section class="on-dark bg-ink text-paper">
  <div class="mx-auto max-w-[1440px] px-6 py-24 lg:px-8">
    <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-12">
      <div class="reveal lg:col-span-6">
        <h2 class="font-display text-[32px] font-bold leading-[1.05] lg:text-[52px]">
          Your next poll can be live in under a minute.
        </h2>
        <div class="mt-8 flex flex-wrap gap-3">
          <a class="btn btn-voltage" href="poll-edit.php">Create your first poll</a>
          <a class="btn btn-secondary" href="signin.php">Sign in</a>
        </div>
      </div>

      <div class="reveal lg:col-span-5 lg:col-start-8">
        <div class="border border-paper bg-paper p-6 text-ink">
          <div class="flex items-center gap-3">
            <span class="registration-mark">
              <svg width="40" height="16" viewBox="0 0 40 16" aria-hidden="true">
                <circle cx="8" cy="8" r="7" fill="none" stroke="#151515"></circle>
                <line x1="16" y1="8" x2="40" y2="8" stroke="#151515"></line>
              </svg>
            </span>
            <p class="mono text-ink-muted">SPECIMEN RECEIPT</p>
          </div>
          <dl class="mt-6 space-y-3">
            <div class="flex items-baseline justify-between border-b border-line-light pb-3">
              <dt class="mono text-ink-muted">POLL</dt>
              <dd class="mono">POLL-0048</dd>
            </div>
            <div class="flex items-baseline justify-between border-b border-line-light pb-3">
              <dt class="mono text-ink-muted">CATEGORY</dt>
              <dd class="mono">COMMUNITY TRANSPORT</dd>
            </div>
            <div class="flex items-baseline justify-between border-b border-line-light pb-3">
              <dt class="mono text-ink-muted">VOTES</dt>
              <dd class="mono">1,284 VOTES</dd>
            </div>
            <div class="flex items-baseline justify-between">
              <dt class="mono text-ink-muted">STATUS</dt>
              <dd class="mono">OPEN · 04:32 REMAINING</dd>
            </div>
          </dl>
          <div class="mt-6 h-[2px] w-full bg-voltage"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php else: ?>
<section class="mx-auto max-w-[720px] px-6 py-32">
  <h1 class="serif-q text-[32px] leading-[1.1]">No polls yet. Make the first question count.</h1>
  <a class="btn btn-primary mt-8" href="poll-edit.php">Create poll</a>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
