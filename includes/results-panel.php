<?php
$isDark = true;
?>
<section class="on-dark bg-ink text-paper chamber-enter" data-live-results
         data-poll-id="<?= (int) $poll['id'] ?>" data-endpoint="api/results.php"
         aria-label="Live poll results">
  <div class="p-6 lg:p-8">

    <div class="flex items-start justify-between gap-4">
      <div>
        <p class="label flex items-center gap-2 text-voltage">
          <span class="live-dot" data-live-dot aria-hidden="true"></span><?= e($title) ?>
        </p>
        <p class="mono-label mt-2 text-paper/60"><?= e(poll_code($poll)) ?> · <?= e(strtoupper($poll['category'])) ?></p>
      </div>
      <div class="text-right">
        <p class="metric-num text-[32px]" data-total-value="<?= (int) $results['total'] ?>">
          <span data-total-text><?= e(nf($results['total'])) ?></span>
        </p>
        <p class="label text-paper/60">votes</p>
      </div>
    </div>

    <div class="mt-6 space-y-5">
      <?php foreach ($results['options'] as $i => $opt): ?>
        <div data-option-id="<?= (int) $opt['id'] ?>" data-row>
          <div class="flex items-baseline justify-between gap-4">
            <p class="text-[16px] font-semibold leading-tight"><?= e($opt['label']) ?></p>
            <p class="display text-[18px] font-bold leading-none">
              <span data-pct-text><?= e(number_format((float) $opt['percent'])) ?>%</span>
            </p>
          </div>
          <div class="bar-track mt-2">
            <div class="bar-fill <?= $opt['leading'] ? 'leading' : '' ?>" data-fill
                 style="--pct: <?= e(number_format((float) $opt['percent'], 1)) ?>%; width: <?= e(number_format((float) $opt['percent'], 1)) ?>%"></div>
          </div>
          <div class="mt-2 flex items-center justify-between">
            <p class="mono text-paper/50"><span data-count-text><?= e(nf($opt['votes'])) ?></span> votes
              <span class="sr-only">, <?= e(number_format((float) $opt['percent'], 1)) ?> percent</span>
            </p>
            <span class="mono-label text-voltage <?= $opt['leading'] ? '' : 'invisible' ?>" data-leading-flag>Leading</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($showMeta): ?>
      <div class="mt-8 h-[1px] w-full bg-line-dark"></div>
      <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
        <p class="mono text-paper/50" data-updated-text>
          Last updated <?= e(time_ago($results['last_vote_at'])) ?>
        </p>
        <p class="mono text-paper/50"><?= e(countdown_label($poll['closes_at'])) ?></p>
      </div>
    <?php endif; ?>

    <p class="sr-only" aria-live="polite" data-live-region></p>
  </div>
</section>
