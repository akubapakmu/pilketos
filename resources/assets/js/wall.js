/**
 * Living Election Wall (Stage 26): GET partisipasi. Markup:
 * app/Views/wall/index.php ([data-wall]), gaya: wall.css + avatar.css.
 *
 * - Avatar DiceBear digambar malas oleh avatar-art.js (dimuat lewat import()
 *   dari URL ber-versi, data-art-module); tanpa modul tetap inisial.
 * - Toolbar: cari nama langsung saat mengetik (tanpa membedakan huruf besar &
 *   tanda diakritik), filter Kelas (semua / siswa / rombel / guru), Urutkan
 *   (nama, sudah dulu, belum dulu). Penyaringan di tempat: kartu yang tidak
 *   cocok disembunyikan, bagian & jenjang kosong ikut tersembunyi. Filter
 *   disimpan di query URL (history.replaceState).
 * - Buka/tutup bagian: "Buka semua / Tutup semua"; bagian yang ditutup
 *   diingat di sessionStorage (hanya nama rombel, bukan data pemilih).
 * - Pembaruan langsung: GET partisipasi/status tiap data-poll detik (20)
 *   selama pemilihan belum selesai & tab terlihat. Kartu yang baru
 *   berpartisipasi: lencana tergambar + satu denyut cahaya; unlock (sudah ->
 *   belum) diam-diam. Kartu hanya berpindah bila urutan = status (FLIP 260 ms).
 * - Gerak: kartu muncul bertahap (70 ms, dibatasi per bagian), angka
 *   bergulir, bar terisi, miring 3D halus saat diarahkan (pointer halus, satu
 *   listener terdelegasi). prefers-reduced-motion: semua langsung.
 */

const STAGGER = 70;
const STAGGER_MAX = 12;
const FLIP_MS = 260;
const ROLL_MS = 800;
const TILT_MAX = 8;
const STORE_KEY = 'osis2026.wall.closed';

const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;

const numberFormat = new Intl.NumberFormat('id-ID');
const percentFormat = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

function searchKey(text) {
  return text.normalize('NFD').replace(/\p{Mn}+/gu, '').toLowerCase();
}

function readClosed() {
  try {
    const value = JSON.parse(window.sessionStorage.getItem(STORE_KEY) || '[]');
    return new Set(Array.isArray(value) ? value : []);
  } catch (e) {
    return new Set();
  }
}

function writeClosed(set) {
  try {
    window.sessionStorage.setItem(STORE_KEY, JSON.stringify(Array.from(set)));
  } catch (e) {
    /* sessionStorage diblokir: status buka/tutup hanya untuk halaman ini */
  }
}

/** Angka bergulir dari nilai yang tampil ke nilai baru. */
function roll(el, to, format) {
  const from = Number(el.getAttribute('data-value') || el.textContent.replace(/\D+/g, '') || 0);
  el.setAttribute('data-value', String(to));
  if (reduced || from === to) {
    el.textContent = format(to);
    return;
  }
  const start = performance.now();
  const step = (now) => {
    const t = Math.min(1, (now - start) / ROLL_MS);
    const eased = 1 - Math.pow(1 - t, 3);
    el.textContent = format(Math.round(from + (to - from) * eased));
    if (t < 1) {
      requestAnimationFrame(step);
    }
  };
  requestAnimationFrame(step);
}

