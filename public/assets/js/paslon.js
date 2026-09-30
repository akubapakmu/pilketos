/**
 * Profil paslon (Stage 15, redesain Stage 16): siswa|guru/paslon/0X +
 * pratinjau admin. Markup: app/Views/paslon/partials/profile.php, gaya: paslon.css.
 *
 * Semua fitur progressive enhancement: tanpa JavaScript seluruh isi tampil
 * utuh, navigasi bagian berupa tautan anchor biasa, poster terbuka sebagai
 * file gambar, dan thumbnail video membuka YouTube.
 *
 * - [data-px-nav]    : bagian aktif (aria-current; bawaan bagian pertama);
 *                      bar HP menggeser tautan aktif ke area terlihat. Di
 *                      desktop navigasi berupa kolom kanan yang selalu tampil.
 * - [data-px-reveal] : muncul bertahap saat masuk layar (sekali).
 * - [data-px-zoom]   : poster dibuka di <dialog> (fokus & Escape bawaan).
 * - [data-px-video]  : thumbnail asli YouTube (i.ytimg.com) + tombol putar;
 *                      iframe youtube-nocookie baru dibuat setelah diklik.
 *                      Thumbnail dinaikkan ke maxresdefault bila tersedia.
 *
 * prefers-reduced-motion: reveal tidak aktif (konten langsung tampil);
 * navigasi, lightbox, dan video tetap berfungsi.
 * Teks dari server hanya dipakai lewat textContent / setAttribute.
 */
