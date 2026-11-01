<?php
require __DIR__ . '/config.php';

if (current_user()) {
    redirect('dashboard.php');
}

$errors = [];
$values = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $values['name']  = trim((string) ($_POST['name'] ?? ''));
    $values['email'] = trim((string) ($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password_confirm'] ?? '');
    $terms = isset($_POST['terms']);

    if (mb_strlen($values['name']) < 2) {
        $errors['name'] = 'Enter your name (at least 2 characters).';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    } else {
        $st = db()->prepare('SELECT COUNT(*) AS n FROM users WHERE email = ?');
        $st->execute([strtolower($values['email'])]);
        if ((int) $st->fetch()['n'] > 0) {
            $errors['email'] = 'An account already exists for that email address.';
        }
    }
    if (strlen($pass) < 8) {
        $errors['password'] = 'Use at least 8 characters.';
    } elseif ($pass !== $pass2) {
        $errors['password_confirm'] = 'The two passwords do not match.';
    }
    if (!$terms) {
        $errors['terms'] = 'Please accept the terms of use.';
    }

    if (!$errors) {
        $st = db()->prepare(
            'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)'
        );
        $st->execute([$values['name'], strtolower($values['email']), password_hash($pass, PASSWORD_DEFAULT)]);
        $id = (int) db()->lastInsertId();
        login_user($id);
        log_activity($id, 'account.created', 'Account created');
        flash('success', 'Your account is ready. Make your first poll.');
        redirect('poll-edit.php');
    }
}

$pageTitle = 'Create account';
include __DIR__ . '/includes/header.php';
$authMode = 'signup';
include __DIR__ . '/includes/auth-shell-open.php';
?>

<h1 class="font-display text-[32px] font-bold leading-[1.05] lg:text-[40px]">Create account</h1>
<p class="mt-3 text-[16px] text-ink-muted">Keep track of every question you ask.</p>

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
    <label for="password" class="label block text-ink-muted">Password</label>
    <input id="password" name="password" type="password" class="field mt-2 <?= isset($errors['password']) ? 'field-error' : '' ?>"
           autocomplete="new-password" required>
    <p class="field-note mt-2">Eight characters minimum. Numbers and symbols help.</p>
    <?php if (isset($errors['password'])): ?><p class="field-error-text mt-2"><?= e($errors['password']) ?></p><?php endif; ?>
  </div>

  <div>
    <label for="password_confirm" class="label block text-ink-muted">Confirm password</label>
    <input id="password_confirm" name="password_confirm" type="password"
           class="field mt-2 <?= isset($errors['password_confirm']) ? 'field-error' : '' ?>" autocomplete="new-password" required>
    <?php if (isset($errors['password_confirm'])): ?><p class="field-error-text mt-2"><?= e($errors['password_confirm']) ?></p><?php endif; ?>
  </div>

  <label class="flex min-h-[44px] items-start gap-3 text-[14px]">
    <input type="checkbox" name="terms" class="mt-1 h-5 w-5 accent-[#D8FF3E]" <?= isset($_POST['terms']) ? 'checked' : '' ?>>
    <span>I agree to the terms of use and the privacy policy.</span>
  </label>
  <?php if (isset($errors['terms'])): ?><p class="field-error-text"><?= e($errors['terms']) ?></p><?php endif; ?>

  <button type="submit" class="btn btn-primary btn-block">Create account</button>

  <p class="text-[14px] text-ink-muted">
    Already have an account? <a class="font-semibold text-ink underline underline-offset-4" href="signin.php">Sign in</a>
  </p>
</form>

<?php
include __DIR__ . '/includes/auth-shell-close.php';
include __DIR__ . '/includes/footer.php';
?>
