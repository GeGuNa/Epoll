<?php
?>
      </div>
    </div>

    <aside class="on-dark relative bg-ink text-paper lg:col-span-7">
      <div class="flex h-full min-h-[520px] flex-col justify-between p-8 lg:p-12">
        <div>
          <p class="label flex items-center gap-2 text-voltage">
            <span class="live-dot" aria-hidden="true"></span>Ballot / Live
          </p>
          <h2 class="display mt-8 text-[32px] font-bold leading-[0.94] lg:text-[72px]">
            <?= e($authHeading) ?>
          </h2>
        </div>

        <svg class="tally-matrix mt-12 w-full" height="140" viewBox="0 0 600 140" preserveAspectRatio="none" aria-hidden="true">
          <?php for ($x = 0; $x <= 600; $x += 30): ?>
            <line x1="<?= $x ?>" y1="0" x2="<?= $x ?>" y2="140"></line>
          <?php endfor; ?>
          <?php for ($y = 0; $y <= 140; $y += 28): ?>
            <line x1="0" y1="<?= $y ?>" x2="600" y2="<?= $y ?>"></line>
          <?php endfor; ?>
          <line class="live" x1="0" y1="98" x2="420" y2="98"></line>
          <line class="live" x1="0" y1="42" x2="240" y2="42"></line>
        </svg>

        <dl class="mt-12 grid grid-cols-2 gap-8 sm:grid-cols-4">
          <?php foreach ([['2.8M', 'votes counted'], ['184', 'countries'], ['5 sec', 'result refresh'], ['99.99%', 'uptime']] as [$n, $l]): ?>
            <div>
              <dt class="metric-num text-[26px] text-paper"><?= e($n) ?></dt>
              <dd class="label mt-2 text-paper/60"><?= e($l) ?></dd>
            </div>
          <?php endforeach; ?>
        </dl>
      </div>
    </aside>

  </div>
</section>
