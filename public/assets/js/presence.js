/**
 * SMP 1 DAWE — Pemilihan Ketua OSIS 2026
 * Detak pengunjung online (Stage 19). Dimuat di semua layout setelah app.js
 * (partials/presence): selama halaman terbuka & terlihat, kirim detak ke
 * POST kunjungan/detak setiap data-presence-interval detik agar pengunjung
 * yang diam di satu halaman tetap terhitung online.
 *
 * Isi detak hanya data tampilan browser (ukuran layar & viewport, rasio
 * piksel, zona waktu, titik sentuh, jenis jaringan, tema). Identitas tetap
 * dibaca server dari cookie & sesi. Balasan berisi angka ringkas online,
 * dipakai untuk memperbarui setiap [data-online-count="total|guests|users"]
 * di halaman (beranda). Tab tersembunyi tidak mengirim detak; 401/403/404
 * (sesi habis, fitur dimatikan) menghentikan detak sampai halaman dimuat ulang.
 */
(function () {
  'use strict';

  var script = document.currentScript;

  if (!script || !window.fetch || !window.JSON) {
    return;
  }

  var url = script.getAttribute('data-presence-url');
  var interval = Math.max(30, parseInt(script.getAttribute('data-presence-interval'), 10) || 60) * 1000;
  var timer = null;
  var last = 0;
  var busy = false;
  var stopped = !url;
  var format = window.Intl && window.Intl.NumberFormat ? new window.Intl.NumberFormat('id-ID') : null;

  function size(width, height) {
    return Math.round(width) + 'x' + Math.round(height);
  }

  function info() {
    var connection = navigator.connection || {};
    var zone = '';
    var dark = false;

    try {
      zone = window.Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    } catch (e) {
      zone = '';
    }

    try {
      dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    } catch (e) {
      dark = false;
    }

    return {
      screen: size(window.screen.width, window.screen.height),
      viewport: size(window.innerWidth, window.innerHeight),
      dpr: window.devicePixelRatio || 1,
      tz: zone,
      touch: navigator.maxTouchPoints || 0,
      net: connection.effectiveType || '',
      scheme: dark ? 'dark' : 'light'
    };
  }

  function headers() {
    if (window.App && typeof window.App.jsonHeaders === 'function') {
      return window.App.jsonHeaders();
    }

    var meta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
    var result = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (meta) {
      result['X-CSRF-TOKEN'] = meta.getAttribute('content');
    }
    return result;
  }

  function render(online) {
    document.querySelectorAll('[data-online-count]').forEach(function (node) {
      var value = online[node.getAttribute('data-online-count')];
      if (typeof value === 'number') {
        node.textContent = format ? format.format(value) : String(value);
      }
    });
  }

  function schedule() {
    window.clearTimeout(timer);
    if (!stopped) {
      timer = window.setTimeout(beat, interval);
    }
  }

  function beat() {
    if (stopped || busy || document.visibilityState === 'hidden') {
      return;
    }

    busy = true;
    last = Date.now();

    window.fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: headers(),
      body: JSON.stringify(info())
    }).then(function (response) {
      if (response.status === 401 || response.status === 403 || response.status === 404) {
        stopped = true;
        return null;
      }
      return response.ok ? response.json() : null;
    }).then(function (data) {
      if (!data) {
        return;
      }
      if (typeof data.interval === 'number' && data.interval >= 30) {
        interval = data.interval * 1000;
      }
      if (data.online) {
        render(data.online);
      }
    }).catch(function () {
      // jaringan putus sesaat: coba lagi pada detak berikutnya
    }).then(function () {
      busy = false;
      schedule();
    });
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible' && Date.now() - last > interval / 2) {
      beat();
    }
  });

  // detak pertama segera setelah halaman tampil (membawa ukuran layar dst.)
  window.setTimeout(beat, 1500);
})();
