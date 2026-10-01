/**
 * Pembuka alur memilih (Stage 22): satu layar satu langkah, tulisan besar
 * diketik di tengah layar. Markup: app/Views/voting/index.php, gaya: voting.css.
 *
 * - Langkah disalin ke "panggung" visual (aria-hidden); daftar asli tetap
 *   ada untuk pembaca layar (visually-hidden), jadi teks tidak terbaca
 *   setengah-setengah selama diketik.
 * - Tiap text node diketik terpisah sehingga <strong> tetap utuh. Huruf yang
 *   belum diketik tetap ada (transparan) agar baris tidak bergeser.
 * - Judul diketik, lalu deskripsi; jeda sebentar, langkah memudar, langkah
 *   berikutnya diketik. Setelah langkah terakhir tombol "Selanjutnya" muncul.
 * - Ketuk/klik layar: selesaikan langkah yang sedang diketik / lanjut.
 *   "Lewati": langsung ke akhir dan memfokuskan "Selanjutnya".
 * - Sudah ditonton di sesi ini (sessionStorage, non-sensitif) atau
 *   prefers-reduced-motion: tidak ada animasi, ketiga langkah tampil
 *   bertumpuk bersama tombolnya (sama dengan tanpa JavaScript).
 */
(function () {
  'use strict';

  var SEEN_KEY = 'osis2026.stepsSeen';
  var SPEED = { title: 62, desc: 19 }; // ms per huruf
  var GAP = 320;   // judul selesai -> deskripsi mulai
  var HOLD = 1900; // langkah utuh terlihat sebelum pindah
  var FADE = 380;  // sama dengan transisi .steps__item di voting.css

  var root = document.querySelector('[data-steps]');
  var reduced = window.App && window.App.reducedMotion ? window.App.reducedMotion() : false;

  if (!root || reduced || !document.createTreeWalker) {
    return;
  }

  function seen() {
    try {
      return window.sessionStorage.getItem(SEEN_KEY) === '1';
    } catch (e) {
      return false;
    }
  }

  function markSeen() {
    try {
      window.sessionStorage.setItem(SEEN_KEY, '1');
    } catch (e) { /* mode privat: animasi diputar lagi, tidak masalah */ }
  }

  if (seen()) {
    return;
  }

  var list = root.querySelector('[data-steps-list]');
  var end = root.querySelector('[data-steps-end]');
  var bar = root.querySelector('[data-steps-bar]');
  var skip = root.querySelector('[data-steps-skip]');
  var next = root.querySelector('[data-steps-next]');
  var dots = root.querySelectorAll('[data-steps-dot]');

  if (!list || !end || !bar || !skip) {
    return;
  }

  /* ---------- panggung visual ---------- */

  var stage = list.cloneNode(true);
  stage.removeAttribute('data-steps-list');
  stage.className = 'steps__stage';
  stage.setAttribute('aria-hidden', 'true');
  list.classList.add('visually-hidden');
  list.parentNode.insertBefore(stage, list);

  var caret = document.createElement('span');
  caret.className = 'steps__caret';

  var items = Array.prototype.slice.call(stage.querySelectorAll('[data-step]')).map(function (item) {
    var units = [];
    Array.prototype.forEach.call(item.querySelectorAll('[data-type]'), function (field) {
      var walker = document.createTreeWalker(field, NodeFilter.SHOW_TEXT, null);
      var nodes = [];
      while (walker.nextNode()) {
        nodes.push(walker.currentNode);
      }
      nodes.forEach(function (node) {
        var rest = document.createElement('span');
        rest.className = 'steps__rest';
        rest.textContent = node.nodeValue;
        units.push({ node: node, rest: rest, text: node.nodeValue, speed: SPEED[field.getAttribute('data-type')] || SPEED.desc, first: nodes[0] === node });
        node.nodeValue = '';
        node.parentNode.insertBefore(rest, node.nextSibling);
      });
    });
    return { el: item, units: units };
  });

  /* ---------- alur ---------- */

  var index = -1;
  var unit = 0;
  var chars = 0;
  var timer = null;
  var phase = 'idle'; // typing | holding | done

  function wait(fn, ms) {
    window.clearTimeout(timer);
    timer = window.setTimeout(fn, ms);
  }

  function setDots(current) {
    Array.prototype.forEach.call(dots, function (dot, i) {
      dot.classList.toggle('is-done', i < current);
      dot.classList.toggle('is-current', i === current);
    });
  }

  function fill(item) {
    item.units.forEach(function (u) {
      u.node.nodeValue = u.text;
      u.rest.textContent = '';
    });
  }

  function show(i) {
    items.forEach(function (item, k) {
      item.el.classList.toggle('is-active', k === i);
      item.el.classList.remove('is-leaving');
    });
    setDots(i);
  }

  function type() {
    var item = items[index];
    var u = item.units[unit];

    if (!u) {
      caret.remove();
      phase = 'holding';
      wait(index < items.length - 1 ? leave : finish, index < items.length - 1 ? HOLD : 600);
      return;
    }

    if (chars === 0 && u.first && unit > 0) {
      // judul selesai: jeda sebentar sebelum deskripsi
      chars = -1;
      u.rest.parentNode.insertBefore(caret, u.rest);
      wait(type, GAP);
      return;
    }

    chars = Math.max(chars, 0) + 1;
    u.node.nodeValue = u.text.slice(0, chars);
    u.rest.textContent = u.text.slice(chars);
    u.rest.parentNode.insertBefore(caret, u.rest);

    if (chars >= u.text.length) {
      unit++;
      chars = 0;
    }
    wait(type, u.speed);
  }

  function start(i) {
    index = i;
    unit = 0;
    chars = 0;
    phase = 'typing';
    show(i);
    type();
  }

  function leave() {
    items[index].el.classList.add('is-leaving');
    wait(function () {
      start(index + 1);
    }, FADE);
  }

  function finish() {
    window.clearTimeout(timer);
    caret.remove();
    items.forEach(fill);
    index = items.length - 1;
    show(index);
    setDots(items.length);
    phase = 'done';
    root.classList.add('is-done');
    skip.hidden = true;
    markSeen();
  }

  /** Ketuk layar: selesaikan ketikan langkah ini, atau langsung lanjut. */
  function advance() {
    if (phase === 'typing') {
      fill(items[index]);
      caret.remove();
      phase = 'holding';
      wait(index < items.length - 1 ? leave : finish, index < items.length - 1 ? 900 : 300);
    } else if (phase === 'holding') {
      if (index < items.length - 1) {
        leave();
      } else {
        finish();
      }
    }
  }

  root.addEventListener('click', function (event) {
    if (event.target.closest('a, button')) {
      return;
    }
    advance();
  });

  skip.addEventListener('click', function () {
    finish();
    if (next) {
      next.focus();
    }
  });

  root.classList.add('steps--live');
  bar.hidden = false;
  start(0);
})();
