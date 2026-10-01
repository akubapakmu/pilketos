/**
 * SMP 1 DAWE — Pemilihan Ketua OSIS 2026
 * Stage 9: login pemilih dua tahap (partials/voter_login.php, auth.css).
 *
 * Tahap 1 NISN/NIP (hanya diperiksa bentuknya di browser, tidak ke server),
 * tahap 2 kode unik. Form dikirim lewat fetch dengan header AJAX; server
 * menjawab JSON (BaseController::failLogin / loginSucceeded). Berhasil ->
 * gembok terbuka, lalu pindah ke dasbor. Jawaban yang bukan JSON (mis. token
 * CSRF kedaluwarsa) -> form dikirim ulang secara biasa agar server yang
 * menanganinya.
 *
 * Progressive enhancement: kelas .is-stepped baru dipasang di sini, jadi
 * tanpa skrip ini kedua isian tampil dan form terkirim biasa.
 * Tidak menyimpan NISN/NIP/kode unik di localStorage/sessionStorage.
 */
(function () {
  'use strict';

  var UNLOCK_MS = 1350;

  function reducedMotion() {
    return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  }

  function init(root) {
    var form = root.querySelector('[data-auth-form]');
    var idInput = root.querySelector('[data-auth-id]');
    var codeInput = root.querySelector('[data-auth-code]');
    var panels = root.querySelectorAll('[data-auth-panel]');
    var steps = root.querySelectorAll('[data-auth-step]');
    var nextButton = root.querySelector('[data-auth-next]');
    var backButton = root.querySelector('[data-auth-back]');
    var submitButton = root.querySelector('[data-auth-submit]');
    var echo = root.querySelector('[data-auth-echo]');
    var errorBox = root.querySelector('[data-auth-error]');
    var status = root.querySelector('[data-auth-status]');

    if (!form || !idInput || !codeInput || !nextButton || !errorBox || panels.length < 2) {
      return;
    }

    var idLabel = (root.querySelector('label[for="' + idInput.id + '"]') || {}).textContent || 'Identitas';
    var numeric = idInput.getAttribute('data-auth-numeric') === '1';
    var submitText = submitButton ? submitButton.textContent : 'Masuk';
    var current = 1;
    var busy = false;

    function announce(text) {
      if (status) {
        status.textContent = '';
        window.setTimeout(function () {
          status.textContent = text;
        }, 30);
      }
    }

    function showError(messages) {
      var list = [].concat(messages || []).filter(Boolean);
      if (!list.length) {
        errorBox.hidden = true;
        errorBox.textContent = '';
        return;
      }
      errorBox.textContent = list.join(' ');
      errorBox.hidden = false;
    }

    function deny() {
      root.classList.remove('is-denied');
      // reflow agar animasi getar bisa diulang
      void root.offsetWidth;
      root.classList.add('is-denied');
      window.setTimeout(function () {
        root.classList.remove('is-denied');
      }, 600);
    }

    /** Indikator: langkah < n selesai, n aktif; n = 4 berarti semua selesai. */
    function markSteps(n) {
      for (var i = 0; i < steps.length; i++) {
        var step = i + 1;
        steps[i].classList.toggle('is-done', step < n);
        steps[i].classList.toggle('is-current', step === n);
        if (step === n) {
          steps[i].setAttribute('aria-current', 'step');
        } else {
          steps[i].removeAttribute('aria-current');
        }
      }
    }

    function goTo(n, focus) {
      var forward = n > current;
      current = n;
      for (var i = 0; i < panels.length; i++) {
        var panel = panels[i];
        var active = Number(panel.getAttribute('data-auth-panel')) === n;
        panel.classList.toggle('is-active', active);
        panel.classList.remove('is-entering-next', 'is-entering-prev');
        if (active && !reducedMotion()) {
          void panel.offsetWidth;
          panel.classList.add(forward ? 'is-entering-next' : 'is-entering-prev');
        }
      }
      markSteps(n);
      if (focus) {
        (n === 1 ? idInput : codeInput).focus();
      }
    }

    function normalizedId() {
      return idInput.value.replace(/\s+/g, '');
    }

    function checkId() {
      var value = normalizedId();
      var max = Number(idInput.getAttribute('maxlength')) || 30;
      if (value === '') {
        return idLabel + ' wajib diisi.';
      }
      if (numeric && !/^[0-9]+$/.test(value)) {
        return idLabel + ' hanya berisi angka.';
      }
      if (value.length > max) {
        return idLabel + ' terlalu panjang.';
      }
      return '';
    }

    function next() {
      var problem = checkId();
      if (problem) {
        showError(problem);
        idInput.setAttribute('aria-invalid', 'true');
        deny();
        idInput.focus();
        return;
      }
      idInput.removeAttribute('aria-invalid');
      idInput.value = normalizedId();
      showError(null);
      if (echo) {
        echo.textContent = idInput.value;
      }
      goTo(2, true);
      announce('Langkah 2 dari 3: masukkan kode unik.');
    }

    function back() {
      showError(null);
      codeInput.value = '';
      goTo(1, true);
      announce('Langkah 1 dari 3: ' + idLabel + '.');
    }

    function setBusy(on) {
      busy = on;
      root.classList.toggle('is-busy', on);
      if (submitButton) {
        if (on) {
          submitButton.setAttribute('aria-disabled', 'true');
          submitButton.textContent = submitButton.getAttribute('data-loading-text') || submitText;
        } else {
          submitButton.removeAttribute('aria-disabled');
          submitButton.textContent = submitText;
        }
      }
    }

    function sameOrigin(url) {
      try {
        return new URL(url, window.location.href).origin === window.location.origin;
      } catch (e) {
        return false;
      }
    }

    function unlock(target) {
      root.classList.remove('is-busy');
      root.classList.add('is-unlocked');
      if (submitButton) {
        submitButton.textContent = 'Terbuka, mengarahkan...';
      }
      markSteps(3);
      announce('Kode cocok. Gembok terbuka, membuka dasbor.');
      window.setTimeout(function () {
        markSteps(4);
      }, 450);
      window.setTimeout(function () {
        window.location.assign(target);
      }, reducedMotion() ? 200 : UNLOCK_MS);
    }

    /** Jawaban bukan JSON: biarkan server menangani lewat kiriman biasa. */
    function fallback() {
      form.dataset.submitting = '1';
      HTMLFormElement.prototype.submit.call(form);
    }

    function send() {
      if (codeInput.value.replace(/[\s\-/.]+/g, '') === '') {
        showError('Kode unik wajib diisi.');
        codeInput.setAttribute('aria-invalid', 'true');
        deny();
        codeInput.focus();
        return;
      }
      codeInput.removeAttribute('aria-invalid');
      showError(null);
      setBusy(true);

      var body = new URLSearchParams(new FormData(form));

      fetch(form.action, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      }).then(function (response) {
        var type = response.headers.get('Content-Type') || '';
        if (type.indexOf('application/json') === -1) {
          throw new Error('not-json');
        }
        return response.json();
      }).then(function (data) {
        if (data && data.ok && data.redirect && sameOrigin(data.redirect)) {
          unlock(data.redirect);
          return;
        }

        setBusy(false);
        showError((data && data.messages) || 'Masuk belum berhasil. Coba lagi.');
        deny();
        if (data && data.step === 1) {
          codeInput.value = '';
          idInput.setAttribute('aria-invalid', 'true');
          goTo(1, true);
        } else {
          codeInput.value = '';
          codeInput.setAttribute('aria-invalid', 'true');
          codeInput.focus();
        }
      }).catch(function (error) {
        if (error && error.message === 'not-json') {
          fallback();
          return;
        }
        setBusy(false);
        showError('Koneksi terputus. Periksa jaringan, lalu coba lagi.');
        deny();
      });
    }

    // Siap: aktifkan mode bertahap.
    root.classList.add('is-stepped');

    nextButton.addEventListener('click', next);
    if (backButton) {
      backButton.addEventListener('click', back);
    }

    idInput.addEventListener('input', function () {
      idInput.removeAttribute('aria-invalid');
    });
    codeInput.addEventListener('input', function () {
      codeInput.removeAttribute('aria-invalid');
    });

    // Enter di tahap 1 = Lanjut; di tahap 2 = Masuk (lewat fetch).
    // stopPropagation: pencegah submit ganda app.js tidak ikut campur,
    // status tombol diatur sendiri di sini.
    form.addEventListener('submit', function (event) {
      if (form.dataset.submitting === '1') {
        return;
      }
      event.preventDefault();
      event.stopPropagation();
      if (busy || root.classList.contains('is-unlocked')) {
        return;
      }
      if (current === 1) {
        next();
      } else {
        send();
      }
    });

    // Kembali lewat bfcache setelah pindah halaman: kosongkan kode unik.
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) {
        root.classList.remove('is-unlocked', 'is-busy');
        setBusy(false);
        codeInput.value = '';
        form.dataset.submitting = '';
        goTo(normalizedId() === '' ? 1 : 2, false);
      }
    });

    // Setelah kiriman biasa ditolak server, NISN/NIP terisi ulang: langsung
    // ke tahap kode unik.
    if (normalizedId() !== '' && checkId() === '') {
      if (echo) {
        echo.textContent = normalizedId();
      }
      current = 2;
      for (var i = 0; i < panels.length; i++) {
        panels[i].classList.toggle('is-active', panels[i].getAttribute('data-auth-panel') === '2');
      }
      markSteps(2);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var roots = document.querySelectorAll('[data-auth]');
    for (var i = 0; i < roots.length; i++) {
      init(roots[i]);
    }
  });
})();
