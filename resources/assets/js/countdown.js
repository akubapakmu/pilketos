/**
 * Countdown pemilihan (Stage 2) — odometer bersegmen + garis waktu.
 *
 * Sumber kebenaran = server. Halaman membawa jam server (data-now) dan jadwal
 * (data-start / data-end, milidetik epoch). Waktu berjalan dihitung dengan
 * performance.now() (monoton), jadi mengubah jam perangkat tidak berpengaruh.
 * Countdown hanya visual: saat mencapai nol, status dikonfirmasi ke server
 * (GET jam-server) lalu halaman dimuat ulang agar server yang memutuskan.
 *
 * - tidak pernah negatif, berhenti pada status akhir;
 * - tick 1x per detik, dijeda saat tab tidak terlihat;
 * - sinkron ulang ke server hanya saat tab aktif kembali / hitungan habis.
 */
(function () {
  'use strict';

  var SECOND = 1000;
  var UNITS = ['days', 'hours', 'minutes', 'seconds'];

  function pad(value, length) {
    var text = String(value);
    while (text.length < length) {
      text = '0' + text;
    }
    return text;
  }

  /**
   * Status pemilihan di server berubah: muat ulang. Event diberitahukan dulu
   * agar beranda tidak mengulang layar pembuka (home.js, Stage 6).
   */
  function reloadForStatus() {
    try {
      document.dispatchEvent(new CustomEvent('osis:status-reload'));
    } catch (e) {
      /* browser lama tanpa CustomEvent: muat ulang biasa */
    }
    window.location.reload();
  }

  function split(ms) {
    var total = Math.max(0, Math.floor(ms / SECOND));
    return {
      days: Math.floor(total / 86400),
      hours: Math.floor((total % 86400) / 3600),
      minutes: Math.floor((total % 3600) / 60),
      seconds: total % 60
    };
  }

  /**
   * Satu keping digit odometer. Strip berisi 9,0,1,...,9 sehingga pergantian
   * 0 -> 9 (hitung mundur) tetap bergulir satu langkah, lalu melompat diam-diam.
   */
  function Odo() {
    var el = document.createElement('span');
    el.className = 'odo';
    var strip = document.createElement('span');
    strip.className = 'odo__strip is-instant';
    var html = '<span>9</span>';
    for (var d = 0; d <= 9; d++) {
      html += '<span>' + d + '</span>';
    }
    strip.innerHTML = html;
    el.appendChild(strip);

    this.el = el;
    this.strip = strip;
    this.value = null;
    var self = this;

    strip.addEventListener('transitionend', function () {
      if (self.value === 9 && self.pos === 0) {
        self.jump(10);
      }
    });
  }

  Odo.prototype.jump = function (pos) {
    var strip = this.strip;
    strip.classList.add('is-instant');
    strip.style.setProperty('--pos', pos);
    this.pos = pos;
    // paksa reflow agar transisi berikutnya berjalan normal
    void strip.offsetHeight;
    strip.classList.remove('is-instant');
  };

  Odo.prototype.set = function (digit, animate) {
    if (digit === this.value) {
      return;
    }
    var previous = this.value;
    this.value = digit;

    if (!animate || previous === null) {
      this.jump(digit + 1);
      return;
    }

    // Hitung mundur 0 -> 9: gulir ke "9" di atas "0", lalu lompat ke posisi 10.
    var pos = (previous === 0 && digit === 9) ? 0 : digit + 1;
    this.strip.classList.remove('is-instant');
    this.strip.style.setProperty('--pos', pos);
    this.pos = pos;
  };

  /** Deretan keping untuk satu satuan (hari/jam/menit/detik). */
  function DigitGroup(container) {
    this.container = container;
    this.odos = [];
  }

  DigitGroup.prototype.render = function (value, animate) {
    var text = pad(value, 2);

    if (this.odos.length !== text.length) {
      this.container.textContent = '';
      this.odos = [];
      for (var i = 0; i < text.length; i++) {
        var odo = new Odo();
        this.odos.push(odo);
        this.container.appendChild(odo.el);
      }
      animate = false;
    }

    for (var j = 0; j < text.length; j++) {
      this.odos[j].set(parseInt(text.charAt(j), 10), animate);
    }
  };

  function Countdown(root) {
    this.root = root;
    this.status = root.getAttribute('data-status');
    this.start = parseInt(root.getAttribute('data-start'), 10) || null;
    this.end = parseInt(root.getAttribute('data-end'), 10) || null;
    this.clockUrl = root.getAttribute('data-clock-url');
    this.label = root.querySelector('[data-countdown-label]');
    this.fill = root.querySelector('[data-timeline-fill]');
    this.marker = root.querySelector('[data-timeline-marker]');
    this.timer = null;
    this.hiddenAt = null;
    this.reloadAttempts = 0;
    this.groups = {};

    var self = this;
    UNITS.forEach(function (unit) {
      var el = root.querySelector('[data-unit="' + unit + '"]');
      if (el) {
        self.groups[unit] = new DigitGroup(el);
      }
    });

    this.sync(parseInt(root.getAttribute('data-now'), 10));
  }

  /** Samakan jam lokal dengan jam server (tanpa bergantung jam perangkat). */
  Countdown.prototype.sync = function (serverNow) {
    this.serverNow = serverNow;
    this.perfAtSync = window.performance.now();
  };

  Countdown.prototype.now = function () {
    return this.serverNow + (window.performance.now() - this.perfAtSync);
  };

  Countdown.prototype.target = function () {
    if (this.status === 'UPCOMING') {
      return this.start;
    }
    if (this.status === 'ONGOING') {
      return this.end;
    }
    return null;
  };

  Countdown.prototype.render = function (animate) {
    var now = this.now();
    var target = this.target();

    if (this.fill && this.start && this.end && this.end > this.start) {
      var progress = Math.min(1, Math.max(0, (now - this.start) / (this.end - this.start)));
      this.fill.style.setProperty('--progress', progress.toFixed(4));
      if (this.marker) {
        this.marker.style.setProperty('--progress', progress.toFixed(4));
      }
    }

    if (target === null) {
      return 0;
    }

    var remaining = Math.max(0, target - now);
    var parts = split(remaining);
    var groups = this.groups;

    UNITS.forEach(function (unit) {
      if (groups[unit]) {
        groups[unit].render(parts[unit], animate);
      }
    });

    return remaining;
  };

  Countdown.prototype.schedule = function () {
    var self = this;
    window.clearTimeout(this.timer);

    if (document.hidden) {
      return;
    }

    var remaining = this.render(true);

    if (this.target() !== null && remaining <= 0) {
      this.confirmWithServer();
      return;
    }

    if (this.target() === null) {
      return;
    }

    // Tepat di pergantian detik berikutnya.
    var delay = SECOND - (Math.floor(this.now()) % SECOND) + 20;
    this.timer = window.setTimeout(function () {
      self.schedule();
    }, delay);
  };

  /**
   * Hitungan habis: tanya server. Muat ulang hanya bila status server memang
   * sudah berubah (menghindari loop reload bila jam server sedikit tertinggal).
   */
  Countdown.prototype.confirmWithServer = function () {
    var self = this;

    this.fetchClock().then(function (clock) {
      if (clock && clock.status && clock.status !== self.status) {
        reloadForStatus();
        return;
      }

      if (clock && clock.now) {
        self.sync(clock.now);
      }

      self.reloadAttempts++;
      if (self.reloadAttempts <= 5) {
        self.timer = window.setTimeout(function () {
          self.confirmWithServer();
        }, 2000 * self.reloadAttempts);
      }
    });
  };

  Countdown.prototype.fetchClock = function () {
    if (!this.clockUrl || !window.fetch) {
      return Promise.resolve(null);
    }

    return fetch(this.clockUrl, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    }).then(function (response) {
      return response.ok ? response.json() : null;
    }).catch(function () {
      return null;
    });
  };

  Countdown.prototype.onVisibility = function () {
    var self = this;

    if (document.hidden) {
      window.clearTimeout(this.timer);
      this.hiddenAt = window.performance.now();
      return;
    }

    var away = this.hiddenAt === null ? 0 : window.performance.now() - this.hiddenAt;
    this.hiddenAt = null;

    // HP yang tertidur bisa membekukan performance.now(): sinkron ulang ke server.
    if (away > 30 * SECOND) {
      this.fetchClock().then(function (clock) {
        if (clock && clock.status && clock.status !== self.status) {
          reloadForStatus();
          return;
        }
        if (clock && clock.now) {
          self.sync(clock.now);
        }
        self.schedule();
      });
      return;
    }

    this.schedule();
  };

  Countdown.prototype.init = function () {
    var self = this;
    this.render(false);
    this.schedule();

    document.addEventListener('visibilitychange', function () {
      self.onVisibility();
    });

    // Kembali lewat tombol back (bfcache): jam di halaman sudah basi.
    window.addEventListener('pageshow', function (event) {
      if (event.persisted) {
        self.hiddenAt = -Infinity;
        self.onVisibility();
      }
    });
  };

  function init() {
    if (!window.performance || !window.performance.now) {
      return;
    }

    var roots = document.querySelectorAll('[data-countdown]');
    for (var i = 0; i < roots.length; i++) {
      new Countdown(roots[i]).init();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
