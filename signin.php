<?php
require __DIR__ . '/config.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$next   = trim((string) ($_GET['next'] ?? $_POST['next'] ?? ''));
$allowedNext = ['dashboard.php', 'settings.php', 'poll-edit.php', 'index.php'];
if (!in_array($next, $allowedNext, true)) {
    $next = 'dashboard.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string) ($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if ($pass === '') {
        $errors['password'] = 'Enter your password.';
    }

    if (!$errors) {
        $st = db()->prepare('SELECT id, password_hash FROM users WHERE email = ? LIMIT 1');
        $st->execute([strtolower($email)]);
        $row = $st->fetch();

        if (!$row || !password_verify($pass, $row['password_hash'])) {
            $errors['email'] = 'That email and password combination did not match.';
        } else {
            login_user((int) $row['id']);
            log_activity((int) $row['id'], 'auth.signin', 'Signed in');
            flash('success', 'Signed in. Welcome back.');
            redirect($next);
        }
    }
}

$pageTitle = 'Sign in';
include __DIR__ . '/includes/header.php';
$authMode = 'signin';
include __DIR__ . '/includes/auth-shell-open.php';
?>

<h1 class="font-display text-[32px] font-bold leading-[1.05] lg:text-[40px]">Sign in</h1>
<p class="mt-3 text-[16px] text-ink-muted">Access your polls and settings.</p>

<form method="post" class="mt-8 space-y-6" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">

  <div>
    <label for="email" class="label block text-ink-muted">Email</label>
    <input id="email" name="email" type="email" class="field mt-2 <?= isset($errors['email']) ? 'field-error' : '' ?>"
           value="<?= e($_POST['email'] ?? '') ?>" autocomplete="email" required>
    <?php if (isset($errors['email'])): ?>
      <p class="field-error-text mt-2"><?= e($errors['email']) ?></p>
    <?php endif; ?>
  </div>

  <div>
    <div class="flex items-center justify-between">
      <label for="password" class="label block text-ink-muted">Password</label>
      <a href="recover.php" class="label text-ink underline underline-offset-4">Forgot your password?</a>
    </div>
    <input id="password" name="password" type="password" class="field mt-2 <?= isset($errors['password']) ? 'field-error' : '' ?>"
           autocomplete="current-password" required>
    <?php if (isset($errors['password'])): ?>
      <p class="field-error-text mt-2"><?= e($errors['password']) ?></p>
    <?php endif; ?>
  </div>

  <button type="submit" class="btn btn-primary btn-block">Sign in</button>

  <p class="text-[14px] text-ink-muted">
    New here? <a class="font-semibold text-ink underline underline-offset-4" href="signup.php">Create an account</a>
  </p>
  <?php if (APP_DEMO_MODE): ?>
    <p class="mono border border-line-light bg-canvas p-3 text-ink-muted">
      DEMO · SIGN IN WITH demo@ballot.live / demo1234
    </p>
  <?php endif; ?>
</form>

<?php
include __DIR__ . '/includes/auth-shell-close.php';
include __DIR__ . '/includes/footer.php';
?>
