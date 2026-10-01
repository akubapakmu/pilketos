/**
 * SMP 1 DAWE — Pemilihan Ketua OSIS 2026
 * Analitik: "pill section header" + isi bagian dimuat lewat fetch (Stage 11).
 *
 * Setiap pill adalah tautan biasa (admin/analitik/<bagian>) yang dirender
 * penuh server, jadi tanpa JavaScript tetap berfungsi. Dengan skrip ini:
 * - klik pill -> GET URL itu dengan header X-Analytics-Pane; server hanya
 *   mengirim isi bagian ([data-pane]) yang lalu menggantikan isi
 *   [data-pane-host] tanpa memuat ulang halaman; URL & judul tab ikut
 *   berganti (history.pushState), tombol Back/Forward memuat bagian lagi;
 * - di dalam bagian (detail suara) filter, reset, dan pagination memakai
 *   jalur yang sama;
 * - "Perbarui" di bar live memuat ulang detail suara (bagian lain sudah
 *   diperbarui admin-live.js);
 * - gagal/timeout/sesi habis -> pindah halaman biasa (server yang memutuskan).
 * Stage 12: selama bagian diambil tampil kerangka "memuat" ([data-pane-loader])
 * setelah jeda singkat, bukan lagi garis progres di bawah pil (yang berkedip
 * untuk respon cepat). Topbar kini berisi breadcrumb, jadi tidak diubah.
 * Isi dari server sudah di-escape oleh view; skrip tidak membangun HTML dari
 * data mentah.
 */