(function () {
  'use strict';

  var reduced = window.App && window.App.reducedMotion ? window.App.reducedMotion() : false;
  var hasIO = 'IntersectionObserver' in window;

  /* ---------- navigasi bagian ---------- */

  function initNav(root) {
    var nav = root.querySelector('[data-px-nav]');
    if (!nav) {
      return;
    }

    var links = Array.prototype.slice.call(nav.querySelectorAll('[data-px-nav-link]'));
    var list = nav.querySelector('.px-nav__list');
    var sections = [];

    links.forEach(function (link) {
      var target = document.getElementById(link.getAttribute('href').slice(1));
      if (target) {
        sections.push({ link: link, el: target });
      }
    });

    if (sections.length === 0) {
      return;
    }

    var activeIndex = -1;

    function setActive(index) {
      if (index === activeIndex) {
        return;
      }
      activeIndex = index;

      sections.forEach(function (item, i) {
        if (i === index) {
          item.link.setAttribute('aria-current', 'true');
        } else {
          item.link.removeAttribute('aria-current');
        }
      });

      // Bar HP: tautan aktif digeser ke area terlihat tanpa menggulir halaman.
      if (list && list.scrollWidth > list.clientWidth) {
        var link = sections[index].link;
        var left = link.offsetLeft - (list.clientWidth - link.offsetWidth) / 2;
        list.scrollTo({ left: Math.max(0, left), behavior: reduced ? 'auto' : 'smooth' });
      }
    }

    var ticking = false;

    function update() {
      ticking = false;
      // Garis baca di sepertiga atas layar (maks. 260 px): bagian pendek yang
      // dituju lewat navigasi tetap menjadi bagian aktif, bukan bagian sesudahnya.
      var line = Math.min(window.innerHeight * 0.3, 260);
      var index = 0;

      for (var i = 0; i < sections.length; i++) {
        if (sections[i].el.getBoundingClientRect().top <= line) {
          index = i;
        }
      }

      // Ujung halaman: bagian terakhir aktif walau belum mencapai garis.
      var doc = document.documentElement;
      if (window.innerHeight + window.pageYOffset >= doc.scrollHeight - 4) {
        index = sections.length - 1;
      }

      setActive(index);
    }

    function request() {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(update);
      }
    }

    window.addEventListener('scroll', request, { passive: true });
    window.addEventListener('resize', request);
    request();
  }

  /* ---------- muncul bertahap ---------- */

  function initReveal(root) {
    var items = root.querySelectorAll('[data-px-reveal]');
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-in');
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    for (var i = 0; i < items.length; i++) {
      observer.observe(items[i]);
    }
  }

  /* ---------- lightbox poster ---------- */

  function initLightbox(root) {
    var dialog = root.querySelector('[data-px-lightbox]');
    if (!dialog || typeof dialog.showModal !== 'function') {
      return; // tanpa <dialog>: tautan membuka file poster biasa
    }

    var opener = null;

    root.querySelectorAll('[data-px-zoom]').forEach(function (el) {
      el.addEventListener('click', function (event) {
        event.preventDefault();
        opener = el.tagName === 'A' ? el : null;
        dialog.showModal();
      });
    });

    var close = dialog.querySelector('[data-px-close]');
    if (close) {
      close.addEventListener('click', function () {
        dialog.close();
      });
    }

    // Klik di luar gambar (latar gelap) menutup lightbox.
    dialog.addEventListener('click', function (event) {
      if (event.target === dialog) {
        dialog.close();
      }
    });

    dialog.addEventListener('close', function () {
      if (opener) {
        opener.focus();
      }
    });
  }

  /* ---------- video: thumbnail asli -> iframe setelah diklik ---------- */

  function upgradeThumb(img) {
    var hd = img.getAttribute('data-hd') || '';
    if (hd.indexOf('https://i.ytimg.com/vi/') !== 0) {
      return;
    }

    // maxresdefault tidak selalu ada: YouTube membalas gambar 120x90.
    var probe = new Image();
    probe.onload = function () {
      if (probe.naturalWidth > 120) {
        img.src = hd;
      }
    };
    probe.src = hd;
  }

  function initVideo(root) {
    root.querySelectorAll('[data-px-video]').forEach(function (box) {
      var play = box.querySelector('[data-px-play]');
      var frame = box.querySelector('[data-px-video-frame]');
      var thumb = box.querySelector('.px-video__thumb');
      var src = box.getAttribute('data-embed') || '';

      if (thumb) {
        if (thumb.complete && thumb.naturalWidth > 0) {
          upgradeThumb(thumb);
        } else {
          thumb.addEventListener('load', function onLoad() {
            thumb.removeEventListener('load', onLoad);
            upgradeThumb(thumb);
          });
        }
      }

      // Hanya URL embed mode privasi yang dibuat server; selain itu thumbnail
      // tetap berupa tautan ke YouTube.
      if (!play || !frame || src.indexOf('https://www.youtube-nocookie.com/embed/') !== 0) {
        return;
      }

      play.setAttribute('role', 'button');

      function start(event) {
        event.preventDefault();

        var iframe = document.createElement('iframe');
        iframe.setAttribute('src', src);
        iframe.setAttribute('title', box.getAttribute('data-title') || 'Video kampanye');
        iframe.setAttribute('allow', 'autoplay; encrypted-media; picture-in-picture; fullscreen');
        iframe.setAttribute('allowfullscreen', '');
        // Header aplikasi Referrer-Policy: same-origin tidak mengirim
        // referrer ke YouTube (pemutar menolak: error 153). Khusus iframe ini
        // cukup asal situs saja, tanpa path.
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        iframe.setAttribute('loading', 'eager');

        frame.textContent = '';
        frame.appendChild(iframe);
        iframe.focus();
      }

      play.addEventListener('click', start);
      // role="button": Spasi juga memutar (Enter sudah memicu click).
      play.addEventListener('keydown', function (event) {
        if (event.key === ' ' || event.key === 'Spacebar') {
          start(event);
        }
      });
    });
  }

  function init() {
    var root = document.querySelector('[data-paslon]');
    if (!root) {
      return;
    }

    initNav(root);
    initLightbox(root);
    initVideo(root);

    if (reduced || !hasIO) {
      return;
    }

    // Gaya "belum muncul" hanya aktif setelah efek siap.
    root.classList.add('px-motion');
    initReveal(root);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
