<?php
require __DIR__ . '/config.php';

$pageTitle = 'About us';
$navActive = 'About us';
include __DIR__ . '/includes/header.php';
?>

<section class="mx-auto max-w-[1440px] px-6 py-16 lg:px-8 lg:py-24">
  <div class="grid grid-cols-1 gap-12 lg:grid-cols-12">
    <div class="lg:col-span-6">
      <p class="label text-ink-muted">About Ballot / Live</p>
      <h1 class="serif-q mt-6 text-[32px] font-medium leading-[1.05] lg:text-[48px]">
        We make it easier to ask, answer, and understand.
      </h1>
    </div>
    <div class="lg:col-span-5 lg:col-start-8">
      <p class="text-[16px] leading-[1.55] text-ink-muted">
        Ballot / Live is built for people who need a clear answer from a real group. Our job is simple:
        make the question visible, make the count honest, and keep the result available after the moment passes.
      </p>
      <p class="mt-5 text-[16px] leading-[1.55] text-ink-muted">
        Results update live, but accountability stays visible. Every poll keeps its own record — who asked,
        when it opened, when it closed, and how many people answered.
      </p>
    </div>
  </div>
</section>

<section class="border-y border-line-light bg-paper">
  <div class="mx-auto grid max-w-[1440px] grid-cols-1 gap-y-10 px-6 py-14 sm:grid-cols-3 lg:px-8">
    <?php foreach ([['2.8M', 'votes counted'], ['184', 'countries represented'], ['99.99%', 'platform uptime']] as [$n, $l]): ?>
      <div class="reveal border-l border-line-light pl-6">
        <p class="metric-num text-[26px] lg:text-[32px]"><?= e($n) ?></p>
        <p class="label mt-3 text-ink-muted"><?= e($l) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="mx-auto max-w-[1440px] px-6 py-24 lg:px-8">
  <div class="grid grid-cols-1 gap-12 lg:grid-cols-12">
    <div class="reveal lg:col-span-4">
      <h2 class="font-display text-[28px] font-bold leading-[1.15]">Built in the open</h2>
      <p class="mt-4 text-[15px] leading-[1.55] text-ink-muted">
        A short history of how the platform got here.
      </p>
    </div>
    <ol class="lg:col-span-8">
      <?php foreach ([
          ['2021', 'The first poll', 'A single-question tool built for one neighbourhood assembly.'],
          ['2022', 'Live results', 'Tallies started updating on their own, every five seconds.'],
          ['2023', 'Public links', 'Anyone could publish a poll and share one short address.'],
          ['2024', 'Accountable voting', 'Per-poll sign-in, one vote per person, and a permanent result record.'],
      ] as [$year, $title, $copy]): ?>
        <li class="reveal flex flex-col gap-3 border-b border-line-light py-8 md:flex-row md:gap-12">
          <p class="mono w-16 shrink-0 text-[14px] text-ink-muted"><?= e($year) ?></p>
          <div>
            <h3 class="text-[18px] font-semibold"><?= e($title) ?></h3>
            <p class="mt-2 max-w-[520px] text-[15px] leading-[1.55] text-ink-muted"><?= e($copy) ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>

<section class="mx-auto max-w-[1440px] px-6 pb-24 lg:px-8">
  <h2 class="reveal font-display text-[28px] font-bold leading-[1.15]">What people ask here</h2>
  <div class="mt-8 grid grid-cols-1 gap-px border border-line-light bg-line-light sm:grid-cols-3">
    <?php foreach ([
        ['Community', 'Participatory budgets, transport priorities, neighbourhood safety.'],
        ['Product teams', 'Feature requests, naming, roadmap trade-offs.'],
        ['Education', 'Classroom choices, curriculum feedback, event planning.'],
    ] as [$title, $copy]): ?>
      <div class="reveal bg-paper p-8">
        <div class="h-[2px] w-10 bg-voltage"></div>
        <h3 class="mt-5 text-[18px] font-semibold"><?= e($title) ?></h3>
        <p class="mt-2 text-[15px] leading-[1.55] text-ink-muted"><?= e($copy) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="border-t border-line-light">
  <div class="mx-auto max-w-[1440px] px-6 py-24 lg:px-8">
    <h2 class="reveal font-display text-[28px] font-bold leading-[1.15]">The team</h2>
    <div class="mt-8 border-t border-ink">
      <?php foreach ([
          ['AM', 'Ada Moreno', 'Product and research'],
          ['JK', 'Jonas Keller', 'Platform engineering'],
          ['RN', 'Rin Nakamura', 'Community and trust'],
      ] as [$initials, $name, $role]): ?>
        <div class="reveal flex items-center gap-6 border-b border-line-light py-8">
          <div class="flex h-[88px] w-[88px] shrink-0 items-center justify-center border border-ink bg-canvas">
            <span class="font-display text-[24px] font-bold"><?= e($initials) ?></span>
          </div>
          <div>
            <h3 class="text-[18px] font-semibold"><?= e($name) ?></h3>
            <p class="label mt-2 text-ink-muted"><?= e($role) ?></p>
            <p class="mt-2 text-[14px] text-ink-muted">Portraits are supplied by team members.</p>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <a class="btn btn-primary mt-12" href="poll-edit.php">Make a poll <?= icon('arrow', 'h-5 w-5') ?></a>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
