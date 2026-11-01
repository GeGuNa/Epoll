<?php
require __DIR__ . '/config.php';

$errors = [];
$sent   = false;
$values = ['name' => '', 'email' => '', 'topic' => 'general', 'message' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach ($values as $k => $v) {
        $values[$k] = trim((string) ($_POST[$k] ?? $v));
    }
    if (mb_strlen($values['name']) < 2) {
        $errors['name'] = 'Enter your name.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (mb_strlen($values['message']) < 10) {
        $errors['message'] = 'Tell us a little more (at least 10 characters).';
    }
    if (!in_array($values['topic'], ['general', 'product', 'press', 'trust'], true)) {
        $values['topic'] = 'general';
    }

    if (!$errors) {
        db()->prepare('INSERT INTO contacts (name, email, topic, message) VALUES (?, ?, ?, ?)')
            ->execute([$values['name'], $values['email'], $values['topic'], $values['message']]);
        $sent = true;
    }
}

$pageTitle = 'Contact us';
$navActive = 'Contact us';
include __DIR__ . '/includes/header.php';
?>

<section class="mx-auto max-w-[1440px] px-6 py-16 lg:px-8 lg:py-24">
  <div class="grid grid-cols-1 gap-12 lg:grid-cols-12 lg:gap-6">

    <div class="lg:col-span-5">
      <div class="border border-line-light bg-paper p-6 lg:p-10">
        <h1 class="font-display text-[32px] font-bold leading-[1.05] lg:text-[40px]">Contact us</h1>
        <p class="mt-3 text-[16px] leading-[1.55] text-ink-muted">
          Questions about a poll, an account, or the platform? Send a note to the team.
        </p>

        <?php if ($sent): ?>
          <div class="mt-8 border border-line-light border-l-4 border-l-success p-5">
            <p class="label flex items-center gap-2 text-success"><?= icon('check', 'h-5 w-5') ?> Message sent</p>
            <p class="mt-3 text-[15px] leading-[1.55] text-ink-muted">
              We usually reply within one working day.
            </p>
          </div>
          <a class="btn btn-secondary mt-8" href="index.php">Back to polls</a>
        <?php else: ?>
          <form method="post" class="mt-8 space-y-6" novalidate>
            <?= csrf_field() ?>
            <div>
              <label for="name" class="label block text-ink-muted">Name</label>
              <input id="name" name="name" class="field mt-2 <?= isset($errors['name']) ? 'field-error' : '' ?>"
                     value="<?= e($values['name']) ?>" autocomplete="name" required>
              <?php if (isset($errors['name'])): ?><p class="field-error-text mt-2"><?= e($errors['name']) ?></p><?php endif; ?>
            </div>
            <div>
              <label for="email" class="label block text-ink-muted">Email</label>
              <input id="email" name="email" type="email" class="field mt-2 <?= isset($errors['email']) ? 'field-error' : '' ?>"
                     value="<?= e($values['email']) ?>" autocomplete="email" required>
              <?php if (isset($errors['email'])): ?><p class="field-error-text mt-2"><?= e($errors['email']) ?></p><?php endif; ?>
            </div>
            <div>
              <label for="topic" class="label block text-ink-muted">Topic</label>
              <select id="topic" name="topic" class="field mt-2">
                <?php foreach (['general' => 'General question', 'product' => 'Product feedback',
                                'press' => 'Press inquiry', 'trust' => 'Trust and safety'] as $val => $lab): ?>
                  <option value="<?= e($val) ?>" <?= $values['topic'] === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="message" class="label block text-ink-muted">Message</label>
              <textarea id="message" name="message" rows="6"
                        class="field mt-2 <?= isset($errors['message']) ? 'field-error' : '' ?>" required><?= e($values['message']) ?></textarea>
              <?php if (isset($errors['message'])): ?><p class="field-error-text mt-2"><?= e($errors['message']) ?></p><?php endif; ?>
            </div>
            <button type="submit" class="btn btn-voltage btn-block">Send message</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <aside class="on-dark bg-ink text-paper lg:col-span-6 lg:col-start-7">
      <div class="flex h-full min-h-[520px] flex-col justify-between p-8 lg:p-10">
        <div>
          <p class="label flex items-center gap-2 text-voltage">
            <span class="live-dot" aria-hidden="true"></span>Response desk
          </p>
          <h2 class="serif-q mt-6 text-[26px] leading-[1.2] lg:text-[32px]">
            Real people read every message. No ticket numbers, no queue music.
          </h2>
        </div>

        <dl class="mt-12 space-y-px">
          <?php foreach ([
              'Email'      => 'hello@ballot.live',
              'Response time' => 'Usually within one working day',
              'Press'      => 'press@ballot.live',
              'Trust and safety' => 'trust@ballot.live',
          ] as $k => $v): ?>
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-t border-line-dark py-4">
              <dt class="label text-paper/60"><?= e($k) ?></dt>
              <dd class="mono text-paper"><?= e($v) ?></dd>
            </div>
          <?php endforeach; ?>
        </dl>

        <div class="mt-10 border border-line-dark p-5">
          <p class="mono text-paper/60">SUPPORT HOURS · 09:00–18:00 UTC · MON–FRI</p>
          <p class="mt-3 text-[14px] leading-[1.55] text-paper/70">
            Outage reports go straight to the on-call engineer and are answered at any hour.
          </p>
        </div>
      </div>
    </aside>

  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
