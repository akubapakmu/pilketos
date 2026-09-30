/**
 * Profil paslon (Stage 15): siswa|guru/paslon/0X + pratinjau admin.
 * Markup: app/Views/paslon/partials/profile.php, gaya: paslon.css.
 *
 * Semua fitur progressive enhancement: tanpa JavaScript seluruh isi tampil
 * utuh, navigasi bagian berupa tautan anchor biasa, poster terbuka sebagai
 * file gambar, dan video tersedia lewat tautan "Buka di YouTube".
 *
 * - [data-px-nav]      : bagian aktif (aria-current) + rel progres; bar HP
 *                        menggeser tautan aktif ke area terlihat; rel desktop
 *                        muncul setelah pembuka terlewati.
 * - [data-px-reveal]   : muncul bertahap saat masuk layar (sekali).
 * - [data-px-parallax] : nomor raksasa & artwork bergerak halus (pembuka saja).
 * - [data-px-words]    : visi menyala kata demi kata mengikuti scroll.
 * - [data-px-zoom]     : poster dibuka di <dialog> (fokus & Escape bawaan).
 * - [data-px-video]    : iframe youtube-nocookie baru dibuat setelah tombol
 *                        putar ditekan (tanpa permintaan ke YouTube sebelumnya).
 *
 * prefers-reduced-motion: reveal, parallax, dan kata menyala tidak aktif
 * (konten langsung tampil); navigasi, lightbox, dan video tetap berfungsi.
 * Teks dari server hanya dipakai lewat textContent / setAttribute.
 */
(function () {
  'use strict';

  var reduced = window.App && window.App.reducedMotion ? window.App.reducedMotion() : false;
  var hasIO = 'IntersectionObserver' in window;

  /* ---------- navigasi bagian ---------- */

  function initNav(root, hero) {
    var nav = root.querySelector('[data-px-nav]');
    if (!nav) {
      return;
    }

    var links = Array.prototype.slice.call(nav.querySelectorAll('[data-px-nav-link]'));
    var fill = nav.querySelector('[data-px-nav-fill]');
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
      if (index >= 0 && list && list.scrollWidth > list.clientWidth) {
        var link = sections[index].link;
        var left = link.offsetLeft - (list.clientWidth - link.offsetWidth) / 2;
        list.scrollTo({ left: Math.max(0, left), behavior: reduced ? 'auto' : 'smooth' });
      }
    }

    var ticking = false;

    function update() {
      ticking = false;
      var line = window.innerHeight * 0.4;
      var index = -1;

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

      if (fill) {
        var first = sections[0].el.getBoundingClientRect().top + window.pageYOffset;
        var lastEl = sections[sections.length - 1].el;
        var last = lastEl.getBoundingClientRect().top + window.pageYOffset;
        var span = Math.max(1, last - first);
        var progress = (window.pageYOffset + line - first) / span;
        nav.style.setProperty('--px-progress', Math.max(0, Math.min(1, progress)).toFixed(3));
      }
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

    // Rel desktop muncul setelah pembuka terlewati.
    if (hero && hasIO) {
      new IntersectionObserver(function (entries) {
        nav.classList.toggle('is-visible', !entries[entries.length - 1].isIntersecting);
      }, { rootMargin: '-35% 0px 0px 0px' }).observe(hero);
    } else {
      nav.classList.add('is-visible');
    }
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

  /* ---------- visi: kata demi kata ---------- */

  function splitWords(block) {
    var p = block.querySelector('p') || block;
    var words = p.textContent.trim().split(/\s+/);
    p.textContent = '';

    words.forEach(function (word, index) {
      var span = document.createElement('span');
      span.className = 'w';
      span.textContent = word;
      p.appendChild(span);
      if (index < words.length - 1) {
        p.appendChild(document.createTextNode(' '));
      }
    });

    return p.querySelectorAll('.w');
  }

  function initWords(blocks) {
    var items = blocks.map(function (block) {
      return { block: block, words: splitWords(block), lit: -1 };
    });

    return function (viewportHeight) {
      items.forEach(function (item) {
        var rect = item.block.getBoundingClientRect();
        if (rect.bottom < 0 || rect.top > viewportHeight) {
          return;
        }

        // 0 saat blok masuk dari bawah (88% layar), 1 saat mencapai 40% layar.
        var startY = viewportHeight * 0.88;
        var endY = viewportHeight * 0.4;
        var progress = Math.min(1, Math.max(0, (startY - rect.top) / (startY - endY)));
        var lit = Math.round(progress * item.words.length) - 1;

        if (lit === item.lit) {
          return;
        }
        item.lit = lit;

        for (var i = 0; i < item.words.length; i++) {
          item.words[i].classList.toggle('is-lit', i <= lit);
        }
      });
    };
  }

  /* ---------- parallax pembuka + kata visi (satu rAF per frame) ---------- */

  function initScroll(hero, updateWords) {
    var layers = hero ? hero.querySelectorAll('[data-px-parallax]') : [];
    var heroVisible = true;
    var ticking = false;

    function frame() {
      ticking = false;
      var vh = window.innerHeight;

      if (heroVisible && layers.length) {
        var y = window.pageYOffset;
        for (var i = 0; i < layers.length; i++) {
          var speed = parseFloat(layers[i].getAttribute('data-px-parallax')) || 0;
          var shift = Math.max(-120, Math.min(120, y * speed));
          layers[i].style.setProperty('--px-shift', shift.toFixed(1));
        }
      }

      if (updateWords) {
        updateWords(vh);
      }
    }

    function request() {
      if (!ticking) {
        ticking = true;
        window.requestAnimationFrame(frame);
      }
    }

    if (hero) {
      new IntersectionObserver(function (entries) {
        heroVisible = entries[entries.length - 1].isIntersecting;
        request();
      }).observe(hero);
    }

    window.addEventListener('scroll', request, { passive: true });
    window.addEventListener('resize', request);
    request();
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

  /* ---------- video: facade -> iframe setelah diklik ---------- */

  function initVideo(root) {
    root.querySelectorAll('[data-px-video]').forEach(function (box) {
      var play = box.querySelector('[data-px-play]');
      var frame = box.querySelector('[data-px-video-frame]');
      var src = box.getAttribute('data-embed') || '';

      // Hanya URL embed mode privasi yang dibuat server.
      if (!play || !frame || src.indexOf('https://www.youtube-nocookie.com/embed/') !== 0) {
        return;
      }

      play.hidden = false;

      play.addEventListener('click', function () {
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
      });
    });
  }

  function init() {
    var root = document.querySelector('[data-paslon]');
    if (!root) {
      return;
    }

    var hero = root.querySelector('[data-px-hero]');

    initNav(root, hero);
    initLightbox(root);
    initVideo(root);

    if (reduced || !hasIO) {
      return;
    }

    // Gaya "belum muncul" hanya aktif setelah efek siap.
    root.classList.add('px-motion');
    initReveal(root);
    initScroll(hero, initWords(Array.prototype.slice.call(root.querySelectorAll('[data-px-words]'))));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
