/**
 * SMP 1 DAWE — Pemilihan Ketua OSIS 2026
 * Halaman Pengunjung online (Stage 19): muat ulang bagian halaman setiap
 * data-visitors-refresh detik tanpa muat ulang penuh.
 *
 * Halaman yang sama diambil lewat fetch (X-Requested-With: tidak dihitung
 * sebagai kunjungan), lalu setiap [data-live-region] diganti pasangannya
 * dari balasan, sama seperti live search (admin.js). Rincian User-Agent yang
 * sedang dibuka tetap terbuka. Tidak memuat ulang saat tab tersembunyi,
 * saat live search sedang berjalan, atau saat kursor sedang di kolom saring.
 */
(function () {
  'use strict';

  var page = document.querySelector('[data-visitors]');

  if (!page || !window.fetch || !window.DOMParser) {
    return;
  }

  var seconds = Math.max(10, parseInt(page.getAttribute('data-visitors-refresh'), 10) || 20);
  var timer = null;
  var busy = false;

  function regions() {
    return Array.prototype.slice.call(document.querySelectorAll('[data-live-region]'));
  }

  function idle() {
    var active = document.activeElement;
    var typing = active && active.closest && active.closest('form.filters') && active.matches('input, select');
    return document.visibilityState === 'visible'
      && !typing
      && !document.querySelector('[data-live-region][aria-busy]');
  }

  function openDetails() {
    return Array.prototype.map.call(document.querySelectorAll('details[data-visitor-detail][open]'), function (node) {
      return node.getAttribute('data-visitor-detail');
    });
  }

  function refresh() {
    if (busy || !idle()) {
      schedule();
      return;
    }

    busy = true;
    var keep = openDetails();

    window.fetch(window.location.href, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (response) {
      return response.ok && !response.redirected ? response.text() : null;
    }).then(function (html) {
      if (!html || !idle()) {
        return;
      }
      var doc = new DOMParser().parseFromString(html, 'text/html');
      regions().forEach(function (region) {
        var fresh = doc.querySelector('[data-live-region="' + region.getAttribute('data-live-region') + '"]');
        if (fresh) {
          region.parentNode.replaceChild(document.importNode(fresh, true), region);
        }
      });
      keep.forEach(function (id) {
        var node = document.querySelector('details[data-visitor-detail="' + id + '"]');
        if (node) {
          node.open = true;
        }
      });
    }).catch(function () {
      // jaringan putus sesaat: coba lagi pada putaran berikutnya
    }).then(function () {
      busy = false;
      schedule();
    });
  }

  function schedule() {
    window.clearTimeout(timer);
    timer = window.setTimeout(refresh, seconds * 1000);
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      refresh();
    }
  });

  schedule();
})();
