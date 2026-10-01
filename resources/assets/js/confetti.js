/**
 * SMP 1 DAWE — Pemilihan Ketua OSIS 2026
 * Confetti halaman hasil akhir (Stage 4, MASTER section 17).
 *
 * Server hanya merender <canvas data-confetti> (dan memuat file ini) bila
 * pemilihan FINISHED menurut jam server dan ada tepat satu pasangan terpilih.
 * Skrip ini tidak memutuskan apa pun tentang hasil.
 *
 * - Kanvas 2D ringan tanpa library: potongan kertas berwarna SOLID (aksen
 *   pasangan, tinta, abu-abu). Tanpa emoji, tanpa gradient.
 * - Sekali per sesi browser (sessionStorage, penanda non-sensitif); bila
 *   storage diblokir, sekali per pemuatan halaman.
 * - prefers-reduced-motion: tidak dijalankan sama sekali.
 * - Tidak mengganggu interaksi: pointer-events none + aria-hidden, kanvas
 *   dihapus dari halaman setelah selesai (sekitar 5 detik).
 * - Hemat perangkat: jumlah potongan menyesuaikan luas layar & kemampuan HP,
 *   devicePixelRatio dibatasi 2, loop berhenti saat tab disembunyikan.
 */
(function () {
  'use strict';

  var canvas = document.querySelector('[data-confetti]');
  if (!canvas) {
    return;
  }

  var storageKey = 'osis2026.confetti.' + (canvas.getAttribute('data-confetti-key') || 'final');
  var reduced = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  function remove() {
    if (canvas.parentNode) {
      canvas.parentNode.removeChild(canvas);
    }
  }

  function alreadyShown() {
    try {
      return window.sessionStorage.getItem(storageKey) === '1';
    } catch (e) {
      return false;
    }
  }

  function markShown() {
    try {
      window.sessionStorage.setItem(storageKey, '1');
    } catch (e) {
      /* storage diblokir: cukup sekali per halaman */
    }
  }

  var ctx = canvas.getContext ? canvas.getContext('2d') : null;

  if (reduced || !ctx || !window.requestAnimationFrame || alreadyShown()) {
    remove();
    return;
  }

  var palette = (canvas.getAttribute('data-confetti-colors') || '').split(',').filter(function (color) {
    return /^#[0-9a-f]{6}$/i.test(color);
  });
  // Warna pertama = aksen pasangan terpilih, muncul paling sering.
  var colors = palette.length > 0 ? [palette[0], palette[0], palette[0]].concat(palette) : [];
  colors.push('#15141A', '#15141A', '#8D897D', '#E3E0D8');

  var DURATION = 5200;   // ms, seluruh efek
  var FADE = 900;        // ms terakhir memudar
  var GRAVITY = 900;     // px/s^2
  var width = 0;
  var height = 0;
  var dpr = 1;
  var pieces = [];
  var volleys = [];
  var started = 0;
  var last = 0;
  var rafId = null;
  var done = false;

  function rand(min, max) {
    return min + Math.random() * (max - min);
  }

  function resize() {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    width = window.innerWidth;
    height = window.innerHeight;
    canvas.width = Math.round(width * dpr);
    canvas.height = Math.round(height * dpr);
  }

  function budget() {
    var nav = window.navigator;
    var weak = (nav.deviceMemory && nav.deviceMemory <= 2) ||
      (nav.hardwareConcurrency && nav.hardwareConcurrency <= 2) ||
      (nav.connection && nav.connection.saveData === true);
    return Math.max(weak ? 60 : 90, Math.min(weak ? 80 : 200, Math.round(width * height / 4500)));
  }

  function makePiece(x, y, angle, speed) {
    var kind = Math.random();
    return {
      x: x,
      y: y,
      vx: Math.cos(angle) * speed,
      vy: Math.sin(angle) * speed,
      w: rand(7, 12),
      h: kind < 0.55 ? rand(11, 17) : rand(4, 7),
      round: kind > 0.9,
      rot: rand(0, Math.PI * 2),
      spin: rand(-8, 8),
      flip: rand(0, Math.PI * 2),
      flipSpeed: rand(5, 11),
      drag: rand(0.9, 1.6),
      fall: rand(110, 220),
      sway: rand(16, 40),
      color: colors[Math.floor(Math.random() * colors.length)]
    };
  }

  /** Semburan dari sudut kiri-bawah dan kanan-bawah ke arah tengah atas. */
  function burst(count) {
    for (var i = 0; i < count; i++) {
      var fromLeft = i % 2 === 0;
      var angle = (fromLeft ? -0.3 : -0.7) * Math.PI + rand(-0.26, 0.26);
      var speed = rand(0.8, 1.15) * 2 * height;
      pieces.push(makePiece(fromLeft ? width * 0.03 : width * 0.97, height + 12, angle, speed));
    }
  }

  /** Hujan singkat dari atas layar. */
  function rain(count) {
    for (var i = 0; i < count; i++) {
      pieces.push(makePiece(rand(0, width), rand(-140, -12), Math.PI / 2, rand(40, 120)));
    }
  }

  function step(dt) {
    for (var i = pieces.length - 1; i >= 0; i--) {
      var p = pieces[i];
      var damp = Math.exp(-p.drag * dt);

      p.vy += GRAVITY * dt;
      p.vx *= damp;
      p.vy = p.vy > 0 ? Math.min(p.vy, p.fall) : p.vy * damp;
      p.flip += p.flipSpeed * dt;
      p.rot += p.spin * dt;
      p.x += (p.vx + Math.sin(p.flip) * p.sway) * dt;
      p.y += p.vy * dt;

      if (p.y > height + 40 && p.vy > 0) {
        pieces.splice(i, 1);
      }
    }
  }

  function draw(alpha) {
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.globalAlpha = alpha;

    for (var i = 0; i < pieces.length; i++) {
      var p = pieces[i];
      var cos = Math.cos(p.rot);
      var sin = Math.sin(p.rot);
      var squash = Math.cos(p.flip); // kertas berputar: tampak menipis lalu melebar

      ctx.setTransform(dpr * cos, dpr * sin, -dpr * sin * squash, dpr * cos * squash, dpr * p.x, dpr * p.y);
      ctx.fillStyle = p.color;

      if (p.round) {
        ctx.beginPath();
        ctx.arc(0, 0, p.w / 2, 0, Math.PI * 2);
        ctx.fill();
      } else {
        ctx.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
      }
    }
  }

  function stop() {
    if (done) {
      return;
    }
    done = true;
    if (rafId !== null) {
      window.cancelAnimationFrame(rafId);
      rafId = null;
    }
    window.removeEventListener('resize', resize);
    document.removeEventListener('visibilitychange', onVisibility);
    remove();
  }

  function frame(time) {
    rafId = null;
    var elapsed = time - started;
    var dt = Math.min(0.05, Math.max(0.001, (time - last) / 1000));
    last = time;

    while (volleys.length > 0 && elapsed >= volleys[0].at) {
      volleys.shift().run();
    }

    step(dt);

    var remaining = DURATION - elapsed;
    if (remaining <= 0 || (volleys.length === 0 && pieces.length === 0)) {
      stop();
      return;
    }

    draw(remaining < FADE ? remaining / FADE : 1);
    rafId = window.requestAnimationFrame(frame);
  }

  function onVisibility() {
    // Tab disembunyikan di tengah efek: hentikan (tidak diulang saat kembali).
    if (document.hidden && started > 0) {
      stop();
    }
  }

  function start() {
    // Tab tersembunyi: tunggu sampai terlihat agar perayaan tidak "terpakai" tanpa dilihat.
    if (started > 0 || done || document.hidden) {
      return;
    }

    resize();
    var total = budget();
    volleys = [
      { at: 0, run: function () { burst(Math.round(total * 0.45)); } },
      { at: 250, run: function () { burst(Math.round(total * 0.25)); } },
      { at: 550, run: function () { rain(Math.round(total * 0.3)); } }
    ];

    markShown();
    canvas.hidden = false;
    window.addEventListener('resize', resize);
    started = window.performance && window.performance.now ? window.performance.now() : Date.now();
    last = started;
    rafId = window.requestAnimationFrame(function (time) {
      started = time;
      last = time;
      frame(time);
    });
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && started === 0 && !done) {
      window.setTimeout(start, 350);
    }
  });
  document.addEventListener('visibilitychange', onVisibility);

  // Beri waktu panggung pemenang tampil lebih dulu. Tab di latar belakang:
  // tunggu sampai tab dibuka (hanya sekali).
  if (!document.hidden) {
    window.setTimeout(start, 350);
  }
})();
