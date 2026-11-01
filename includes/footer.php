<?php

?>
</main>

<footer class="on-dark bg-ink text-paper">
  <div class="mx-auto max-w-[1440px] px-6 py-16 lg:px-8">
    <div class="flex flex-col gap-12 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <p class="font-display text-[22px] font-bold leading-none">BALLOT / LIVE</p>
        <p class="serif-q mt-4 max-w-[280px] text-[20px] leading-[1.25] text-paper/80">Your voice, counted in public.</p>
      </div>

      <nav class="grid grid-cols-2 gap-x-16 gap-y-2 sm:grid-cols-2" aria-label="Footer">
        <?php foreach (['Polls' => 'index.php#public-record', 'About us' => 'about.php', 'Contact us' => 'contact.php',
                        'Dashboard' => 'dashboard.php', 'Create Poll' => 'poll-edit.php', 'Settings' => 'settings.php'] as $label => $href): ?>
          <a href="<?= e($href) ?>" class="min-h-[44px] flex items-center text-[14px] font-semibold text-paper/80 hover:text-voltage"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>

      <div class="flex flex-col gap-3">
        <a href="poll-edit.php" class="btn btn-voltage">Create poll</a>
        <a href="signin.php" class="btn btn-secondary">Sign in</a>
      </div>
    </div>

    <div class="mt-16 h-[1px] w-full bg-line-dark"></div>

    <div class="mt-8 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between">
      <p class="label text-paper/60">Make something worth asking.</p>
      <p class="mono text-paper/50">© <?= date('Y') ?> BALLOT / LIVE · RESULTS UPDATED EVERY 5 SECONDS · ALL TIMESTAMPS UTC</p>
    </div>
  </div>
</footer>

<div id="toast-root"></div>
<script src="assets/js/app.js"></script>
</body>
</html>