(function () {
  'use strict';

  var nav = document.querySelector('[data-pane-nav]');
  var host = document.querySelector('[data-pane-host]');
  if (!nav || !host || !window.fetch || !window.history || !window.history.pushState || !('content' in document.createElement('template'))) {
    return;
  }

  var HEADER = 'X-Analytics-Pane';
  var track = nav.querySelector('.pills__track');
  var glider = nav.querySelector('[data-pane-glider]');
  var announcer = document.querySelector('[data-pane-announce]');
  var loader = document.querySelector('[data-pane-loader]');
  var loaderLabel = loader ? loader.querySelector('[data-pane-loader-label]') : null;
  var reduced = window.App && window.App.reducedMotion ? window.App.reducedMotion() : false;
  var links = Array.prototype.slice.call(nav.querySelectorAll('[data-pane-link]'));
  var paths = {};
  var controller = null;
  var requestId = 0;
  var LOADER_DELAY = 120; // respon lebih cepat dari ini: tanpa kerangka
  var LOADER_MIN = 320;   // kerangka yang sudah tampil tidak langsung hilang
  var loaderTimer = null;
  var loaderShownAt = 0;

  links.forEach(function (link) {
    paths[normalize(link.href)] = link.getAttribute('data-pane-link');
  });

  function normalize(href) {
    var url = new URL(href, window.location.href);
    return url.origin + url.pathname.replace(/\/+$/, '');
  }

  function paneOf(href) {
    try {
      var url = new URL(href, window.location.href);
      if (url.origin !== window.location.origin) {
        return null;
      }
      return paths[normalize(url.href)] || null;
    } catch (e) {
      return null;
    }
  }

  function current() {
    var pane = host.querySelector('[data-pane]');
    return pane ? pane.getAttribute('data-pane') : null;
  }

  /* -- pill aktif + penanda yang bergeser ----------------------------------- */
  function activeLink(slug) {
    for (var i = 0; i < links.length; i++) {
      if (links[i].getAttribute('data-pane-link') === slug) {
        return links[i];
      }
    }
    return null;
  }

  function moveGlider(link, animate) {
    if (!glider || !link) {
      return;
    }
    if (!animate) {
      glider.classList.add('is-instant');
    }
    glider.style.width = link.offsetWidth + 'px';
    glider.style.transform = 'translateX(' + link.offsetLeft + 'px)';
    glider.classList.add('is-ready');
    if (!animate) {
      void glider.offsetWidth;
      glider.classList.remove('is-instant');
    }
  }

  function revealPill(link) {
    if (!track || !link || track.scrollWidth <= track.clientWidth) {
      return;
    }
    var left = link.offsetLeft - (track.clientWidth - link.offsetWidth) / 2;
    if (typeof track.scrollTo === 'function') {
      track.scrollTo({ left: Math.max(0, left), behavior: reduced ? 'auto' : 'smooth' });
    } else {
      track.scrollLeft = Math.max(0, left);
    }
  }

  function markActive(slug, animate) {
    links.forEach(function (link) {
      if (link.getAttribute('data-pane-link') === slug) {
        link.setAttribute('aria-current', 'page');
      } else {
        link.removeAttribute('aria-current');
      }
    });
    var link = activeLink(slug);
    moveGlider(link, animate);
    revealPill(link);

    // Menu samping: "Analitik" vs "Detail suara".
    document.querySelectorAll('.admin-side__link').forEach(function (item) {
      var match = paneOf(item.href);
      if (match === null) {
        return;
      }
      var on = slug === 'suara' ? match === 'suara' : match === 'keseluruhan';
      if (on) {
        item.setAttribute('aria-current', 'page');
      } else {
        item.removeAttribute('aria-current');
      }
    });
  }

  /* -- kerangka "memuat" ------------------------------------------------------ */
  function showLoader(slug) {
    if (!loader) {
      return;
    }
    window.clearTimeout(loaderTimer);
    loaderTimer = window.setTimeout(function () {
      var link = activeLink(slug);
      if (loaderLabel) {
        loaderLabel.textContent = link ? 'Memuat ' + link.textContent.trim() + '\u2026' : 'Memuat bagian\u2026';
      }
      loader.hidden = false;
      host.classList.add('is-hidden');
      loaderShownAt = Date.now();
    }, LOADER_DELAY);
  }

  function hideLoader() {
    window.clearTimeout(loaderTimer);
    loaderTimer = null;
    loaderShownAt = 0;
    host.classList.remove('is-hidden');
    if (loader) {
      loader.hidden = true;
    }
  }

  // Sisa waktu tampil kerangka (0 bila kerangka belum sempat tampil).
  function loaderRemaining() {
    return loaderShownAt ? Math.max(0, LOADER_MIN - (Date.now() - loaderShownAt)) : 0;
  }

  /* -- memuat bagian --------------------------------------------------------- */
  function hardNavigate(url) {
    window.location.assign(url);
  }

  function stickyOffset() {
    var top = document.querySelector('.admin-top');
    return (top ? top.getBoundingClientRect().height : 0) + nav.getBoundingClientRect().height + 8;
  }

  function swap(pane, url, slug, options) {
    var focusId = options.focusId;
    var label = activeLink(slug) ? activeLink(slug).textContent.trim() : '';

    host.textContent = '';
    host.appendChild(pane);
    if (!reduced) {
      pane.classList.add('is-entering');
      window.setTimeout(function () {
        pane.classList.remove('is-entering');
      }, 400);
    }

    markActive(slug, true);

    var title = pane.getAttribute('data-pane-title');
    if (title) {
      document.title = title;
    }

    if (options.push) {
      window.history.pushState({ pane: slug }, '', url);
    }

    // Isi di atas layar (halaman sudah digulir jauh): kembali ke awal bagian.
    var top = host.getBoundingClientRect().top;
    if (top < 0) {
      window.scrollTo({ top: window.pageYOffset + top - stickyOffset(), behavior: reduced ? 'auto' : 'smooth' });
    }

    var focusTarget = focusId ? document.getElementById(focusId) : null;
    if (focusTarget && host.contains(focusTarget)) {
      focusTarget.focus({ preventScroll: true });
    } else if (options.focusHeading) {
      var heading = pane.querySelector('#pane-title');
      if (heading) {
        heading.focus({ preventScroll: true });
      }
    }

    if (announcer) {
      announcer.textContent = '';
      window.setTimeout(function () {
        announcer.textContent = (label ? 'Bagian ' + label + ' dimuat.' : 'Bagian dimuat.');
      }, 50);
    }

    document.dispatchEvent(new CustomEvent('osis:pane-loaded', { detail: { pane: slug } }));
  }

  function load(url, options) {
    options = options || {};
    var slug = paneOf(url);
    if (!slug) {
      hardNavigate(url);
      return;
    }

    if (controller) {
      controller.abort();
    }
    controller = window.AbortController ? new AbortController() : null;
    var id = ++requestId;
    var timedOut = false;
    var abortTimer = window.setTimeout(function () {
      timedOut = true;
      if (controller) {
        controller.abort();
      }
    }, 12000);

    host.classList.add('is-loading');
    host.setAttribute('aria-busy', 'true');
    showLoader(slug);
    if (options.optimistic !== false) {
      markActive(slug, true);
    }

    var headers = { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' };
    headers[HEADER] = '1';

    window.fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: headers,
      signal: controller ? controller.signal : undefined
    }).then(function (response) {
      if (id !== requestId) {
        return null;
      }
      if (response.status === 401) {
        return response.json().catch(function () { return {}; }).then(function (body) {
          hardNavigate(body && body.redirect ? body.redirect : url);
          return null;
        });
      }
      // Diarahkan ke halaman lain (mis. login) atau error: halaman penuh.
      if (!response.ok || response.redirected) {
        hardNavigate(response.redirected ? response.url : url);
        return null;
      }
      return response.text();
    }).then(function (html) {
      if (html === null || html === undefined || id !== requestId) {
        return;
      }
      var template = document.createElement('template');
      template.innerHTML = html;
      var pane = template.content.querySelector('[data-pane]');
      if (!pane) {
        hardNavigate(url);
        return;
      }
      return new Promise(function (resolve) {
        window.setTimeout(resolve, loaderRemaining());
      }).then(function () {
        if (id === requestId) {
          swap(pane, url, pane.getAttribute('data-pane') || slug, options);
        }
      });
    }).catch(function (error) {
      // Dibatalkan karena pill lain dipilih: abaikan. Timeout/jaringan: halaman biasa.
      if (id !== requestId || (error && error.name === 'AbortError' && !timedOut)) {
        return;
      }
      hardNavigate(url);
    }).then(function () {
      window.clearTimeout(abortTimer);
      if (id === requestId) {
        host.classList.remove('is-loading');
        host.removeAttribute('aria-busy');
        hideLoader();
      }
    });
  }

  function plainClick(event) {
    return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey && !event.defaultPrevented;
  }

  /* -- klik pill ---------------------------------------------------------------- */
  nav.addEventListener('click', function (event) {
    var link = event.target.closest('[data-pane-link]');
    if (!link || !plainClick(event)) {
      return;
    }
    event.preventDefault();
    if (link.getAttribute('aria-current') === 'page' && normalize(link.href) === normalize(window.location.href) && !window.location.search) {
      return;
    }
    load(link.href, { push: true });
  });

  /* -- tautan & filter di dalam bagian (reset, pagination, ...) ------------------ */
  host.addEventListener('click', function (event) {
    var link = event.target.closest('a[href]');
    if (!link || !plainClick(event) || link.target || link.hasAttribute('download') || !paneOf(link.href)) {
      return;
    }
    event.preventDefault();
    load(link.href, { push: true, focusHeading: true });
  });

  host.addEventListener('submit', function (event) {
    var form = event.target.closest('form[data-pane-form]');
    if (!form || (form.method || 'get').toLowerCase() !== 'get') {
      return;
    }
    event.preventDefault();

    var url = new URL(form.action, window.location.href);
    var params = new URLSearchParams();
    new FormData(form).forEach(function (value, key) {
      if (typeof value === 'string' && value.trim() !== '') {
        params.append(key, value.trim());
      }
    });
    url.search = params.toString();

    var active = document.activeElement;
    load(url.href, { push: true, focusId: active && form.contains(active) ? active.id : null });
  });

  /* -- Back / Forward ----------------------------------------------------------- */
  window.addEventListener('popstate', function () {
    if (paneOf(window.location.href)) {
      load(window.location.href, { push: false });
    }
  });

  /* -- Perbarui: detail suara tidak ikut live count, jadi dimuat ulang -------- */
  document.addEventListener('click', function (event) {
    var refresh = event.target.closest('[data-live-refresh]');
    if (refresh && current() === 'suara') {
      load(window.location.href, { push: false, optimistic: false });
    }
  });

  var resizeTimer = null;
  window.addEventListener('resize', function () {
    window.clearTimeout(resizeTimer);
    resizeTimer = window.setTimeout(function () {
      moveGlider(activeLink(current()), false);
    }, 100);
  });

  window.history.replaceState({ pane: current() }, '', window.location.href);
  nav.classList.add('is-enhanced');
  markActive(current(), false);
  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(function () {
      moveGlider(activeLink(current()), false);
    });
  }
})();
