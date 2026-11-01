<?php
require __DIR__ . '/config.php';

$sent = false;
$demoLink = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string) ($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (!$errors) {
        $st = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $st->execute([strtolower($email)]);
        $user = $st->fetch();

        if ($user) {
            $token = bin2hex(random_bytes(32));
            db()->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
            )->execute([(int) $user['id'], hash('sha256', $token), date('Y-m-d H:i:s', time() + 3600)]);
            $demoLink = 'reset.php?token=' . $token;
            // snd something from here to the user
        }
        $sent = true;
    }
}

$pageTitle = 'Recover your password';
include __DIR__ . '/includes/header.php';
$authMode = 'recover';
include __DIR__ . '/includes/auth-shell-open.php';
?>

<h1 class="font-display text-[32px] font-bold leading-[1.05] lg:text-[40px]">Recover your password</h1>
<p class="mt-3 text-[16px] text-ink-muted">We will send a secure recovery link to your inbox.</p>

<?php if ($sent): ?>
  <div class="mt-8 border-l-4 border-success border border-line-light bg-paper p-5">
    <p class="label flex items-center gap-2 text-success"><?= icon('check', 'h-5 w-5') ?> Recovery link requested</p>
    <p class="mt-3 text-[15px] leading-[1.55] text-ink-muted">
      If an account exists for that address, a recovery link is on its way. The link expires in one hour.
    </p>
    <?php if (APP_DEMO_MODE && $demoLink !== ''): ?>
      <p class="mono mt-4 break-all border border-line-light bg-canvas p-3">
        DEMO MODE · NO SMTP CONFIGURED ·
        <a class="underline" href="<?= e($demoLink) ?>"><?= e(APP_URL . '/' . $demoLink) ?></a>
      </p>
    <?php endif; ?>
  </div>
  <a class="btn btn-secondary mt-8" href="signin.php">Back to sign in</a>
<?php else: ?>
  <form method="post" class="mt-8 space-y-6" novalidate>
    <?= csrf_field() ?>
    <div>
      <label for="email" class="label block text-ink-muted">Email</label>
      <input id="email" name="email" type="email" class="field mt-2 <?= isset($errors['email']) ? 'field-error' : '' ?>"
             value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required>
      <?php if (isset($errors['email'])): ?><p class="field-error-text mt-2"><?= e($errors['email']) ?></p><?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Send recovery link</button>
    <a class="label block text-ink underline underline-offset-4" href="signin.php">Back to sign in</a>
  </form>
<?php endif; ?>

<?php
include __DIR__ . '/includes/auth-shell-close.php';
include __DIR__ . '/includes/footer.php';
?>
