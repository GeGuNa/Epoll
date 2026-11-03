<?php
require __DIR__ . '/config.php';

$user = require_login('settings.php');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'profile') {
        $name  = trim((string) ($_POST['name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        if (mb_strlen($name) < 2) {
            $errors['name'] = 'Enter your name.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        } else {
            $st = db()->prepare('SELECT COUNT(*) AS n FROM users WHERE email = ? AND id <> ?');
            $st->execute([strtolower($email), (int) $user['id']]);
            if ((int) $st->fetch()['n'] > 0) {
                $errors['email'] = 'That email address is already in use.';
            }
        }
        if (!$errors) {
            db()->prepare('UPDATE users SET name = ?, email = ? WHERE id = ?')
                ->execute([$name, strtolower($email), (int) $user['id']]);
            log_activity((int) $user['id'], 'settings.profile', 'Profile updated');
            flash('success', 'Your profile is saved.');
            redirect('settings.php');
        }
    }

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $pass    = (string) ($_POST['password'] ?? '');
        $pass2   = (string) ($_POST['password_confirm'] ?? '');

        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([(int) $user['id']]);
        if (!password_verify($current, (string) $st->fetch()['password_hash'])) {
            $errors['current_password'] = 'Your current password is not correct.';
        } elseif (strlen($pass) < 8) {
            $errors['password'] = 'Use at least 8 characters.';
        } elseif ($pass !== $pass2) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }

        if (!$errors) {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($pass, PASSWORD_DEFAULT), (int) $user['id']]);
            log_activity((int) $user['id'], 'settings.password', 'Password changed');
            flash('success', 'Your password has been updated.');
            redirect('settings.php');
        }
    }

    if ($action === 'notifications') {
        db()->prepare('UPDATE users SET notify_results = ?, notify_closing = ?, notify_news = ? WHERE id = ?')
            ->execute([
                isset($_POST['notify_results']) ? 1 : 0,
                isset($_POST['notify_closing']) ? 1 : 0,
                isset($_POST['notify_news']) ? 1 : 0,
                (int) $user['id'],
            ]);
        flash('success', 'Notification preferences saved.');
        redirect('settings.php');
    }

    if ($action === 'delete_account') {
        if (!password_verify((string) ($_POST['confirm_password'] ?? ''), (string) ($user['password_hash'] ?? ''))) {
            
        }
        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([(int) $user['id']]);
        if (!password_verify((string) ($_POST['confirm_password'] ?? ''), (string) $st->fetch()['password_hash'])) {
            flash('error', 'Confirm your password to delete the account.');
            redirect('settings.php');
        }
        db()->prepare('DELETE FROM users WHERE id = ?')->execute([(int) $user['id']]);
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('success', 'Your account and polls have been removed.');
        redirect('index.php');
    }

    flash('error', 'We could not save your changes. Check the highlighted fields.');
}

$st = db()->prepare('SELECT action, meta, created_at FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$st->execute([(int) $user['id']]);
$activity = $st->fetchAll();

$pageTitle = 'Settings';
$navActive = 'Settings';
include __DIR__ . '/includes/header.php';
?>

<section class="mx-auto max-w-[1440px] px-6 py-12 lg:px-8 lg:py-16">
  <div class="mx-auto max-w-[680px]">
    <p class="label text-ink-muted">Settings</p>
    <h1 class="mt-3 font-display text-[32px] font-bold leading-[1.05] lg:text-[40px]">Your account</h1>
    <p class="mt-3 text-[16px] text-ink-muted">Profile details, password, notifications, and account controls.</p>


    <form method="post" class="mt-10 border border-line-light bg-paper p-6 lg:p-8">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="profile">
      <h2 class="font-display text-[22px] font-bold leading-[1.15]">Profile details</h2>

      <div class="mt-6 flex items-center gap-4">
        <?php if (!empty($user['avatar_url'])): ?>
          <img src="<?= e($user['avatar_url']) ?>" alt="" class="h-[88px] w-[88px] border border-ink object-cover">
        <?php else: ?>
          <div class="flex h-[88px] w-[88px] items-center justify-center border border-ink bg-canvas">
            <span class="font-display text-[24px] font-bold"><?= e(strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
          </div>
        <?php endif; ?>
        <div>
          <p class="text-[15px] font-semibold"><?= e($user['name']) ?></p>
          <p class="mono mt-1 text-ink-muted">MEMBER SINCE <?= e(strtoupper(date('M Y', strtotime($user['created_at'])))) ?></p>
          <p class="field-note mt-2">Avatar upload replaces the initials block. Max 1MB square image.</p>
        </div>
      </div>

      <div class="mt-8">
        <label for="name" class="label block text-ink-muted">Name</label>
        <input id="name" name="name" class="field mt-2 <?= isset($errors['name']) ? 'field-error' : '' ?>" value="<?= e($user['name']) ?>">
        <?php if (isset($errors['name'])): ?><p class="field-error-text mt-2"><?= e($errors['name']) ?></p><?php endif; ?>
      </div>

      <div class="mt-6">
        <label for="email" class="label block text-ink-muted">Email</label>
        <input id="email" name="email" type="email" class="field mt-2 <?= isset($errors['email']) ? 'field-error' : '' ?>" value="<?= e($user['email']) ?>">
        <?php if (isset($errors['email'])): ?><p class="field-error-text mt-2"><?= e($errors['email']) ?></p><?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary mt-8">Save changes</button>
    </form>


    <form method="post" class="mt-8 border border-line-light bg-paper p-6 lg:p-8">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="password">
      <h2 class="font-display text-[22px] font-bold leading-[1.15]">Password</h2>

      <div class="mt-6">
        <label for="current_password" class="label block text-ink-muted">Current password</label>
        <input id="current_password" name="current_password" type="password"
               class="field mt-2 <?= isset($errors['current_password']) ? 'field-error' : '' ?>">
        <?php if (isset($errors['current_password'])): ?><p class="field-error-text mt-2"><?= e($errors['current_password']) ?></p><?php endif; ?>
      </div>
      <div class="mt-6">
        <label for="password" class="label block text-ink-muted">New password</label>
        <input id="password" name="password" type="password" class="field mt-2 <?= isset($errors['password']) ? 'field-error' : '' ?>">
        <?php if (isset($errors['password'])): ?><p class="field-error-text mt-2"><?= e($errors['password']) ?></p><?php endif; ?>
      </div>
      <div class="mt-6">
        <label for="password_confirm" class="label block text-ink-muted">Confirm new password</label>
        <input id="password_confirm" name="password_confirm" type="password"
               class="field mt-2 <?= isset($errors['password_confirm']) ? 'field-error' : '' ?>">
        <?php if (isset($errors['password_confirm'])): ?><p class="field-error-text mt-2"><?= e($errors['password_confirm']) ?></p><?php endif; ?>
      </div>

      <button type="submit" class="btn btn-primary mt-8">Update password</button>
    </form>


    <form method="post" class="mt-8 border border-line-light bg-paper p-6 lg:p-8">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="notifications">
      <h2 class="font-display text-[22px] font-bold leading-[1.15]">Notifications</h2>

      <div class="mt-6 space-y-6">
        <?php foreach ([
            ['notify_results', 'Results updates', 'When a poll of yours passes a new vote milestone.'],
            ['notify_closing', 'Poll closing alerts', 'One hour before a poll stops accepting votes.'],
            ['notify_news', 'Product updates', 'Occasional notes about new platform features.'],
        ] as $key, ) { } ?>
      </div>
    </form>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
