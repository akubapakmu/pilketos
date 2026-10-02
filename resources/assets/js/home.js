/**
 * SMP 1 DAWE — Pemilihan Ketua OSIS 2026
 * Beranda imersif (Stage 5, STAGE5-NOTES.md) + sistem gerak Stage 6
 * (STAGE6-NOTES.md, 10-MOTION-AND-INTERACTION-SYSTEM-PILKETOS.md).
 *
 * 1. Layar pembuka: SELALU tampil setiap kali beranda dimuat penuh, durasi
 *    sama (minimal 1,5 detik, maksimal 5,2 detik) mengikuti aset layar
 *    pertama yang benar-benar dimuat (lockup, latar pembuka, latar hero,
 *    font layar pertama). Keluar = "kabut tersingkap": latar kabut memudar
 *    menjadi hero yang jernih di bingkai yang sama sementara lockup terbang
 *    ke navigasi. Pengecualian satu-satunya: muat ulang otomatis karena
 *    status pemilihan berubah (penanda sekali pakai sessionStorage
 *    "osis2026.skipIntro", non-sensitif; dibaca layouts/main.php).
 * 2. Scene layar penuh, satu per satu: roda mouse & trackpad (satu gestur =
 *    satu scene; inersia trackpad tidak melompati scene), geser sentuh,
 *    keyboard (panah, PageUp/PageDown, spasi, Home/End), navigasi scene,
 *    tautan "#", dan fokus Tab (scene mengikuti elemen yang difokus).
 *    Konten yang lebih tinggi dari layar (HP miring, zoom besar) digulir di
 *    dalam scene lebih dulu; scene berpindah di batas atas/bawahnya.
 * 3. Live count publik: GET hitung-suara sesuai irama dari server (30 detik),
 *    dijeda saat tab tidak aktif, angka bergulir halus saat berubah, muat
 *    ulang bila status pemilihan berubah (server merender keadaan baru).
 * 4. Hero berlapis: paralaks pointer berlapis (latar 6 px, lapisan depan
 *    14 px, tombol 3 px; desktop saja), canvas "embun / lubang
 *    coblos" yang hanya terlihat di sekitar pointer dan saat diketuk; portal
 *    Siswa/Guru yang mengikuti pointer.
 * 5. Stage 21: foto pasangan di scene perolehan suara dapat diklik untuk
 *    tampil ukuran penuh (<dialog>) dengan tautan "Lihat profil pasangan"
 *    bagi pengunjung yang login.
 * 6. Stage 24: kalimat hero dari admin (Teks Halaman Utama) diketik
 *    bergantian di tengah gambar; jeda 3 detik setelah kalimat terakhir.
 *
 * Semua gerak mengikuti prefers-reduced-motion. Teks dari server hanya
 * lewat textContent. Tanpa JavaScript beranda tetap halaman bergulir biasa.
 */
