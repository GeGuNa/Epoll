(function () {
  'use strict';

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  var menuToggle = $('#menu-toggle');
  var mobileMenu = $('#mobile-menu');
  if (menuToggle && mobileMenu) {
    menuToggle.addEventListener('click', function () {
      var open = mobileMenu.classList.toggle('hidden') === false;
      menuToggle.setAttribute('aria-expanded', String(open));
      menuToggle.classList.toggle('rounded-full', true);
    });
  }

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });

    $$('.reveal, .chamber-enter, .draw-rule').forEach(function (el, i) {
      el.style.transitionDelay = (Math.min(i, 6) * 80) + 'ms';
      io.observe(el);
    });
  } else {
    $$('.reveal, .chamber-enter, .draw-rule').forEach(function (el) { el.classList.add('is-in'); });
  }

  function toast(message, tone) {
    var root = $('#toast-root');
    if (!root) { return; }
    var wrap = document.createElement('div');
    wrap.className = 'fixed bottom-6 right-6 z-50 w-[320px] pointer-events-none';
    wrap.innerHTML =
      '<div data-toast class="pointer-events-auto border-l-4 bg-paper p-4 text-[14px] ' +
      (tone === 'error' ? 'border-danger' : tone === 'success' ? 'border-success' : 'border-ink') +
      ' border border-ink/10"><p></p></div>';
    wrap.querySelector('p').textContent = message;
    root.appendChild(wrap);
    window.setTimeout(function () { wrap.remove(); }, 3600);
  }
  window.ballotToast = toast;

  function animateNumber(el, to, done) {
    var from = parseInt(el.getAttribute('data-value') || el.textContent.replace(/[^0-9]/g, '') || '0', 10);
    el.setAttribute('data-value', String(to));
    if (reduceMotion || from === to) {
      el.textContent = to.toLocaleString('en-US');
      if (done) { done(); }
      return;
    }
    var start = performance.now();
    var dur = 480;
    function step(now) {
      var t = Math.min(1, (now - start) / dur);
      var eased = 1 - Math.pow(1 - t, 3);
      el.textContent = Math.round(from + (to - from) * eased).toLocaleString('en-US');
      if (t < 1) { window.requestAnimationFrame(step); } else if (done) { done(); }
    }
    window.requestAnimationFrame(step);
  }

  function applyResults(root, data) {
    var totalEl = $('[data-total-text]', root);
    var totalBox = $('[data-total-value]', root);
    var previous = parseInt((totalBox && totalBox.getAttribute('data-value')) || '0', 10);

    if (totalEl) {
      animateNumber(totalEl, data.total);
      if (totalBox) { totalBox.setAttribute('data-value', String(data.total)); }
    }

    var maxVotes = 0;
    data.options.forEach(function (o) { maxVotes = Math.max(maxVotes, o.votes); });

    data.options.forEach(function (opt, i) {
      var row = root.querySelector('[data-option-id="' + opt.id + '"]');
      if (!row) { return; }
      var fill = $('[data-fill]', row);
      var pct = $('[data-pct-text]', row);
      var count = $('[data-count-text]', row);
      var flag = $('[data-leading-flag]', row);

      if (fill) {
        if (reduceMotion) {
          fill.style.transition = 'none';
        } else if (i > 0) {
          fill.style.transitionDelay = (i * 60) + 'ms';
        }
        fill.style.width = opt.percent + '%';
        fill.style.setProperty('--pct', opt.percent + '%');
        fill.classList.toggle('leading', opt.votes > 0 && opt.votes === maxVotes);
      }
      if (pct) {
        window.setTimeout(function () {
          pct.textContent = (Math.round(opt.percent * 10) / 10).toLocaleString('en-US') + '%';
        }, reduceMotion ? 0 : 240);
      }
      if (count) { animateNumber(count, opt.votes); }
      if (flag) {
        var isLeading = opt.votes > 0 && opt.votes === maxVotes;
        flag.classList.toggle('invisible', !isLeading);
      }
    });

    if (data.total !== previous && previous !== 0) {
      var dot = $('[data-live-dot]', root);
      if (dot && !reduceMotion) {
        dot.classList.add('pulse-once');
        window.setTimeout(function () { dot.classList.remove('pulse-once'); }, 400);
      }
    }

    var region = $('[data-live-region]', root);
    if (region && data.total !== previous && previous !== 0) {
      var lead = data.options.slice().sort(function (a, b) { return b.votes - a.votes; })[0];
      region.textContent = 'Results updated. ' + data.total + ' votes counted. ' +
        (lead ? lead.label + ' is leading at ' + Math.round(lead.percent) + ' percent.' : '');
    }

    root.setAttribute('data-last-fetch', String(Date.now()));
    var updated = $('[data-updated-text]', root);
    if (updated) { updated.textContent = 'Last updated just now'; }
  }

  function pollResults(root) {
    var id = root.getAttribute('data-poll-id');
    var endpoint = root.getAttribute('data-endpoint') || 'api/results.php';
    fetch(endpoint + '?poll_id=' + encodeURIComponent(id), {
      headers: { 'Accept': 'application/json' }
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) { if (data) { applyResults(root, data); } })
      .catch(function () {});
  }

  $$('[data-live-results]').forEach(function (root) {
    root.setAttribute('data-last-fetch', String(Date.now()));
    window.setInterval(function () { pollResults(root); }, 5000);
    window.setInterval(function () {
      var updated = $('[data-updated-text]', root);
      if (!updated) { return; }
      var secs = Math.round((Date.now() - parseInt(root.getAttribute('data-last-fetch'), 10)) / 1000);
      updated.textContent = secs < 5 ? 'Last updated just now' : 'Last updated ' + secs + ' sec ago';
    }, 1000);
  });

  $$('[data-countdown]').forEach(function (el) {
    var end = parseInt(el.getAttribute('data-countdown'), 10) * 1000;
    function tick() {
      var left = Math.max(0, Math.round((end - Date.now()) / 1000));
      var h = Math.floor(left / 3600);
      var m = Math.floor((left % 3600) / 60);
      var s = left % 60;
      el.textContent = left <= 0
        ? 'CLOSED'
        : ('0' + h).slice(-2) + ':' + ('0' + m).slice(-2) + ':' + ('0' + s).slice(-2) + ' REMAINING';
    }
    tick();
    window.setInterval(tick, 1000);
  });

  $$('[data-vote-form]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      var picked = form.querySelectorAll('input[name="options[]"]:checked');
      var min = parseInt(form.getAttribute('data-min') || '1', 10);
      var max = parseInt(form.getAttribute('data-max') || '1', 10);
      var errorBox = $('[data-vote-error]', form);

      if (picked.length < min || picked.length > max) {
        ev.preventDefault();
        if (errorBox) {
          errorBox.textContent = max === 1
            ? 'Choose one answer before voting.'
            : 'Choose between ' + min + ' and ' + max + ' answers before voting.';
          errorBox.classList.remove('hidden');
        }
        return;
      }
      if (errorBox) { errorBox.classList.add('hidden'); }

      if (window.fetch) {
        ev.preventDefault();
        var submit = form.querySelector('[type="submit"]');
        if (submit) { submit.disabled = true; submit.textContent = 'Recording vote…'; }
        fetch(form.getAttribute('action'), {
          method: 'POST',
          body: new FormData(form),
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
          .then(function (r) { return r.json().then(function (body) { return { ok: r.ok, body: body }; }); })
          .then(function (res) {
            if (!res.ok) {
              if (errorBox) {
                errorBox.textContent = res.body.message || 'We could not record your vote.';
                errorBox.classList.remove('hidden');
              }
              if (submit) { submit.disabled = false; submit.textContent = 'Submit vote'; }
              return;
            }
            form.setAttribute('data-voted', 'true');
            $$('input[name="options[]"]', form).forEach(function (i) { i.disabled = true; });
            if (submit) { submit.textContent = 'Your vote has been recorded.'; }
            var after = $('[data-after-vote]', form.closest('[data-vote-scope]') || document);
            if (after) { after.classList.remove('hidden'); }
            $$('[data-live-results]').forEach(function (root) {
              if (root.getAttribute('data-poll-id') === String(res.body.poll_id)) {
                applyResults(root, res.body);
              }
            });
            toast('Your vote has been recorded.', 'success');
          })
          .catch(function () { form.submit(); });
      }
    });
  });

  $$('.answer-row').forEach(function (row) {
    row.addEventListener('keydown', function (ev) {
      if (ev.key === ' ' || ev.key === 'Enter') {
        ev.preventDefault();
        var input = row.querySelector('input');
        if (input && !input.disabled) {
          input.checked = input.type === 'checkbox' ? !input.checked : true;
          input.dispatchEvent(new Event('change', { bubbles: true }));
        }
      }
    });
    row.setAttribute('tabindex', '0');
    row.setAttribute('role', 'presentation');
  });

  var optionList = $('#option-list');
  if (optionList) {
    var template = $('#option-row-template');

    function renumber() {
      $$('.option-row', optionList).forEach(function (row, i) {
        var num = $('[data-option-number]', row);
        if (num) { num.textContent = String(i + 1).padStart(2, '0'); }
        var input = $('input[type="text"]', row);
        if (input) { input.setAttribute('aria-label', 'Option ' + (i + 1)); }
      });
      var addBtn = $('#add-option');
      if (addBtn) { addBtn.disabled = $$('.option-row', optionList).length >= 12; }
    }

    $('#add-option') && $('#add-option').addEventListener('click', function () {
      if (!template) { return; }
      var node = template.content.cloneNode(true);
      optionList.appendChild(node);
      renumber();
      var rows = $$('.option-row', optionList);
      var last = rows[rows.length - 1];
      last && last.querySelector('input[type="text"]').focus();
    });

    optionList.addEventListener('click', function (ev) {
      var btn = ev.target.closest('button');
      if (!btn) { return; }
      var row = btn.closest('.option-row');
      if (!row) { return; }
      var action = btn.getAttribute('data-action');

      if (action === 'remove') {
        if ($$('.option-row', optionList).length <= 2) {
          toast('A poll needs at least two answers.', 'error');
          return;
        }
        row.remove();
        renumber();
      }
      if (action === 'up' && row.previousElementSibling) {
        optionList.insertBefore(row, row.previousElementSibling);
        renumber();
        row.querySelector('button[data-action="up"]').focus();
      }
      if (action === 'down' && row.nextElementSibling) {
        optionList.insertBefore(row.nextElementSibling, row);
        renumber();
        row.querySelector('button[data-action="down"]').focus();
      }
    });

    optionList.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' && ev.target.matches('input[type="text"]')) {
        ev.preventDefault();
        $('#add-option') && $('#add-option').click();
      }
    });

    renumber();
  }

  $$('[data-stepper]').forEach(function (wrap) {
    var input = wrap.querySelector('input');
    var step = parseInt(wrap.getAttribute('data-stepper'), 10) || 1;
    wrap.addEventListener('click', function (ev) {
      var btn = ev.target.closest('button');
      if (!btn || !input) { return; }
      var dir = btn.getAttribute('data-dir') === 'up' ? 1 : -1;
      var min = parseInt(input.min || '0', 10);
      var max = parseInt(input.max || '999999', 10);
      input.value = String(Math.min(max, Math.max(min, (parseInt(input.value, 10) || 0) + dir * step)));
    });
  });

  var actionPanel = $('#action-panel');
  if (actionPanel) {
    function selectRow(row) {
      $$('.table-row').forEach(function (r) { r.classList.remove('is-selected'); });
      row.classList.add('is-selected');
      actionPanel.setAttribute('data-poll-id', row.getAttribute('data-poll-id'));
      $$('[data-panel="title"]').forEach(function (el) { el.textContent = row.getAttribute('data-poll-title'); });
      $$('[data-panel="code"]').forEach(function (el) { el.textContent = row.getAttribute('data-poll-code'); });
      $$('[data-panel="status"]').forEach(function (el) { el.textContent = row.getAttribute('data-poll-status'); });
      $$('[data-panel="votes"]').forEach(function (el) { el.textContent = row.getAttribute('data-poll-votes'); });
      $$('[data-panel-link]').forEach(function (el) {
        var href = el.getAttribute('data-panel-link')
          .replace('__ID__', row.getAttribute('data-poll-id'))
          .replace('__SLUG__', row.getAttribute('data-poll-slug'));
        el.setAttribute('href', href);
      });
      $$('[data-panel-post]').forEach(function (el) {
        var idInput = el.querySelector('input[name="poll_id"]');
        if (idInput) { idInput.value = row.getAttribute('data-poll-id'); }
        var actionInput = el.querySelector('input[name="action"]');
        if (actionInput) {
          actionInput.value = row.getAttribute('data-poll-status') === 'Open' ? 'close' : 'reopen';
        }
        var label = el.querySelector('[data-post-label]');
        if (label) {
          label.textContent = row.getAttribute('data-poll-status') === 'Open' ? 'Close poll' : 'Reopen poll';
        }
      });
    }
    $$('.table-row').forEach(function (row) {
      row.addEventListener('click', function (ev) {
        if (ev.target.closest('a, button, form')) { return; }
        selectRow(row);
      });
      row.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') {
          ev.preventDefault();
          selectRow(row);
        }
      });
    });
    var first = $('.table-row');
    if (first) { selectRow(first); }
  }

  var modal = $('#confirm-modal');
  $$('[data-confirm-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (!modal) { return; }
      modal.classList.remove('hidden');
      var form = modal.querySelector('form');
      var id = btn.getAttribute('data-confirm-poll-id');
      if (form && id) {
        var idInput = form.querySelector('input[name="poll_id"]');
        if (idInput) { idInput.value = id; }
      }
      var action = btn.getAttribute('data-confirm-action');
      if (form && action) {
        var a = form.querySelector('input[name="action"]');
        if (a) { a.value = action; }
      }
      $$('[data-confirm-copy]', modal).forEach(function (el) {
        el.textContent = btn.getAttribute('data-confirm-copy') || el.textContent;
      });
      modal.querySelector('button').focus();
    });
  });
  $$('[data-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', function () { modal && modal.classList.add('hidden'); });
  });
  if (modal) {
    modal.addEventListener('keydown', function (ev) {
      if (ev.key === 'Escape') { modal.classList.add('hidden'); }
    });
  }

  $$('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var value = btn.getAttribute('data-copy');
      if (navigator.clipboard) {
        navigator.clipboard.writeText(value).then(function () {
          toast('Link copied to clipboard.', 'success');
        });
      } else {
        toast('Copy this link: ' + value);
      }
    });
  });

  $$('[data-filter]').forEach(function (chip) {
    chip.addEventListener('click', function () {
      var filter = chip.getAttribute('data-filter');
      $$('[data-filter]').forEach(function (c) {
        c.classList.remove('bg-ink', 'text-paper');
        c.classList.add('border-ink');
      });
      chip.classList.add('bg-ink', 'text-paper');
      $$('.table-row').forEach(function (row) {
        var state = row.getAttribute('data-state');
        row.style.display = (filter === 'all' || filter === state) ? '' : 'none';
      });
    });
  });
})();