function init(root) {
  const form = root.querySelector('[data-wall-tools]');
  const q = form.querySelector('[data-wall-q]');
  const kelas = form.querySelector('[data-wall-kelas]');
  const sort = form.querySelector('[data-wall-sort]');
  const count = root.querySelector('[data-wall-count]');
  const toggle = root.querySelector('[data-wall-toggle]');
  const toggleLabel = root.querySelector('[data-wall-toggle-label]');
  const empty = root.querySelector('[data-wall-empty]');
  const notice = root.querySelector('[data-wall-notice]');
  const state = root.querySelector('[data-wall-state]');
  const groups = Array.from(root.querySelectorAll('.wall-group'));
  const grades = Array.from(root.querySelectorAll('.wall-grade'));
  const cards = new Map();
  const scopeEls = {
    voted: root.querySelector('[data-scope-voted]'),
    total: root.querySelector('[data-scope-total]'),
    noun: root.querySelector('[data-scope-noun]'),
    bar: root.querySelector('[data-scope-bar]'),
    percent: root.querySelector('[data-scope-percent]'),
    yes: root.querySelector('[data-scope-yes]'),
    no: root.querySelector('[data-scope-no]')
  };

  let totals = {};
  try {
    totals = JSON.parse(root.getAttribute('data-totals') || '{}');
  } catch (e) {
    totals = {};
  }

  // data-index = urutan nama dari server (kunci kedua semua urutan).
  root.querySelectorAll('.wall-card').forEach((card) => {
    cards.set(card.getAttribute('data-key'), card);
  });

  import(root.getAttribute('data-art-module')).catch(() => {
    /* tanpa modul avatar: inisial tetap tampil */
  });

  // -- buka / tutup bagian ----------------------------------------------
  // Event "toggle" datang belakangan (asinkron): bagian yang dibuka/ditutup
  // skrip ditandai agar tidak tercatat sebagai pilihan pengguna.
  const closed = readClosed();
  const scripted = new WeakSet();

  function setOpen(group, open) {
    if (group.open !== open) {
      scripted.add(group);
      group.open = open;
    }
  }

  function restoreOpen() {
    groups.forEach((group) => setOpen(group, !closed.has(group.getAttribute('data-group'))));
  }

  groups.forEach((group) => {
    group.addEventListener('toggle', () => {
      updateToggle();
      if (scripted.has(group)) {
        scripted.delete(group);
        return;
      }
      if (q.value.trim() !== '') {
        return;
      }
      const key = group.getAttribute('data-group');
      if (group.open) {
        closed.delete(key);
        reveal(group);
      } else {
        closed.add(key);
      }
      writeClosed(closed);
    });
  });

  function visibleGroups() {
    return groups.filter((group) => !group.hidden);
  }

  function updateToggle() {
    const shown = visibleGroups();
    toggle.hidden = shown.length === 0;
    const anyOpen = shown.some((group) => group.open);
    toggleLabel.textContent = anyOpen ? 'Tutup semua' : 'Buka semua';
    toggle.classList.toggle('is-collapsed', !anyOpen);
    toggle.setAttribute('aria-expanded', anyOpen ? 'true' : 'false');
  }

  toggle.addEventListener('click', () => {
    const open = !visibleGroups().some((group) => group.open);
    visibleGroups().forEach((group) => {
      setOpen(group, open);
      const key = group.getAttribute('data-group');
      if (open) {
        closed.delete(key);
        reveal(group);
      } else {
        closed.add(key);
      }
    });
    if (q.value.trim() === '') {
      writeClosed(closed);
    }
    updateToggle();
  });

  // -- urutan -----------------------------------------------------------
  function rank(card, mode) {
    if (mode === 'nama') {
      return 0;
    }
    const voted = card.classList.contains('is-voted');
    return (mode === 'sudah') === voted ? 0 : 1;
  }

  function sortGroup(group, mode, animate) {
    const list = group.querySelector('.wall-grid');
    const items = Array.from(list.children);
    const ordered = items.slice().sort((a, b) => rank(a, mode) - rank(b, mode)
      || Number(a.getAttribute('data-index')) - Number(b.getAttribute('data-index')));

    if (ordered.every((item, i) => item === items[i])) {
      return;
    }

    const flip = animate && !reduced && group.open && !group.hidden;
    const first = flip ? new Map(items.map((item) => [item, item.getBoundingClientRect()])) : null;

    ordered.forEach((item) => list.appendChild(item));

    if (!flip) {
      return;
    }
    ordered.forEach((item) => {
      if (item.hidden) {
        return;
      }
      const a = first.get(item);
      const b = item.getBoundingClientRect();
      const dx = a.left - b.left;
      const dy = a.top - b.top;
      if (!dx && !dy) {
        return;
      }
      item.animate([
        { transform: 'translate(' + dx + 'px, ' + dy + 'px)' },
        { transform: 'none' }
      ], { duration: FLIP_MS, easing: 'cubic-bezier(0.16, 1, 0.3, 1)' });
    });
  }

  // -- cakupan & angka --------------------------------------------------
  function scopeMatches(group, scope) {
    const type = group.getAttribute('data-type');
    if (scope === '') {
      return true;
    }
    if (scope === 'siswa') {
      return type === 'student';
    }
    if (scope === 'guru') {
      return type === 'teacher';
    }
    return type === 'student' && group.getAttribute('data-group') === scope;
  }

  function scopeNumbers(scope) {
    const pick = { '': ['all', 'pemilih'], siswa: ['students', 'siswa'], guru: ['teachers', 'guru'] }[scope];
    if (pick && totals[pick[0]]) {
      return { total: totals[pick[0]].total, voted: totals[pick[0]].voted, noun: pick[1] };
    }
    const group = groups.find((g) => g.getAttribute('data-type') === 'student' && g.getAttribute('data-group') === scope);
    if (!group) {
      return { total: 0, voted: 0, noun: 'pemilih' };
    }
    return {
      total: group.querySelectorAll('.wall-card').length,
      voted: group.querySelectorAll('.wall-card.is-voted').length,
      noun: 'siswa ' + scope
    };
  }

  function updateScope() {
    const n = scopeNumbers(kelas.value);
    const ratio = n.total > 0 ? n.voted / n.total : 0;
    roll(scopeEls.voted, n.voted, (v) => numberFormat.format(v));
    roll(scopeEls.yes, n.voted, (v) => numberFormat.format(v));
    roll(scopeEls.no, n.total - n.voted, (v) => numberFormat.format(v));
    scopeEls.total.textContent = numberFormat.format(n.total);
    scopeEls.noun.textContent = n.noun;
    scopeEls.percent.textContent = percentFormat.format(Math.round(ratio * 1000) / 10) + '%';
    scopeEls.bar.style.setProperty('--p', ratio.toFixed(4));
  }

  function updateGroupTally(group) {
    const total = group.querySelectorAll('.wall-card').length;
    const voted = group.querySelectorAll('.wall-card.is-voted').length;
    roll(group.querySelector('[data-group-voted]'), voted, (v) => numberFormat.format(v));
    group.querySelector('[data-group-total]').textContent = numberFormat.format(total);
    group.querySelector('[data-group-bar]').style.setProperty('--p', total > 0 ? (voted / total).toFixed(4) : '0');
  }

  // -- penyaringan di tempat ------------------------------------------
  let searching = false;

  function syncUrl() {
    const url = new URL(window.location.href);
    const params = { kelas: kelas.value, q: q.value.trim(), urut: sort.value === 'nama' ? '' : sort.value };
    Object.keys(params).forEach((key) => {
      if (params[key]) {
        url.searchParams.set(key, params[key]);
      } else {
        url.searchParams.delete(key);
      }
    });
    window.history.replaceState(null, '', url.pathname + url.search + url.hash);
  }

  function apply() {
    const term = searchKey(q.value.trim());
    const scope = kelas.value;
    let total = 0;

    groups.forEach((group) => {
      const inScope = scopeMatches(group, scope);
      let shown = 0;
      group.querySelectorAll('.wall-card').forEach((card) => {
        const show = inScope && (term === '' || card.getAttribute('data-name').includes(term));
        card.hidden = !show;
        shown += show ? 1 : 0;
      });
      group.hidden = shown === 0;
      if (term !== '' && shown > 0) {
        setOpen(group, true);
      }
      sortGroup(group, sort.value, false);
      total += shown;
    });

    if (term === '' && searching) {
      restoreOpen();
    }
    searching = term !== '';

    grades.forEach((grade) => {
      grade.hidden = !grade.querySelector('.wall-group:not([hidden])');
    });

    count.textContent = total === 0 ? 'Tidak ada nama yang cocok' : numberFormat.format(total) + ' nama';
    empty.hidden = total !== 0 || cards.size === 0;
    updateScope();
    updateToggle();
    syncUrl();
  }

  form.classList.add('is-live');
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    apply();
  });
  q.addEventListener('input', apply);
  kelas.addEventListener('change', apply);
  sort.addEventListener('change', apply);

  // -- kartu muncul bertahap ------------------------------------------
  let revealer = null;

  function stagger(group) {
    let i = 0;
    group.querySelectorAll('.wall-card').forEach((card) => {
      if (!card.hidden) {
        card.style.setProperty('--d', Math.min(i, STAGGER_MAX) * STAGGER + 'ms');
        i += 1;
      }
    });
  }

  /** Bagian dibuka lagi oleh pengguna: kartunya muncul bertahap ulang. */
  function reveal(group) {
    if (!revealer || !group.classList.contains('is-in')) {
      return;
    }
    group.classList.remove('is-in');
    stagger(group);
    void group.offsetWidth;
    group.classList.add('is-in');
  }

  if (!reduced && 'IntersectionObserver' in window) {
    root.classList.add('is-animated');
    revealer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          revealer.unobserve(entry.target);
          stagger(entry.target);
          entry.target.classList.add('is-in');
        }
      });
    }, { rootMargin: '0px 0px -8% 0px' });
    groups.forEach((group) => revealer.observe(group));
    requestAnimationFrame(() => requestAnimationFrame(() => root.classList.add('is-ready')));
  }

  // -- miring 3D halus (pointer halus, satu listener) ------------------
  if (finePointer && !reduced) {
    let active = null;
    let frame = 0;
    const sections = root.querySelector('[data-wall-sections]');

    const reset = () => {
      if (active) {
        active.classList.remove('is-tilting');
        active.style.removeProperty('--rx');
        active.style.removeProperty('--ry');
        active = null;
      }
    };

    sections.addEventListener('pointermove', (event) => {
      const card = event.target.closest('.wall-card');
      if (card !== active) {
        reset();
      }
      if (!card) {
        return;
      }
      active = card;
      const figure = card.querySelector('.wall-card__figure');
      const rect = figure.getBoundingClientRect();
      const px = Math.max(-0.5, Math.min(0.5, (event.clientX - rect.left) / rect.width - 0.5));
      const py = Math.max(-0.5, Math.min(0.5, (event.clientY - rect.top) / rect.height - 0.5));
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        card.classList.add('is-tilting');
        card.style.setProperty('--rx', (-py * 2 * TILT_MAX).toFixed(2) + 'deg');
        card.style.setProperty('--ry', (px * 2 * TILT_MAX).toFixed(2) + 'deg');
      });
    });
    sections.addEventListener('pointerleave', () => {
      cancelAnimationFrame(frame);
      reset();
    });
  }

  // -- pembaruan langsung ------------------------------------------------
  let interval = Number(root.getAttribute('data-poll')) || 0;
  let timer = 0;

  function setVoted(card, voted, animate) {
    card.classList.toggle('is-voted', voted);
    card.querySelector('[data-card-state]').textContent = voted ? 'Sudah berpartisipasi' : 'Belum berpartisipasi';
    if (animate) {
      card.classList.remove('is-new');
      void card.offsetWidth;
      card.classList.add('is-new');
      window.setTimeout(() => card.classList.remove('is-new'), 1400);
    } else {
      card.classList.remove('is-new');
    }
  }

  function applyStatus(data) {
    const voted = new Set(Array.isArray(data.voted) ? data.voted : []);
    const touched = new Set();

    cards.forEach((card, key) => {
      const was = card.classList.contains('is-voted');
      const now = voted.has(key);
      if (was !== now) {
        // Unlock (sudah -> belum) ditangani diam-diam, tanpa animasi.
        setVoted(card, now, now && !reduced);
        touched.add(card.closest('.wall-group'));
      }
    });

    if (data.totals) {
      totals = data.totals;
    }
    touched.forEach((group) => {
      updateGroupTally(group);
      if (sort.value !== 'nama') {
        sortGroup(group, sort.value, true);
      }
    });
    updateScope();

    if (typeof data.notice === 'string' && notice.textContent !== data.notice) {
      notice.textContent = data.notice;
    }
    if (data.status !== undefined && data.status !== root.getAttribute('data-status')) {
      root.setAttribute('data-status', data.status || '');
      state.className = 'wall-state wall-state--' + String(data.status || 'none').toLowerCase();
    }
    interval = data.poll && Number(data.poll.interval) > 0 ? Number(data.poll.interval) : 0;
  }

  function schedule() {
    window.clearTimeout(timer);
    if (interval > 0 && !document.hidden) {
      timer = window.setTimeout(poll, interval * 1000);
    }
  }

  function poll() {
    fetch(root.getAttribute('data-status-url'), {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    }).then((response) => (response.ok ? response.json() : Promise.reject(new Error(String(response.status)))))
      .then(applyStatus)
      .catch(() => { /* jaringan putus: coba lagi pada jadwal berikutnya */ })
      .finally(schedule);
  }

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      window.clearTimeout(timer);
    } else if (interval > 0) {
      poll();
    }
  });

  // -- mulai ------------------------------------------------------------
  restoreOpen();
  if (q.value.trim() !== '') {
    searching = true;
  }
  apply();
  // angka awal bergulir dari nol
  if (!reduced) {
    [scopeEls.voted, scopeEls.yes].forEach((el) => el.setAttribute('data-value', '0'));
    scopeEls.voted.textContent = '0';
    updateScope();
  }
  schedule();
}

const wall = document.querySelector('[data-wall]');
if (wall) {
  init(wall);
}
