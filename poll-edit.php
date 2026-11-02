<?php
require __DIR__ . '/config.php';

$user   = require_login('poll-edit.php');
$id     = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$poll   = $id ? get_poll($id) : null;
$isEdit = (bool) $poll;

if ($isEdit && (int) $poll['author_id'] !== (int) $user['id']) {
    flash('error', 'That poll is not yours to edit.');
    redirect('dashboard.php');
}

$errors = [];
$defaults = [
    'title' => '', 'question' => '', 'description' => '', 'category' => 'community',
    'mode' => 'single', 'max_choices' => 3, 'minutes_open' => 10080,
    'require_signin' => 0, 'show_results' => 1,
];
if ($isEdit) {
    foreach ($defaults as $k => $v) {
        $defaults[$k] = $poll[$k] ?? $v;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach (['title', 'question', 'description', 'category'] as $k) {
        $defaults[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $defaults['mode']          = ($_POST['mode'] ?? 'single') === 'multiple' ? 'multiple' : 'single';
    $defaults['max_choices']   = max(2, min(12, (int) ($_POST['max_choices'] ?? 3)));
    $defaults['minutes_open']  = max(5, min(525600, (int) ($_POST['minutes_open'] ?? 10080)));
    $defaults['require_signin'] = isset($_POST['require_signin']) ? 1 : 0;
    $defaults['show_results']   = isset($_POST['show_results']) ? 1 : 0;

    $labels = array_values(array_filter(array_map('trim', (array) ($_POST['option_labels'] ?? [])), fn ($l) => $l !== ''));

    if (mb_strlen($defaults['title']) < 4 || mb_strlen($defaults['title']) > 160) {
        $errors['title'] = 'Use between 4 and 160 characters.';
    }
    if (mb_strlen($defaults['question']) < 10) {
        $errors['question'] = 'A question needs at least 10 characters.';
    }
    if (count($labels) < 2) {
        $errors['options'] = 'Add at least two answers.';
    }
    if (count($labels) > 12) {
        $errors['options'] = 'Keep it to twelve answers or fewer.';
    }
    foreach ($labels as $i => $label) {
        if (mb_strlen($label) > 180) {
            $errors['options'] = 'Answer ' . ($i + 1) . ' is longer than 180 characters.';
        }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();

        $closesAt = date('Y-m-d H:i:s', time() + $defaults['minutes_open'] * 60);
        $status   = ($_POST['status'] ?? 'draft') === 'publish' ? 'open' : 'draft';

        if ($isEdit) {
            $pdo->prepare(
                'UPDATE polls SET title = ?, question = ?, description = ?, category = ?, mode = ?,
                        max_choices = ?, minutes_open = ?, require_signin = ?, show_results = ?,
                        closes_at = ?, status = ?, updated_at = UTC_TIMESTAMP()
                  WHERE id = ?'
            )->execute([
                $defaults['title'], $defaults['question'], $defaults['description'], $defaults['category'],
                $defaults['mode'], $defaults['max_choices'], $defaults['minutes_open'],
                $defaults['require_signin'], $defaults['show_results'], $closesAt,
                $status === 'open' ? 'open' : $poll['status'], $id,
            ]);
            $pollId = $id;

            $existing = $pdo->prepare('SELECT id, label FROM poll_options WHERE poll_id = ? ORDER BY position, id');
            $existing->execute([$pollId]);
            $existingRows = $existing->fetchAll();

            $keep = [];
            $postedIds = array_map('intval', (array) ($_POST['option_ids'] ?? []));
            foreach ($labels as $position => $label) {
                $postedId = $postedIds[$position] ?? 0;
                $match = null;
                foreach ($existingRows as $row) {
                    if ((int) $row['id'] === $postedId) { $match = $row; break; }
                }
                if ($match) {
                    $pdo->prepare('UPDATE poll_options SET label = ?, position = ? WHERE id = ?')
                        ->execute([$label, $position, $match['id']]);
                    $keep[] = (int) $match['id'];
                } else {
                    $pdo->prepare('INSERT INTO poll_options (poll_id, label, position) VALUES (?, ?, ?)')
                        ->execute([$pollId, $label, $position]);
                    $keep[] = (int) $pdo->lastInsertId();
                }
            }
            $dropStmt = $pdo->prepare('DELETE FROM votes WHERE option_id = ?');
            $delStmt  = $pdo->prepare('DELETE FROM poll_options WHERE id = ?');
            foreach ($existingRows as $row) {
                if (!in_array((int) $row['id'], $keep, true)) {
                    $dropStmt->execute([(int) $row['id']]);
                    $delStmt->execute([(int) $row['id']]);
                }
            }
        } else {
            $pdo->prepare(
                'INSERT INTO polls (author_id, slug, code, title, question, description, category, mode, status,
                                    require_signin, show_results, max_choices, minutes_open, closes_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                (int) $user['id'], unique_slug(slugify($defaults['title'])),
                'POLL-' . str_pad((string) (9000 + random_int(100, 999)), 4, '0', STR_PAD_LEFT),
                $defaults['title'], $defaults['question'], $defaults['description'], $defaults['category'],
                $defaults['mode'], $status, $defaults['require_signin'], $defaults['show_results'],
                $defaults['max_choices'], $defaults['minutes_open'], $closesAt,
            ]);
            $pollId = (int) $pdo->lastInsertId();

            $optStmt = $pdo->prepare('INSERT INTO poll_options (poll_id, label, position) VALUES (?, ?, ?)');
            foreach ($labels as $position => $label) {
                $optStmt->execute([$pollId, $label, $position]);
            }
        }

        $pdo->commit();
        log_activity((int) $user['id'], $isEdit ? 'poll.updated' : 'poll.created', $defaults['title']);

        if ($status === 'open' && !$isEdit) {
            flash('success', 'Poll published. The link is live.');
        } elseif ($status === 'open') {
            flash('success', 'Poll published. The link is live.');
        } else {
            flash('success', 'Saved as a draft. Nothing is public yet.');
        }
        redirect('dashboard.php');
    }

    flash('error', 'We could not save your changes. Check the highlighted fields.');
}

$options = $isEdit ? poll_options((int) $poll['id']) : [['id' => 0, 'label' => ''], ['id' => 0, 'label' => '']];

$pageTitle = $isEdit ? 'Edit poll' : 'Create poll';
$navActive = 'Create Poll';
include __DIR__ . '/includes/header.php';
?>

<section class="mx-auto max-w-[1440px] px-6 py-12 lg:px-8 lg:py-16">
  <div class="flex flex-wrap items-end justify-between gap-6">
    <div>
      <p class="label text-ink-muted"><?= $isEdit ? 'Edit poll' : 'New poll' ?></p>
      <h1 class="mt-3 font-display text-[32px] font-bold leading-[1.05] lg:text-[40px]">
        <?= $isEdit ? e($poll['title']) : 'Ask one clear question' ?>
      </h1>
    </div>
    <?php if ($isEdit): ?>
      <a class="btn btn-secondary" href="poll.php?slug=<?= e($poll['slug']) ?>">Preview poll</a>
    <?php endif; ?>
  </div>

  <form method="post" class="mt-10 grid grid-cols-1 gap-10 lg:grid-cols-12" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $id ?>">

    <div class="lg:col-span-8">
      <div class="border border-line-light bg-paper">
        <div class="border-b border-line-light p-6 lg:p-8">
          <div class="flex items-center gap-3">
            <svg width="40" height="16" viewBox="0 0 40 16" aria-hidden="true">
              <circle cx="8" cy="8" r="7" fill="none" stroke="#151515"></circle>
              <line x1="16" y1="8" x2="40" y2="8" stroke="#151515"></line>
            </svg>
            <p class="mono text-ink-muted"><?= $isEdit ? e(poll_code($poll)) : 'NEW BALLOT' ?> · SHEET 01</p>
          </div>

          <div class="mt-8">
            <label for="title" class="label block text-ink-muted">Poll title</label>
            <input id="title" name="title" class="field mt-2 <?= isset($errors['title']) ? 'field-error' : '' ?>"
                   value="<?= e($defaults['title']) ?>" maxlength="160" required>
            <div class="mt-2 flex items-center justify-between">
              <?php if (isset($errors['title'])): ?>
                <p class="field-error-text"><?= e($errors['title']) ?></p>
              <?php else: ?>
                <p class="field-note">Shown on your dashboard and in the link preview.</p>
              <?php endif; ?>
              <p class="mono text-ink-muted"><?= mb_strlen($defaults['title']) ?>/160</p>
            </div>
          </div>

          <div class="mt-8">
            <label for="question" class="label block text-ink-muted">Question</label>
            <textarea id="question" name="question" rows="3"
                      class="field mt-2 <?= isset($errors['question']) ? 'field-error' : '' ?>" required><?= e($defaults['question']) ?></textarea>
            <?php if (isset($errors['question'])): ?>
              <p class="field-error-text mt-2"><?= e($errors['question']) ?></p>
            <?php else: ?>
              <p class="field-note mt-2">Ask one thing at a time.</p>
            <?php endif; ?>
          </div>

          <div class="mt-8">
            <label for="description" class="label block text-ink-muted">Description <span class="font-normal normal-case tracking-normal">(optional)</span></label>
            <textarea id="description" name="description" rows="3" class="field mt-2"><?= e($defaults['description']) ?></textarea>
          </div>
        </div>


        <div class="p-6 lg:p-8">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <h2 class="font-display text-[22px] font-bold leading-[1.15]">Answers</h2>
              <p class="field-note mt-2">2–12 answers. Press Enter in a field to add another.</p>
            </div>
            <button type="button" id="add-option" class="btn btn-secondary btn-sm"><?= icon('plus', 'h-4 w-4') ?> Add answer</button>
          </div>

          <div id="option-list" class="mt-6 space-y-3">
            <?php foreach ($options as $i => $opt): ?>
              <div class="option-row flex items-center gap-3 border border-line-light p-3">
                <span class="mono w-8 shrink-0 text-ink-muted" data-option-number><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                <input type="hidden" name="option_ids[]" value="<?= (int) ($opt['id'] ?? 0) ?>">
                <input type="text" name="option_labels[]" class="field"
                       value="<?= e($opt['label'] ?? '') ?>" aria-label="Answer <?= $i + 1 ?>" maxlength="180" required>
                <div class="flex shrink-0 items-center gap-1">
                  <button type="button" class="icon-btn" data-action="up" title="Move up">
                    <span class="sr-only">Move answer up</span><?= icon('up') ?>
                  </button>
                  <button type="button" class="icon-btn" data-action="down" title="Move down">
                    <span class="sr-only">Move answer down</span><?= icon('down') ?>
                  </button>
                  <button type="button" class="icon-btn icon-btn-danger" data-action="remove" title="Remove answer">
                    <span class="sr-only">Remove answer</span><?= icon('trash') ?>
                  </button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>

          <?php if (isset($errors['options'])): ?>
            <p class="field-error-text mt-4"><?= e($errors['options']) ?></p>
          <?php endif; ?>

          <template id="option-row-template">
            <div class="option-row flex items-center gap-3 border border-line-light p-3">
              <span class="mono w-8 shrink-0 text-ink-muted" data-option-number>00</span>
              <input type="hidden" name="option_ids[]" value="0">
              <input type="text" name="option_labels[]" class="field" value="" maxlength="180" required>
              <div class="flex shrink-0 items-center gap-1">
                <button type="button" class="icon-btn" data-action="up" title="Move up">
                  <span class="sr-only">Move answer up</span><?= icon('up') ?>
                </button>
                <button type="button" class="icon-btn" data-action="down" title="Move down">
                  <span class="sr-only">Move answer down</span><?= icon('down') ?>
                </button>
                <button type="button" class="icon-btn icon-btn-danger" data-action="remove" title="Remove answer">
                  <span class="sr-only">Remove answer</span><?= icon('trash') ?>
                </button>
              </div>
            </div>
          </template>
        </div>
      </div>

      <div class="mt-6 border border-line-light bg-paper p-6 lg:p-8">
        <label for="preview" class="label block text-ink-muted">Preview</label>
        <div class="mt-4 space-y-3" aria-live="polite">
          <div class="answer-row cursor-default">
            <span class="answer-box round" aria-hidden="true">
              <svg viewBox="0 0 20 20" class="h-3.5 w-3.5" fill="none" stroke="#151515" stroke-width="2.4" stroke-linecap="round"><path d="M4 10.5l4 4 8-9"/></svg>
            </span>
            <span class="text-[16px] font-semibold" id="preview">Your answers appear here exactly as voters will see them.</span>
          </div>
        </div>
      </div>
    </div>

    <aside class="lg:col-span-4">
      <div class="border border-ink bg-paper">
        <div class="border-b border-line-light p-6">
          <p class="label text-ink-muted">Poll settings</p>
        </div>

        <div class="space-y-6 p-6">
          <div>
            <label for="category" class="label block text-ink-muted">Category</label>
            <select id="category" name="category" class="field mt-2">
              <?php foreach (['community' => 'Community', 'product' => 'Product', 'education' => 'Education',
                              'city' => 'City', 'workplace' => 'Workplace', 'events' => 'Events',
                              'research' => 'Research', 'other' => 'Other'] as $val => $lab): ?>
                <option value="<?= e($val) ?>" <?= $defaults['category'] === $val ? 'selected' : '' ?>><?= e($lab) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label for="mode" class="label block text-ink-muted">Answer mode</label>
            <select id="mode" name="mode" class="field mt-2">
              <option value="single" <?= $defaults['mode'] === 'single' ? 'selected' : '' ?>>Single answer</option>
              <option value="multiple" <?= $defaults['mode'] === 'multiple' ? 'selected' : '' ?>>Multiple answers</option>
            </select>
          </div>

          <div>
            <label for="max_choices" class="label block text-ink-muted">Maximum choices</label>
            <div class="mt-2 flex items-center gap-3" data-stepper="1">
              <button type="button" class="icon-btn border border-line-light" data-dir="down">
                <span class="sr-only">Fewer choices</span><?= icon('minus') ?>
              </button>
              <input id="max_choices" name="max_choices" type="number" class="field text-center"
                     min="2" max="12" value="<?= (int) $defaults['max_choices'] ?>">
              <button type="button" class="icon-btn border border-line-light" data-dir="up">
                <span class="sr-only">More choices</span><?= icon('plus') ?>
              </button>
            </div>
            <p class="field-note mt-2">Applies when the mode is multiple answers.</p>
          </div>

          <div>
            <label for="minutes_open" class="label block text-ink-muted">Closes after</label>
            <div class="mt-2 flex items-center gap-3" data-stepper="60">
              <button type="button" class="icon-btn border border-line-light" data-dir="down">
                <span class="sr-only">Shorter</span><?= icon('minus') ?>
              </button>
              <input id="minutes_open" name="minutes_open" type="number" class="field text-center"
                     min="5" max="525600" value="<?= (int) $defaults['minutes_open'] ?>">
              <button type="button" class="icon-btn border border-line-light" data-dir="up">
                <span class="sr-only">Longer</span><?= icon('plus') ?>
              </button>
            </div>
            <p class="field-note mt-2">Minutes. 10080 = 7 days.</p>
          </div>

          <div class="flex items-start justify-between gap-4 border-t border-line-light pt-6">
            <div>
              <label for="require_signin" class="label block text-ink-muted">Require sign in</label>
              <p class="field-note mt-2">One vote per account.</p>
            </div>
            <input id="require_signin" name="require_signin" type="checkbox" class="sr-only"
                   <?= $defaults['require_signin'] ? 'checked' : '' ?>>
            <label for="require_signin" class="switch cursor-pointer" aria-hidden="true"></label>
          </div>

          <div class="flex items-start justify-between gap-4">
            <div>
              <label for="show_results" class="label block text-ink-muted">Show results</label>
              <p class="field-note mt-2">Visible before and after voting.</p>
            </div>
            <input id="show_results" name="show_results" type="checkbox" class="sr-only"
                   <?= $defaults['show_results'] ? 'checked' : '' ?>>
            <label for="show_results" class="switch cursor-pointer" aria-hidden="true"></label>
          </div>
        </div>

        <div class="border-t border-line-light p-6">
          <button type="submit" name="status" value="publish" class="btn btn-voltage btn-block">Publish poll</button>
          <button type="submit" name="status" value="draft" class="btn btn-secondary btn-block mt-3">Save draft</button>
          <?php if ($isEdit): ?>
            <button type="button" class="btn btn-danger btn-block mt-3"
                    data-confirm-open
                    data-confirm-poll-id="<?= (int) $poll['id'] ?>"
                    data-confirm-action="delete"
                    data-confirm-copy="Delete “<?= e($poll['title']) ?>”? This cannot be undone.">
              Delete poll
            </button>
          <?php endif; ?>
        </div>
      </div>
    </aside>
  </form>
</section>

<div id="confirm-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-6"
     style="background:rgba(21,21,21,.6)" role="dialog" aria-modal="true">
  <div class="w-full max-w-[520px] border border-ink bg-paper">
    <div class="p-8">
      <div class="flex h-10 w-10 items-center justify-center border border-danger text-danger"><?= icon('warning') ?></div>
      <h2 class="mt-5 font-display text-[22px] font-bold">Delete this poll?</h2>
      <p class="mt-3 text-[15px] text-ink-muted" data-confirm-copy>This cannot be undone.</p>
    </div>
    <div class="flex flex-wrap gap-3 border-t border-line-light p-6">
      <form method="post" action="poll-action.php" class="flex gap-3">
        <?= csrf_field() ?>
        <input type="hidden" name="poll_id" value="">
        <input type="hidden" name="action" value="delete">
        <button type="submit" class="btn btn-danger">Delete</button>
      </form>
      <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
    </div>
  </div>
</div>

<script>
  (function () {
    var list = document.getElementById('option-list');
    if (!list) { return; }
    var preview = document.getElementById('preview');
    function syncPreview() {
      var first = list.querySelector('input[type="text"]');
      if (preview && first) {
        preview.textContent = first.value.trim() || 'Your answers appear here exactly as voters will see them.';
      }
    }
    list.addEventListener('input', syncPreview);
    syncPreview();
  })();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
