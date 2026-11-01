<?php
$u = current_user();
$pageTitle = $pageTitle ?? APP_NAME;
$navActive = $navActive ?? '';
$publicNav = [
    'Polls'      => 'index.php#public-record',
    'About us'   => 'about.php',
    'Contact us' => 'contact.php',
];
$authNav = [
    'Dashboard'  => 'dashboard.php',
    'Create Poll' => 'poll-edit.php',
    'Settings'   => 'settings.php',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>
<meta name="description" content="Ballot / Live is an online poll and voting system with live results. Make a poll, send a link, watch the room answer.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,400;8..60,500;8..60,600&family=IBM+Plex+Mono:wght@500&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          canvas: '#F3F0E8', paper: '#FFFFFF', ink: '#151515',
          'ink-muted': '#686762', 'line-light': '#B8B3A9', 'line-dark': '#3A3A38',
          'surface-muted': '#D8D3C8', voltage: '#D8FF3E', 'voltage-dark': '#A9CC00',
          danger: '#C94236', success: '#2C7A55'
        },
        fontFamily: {
          display: ['Space Grotesk', 'Arial', 'sans-serif'],
          sans: ['Inter', 'system-ui', 'sans-serif'],
          serif: ['"Source Serif 4"', 'Georgia', 'serif'],
          mono: ['"IBM Plex Mono"', 'monospace']
        }
      }
    }
  }
</script>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="bg-canvas text-ink font-sans">

<header class="sticky top-0 z-40 border-b border-line-light" style="background:rgba(243,240,232,.94);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px)">
  <div class="mx-auto flex h-[72px] max-w-[1440px] items-center justify-between gap-6 px-6 lg:px-8">
    <a href="index.php" class="flex items-center gap-3">
      <span class="font-display text-[16px] font-bold leading-none">BALLOT / LIVE</span>
      <span class="label inline-flex items-center gap-1.5 rounded-full bg-voltage px-2.5 py-1 leading-none">
        <span class="live-dot" aria-hidden="true"></span>Live
      </span>
    </a>

    <nav class="hidden lg:flex items-center gap-8" aria-label="Main">
      <?php foreach ($publicNav as $label => $href): ?>
        <a href="<?= e($href) ?>"
           class="relative text-[14px] font-semibold leading-none py-2 <?= $navActive === $label ? 'after:absolute after:-bottom-1 after:left-0 after:h-[2px] after:w-full after:bg-ink' : 'hover:after:absolute hover:after:-bottom-1 hover:after:left-0 hover:after:h-[2px] hover:after:w-full hover:after:bg-line-light' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
      <?php if ($u): ?>
        <?php foreach ($authNav as $label => $href): ?>
          <a href="<?= e($href) ?>"
             class="relative text-[14px] font-semibold leading-none py-2 <?= $navActive === $label ? 'after:absolute after:-bottom-1 after:left-0 after:h-[2px] after:w-full after:bg-ink' : 'hover:after:absolute hover:after:-bottom-1 hover:after:left-0 hover:after:h-[2px] hover:after:w-full hover:after:bg-line-light' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      <?php endif; ?>
    </nav>

    <div class="flex items-center gap-2">
      <?php if ($u): ?>
        <span class="hidden xl:flex items-center gap-2 pr-2 text-[13px] font-semibold text-ink-muted">
          <span class="flex h-8 w-8 items-center justify-center border border-ink text-[11px] font-bold">
            <?= e(strtoupper(mb_substr($u['name'], 0, 1))) ?>
          </span>
          <?= e($u['name']) ?>
        </span>
        <a href="logout.php" class="btn btn-secondary btn-sm hidden lg:inline-flex">Log out</a>
      <?php else: ?>
        <a href="signin.php" class="btn btn-secondary btn-sm hidden lg:inline-flex">Sign in</a>
      <?php endif; ?>
      <a href="poll-edit.php" class="btn btn-voltage btn-sm">
        <span class="hidden sm:inline">Create poll</span>
        <span class="sm:hidden">Create</span>
      </a>
      <button type="button" id="menu-toggle" class="lg:hidden flex h-[44px] w-[44px] items-center justify-center rounded-full border border-ink" aria-expanded="false" aria-controls="mobile-menu">
        <span class="sr-only">Open menu</span>
        <span class="flex flex-col gap-[5px]" aria-hidden="true">
          <span class="block h-[2px] w-[18px] bg-ink"></span>
          <span class="block h-[2px] w-[18px] bg-ink"></span>
        </span>
      </button>
    </div>
  </div>

  <div id="mobile-menu" class="lg:hidden hidden border-t border-line-light bg-canvas">
    <div class="px-6 py-6">
      <nav class="flex flex-col gap-1" aria-label="Mobile">
        <?php
        $mobile = $u ? array_merge($publicNav, $authNav, ['Log out' => 'logout.php'])
                    : array_merge($publicNav, ['Sign in' => 'signin.php', 'Create account' => 'signup.php']);
        foreach ($mobile as $label => $href): ?>
          <a href="<?= e($href) ?>" class="flex min-h-[44px] items-center justify-between border-b border-line-light text-[16px] font-semibold">
            <?= e($label) ?> <?= icon('arrow', 'h-5 w-5') ?>
          </a>
        <?php endforeach; ?>
      </nav>
    </div>
  </div>
</header>

<main>
<?php render_toasts(); ?>
