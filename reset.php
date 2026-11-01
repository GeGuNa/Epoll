<?php
require __DIR__ . '/config.php';

$token   = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$errors  = [];
$valid   = false;
$row     = null;

if ($token !== '') {
    $st = db()->prepare(
        'SELECT r.id AS reset_id, r.expires_at, r.used_at, u.id AS user_id
           FROM password_resets r
           JOIN users u ON u.id = r.user_id
          WHERE r.token_hash = ? LIMIT 1'
    );
    $st->execute([hash('sha256', $token)]);
    $row = $st->fetch();
    $valid = $row && !$row['used_at'] && strtotime($row['expires_at']) > time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid) {
    csrf_verify();
    $pass  = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password_confirm'] ?? '');

    if (strlen($pass) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif ($pass !== $pass2) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($pass, PASSWORD_DEFAULT), (int) $row['user_id']]);
        $pdo->prepare('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE id = ?')
            ->execute([(int) $row['reset_id']]);
        $pdo->commit();

        flash('success', 'Your password has been updated. Sign in with the new one.');
        redirect('signin.php');
    }
}

$pageTitle = 'Set a new password';
include __DIR__ . '/includes/header.php';
$authMode = 'reset';
include __DIR__ . '/includes/auth-shell-open.php';
?>

<h1 class="font-display text-[32px] font-bold leading-[1.05] lg:text-[40px]">Set a new password</h1>

<?php if (!$valid): ?>
  <p class="mt-4 text-[16px] leading-[1.55] text-ink-muted">
    That recovery link is invalid or has expired.
  </p>
  <a class="btn btn-primary mt-8" href="recover.php">Request a new link</a>
<?php else: ?>
  <p class="mt-3 text-[16px] text-ink-muted">Choose something you have not used on this account before.</p>
  <form method="post" class="mt-8 space-y-6" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <div>
      <label for="password" class="label block text-ink-muted">New password</label>
      <input id="password" name="password" type="password" class="field mt-2 <?= isset($errors['password']) ? 'field-error' : '' ?>" required>
      <?php if (isset($errors['password'])): ?><p class="field-error-text mt-2"><?= e($errors['password']) ?></p><?php endif; ?>
    </div>
    <div>
      <label for="password_confirm" class="label block text-ink-muted">Confirm password</label>
      <input id="password_confirm" name="password_confirm" type="password"
             class="field mt-2 <?= isset($errors['password_confirm']) ? 'field-error' : '' ?>" required>
      <?php if (isset($errors['password_confirm'])): ?><p class="field-error-text mt-2"><?= e($errors['password_confirm']) ?></p><?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Update password</button>
  </form>
<?php endif; ?>

<?php
include __DIR__ . '/includes/auth-shell-close.php';
include __DIR__ . '/includes/footer.php';
?>