(function () {
  'use strict';

  var doc = document.documentElement;
  var body = document.body;
  var stage = document.querySelector('[data-scenes]');

  if (!stage || !window.requestAnimationFrame || !Element.prototype.closest) {
    return;
  }

  var reduced = window.App && window.App.reducedMotion ? window.App.reducedMotion() : false;
  var finePointer = !!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches);
  var SKIP_INTRO_KEY = 'osis2026.skipIntro';

  /* -- util ----------------------------------------------------------------- */
  function now() {
    return window.performance && window.performance.now ? window.performance.now() : Date.now();
  }

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function toArray(list) {
    return Array.prototype.slice.call(list);
  }

  function pad3(value) {
    var text = String(value);
    while (text.length < 3) {
      text = '0' + text;
    }
    return text;
  }

  /**
   * Muat ulang otomatis karena status pemilihan berubah (live count atau
   * hitung mundur): layar pembuka tidak diulang. Penanda sekali pakai, dibaca
   * & dihapus oleh skrip inline layouts/main.php pada pemuatan berikutnya.
   */
  function skipNextIntro() {
    try {
      window.sessionStorage.setItem(SKIP_INTRO_KEY, '1');
    } catch (e) {
      /* penyimpanan diblokir: layar pembuka tampil, tidak apa-apa */
    }
  }

  function currentHash() {
    try {
      return decodeURIComponent(window.location.hash.slice(1));
    } catch (e) {
      return '';
    }
  }

  /** Masih bisa digulir ke arah dir (1 = bawah, -1 = atas)? */
  function canScroll(el, dir) {
    if (!el || el.scrollHeight - el.clientHeight < 2) {
      return false;
    }
    return dir > 0 ? el.scrollTop + el.clientHeight < el.scrollHeight - 1 : el.scrollTop > 1;
  }

  function isTyping(el) {
    return !!el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName));
  }

  function activatesWithSpace(el) {
    return !!el && !!el.closest('button, a[href], summary, [role="button"], input, label');
  }

  /**
   * Selesai setelah gambar dimuat DAN diurai (img.decode()), bukan sekadar
   * "load": gambar yang baru dimuat bisa masih kosong satu-dua frame sebelum
   * terlukis (decoding="async", SVG besar). Gagal dimuat/diurai tetap selesai.
   */
  function whenImage(img, done) {
    if (!img) {
      done();
      return;
    }
    var decoded = function () {
      if (img.naturalWidth > 0 && typeof img.decode === 'function') {
        img.decode().then(done, done);
      } else {
        done();
      }
    };
    if (img.complete && img.naturalWidth > 0) {
      decoded();
      return;
    }
    var finish = function () {
      img.removeEventListener('load', finish);
      img.removeEventListener('error', finish);
      decoded();
    };
    img.addEventListener('load', finish);
    img.addEventListener('error', finish);
  }

  /**
   * Gambar scene lain (portal Siswa/Guru, foto pasangan) tidak ditunggu layar
   * pembuka (10-MOTION §10.4), tetapi diurai lebih dulu begitu aset layar
   * pertama siap, sehingga tidak muncul terlambat saat scene-nya dibuka.
   */
  function warmImages() {
    toArray(document.querySelectorAll('.portal__img, .pair__img')).forEach(function (img) {
      whenImage(img, function () {});
    });
  }

  /**
   * Tinggi navigasi & panel status yang sebenarnya (font, safe-area, dua baris
   * di HP) dipakai scene sebagai ruang atas/bawah: konten tidak pernah
   * tertutup. Disetel di <body> agar menimpa perkiraan CSS di .page-home.
   */
  function measureChrome() {
    var dock = document.querySelector('[data-dock]');
    var nav = document.querySelector('[data-site-nav]');
    var update = function () {
      if (dock) {
        body.style.setProperty('--dock-h', dock.offsetHeight + 'px');
      }
      if (nav) {
        body.style.setProperty('--nav-h', nav.offsetHeight + 'px');
      }
    };

    update();
    if (window.ResizeObserver) {
      var observer = new ResizeObserver(update);
      if (dock) {
        observer.observe(dock);
      }
      if (nav) {
        observer.observe(nav);
      }
    } else {
      window.addEventListener('resize', update);
    }
  }

  /* ==========================================================================
     Scene
     ========================================================================== */
  function Deck(root) {
    this.root = root;
    this.scenes = toArray(root.querySelectorAll('[data-scene]'));
    this.index = 0;
    this.busy = false;
    this.locked = false;
    this.pending = null;
    this.duration = reduced ? 0 : 800; /* --motion-scene */
    this.pager = toArray(document.querySelectorAll('[data-pager] [data-scene-link]'));
    this.announcer = document.querySelector('[data-scene-announce]');
    this.handlers = [];
  }

  Deck.prototype.indexOfId = function (id) {
    for (var i = 0; i < this.scenes.length; i++) {
      if (id !== '' && this.scenes[i].id === id) {
        return i;
      }
    }
    return -1;
  };

  Deck.prototype.indexOfElement = function (el) {
    var scene = el && el.closest ? el.closest('[data-scene]') : null;
    return scene ? this.scenes.indexOf(scene) : -1;
  };

  Deck.prototype.scroller = function () {
    return this.scenes[this.index].querySelector('[data-scene-scroll]');
  };

  Deck.prototype.onChange = function (handler) {
    this.handlers.push(handler);
  };

  Deck.prototype.start = function () {
    var hash = currentHash();
    var initial = this.indexOfId(hash);

    if (initial < 0 && hash !== '') {
      initial = this.indexOfElement(document.getElementById(hash));
    }
    this.index = Math.max(0, initial);

    // Urutan muncul bertahap di tiap scene.
    this.scenes.forEach(function (scene) {
      toArray(scene.querySelectorAll('[data-reveal]')).forEach(function (el, order) {
        el.style.setProperty('--order', order);
      });
    });

    // Posisi awal tanpa transisi, baru mode scene diaktifkan.
    var active = this.index;
    this.scenes.forEach(function (scene, i) {
      scene.classList.add('no-transition');
      scene.classList.toggle('is-active', i === active);
      scene.classList.toggle('is-before', i < active);
      scene.classList.toggle('is-after', i > active);
      scene.classList.remove('is-shown');
    });
    doc.classList.add('has-scenes');
    void this.root.offsetWidth;
    this.scenes.forEach(function (scene) {
      scene.classList.remove('no-transition');
    });

    // overflow: clip mencegah panggung ikut tergulir oleh fokus; pengaman lama:
    var root = this.root;
    root.addEventListener('scroll', function () {
      root.scrollTop = 0;
      root.scrollLeft = 0;
    });

    this.updateNav();
  };

  /** Munculkan isi scene aktif (setelah layar pembuka / saat pertama dibuka). */
  Deck.prototype.reveal = function () {
    this.scenes[this.index].classList.add('is-shown');
    this.emit();
  };

  Deck.prototype.updateNav = function () {
    var id = this.scenes[this.index].id;
    this.pager.forEach(function (link) {
      if (link.getAttribute('href') === '#' + id) {
        link.setAttribute('aria-current', 'true');
      } else {
        link.removeAttribute('aria-current');
      }
    });
    doc.setAttribute('data-scene', id);
  };

  Deck.prototype.emit = function () {
    var index = this.index;
    this.handlers.forEach(function (handler) {
      handler(index);
    });
  };

  Deck.prototype.announce = function (scene) {
    if (!this.announcer) {
      return;
    }
    var heading = document.getElementById(scene.getAttribute('aria-labelledby') || '');
    var label = heading ? heading.textContent.replace(/\s+/g, ' ').trim() : scene.id;
    this.announcer.textContent = 'Bagian ' + (this.scenes.indexOf(scene) + 1) + ' dari ' + this.scenes.length + ': ' + label;
  };

  Deck.prototype.writeHash = function (scene) {
    if (!window.history || !window.history.replaceState) {
      return;
    }
    // Scene pertama = alamat bersih; lainnya #id (muat ulang kembali ke sini).
    var url = this.scenes.indexOf(scene) === 0
      ? window.location.pathname + window.location.search
      : '#' + scene.id;
    try {
      window.history.replaceState(window.history.state, '', url);
    } catch (e) {
      /* abaikan */
    }
  };

  /**
   * Pindah ke scene target. opts: focus (pindahkan fokus ke scene), queue
   * (antrekan bila transisi sedang berjalan), fromFocus (dipicu Tab).
   */
  Deck.prototype.go = function (target, opts) {
    opts = opts || {};
    target = clamp(target, 0, this.scenes.length - 1);

    if (target === this.index) {
      return false;
    }
    if (this.busy) {
      if (opts.queue) {
        this.pending = { target: target, opts: opts };
      }
      return false;
    }

    var self = this;
    var from = this.scenes[this.index];
    var to = this.scenes[target];
    var forward = target > this.index;
    var focusWasInside = from.contains(document.activeElement);

    this.busy = true;

    // Scene lain (bila melompat) langsung ke sisi istirahatnya, tanpa animasi.
    this.scenes.forEach(function (scene, i) {
      if (scene === from || scene === to) {
        return;
      }
      scene.classList.add('no-transition');
      scene.classList.remove('is-active', 'is-shown');
      scene.classList.toggle('is-before', i < target);
      scene.classList.toggle('is-after', i > target);
    });

    // Scene tujuan mulai dari sisi datangnya; isi mulai dari tepi yang sesuai.
    to.classList.add('no-transition');
    to.classList.remove('is-active', 'is-shown', 'is-dragging', 'is-settling');
    to.classList.toggle('is-after', forward);
    to.classList.toggle('is-before', !forward);
    var toScroller = to.querySelector('[data-scene-scroll]');
    if (toScroller) {
      toScroller.scrollTop = forward ? 0 : toScroller.scrollHeight;
      // Dipicu Tab: elemen yang difokus tetap terlihat di dalam scene.
      if (opts.fromFocus && toScroller.contains(document.activeElement)) {
        document.activeElement.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      }
    }
    void to.offsetWidth;
    this.scenes.forEach(function (scene) {
      scene.classList.remove('no-transition');
    });

    // Maju: scene baru meluncur di atas; mundur: scene lama turun di atas.
    from.style.zIndex = forward ? '1' : '3';
    to.style.zIndex = forward ? '3' : '1';

    from.classList.remove('is-active', 'is-dragging', 'is-settling');
    from.style.removeProperty('--drag');
    from.classList.toggle('is-before', forward);
    from.classList.toggle('is-after', !forward);
    to.classList.remove('is-before', 'is-after');
    to.classList.add('is-active');

    this.index = target;
    this.updateNav();
    this.writeHash(to);

    window.setTimeout(function () {
      to.classList.add('is-shown');
    }, reduced ? 0 : Math.round(this.duration * 0.3));

    window.setTimeout(function () {
      from.classList.remove('is-shown');
      from.style.zIndex = '';
      to.style.zIndex = '';
      self.busy = false;
      self.emit();

      if (self.pending) {
        var next = self.pending;
        self.pending = null;
        self.go(next.target, next.opts);
      }
    }, this.duration + 60);

    if (!opts.fromFocus) {
      if (opts.focus || focusWasInside) {
        try {
          to.focus({ preventScroll: true });
        } catch (e) {
          to.focus();
        }
      } else {
        this.announce(to);
      }
    }

    return true;
  };

  Deck.prototype.step = function (dir, opts) {
    var target = this.index + dir;
    if (target < 0 || target >= this.scenes.length) {
      this.bump(dir);
      return false;
    }
    return this.go(target, opts);
  };

  /** Umpan balik di scene pertama/terakhir: tidak ada scene lagi. */
  Deck.prototype.bump = function (dir) {
    if (reduced || this.busy) {
      return;
    }
    var scene = this.scenes[this.index];
    var name = dir > 0 ? 'is-bump-up' : 'is-bump-down';
    scene.classList.remove('is-bump-up', 'is-bump-down');
    void scene.offsetWidth;
    scene.classList.add(name);
    window.setTimeout(function () {
      scene.classList.remove(name);
    }, 650);
  };

  /** Seret sentuh: scene aktif ikut jari dengan hambatan (terasa seperti app). */
  Deck.prototype.drag = function (dy) {
    if (reduced) {
      return;
    }
    var last = this.scenes.length - 1;
    var edge = (dy < 0 && this.index === last) || (dy > 0 && this.index === 0);
    var scene = this.scenes[this.index];
    scene.classList.add('is-dragging');
    scene.style.setProperty('--drag', clamp(dy * (edge ? 0.16 : 0.3), -90, 90).toFixed(1) + 'px');
  };

  Deck.prototype.settle = function (scene) {
    if (!scene.classList.contains('is-dragging')) {
      return;
    }
    scene.classList.remove('is-dragging');
    scene.classList.add('is-settling');
    scene.style.removeProperty('--drag');
    window.setTimeout(function () {
      scene.classList.remove('is-settling');
    }, 520);
  };

  Deck.prototype.bind = function () {
    var self = this;

    /* -- roda mouse & trackpad ---------------------------------------------- */
    var wheel = { last: 0, dir: 0, acc: 0, consumed: false, inner: false, recent: [] };

    // Gestur baru di tengah inersia: delta membesar lagi (bukan ekor inersia).
    function accelerating(list) {
      if (list.length < 6) {
        return false;
      }
      var n = list.length;
      var recent = (list[n - 1] + list[n - 2] + list[n - 3]) / 3;
      var before = (list[n - 4] + list[n - 5] + list[n - 6]) / 3;
      return list[n - 1] > 12 && recent > before * 1.3;
    }

    window.addEventListener('wheel', function (event) {
      if (event.ctrlKey || event.defaultPrevented) {
        return; // pinch-zoom trackpad
      }

      var dy = event.deltaY;
      var dx = event.deltaX;
      if (event.deltaMode === 1) {
        dy *= 16;
        dx *= 16;
      } else if (event.deltaMode === 2) {
        dy *= window.innerHeight;
        dx *= window.innerWidth;
      }
      if (dy === 0 || Math.abs(dx) > Math.abs(dy) || (event.target.closest && event.target.closest('dialog'))) {
        return;
      }

      var dir = dy > 0 ? 1 : -1;
      var t = now();
      var scroller = self.scroller();

      if (t - wheel.last > 200 || dir !== wheel.dir) {
        wheel.dir = dir;
        wheel.acc = 0;
        wheel.consumed = false;
        wheel.recent = [];
        // Gestur yang dimulai saat isi scene masih bisa digulir = milik isi scene.
        wheel.inner = canScroll(scroller, dir);
      }
      wheel.last = t;
      wheel.recent.push(Math.abs(dy));
      if (wheel.recent.length > 12) {
        wheel.recent.shift();
      }

      if (self.locked) {
        event.preventDefault();
        return;
      }

      if (wheel.inner) {
        if (scroller && !scroller.contains(event.target)) {
          // mis. roda di atas panel status: gulir isi scene aktif
          event.preventDefault();
          scroller.scrollTop += dy;
        }
        return;
      }

      event.preventDefault();

      if (self.busy) {
        wheel.consumed = true;
        return;
      }
      if (wheel.consumed) {
        if (!accelerating(wheel.recent)) {
          return;
        }
        wheel.consumed = false;
        wheel.acc = 0;
      }

      wheel.acc += Math.abs(dy);
      if (wheel.acc >= 40) {
        wheel.consumed = true;
        wheel.acc = 0;
        self.step(dir);
      }
    }, { passive: false });

    /* -- sentuh --------------------------------------------------------------- */
    var touch = null;

    window.addEventListener('touchstart', function (event) {
      // Stage 21: foto pasangan ukuran penuh terbuka: geser = gulir dialog,
      // bukan pindah scene.
      if (event.touches.length !== 1 || self.locked || document.querySelector('dialog[open]')) {
        touch = null;
        return;
      }
      var point = event.touches[0];
      var scroller = self.scroller();
      touch = {
        x: point.clientX,
        y: point.clientY,
        t: now(),
        dy: 0,
        axis: null,
        scene: self.scenes[self.index],
        canUp: canScroll(scroller, -1),
        canDown: canScroll(scroller, 1)
      };
    }, { passive: true });

    window.addEventListener('touchmove', function (event) {
      if (!touch || event.touches.length !== 1) {
        return;
      }
      var point = event.touches[0];
      var dx = point.clientX - touch.x;
      var dy = point.clientY - touch.y;

      if (touch.axis === null) {
        if (Math.abs(dx) < 8 && Math.abs(dy) < 8) {
          return;
        }
        touch.axis = Math.abs(dy) >= Math.abs(dx) ? 'y' : 'x';
      }
      if (touch.axis !== 'y') {
        return;
      }

      // Isi scene masih bisa digulir ke arah ini: biarkan gulir bawaan.
      if (dy < 0 ? touch.canDown : touch.canUp) {
        return;
      }
      if (event.cancelable) {
        event.preventDefault(); // tanpa pantulan / tarik-untuk-muat-ulang
      }
      touch.dy = dy;
      if (!self.busy) {
        self.drag(dy);
      }
    }, { passive: false });

    var endTouch = function () {
      if (!touch) {
        return;
      }
      var dy = touch.dy;
      var speed = Math.abs(dy) / Math.max(1, now() - touch.t);
      var scene = touch.scene;
      var moved = false;
      touch = null;

      if (dy !== 0 && !self.busy && (Math.abs(dy) > Math.min(90, window.innerHeight * 0.12) || (speed > 0.45 && Math.abs(dy) > 24))) {
        moved = self.step(dy < 0 ? 1 : -1);
      }
      if (!moved) {
        self.settle(scene);
      }
    };

    window.addEventListener('touchend', endTouch);
    window.addEventListener('touchcancel', endTouch);

    /* -- keyboard ------------------------------------------------------------ */
    document.addEventListener('keydown', function (event) {
      if (event.defaultPrevented || event.altKey || event.ctrlKey || event.metaKey || self.locked) {
        return;
      }
      if (isTyping(event.target) || document.querySelector('dialog[open]')) {
        return;
      }

      var dir = 0;
      var page = false;

      switch (event.key) {
        case 'ArrowDown':
        case 'Down':
          dir = 1;
          break;
        case 'ArrowUp':
        case 'Up':
          dir = -1;
          break;
        case 'PageDown':
          dir = 1;
          page = true;
          break;
        case 'PageUp':
          dir = -1;
          page = true;
          break;
        case ' ':
        case 'Spacebar':
          if (activatesWithSpace(event.target)) {
            return;
          }
          dir = event.shiftKey ? -1 : 1;
          page = true;
          break;
        case 'Home':
          event.preventDefault();
          self.go(0, { focus: true });
          return;
        case 'End':
          event.preventDefault();
          self.go(self.scenes.length - 1, { focus: true });
          return;
        default:
          return;
      }

      event.preventDefault();
      var scroller = self.scroller();
      if (canScroll(scroller, dir)) {
        scroller.scrollBy({
          top: dir * (page ? scroller.clientHeight * 0.85 : 80),
          behavior: reduced ? 'auto' : 'smooth'
        });
        return;
      }
      if (!self.busy) {
        self.step(dir, { focus: true });
      }
    });

    /* -- tautan "#scene" (CTA, navigasi scene, kembali ke awal) ---------------- */
    document.addEventListener('click', function (event) {
      if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
      }
      var link = event.target.closest ? event.target.closest('a[href^="#"]') : null;
      if (!link) {
        return;
      }
      var id = '';
      try {
        id = decodeURIComponent(link.getAttribute('href').slice(1));
      } catch (e) {
        return;
      }
      var index = self.indexOfId(id);
      if (index < 0) {
        return; // mis. tautan lewati ke konten (#main)
      }
      event.preventDefault();

      if (index === self.index) {
        var scroller = self.scroller();
        if (scroller && scroller.scrollTop > 0) {
          scroller.scrollTo({ top: 0, behavior: reduced ? 'auto' : 'smooth' });
        }
        return;
      }
      self.go(index, { focus: true, queue: true });
    });

    /* -- fokus Tab masuk ke scene lain: scene ikut pindah --------------------- */
    document.addEventListener('focusin', function (event) {
      var index = self.indexOfElement(event.target);
      if (index >= 0 && index !== self.index) {
        self.go(index, { queue: true, fromFocus: true });
      }
    });

    window.addEventListener('hashchange', function () {
      var index = self.indexOfId(currentHash());
      if (index >= 0 && index !== self.index) {
        self.go(index, { focus: true, queue: true });
      }
    });
  };

  /* ==========================================================================
     Layar pembuka
     ========================================================================== */
  function Intro(el) {
    this.el = el;
    this.fill = el.querySelector('[data-splash-fill]');
    this.pct = el.querySelector('[data-splash-pct]');
    this.brand = el.querySelector('[data-splash-brand]');
    this.bg = el.querySelector('[data-splash-bg]');
    this.shown = 0;
    this.loaded = 0;
    this.total = 0;
    this.finished = false;
    this.startedAt = now();
  }

  /* --motion-splash-min / --motion-splash-max (10-MOTION §2, §10.2) */
  Intro.MIN = 1500;
  Intro.MAX = 5200;

  Intro.prototype.run = function (done) {
    var self = this;
    var waits = [];

    this.done = done;
    this.el.classList.add('is-managed');
    doc.classList.add('intro-active');

    // Latar pembuka tampil langsung dari HTML (tidak menunggu skrip ini):
    // diminta sejak <head> (preload di home/index.php), jadi terlihat sejak
    // frame pertama, bukan setelah home.js berjalan.

    // Yang ditunggu (10-MOTION §10.4): lockup (pembuka & navigasi), latar
    // pembuka, latar hero yang dipilih <picture>, dan font layar pertama.
    // Gambar portal & foto pasangan TIDAK ditunggu (dimuat prioritas rendah),
    // sehingga durasi tetap sama dan tidak tertahan aset scene lain.
    // (DOMContentLoaded sudah terjadi saat init dijalankan.)
    var images = toArray(this.el.querySelectorAll('[data-splash-wait]'))
      .concat([this.bg])
      .concat(toArray(document.querySelectorAll('[data-nav-logo], [data-hero-img]')));

    images.forEach(function (img) {
      if (img) {
        waits.push(function (next) {
          whenImage(img, next);
        });
      }
    });
    if (document.fonts && document.fonts.load && window.Promise) {
      var fonts = ['600 16px "Plus Jakarta Sans"', '500 16px "JetBrains Mono"'];
      // Stage 24: kalimat hero (Newsreader) tidak berganti font saat diketik.
      if (document.querySelector('[data-hero-type]')) {
        fonts.push('400 16px "Newsreader"');
      }
      waits.push(function (next) {
        Promise.all(fonts.map(function (font) {
          return document.fonts.load(font);
        })).then(next, next);
      });
    }

    this.total = waits.length;
    waits.forEach(function (wait) {
      var called = false;
      wait(function () {
        if (!called) {
          called = true;
          self.loaded++;
          if (self.loaded === self.total) {
            warmImages();
          }
        }
      });
    });

    // Bilah dipacu waktu (tidak lebih cepat dari MIN) dan dibatasi aset nyata
    // (paling lama MAX). Gerak dikurangi: durasi sama, terisi bertahap linear.
    var tick = function () {
      if (self.finished) {
        return;
      }
      var elapsed = now() - self.startedAt;
      var real = self.total > 0 ? self.loaded / self.total : 1;
      var paced = Math.min(1, elapsed / Intro.MIN);
      var goal = elapsed >= Intro.MAX ? 1 : Math.min(real, paced);

      if (reduced) {
        self.shown = goal;
      } else {
        self.shown += (goal - self.shown) * 0.12;
        if (goal - self.shown < 0.003) {
          self.shown = goal;
        }
      }
      self.render();

      if (self.shown >= 1 && elapsed >= Intro.MIN) {
        self.finish();
        return;
      }
      window.requestAnimationFrame(tick);
    };
    window.requestAnimationFrame(tick);
  };

  Intro.prototype.render = function () {
    if (this.fill) {
      this.fill.style.setProperty('--p', this.shown.toFixed(4));
    }
    if (this.pct) {
      this.pct.textContent = pad3(Math.round(this.shown * 100));
    }
  };

  /**
   * Keluar "kabut tersingkap" (10-MOTION §10.3): pada t0 bilah memudar, latar
   * kabut memudar di atas hero yang sudah dirender, lockup terbang ke lockup
   * navigasi (shared element); reveal hero dimulai t0+260 ms, sebelum layar
   * pembuka benar-benar hilang. Gerak dikurangi: pudar 200 ms, tanpa terbang.
   */
  Intro.prototype.finish = function () {
    var self = this;
    var navBrand = document.querySelector('[data-nav-brand]');
    var brand = this.brand;

    this.finished = true;

    if (!reduced && brand && navBrand) {
      var a = brand.getBoundingClientRect();
      var b = navBrand.getBoundingClientRect();

      if (a.height > 0 && b.height > 0) {
        brand.style.animation = 'none';
        brand.style.opacity = '1';
        brand.style.transform = 'none';
        void brand.offsetWidth;
        this.el.classList.add('is-leaving');
        brand.style.transform = 'translate3d(' +
          ((b.left + b.width / 2) - (a.left + a.width / 2)).toFixed(1) + 'px,' +
          ((b.top + b.height / 2) - (a.top + a.height / 2)).toFixed(1) + 'px,0) scale(' +
          (b.height / a.height).toFixed(4) + ')';
      }
    }

    // Gerak dikurangi / tanpa navigasi: seluruh layar pembuka memudar.
    this.el.classList.add('is-leaving');

    window.setTimeout(function () {
      self.done();
    }, reduced ? 0 : 260);

    window.setTimeout(function () {
      doc.classList.remove('intro-active');
      window.setTimeout(function () {
        if (self.el.parentNode) {
          self.el.parentNode.removeChild(self.el);
        }
      }, 80);
    }, reduced ? 200 : 820);
  };

  /* ==========================================================================
     Live count publik
     ========================================================================== */
  function Live(el) {
    var self = this;
    this.el = el;
    this.url = el.getAttribute('data-live-url');
    this.status = el.getAttribute('data-live-status') || '';
    this.interval = parseInt(el.getAttribute('data-live-interval'), 10) || 0;
    this.timer = null;
    this.busy = false;
    this.stopped = false;
    this.failures = 0;
    this.pairs = {};
    this.fmtPct = new Intl.NumberFormat('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
    this.fmtInt = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 });

    toArray(el.querySelectorAll('[data-live-pair]')).forEach(function (item) {
      var num = item.querySelector('[data-live-pct]');
      if (num) {
        self.pairs[item.getAttribute('data-live-pair')] = {
          item: item,
          el: num,
          bar: item.querySelector('[data-live-bar]'),
          value: parseFloat(num.getAttribute('data-value')) || 0,
          raf: 0
        };
      }
    });

    var turnout = el.querySelector('[data-live-turnout]');
    this.turnout = turnout ? { el: turnout, value: parseFloat(turnout.getAttribute('data-value')) || 0, raf: 0 } : null;
    this.meter = el.querySelector('[data-live-meter]');
    this.voted = el.querySelector('[data-live-voted]');
    this.total = el.querySelector('[data-live-total]');
    this.updated = el.querySelector('[data-live-updated]');
    this.announcer = el.querySelector('[data-live-announce]');
  }

  Live.prototype.start = function () {
    var self = this;

    document.addEventListener('visibilitychange', function () {
      if (self.stopped) {
        return;
      }
      if (document.hidden) {
        window.clearTimeout(self.timer);
        self.timer = null;
      } else if (self.interval > 0) {
        self.tick();
      }
    });

    window.addEventListener('pageshow', function (event) {
      if (event.persisted && !self.stopped && self.interval > 0) {
        self.tick();
      }
    });

    this.schedule(this.interval);
  };

  Live.prototype.schedule = function (seconds) {
    window.clearTimeout(this.timer);
    this.timer = null;
    if (!this.stopped && seconds > 0 && !document.hidden) {
      this.timer = window.setTimeout(this.tick.bind(this), seconds * 1000);
    }
  };

  Live.prototype.tick = function () {
    if (this.busy || this.stopped || !window.fetch) {
      return;
    }
    var self = this;
    var controller = window.AbortController ? new AbortController() : null;
    var abortTimer = window.setTimeout(function () {
      if (controller) {
        controller.abort();
      }
    }, 10000);

    this.busy = true;
    window.fetch(this.url, {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      signal: controller ? controller.signal : undefined
    }).then(function (response) {
      if (response.status === 404) {
        self.stopped = true; // live count publik dimatikan panitia
        return null;
      }
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }
      return response.json();
    }).then(function (data) {
      if (!data) {
        return;
      }
      self.failures = 0;

      // Status berubah (dibuka/ditutup) atau susunan pasangan berubah:
      // biarkan server merender keadaan baru.
      if ((data.status || '') !== self.status || !self.samePairs(data.candidates || [])) {
        self.reload(data);
        return;
      }

      self.render(data);
      self.interval = data.poll && typeof data.poll.interval === 'number' ? data.poll.interval : 0;
      self.schedule(self.interval);
    }).catch(function () {
      self.failures++;
      self.schedule(Math.min(120, Math.max(self.interval, 10) * Math.pow(2, Math.min(self.failures, 3))));
    }).then(function () {
      window.clearTimeout(abortTimer);
      self.busy = false;
    });
  };

  Live.prototype.samePairs = function (candidates) {
    var ids = Object.keys(this.pairs);
    if (candidates.length !== ids.length) {
      return false;
    }
    for (var i = 0; i < candidates.length; i++) {
      if (!this.pairs[String(candidates[i].id)]) {
        return false;
      }
    }
    return true;
  };

  Live.prototype.reload = function (data) {
    this.stopped = true;
    skipNextIntro();
    if (this.announcer) {
      this.announcer.textContent = data.status === 'FINISHED'
        ? 'Pemilihan telah selesai. Halaman dimuat ulang.'
        : 'Status pemilihan berubah. Halaman dimuat ulang.';
    }
    window.setTimeout(function () {
      window.location.reload();
    }, 1500);
  };

  Live.prototype.render = function (data) {
    var self = this;

    (data.candidates || []).forEach(function (candidate) {
      var pair = self.pairs[String(candidate.id)];
      if (!pair) {
        return;
      }
      var value = Number(candidate.percent) || 0;
      if (Math.abs(value - pair.value) >= 0.005) {
        self.tween(pair, value);
        pair.item.classList.remove('is-changed');
        void pair.item.offsetWidth;
        pair.item.classList.add('is-changed');
      }
      if (pair.bar) {
        pair.bar.style.setProperty('--share', clamp(value, 0, 100).toFixed(2));
      }
    });

    if (data.turnout) {
      var share = Number(data.turnout.percent) || 0;
      if (this.turnout && Math.abs(share - this.turnout.value) >= 0.005) {
        this.tween(this.turnout, share);
      }
      if (this.meter) {
        this.meter.style.setProperty('--share', clamp(share, 0, 100).toFixed(2));
      }
      if (this.voted) {
        this.voted.textContent = this.fmtInt.format(Number(data.turnout.voted) || 0);
      }
      if (this.total) {
        this.total.textContent = this.fmtInt.format(Number(data.turnout.total) || 0);
      }
    }

    if (this.updated && data.updated_label) {
      this.updated.textContent = String(data.updated_label);
      if (data.updated_at) {
        this.updated.setAttribute('datetime', String(data.updated_at));
      }
    }
  };

  /** Angka bergulir halus dari nilai lama ke nilai baru. */
  Live.prototype.tween = function (target, value) {
    var fmt = this.fmtPct;
    var from = target.value;

    target.value = value;
    target.el.setAttribute('data-value', value.toFixed(2));
    window.cancelAnimationFrame(target.raf);

    if (reduced || document.hidden) {
      target.el.textContent = fmt.format(value);
      return;
    }

    var start = null;
    var frame = function (t) {
      if (start === null) {
        start = t;
      }
      var k = Math.min(1, (t - start) / 750); /* 600-900 ms (10-MOTION §9) */
      var eased = 1 - Math.pow(1 - k, 3);
      target.el.textContent = fmt.format(from + (value - from) * eased);
      target.raf = k < 1 ? window.requestAnimationFrame(frame) : 0;
    };
    target.raf = window.requestAnimationFrame(frame);
  };

  /* ==========================================================================
     Stage 24: kalimat hero diketik bergantian (admin > Teks Halaman Utama).
     Kalimat dibaca dari daftar pembaca layar ([data-hero-type-lines] li,
     textContent). Tiap kalimat diketik, tampil utuh, lalu dihapus mundur;
     setelah kalimat terakhir terhapus, jeda 3 detik lalu diulang dari awal.

     Tata letak tidak bergeser: pemenggalan baris diukur sekali dari kalimat
     utuh (text-wrap: balance di home.css), lalu tiap baris dibekukan menjadi
     blok tanpa bungkus (.hero-type__line). Huruf yang belum diketik / sudah
     dihapus tetap ada (transparan), dan caret (span kosong) tidak pernah
     berpindah baris. Diukur ulang tiap kalimat baru dan saat ukuran layar
     berubah. Berjalan hanya saat scene hero aktif & tab terlihat. Gerak
     dikurangi: kalimat utuh berganti tanpa efek ketik.
     ========================================================================== */
  function HeroType(el) {
    this.out = el.querySelector('[data-hero-type-text]');
    // spasi biasa saja yang dirapatkan: spasi tak terpisah (SMP 1 DAWE) tetap
    this.lines = toArray(el.querySelectorAll('[data-hero-type-lines] li')).map(function (li) {
      return li.textContent.replace(/[ \t\r\n]+/g, ' ').trim();
    }).filter(function (line) {
      return line !== '';
    });
    this.index = 0;
    this.chars = 0;
    this.segments = [];
    this.erasing = false;
    this.started = false;
    this.active = false;
    this.timer = 0;
    this.step = this.step.bind(this);
  }

  HeroType.TYPE = 55;    // ms per huruf diketik
  HeroType.JITTER = 30;  // variasi acak per huruf, agar terasa diketik tangan
  HeroType.STOP = 240;   // tambahan setelah tanda baca
  HeroType.HOLD = 2600;  // kalimat utuh terlihat sebelum dihapus
  HeroType.ERASE = 24;   // ms per huruf dihapus
  HeroType.GAP = 450;    // terhapus -> kalimat berikutnya
  HeroType.PAUSE = 3000; // semua kalimat sudah tampil -> diulang dari awal
  HeroType.START = 700;  // scene hero tampil -> huruf pertama
  HeroType.STILL = 4200; // gerak dikurangi: lama tiap kalimat utuh

  /** Ganti isi server (kalimat pertama) dengan panggung ketik. */
  HeroType.prototype.mount = function () {
    var self = this;
    var resizeTimer = 0;

    if (!this.out || this.lines.length === 0) {
      return false;
    }

    this.caret = document.createElement('span');
    this.caret.className = 'hero-type__caret';
    if (reduced) {
      this.chars = this.lines[0].length;
    }
    this.measure();

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        self.halt();
      } else {
        self.resume();
      }
    });
    window.addEventListener('resize', function () {
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(function () {
        self.measure();
      }, 150);
    });
    if (document.fonts && document.fonts.ready) {
      document.fonts.ready.then(function () {
        self.measure();
      });
    }
    return true;
  };

  /**
   * Pemenggalan baris kalimat ini: kalimat utuh dipasang sesaat (tanpa
   * sempat dilukis), posisi tiap huruf dibaca, lalu dirender per baris.
   * Spasi pemisah di ujung baris tidak ikut ke baris mana pun.
   */
  HeroType.prototype.measure = function () {
    var line = this.lines[this.index];
    var segments = [];
    var start = 0;
    var prevTop = null;
    var range = document.createRange();

    this.out.textContent = line;
    var node = this.out.firstChild;

    for (var i = 0; i < line.length; i++) {
      if (line.charAt(i) === ' ') {
        continue;
      }
      range.setStart(node, i);
      range.setEnd(node, i + 1);
      var rect = range.getBoundingClientRect();
      if (prevTop !== null && rect.height > 0 && rect.top - prevTop > rect.height / 2) {
        var end = i;
        while (end > start && line.charAt(end - 1) === ' ') {
          end--;
        }
        segments.push([start, end]);
        start = i;
      }
      prevTop = rect.top;
    }
    segments.push([start, line.length]);

    this.segments = segments;
    this.render();
  };

  HeroType.prototype.render = function () {
    var line = this.lines[this.index];
    var k = this.chars;
    var out = this.out;
    var caretAt = 0;

    // Caret di baris terakhir yang sudah mulai diketik.
    this.segments.forEach(function (seg, j) {
      if (seg[0] <= k) {
        caretAt = j;
      }
    });

    out.textContent = '';
    this.segments.forEach(function (seg, j) {
      var row = document.createElement('span');
      var cut = Math.max(seg[0], Math.min(k, seg[1]));
      row.className = 'hero-type__line';
      row.appendChild(document.createTextNode(line.slice(seg[0], cut)));
      if (j === caretAt && !reduced) {
        row.appendChild(this.caret);
      }
      if (cut < seg[1]) {
        var rest = document.createElement('span');
        rest.className = 'hero-type__rest';
        rest.textContent = line.slice(cut, seg[1]);
        row.appendChild(rest);
      }
      out.appendChild(row);
    }, this);
  };

  HeroType.prototype.setActive = function (active) {
    this.active = active;
    if (active) {
      this.resume();
    } else {
      this.halt();
    }
  };

  HeroType.prototype.resume = function () {
    if (this.timer || !this.active || document.hidden) {
      return;
    }
    var delay = this.started ? 400 : (reduced ? HeroType.STILL : HeroType.START);
    this.started = true;
    this.timer = window.setTimeout(this.step, delay);
  };

  HeroType.prototype.halt = function () {
    window.clearTimeout(this.timer);
    this.timer = 0;
    this.out.classList.remove('is-typing');
  };

  HeroType.prototype.step = function () {
    this.timer = 0;
    if (!this.active || document.hidden) {
      return;
    }
    var delay = reduced ? this.stepStill() : this.stepTyping();
    this.timer = window.setTimeout(this.step, delay);
  };

  /** Satu huruf diketik atau dihapus; mengembalikan jeda ke langkah berikutnya. */
  HeroType.prototype.stepTyping = function () {
    var line = this.lines[this.index];

    if (!this.erasing) {
      this.chars++;
      this.render();
      if (this.chars >= line.length) {
        this.erasing = true;
        this.out.classList.remove('is-typing');
        return HeroType.HOLD;
      }
      this.out.classList.add('is-typing');
      return HeroType.TYPE + Math.round(Math.random() * HeroType.JITTER)
        + (/[.,:;!?]/.test(line.charAt(this.chars - 1)) ? HeroType.STOP : 0);
    }

    this.chars--;
    if (this.chars > 0) {
      this.out.classList.add('is-typing');
      this.render();
      return HeroType.ERASE;
    }

    // Kalimat terhapus: kalimat berikutnya (transparan) disiapkan di tempat.
    var last = this.index === this.lines.length - 1;
    this.index = last ? 0 : this.index + 1;
    this.erasing = false;
    this.out.classList.remove('is-typing');
    this.measure();
    return last ? HeroType.PAUSE : HeroType.GAP;
  };

  /** Gerak dikurangi: kalimat utuh berganti, kosong 3 detik setelah terakhir. */
  HeroType.prototype.stepStill = function () {
    if (this.chars > 0) {
      var last = this.index === this.lines.length - 1;
      this.index = last ? 0 : this.index + 1;
      this.chars = 0;
      if (last) {
        this.measure();
        return HeroType.PAUSE;
      }
    }
    this.chars = this.lines[this.index].length;
    this.measure();
    return HeroType.STILL;
  };

  /* ==========================================================================
     Canvas hero "embun / lubang coblos" (10-MOTION §6): titik hanya terlihat
     di sekitar pointer (radius 140 px) dan sebagai riak saat diketuk; tidak
     ada bidang titik penuh atau gerak terus-menerus. Diredam di area fokus
     gambar & di belakang teks. Berjalan hanya saat ada interaksi, hero aktif,
     dan tab terlihat; DPR maks 2; mati saat gerak dikurangi.
     ========================================================================== */
  function Field(canvas, scene) {
    this.canvas = canvas;
    this.scene = scene;
    // Stage 24: tombol & kalimat hero (bila ada) sama-sama diredam.
    this.texts = toArray(scene.querySelectorAll('.hero, [data-hero-type-text]'));
    this.ctx = canvas.getContext('2d');
    this.pointer = { x: -1e4, y: -1e4, tx: -1e4, ty: -1e4, on: false, a: 0 };
    this.ripples = [];
    this.active = false;
    this.visible = !document.hidden;
    this.raf = 0;
    this.radius = 140;
    this.frame = this.frame.bind(this);
  }

  Field.GAP = 22;
  Field.ALPHA = 0.35;
  Field.RIPPLE_MS = 1400;

  Field.prototype.init = function () {
    var self = this;
    var resizeTimer = null;

    this.resize();

    var place = function (event) {
      var rect = self.canvas.getBoundingClientRect();
      self.pointer.tx = event.clientX - rect.left;
      self.pointer.ty = event.clientY - rect.top;
    };

    // Titik mengikuti pointer halus (mouse/pena) saja: di layar sentuh hanya
    // riak saat diketuk, tanpa perilaku mirip hover.
    this.scene.addEventListener('pointermove', function (event) {
      if (event.pointerType === 'touch') {
        return;
      }
      place(event);
      if (!self.pointer.on) {
        self.pointer.x = self.pointer.tx;
        self.pointer.y = self.pointer.ty;
      }
      self.pointer.on = true;
      self.kick();
    });
    this.scene.addEventListener('pointerdown', function (event) {
      place(event);
      self.ripples.push({ x: self.pointer.tx, y: self.pointer.ty, t0: now() });
      if (self.ripples.length > 3) {
        self.ripples.shift();
      }
      self.kick();
    });
    this.scene.addEventListener('pointerleave', function () {
      self.pointer.on = false;
      self.kick();
    });

    window.addEventListener('resize', function () {
      window.clearTimeout(resizeTimer);
      resizeTimer = window.setTimeout(function () {
        self.resize();
      }, 150);
    });

    document.addEventListener('visibilitychange', function () {
      self.visible = !document.hidden;
      self.kick();
    });
  };

  /**
   * Ukuran dari kotak layout (clientWidth/Height), bukan getBoundingClientRect:
   * hero bisa sedang "mundur" (transform) saat dibuka lewat #scene lain.
   */
  Field.prototype.resize = function () {
    var width = this.canvas.clientWidth;
    var height = this.canvas.clientHeight;
    var dpr = Math.min(window.devicePixelRatio || 1, 2);

    if (width === this.w && height === this.h && dpr === this.dpr) {
      return;
    }
    this.w = width;
    this.h = height;
    this.dpr = dpr;
    this.canvas.width = Math.max(1, Math.round(width * dpr));
    this.canvas.height = Math.max(1, Math.round(height * dpr));
    this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    this.ox = (width % Field.GAP) / 2;
    this.oy = (height % Field.GAP) / 2;
    this.ctx.clearRect(0, 0, width, height);
  };

  Field.prototype.setActive = function (active) {
    this.active = active;
    if (active && this.w !== undefined) {
      this.resize();
    }
    if (!active) {
      this.pointer.on = false;
      this.ripples = [];
      this.pointer.a = 0;
      this.ctx.clearRect(0, 0, this.w || 0, this.h || 0);
    }
    this.kick();
  };

  Field.prototype.kick = function () {
    if (!this.raf && this.active && this.visible) {
      this.raf = window.requestAnimationFrame(this.frame);
    }
  };

  /**
   * Peredam: area fokus gambar (punggungan & atap sekolah, dokumen 08 §5)
   * dan blok teks hero; titik tetap ada tetapi jauh lebih samar di sana.
   */
  Field.prototype.dampers = function () {
    var zones = [];
    var w = this.w;
    var h = this.h;

    if (w >= h) {
      zones.push([w * 0.5, h * 0.15, w * 0.88, h * 0.6]);
    } else {
      zones.push([w * 0.1, h * 0.12, w * 0.9, h * 0.5]);
    }
    if (this.texts.length > 0) {
      var c = this.canvas.getBoundingClientRect();
      this.texts.forEach(function (text) {
        var r = text.getBoundingClientRect();
        if (r.width > 0 && r.height > 0) {
          zones.push([r.left - c.left - 12, r.top - c.top - 12, r.right - c.left + 12, r.bottom - c.top + 12]);
        }
      });
    }
    return zones;
  };

  Field.prototype.frame = function (t) {
    this.raf = 0;
    if (!this.active || !this.visible) {
      return;
    }

    var p = this.pointer;
    var ripples = this.ripples;

    p.x += (p.tx - p.x) * 0.2;
    p.y += (p.ty - p.y) * 0.2;
    p.a += ((p.on ? 1 : 0) - p.a) * 0.18;
    if (!p.on && p.a < 0.01) {
      p.a = 0;
    }

    for (var i = ripples.length - 1; i >= 0; i--) {
      if (t - ripples[i].t0 > Field.RIPPLE_MS) {
        ripples.splice(i, 1);
      }
    }

    this.draw(t);

    var moving = Math.abs(p.tx - p.x) > 0.5 || Math.abs(p.ty - p.y) > 0.5;
    var fading = p.on ? p.a < 0.99 : p.a > 0;
    if (moving || fading || ripples.length > 0) {
      this.raf = window.requestAnimationFrame(this.frame);
    }
  };

  Field.prototype.draw = function (t) {
    var ctx = this.ctx;
    var gap = Field.GAP;
    var p = this.pointer;
    var radius = this.radius;
    var sources = [];
    var zones = this.dampers();

    ctx.clearRect(0, 0, this.w, this.h);

    if (p.a > 0) {
      sources.push({ x: p.x, y: p.y, reach: radius });
    }
    var waves = this.ripples.map(function (r) {
      var age = (t - r.t0) / Field.RIPPLE_MS;
      var wave = { x: r.x, y: r.y, radius: age * 420, strength: 1 - age };
      sources.push({ x: r.x, y: r.y, reach: wave.radius + 30 });
      return wave;
    });
    if (sources.length === 0) {
      return;
    }

    // Hanya sel kisi di sekitar sumber yang dihitung (bukan seluruh hero).
    var x0 = Infinity;
    var y0 = Infinity;
    var x1 = -Infinity;
    var y1 = -Infinity;
    sources.forEach(function (src) {
      x0 = Math.min(x0, src.x - src.reach);
      y0 = Math.min(y0, src.y - src.reach);
      x1 = Math.max(x1, src.x + src.reach);
      y1 = Math.max(y1, src.y + src.reach);
    });
    var i0 = Math.max(0, Math.floor((x0 - this.ox) / gap));
    var i1 = Math.min(Math.ceil(this.w / gap), Math.ceil((x1 - this.ox) / gap));
    var j0 = Math.max(0, Math.floor((y0 - this.oy) / gap));
    var j1 = Math.min(Math.ceil(this.h / gap), Math.ceil((y1 - this.oy) / gap));

    ctx.fillStyle = '#F2F1EC'; /* --on-night (Kabut) */

    for (var j = j0; j <= j1; j++) {
      var cy = this.oy + j * gap;
      for (var i = i0; i <= i1; i++) {
        var cx = this.ox + i * gap;
        var x = cx;
        var y = cy;
        var glow = 0;

        if (p.a > 0) {
          var dx = cx - p.x;
          var dy = cy - p.y;
          var d = Math.sqrt(dx * dx + dy * dy) || 1;
          if (d < radius) {
            var f = 1 - d / radius;
            f *= f;
            x += dx / d * f * 10;
            y += dy / d * f * 10;
            glow = f * p.a;
          }
        }

        for (var k = 0; k < waves.length; k++) {
          var w = waves[k];
          var wx = cx - w.x;
          var wy = cy - w.y;
          var wd = Math.sqrt(wx * wx + wy * wy) || 1;
          var band = 1 - Math.abs(wd - w.radius) / 30;
          if (band > 0) {
            var amp = band * w.strength;
            x += wx / wd * amp * 6;
            y += wy / wd * amp * 6;
            glow = Math.max(glow, amp);
          }
        }

        if (glow < 0.02) {
          continue;
        }

        var damp = 1;
        for (var z = 0; z < zones.length; z++) {
          var zone = zones[z];
          if (cx >= zone[0] && cx <= zone[2] && cy >= zone[1] && cy <= zone[3]) {
            damp = 0.3;
            break;
          }
        }

        var size = 1.2 + glow * 1.8;
        ctx.globalAlpha = Field.ALPHA * glow * damp;
        ctx.beginPath();
        ctx.arc(x, y, size / 2 + 0.3, 0, Math.PI * 2);
        ctx.fill();
      }
    }
    ctx.globalAlpha = 1;
  };

  /* ==========================================================================
     Paralaks berlapis hero (10-MOTION §5): latar 6 px, lapisan depan 14 px,
     tombol 3 px (nilai di home.css lewat --mx/--my). Pointer
     halus saja; lerp 0,08 per frame; berhenti saat hero tidak aktif atau tab
     tersembunyi. HP/tablet: tanpa paralaks, tanpa sensor orientasi.
     ========================================================================== */
  function Parallax(scene) {
    this.scene = scene;
    this.target = { x: 0, y: 0 };
    this.current = { x: 0, y: 0 };
    this.active = false;
    this.raf = 0;
    this.frame = this.frame.bind(this);
  }

  Parallax.prototype.init = function () {
    var self = this;

    window.addEventListener('pointermove', function (event) {
      if (event.pointerType !== 'mouse' || !self.active) {
        return;
      }
      self.target.x = clamp(event.clientX / window.innerWidth * 2 - 1, -1, 1);
      self.target.y = clamp(event.clientY / window.innerHeight * 2 - 1, -1, 1);
      self.kick();
    });

    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        window.cancelAnimationFrame(self.raf);
        self.raf = 0;
      }
    });
  };

  Parallax.prototype.setActive = function (active) {
    this.active = active;
    if (!active) {
      this.target.x = 0;
      this.target.y = 0;
      this.kick();
    }
  };

  Parallax.prototype.kick = function () {
    if (!this.raf && !document.hidden) {
      this.raf = window.requestAnimationFrame(this.frame);
    }
  };

  Parallax.prototype.frame = function () {
    var c = this.current;
    var t = this.target;

    this.raf = 0;
    c.x += (t.x - c.x) * 0.08;
    c.y += (t.y - c.y) * 0.08;
    this.scene.style.setProperty('--mx', c.x.toFixed(4));
    this.scene.style.setProperty('--my', c.y.toFixed(4));

    if (Math.abs(t.x - c.x) > 0.001 || Math.abs(t.y - c.y) > 0.001) {
      this.kick();
    }
  };

  /* -- portal Siswa/Guru: gambar mengikuti pointer (maks 10 px, home.css) -- */
  function initPortals() {
    if (!finePointer || reduced) {
      return;
    }
    toArray(document.querySelectorAll('[data-portal]')).forEach(function (portal) {
      portal.addEventListener('pointermove', function (event) {
        var rect = portal.getBoundingClientRect();
        portal.style.setProperty('--px', ((event.clientX - rect.left) / rect.width - 0.5).toFixed(3));
        portal.style.setProperty('--py', ((event.clientY - rect.top) / rect.height - 0.5).toFixed(3));
      });
      portal.addEventListener('pointerleave', function () {
        portal.style.setProperty('--px', '0');
        portal.style.setProperty('--py', '0');
      });
    });
  }

  /* -- Stage 21: foto pasangan ukuran penuh (<dialog>, home/partials/pair_view)
     Tombol di atas foto (button.pair__zoom) membuka dialog pasangan itu lewat
     showModal(): fokus terkunci di dalam, Escape menutup (bawaan), klik latar
     gelap & tombol tutup juga menutup, fokus kembali ke foto yang diklik.
     Tanpa dukungan <dialog>, tombol disembunyikan (foto tetap tampil). -- */
  function initPairViews() {
    toArray(document.querySelectorAll('[data-pair-zoom]')).forEach(function (button) {
      var dialog = document.getElementById(button.getAttribute('data-pair-zoom'));

      if (!dialog || typeof dialog.showModal !== 'function') {
        button.hidden = true;
        return;
      }

      button.addEventListener('click', function () {
        if (!dialog.open) {
          dialog.showModal();
        }
      });

      if (dialog.hasAttribute('data-pair-ready')) {
        return;
      }
      dialog.setAttribute('data-pair-ready', '');

      var close = dialog.querySelector('[data-pair-close]');
      if (close) {
        close.addEventListener('click', function () {
          dialog.close();
        });
      }

      // Klik di latar gelap (di luar foto & teks) menutup.
      dialog.addEventListener('click', function (event) {
        if (event.target === dialog || event.target.classList.contains('pair-view__inner') || event.target.classList.contains('pair-view__photo')) {
          dialog.close();
        }
      });

      dialog.addEventListener('close', function () {
        if (document.activeElement === document.body || dialog.contains(document.activeElement)) {
          button.focus({ preventScroll: true });
        }
      });
    });
  }

  /* ==========================================================================
     Mulai
     ========================================================================== */
  function init() {
    measureChrome();

    var deck = new Deck(stage);
    deck.start();
    deck.bind();

    var hero = stage.querySelector('.scene--hero');
    var heroIndex = deck.indexOfElement(hero);

    var canvas = stage.querySelector('[data-field]');
    var field = null;
    if (canvas && canvas.getContext && !reduced) {
      field = new Field(canvas, canvas.closest('[data-scene]'));
      deck.onChange(function (index) {
        field.setActive(index === heroIndex);
      });
    }

    // Stage 24: kalimat hero diketik hanya saat scene hero aktif.
    var typeEl = hero ? hero.querySelector('[data-hero-type]') : null;
    if (typeEl) {
      var heroType = new HeroType(typeEl);
      if (heroType.mount()) {
        deck.onChange(function (index) {
          heroType.setActive(index === heroIndex);
        });
      }
    }

    if (hero && finePointer && !reduced) {
      var parallax = new Parallax(hero);
      parallax.init();
      deck.onChange(function (index) {
        parallax.setActive(index === heroIndex);
      });
    }

    initPortals();
    initPairViews();

    // Muat ulang oleh hitung mundur (countdown.js) karena status berubah:
    // sama dengan live count, layar pembuka tidak diulang.
    document.addEventListener('osis:status-reload', skipNextIntro);

    var liveEl = stage.querySelector('[data-live]');
    if (liveEl && window.fetch && window.Intl) {
      new Live(liveEl).start();
    }

    var ready = function () {
      doc.classList.add('is-ready');
      if (field) {
        field.init();
      }
      deck.reveal();
    };

    var splash = document.querySelector('[data-splash]');
    if (splash && !doc.classList.contains('intro-seen') && window.getComputedStyle(splash).display !== 'none') {
      deck.locked = true;
      new Intro(splash).run(function () {
        deck.locked = false;
        ready();
      });
    } else {
      if (splash && splash.parentNode) {
        splash.parentNode.removeChild(splash);
      }
      warmImages();
      // Satu frame dengan keadaan tersembunyi dulu, agar transisi masuk berjalan.
      window.requestAnimationFrame(function () {
        window.requestAnimationFrame(ready);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
