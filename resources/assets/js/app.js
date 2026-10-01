/**
 * SMP 1 DAWE — Pemilihan Ketua OSIS 2026
 * Foundation JS (Stage 1) + helper bersama Stage 2 (postJson, preferensi
 * efek, reduced motion). Interaksi voting/3D/countdown ada di file terpisah:
 * countdown.js, candidates.js, ballot.js, nail-webgl.js.
 *
 * Tidak menyimpan data siswa/guru di localStorage/sessionStorage.
 * localStorage hanya untuk preferensi non-sensitif (Stage 8: preferensi lama
 * "fx" dihapus oleh ballot.js; efek 3D kini selalu nyala).
 */
(function () {
  'use strict';

  /**
   * Token CSRF untuk request AJAX (Stage 2 voting, Stage 3 live count).
   * Sumber: <meta name="X-CSRF-TOKEN"> dari csrf_meta() di layout.
   * Contoh: fetch(url, { method: 'POST', headers: App.jsonHeaders(), body: ... })
   */
  function csrfHeader() {
    var meta = document.querySelector('meta[name="X-CSRF-TOKEN"]');
    var headers = {};
    if (meta) {
      headers['X-CSRF-TOKEN'] = meta.getAttribute('content');
    }
    return headers;
  }

  function jsonHeaders() {
    var headers = csrfHeader();
    headers['Accept'] = 'application/json';
    headers['Content-Type'] = 'application/json';
    headers['X-Requested-With'] = 'XMLHttpRequest';
    return headers;
  }

  /**
   * POST JSON dengan header CSRF. Resolve {status, data}; sesi habis (401)
   * langsung diarahkan ke halaman login dari field "redirect".
   * Gagal jaringan -> reject (pemanggil menampilkan pesan "coba lagi").
   */
  function postJson(url, body) {
    return fetch(url, {
      method: 'POST',
      headers: jsonHeaders(),
      credentials: 'same-origin',
      body: JSON.stringify(body || {})
    }).then(function (response) {
      return response.json().catch(function () {
        return {};
      }).then(function (data) {
        if (response.status === 401 && data && data.redirect) {
          window.location.assign(data.redirect);
        }
        return { status: response.status, data: data || {} };
      });
    });
  }

  function reducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  /**
   * Preferensi non-sensitif (mis. "fx" = 3d/2d). Dibungkus try/catch karena
   * localStorage bisa diblokir (mode privat / kebijakan browser sekolah).
   */
  var PREF_PREFIX = 'osis2026.';

  function getPref(key) {
    try {
      return window.localStorage.getItem(PREF_PREFIX + key);
    } catch (e) {
      return null;
    }
  }

  function setPref(key, value) {
    try {
      if (value === null) {
        window.localStorage.removeItem(PREF_PREFIX + key);
      } else {
        window.localStorage.setItem(PREF_PREFIX + key, value);
      }
    } catch (e) {
      /* abaikan: preferensi hanya kenyamanan */
    }
  }

  /** Modal berbasis <dialog> native. */
  function initModals() {
    document.addEventListener('click', function (event) {
      var opener = event.target.closest('[data-modal-open]');
      if (opener) {
        var dialog = document.getElementById(opener.getAttribute('data-modal-open'));
        if (dialog && typeof dialog.showModal === 'function') {
          event.preventDefault();
          dialog.showModal();
        }
        return;
      }

      var closer = event.target.closest('[data-modal-close]');
      if (closer) {
        var parent = closer.closest('dialog');
        if (parent) {
          event.preventDefault();
          parent.close();
        }
        return;
      }

      // Klik pada backdrop menutup modal (kecuali modal yang mengelola
      // penutupannya sendiri, mis. konfirmasi suara: data-modal-static).
      if (event.target.tagName === 'DIALOG' && event.target.open && !event.target.hasAttribute('data-modal-static')) {
        var rect = event.target.getBoundingClientRect();
        var inside = event.clientX >= rect.left && event.clientX <= rect.right &&
          event.clientY >= rect.top && event.clientY <= rect.bottom;
        if (!inside) {
          event.target.close();
        }
      }
    });
  }

  /**
   * Cegah submit ganda: tombol submit dinonaktifkan setelah form dikirim.
   * Keamanan tetap di server (CSRF, validasi, constraint DB).
   */
  function initSubmitGuard() {
    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
      }
      form.dataset.submitting = '1';

      var button = form.querySelector('button[type="submit"]');
      if (button) {
        button.setAttribute('aria-disabled', 'true');
        if (button.dataset.loadingText) {
          button.textContent = button.dataset.loadingText;
        }
      }
    });

    // Kembali lewat tombol back (bfcache): aktifkan lagi form.
    window.addEventListener('pageshow', function (event) {
      if (!event.persisted) {
        return;
      }
      document.querySelectorAll('form[data-submitting="1"]').forEach(function (form) {
        form.dataset.submitting = '';
        var button = form.querySelector('button[type="submit"]');
        if (button) {
          button.removeAttribute('aria-disabled');
        }
      });
    });
  }

  /** Pesan sukses boleh hilang otomatis; pesan error tetap tampil. */
  function initFlash() {
    document.querySelectorAll('[data-flash-dismiss]').forEach(function (el) {
      var timer = window.setTimeout(function () {
        el.setAttribute('hidden', 'hidden');
      }, 6000);

      el.addEventListener('mouseenter', function () {
        window.clearTimeout(timer);
      });
    });
  }

  /**
   * Stage 13: tombol mata "lihat kata sandi" (partials/password_field).
   * Tombol baru tampil di sini; saat form dikirim isian kembali tersembunyi
   * agar kata sandi tidak terlihat selama halaman berpindah.
   */
  function initPasswordToggles() {
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
      var input = document.getElementById(button.getAttribute('aria-controls'));
      var label = button.querySelector('[data-password-toggle-label]');
      if (!input) {
        return;
      }

      function set(visible) {
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', visible ? 'true' : 'false');
        button.classList.toggle('is-visible', visible);
        if (label) {
          label.textContent = visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi';
        }
      }

      button.hidden = false;
      button.addEventListener('click', function () {
        var end = input.value.length;
        set(input.type === 'password');
        input.focus();
        try {
          input.setSelectionRange(end, end);
        } catch (e) { /* jenis isian tertentu tidak mendukung seleksi */ }
      });
      if (input.form) {
        input.form.addEventListener('submit', function () {
          set(false);
        });
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initModals();
    initSubmitGuard();
    initFlash();
    initPasswordToggles();
  });

  window.App = {
    csrfHeader: csrfHeader,
    jsonHeaders: jsonHeaders,
    postJson: postJson,
    reducedMotion: reducedMotion,
    getPref: getPref,
    setPref: setPref
  };
})();
