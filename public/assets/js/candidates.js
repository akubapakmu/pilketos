/**
 * Bilik suara: langkah memilih & "Sekilas paslon" (Stage 2, 8, 10).
 *
 * Stage 15: bab tiap pasangan (visi-misi interaktif, parallax, tilt,
 * navigasi bab) pindah ke halaman profil paslon (paslon.js). Yang tersisa:
 *
 * - [data-journey] : langkah memilih di pembuka (Stage 8): langkah aktif
 *                    maju saat surat suara tercapai, dan mengikuti event
 *                    "osis:journey" dari ballot.js (konfirmasi dibuka/batal).
 * - [data-lineup]  : "Sekilas paslon" di HP (Stage 10) bergeser sendiri
 *                    01 -> terakhir -> 01 tiap 2 detik. Berhenti saat
 *                    disentuh/digeser (lanjut setelah 5 detik diam), saat
 *                    kartu difokus keyboard, saat tidak terlihat, dan
 *                    mati total dengan prefers-reduced-motion.
 *
 * Semua fitur progressive enhancement: tanpa JavaScript kartu tetap dapat
 * digeser dan tautan tetap berfungsi.
 */
(function () {
  'use strict';

  var reduced = window.App && window.App.reducedMotion ? window.App.reducedMotion() : false;
  var hasIO = 'IntersectionObserver' in window;

  /* ---------- journey timeline: langkah memilih (Stage 8) ---------- */

  function initJourney(journey) {
    if (!journey) {
      return;
    }

    var steps = journey.querySelectorAll('[data-journey-step]');
    var current = 0;

    function setStep(index) {
      current = Math.max(0, Math.min(steps.length - 1, index));
      for (var i = 0; i < steps.length; i++) {
        steps[i].classList.toggle('is-done', i < current);
        steps[i].classList.toggle('is-current', i === current);
        if (i === current) {
          steps[i].setAttribute('aria-current', 'step');
        } else {
          steps[i].removeAttribute('aria-current');
        }
      }
    }

    // Surat suara tercapai: "Kenali paslon" selesai, "Coblos satu" aktif.
    var ballot = document.getElementById('surat-suara');
    if (ballot && hasIO) {
      var observer = new IntersectionObserver(function (entries) {
        for (var i = 0; i < entries.length; i++) {
          if (entries[i].isIntersecting) {
            if (current < 1) {
              setStep(1);
            }
            observer.disconnect();
            return;
          }
        }
      }, { rootMargin: '0px 0px -40% 0px' });
      observer.observe(ballot);
    }

    // ballot.js: kertas tercoblos -> "Konfirmasi & kunci"; batal -> kembali.
    document.addEventListener('osis:journey', function (event) {
      if (event.detail && typeof event.detail.step === 'number') {
        setStep(event.detail.step);
      }
    });
  }

  /* ---------- Sekilas paslon: geser otomatis di HP (Stage 10) ---------- */

  function initLineupAutoplay(list) {
    if (!list || reduced || !window.matchMedia) {
      return;
    }

    var cards = list.querySelectorAll('.pair-card');
    if (cards.length < 2) {
      return;
    }

    var mobile = window.matchMedia('(max-width: 719px)'); // sama dengan voting.css
    var DELAY = 2000; // tiap pasangan tampil 2 detik
    var IDLE = 5000;  // jeda setelah pemilih menyentuh / menggeser sendiri
    var timer = null;
    var holdUntil = 0;
    var inView = !hasIO;
    var focused = false;

    // Posisi geser kartu ke-i: kartu pertama menempel di 0 (scroll-padding).
    function offsetOf(i) {
      return cards[i].getBoundingClientRect().left - cards[0].getBoundingClientRect().left;
    }

    function currentIndex() {
      var left = list.scrollLeft;
      var best = 0;
      for (var i = 1; i < cards.length; i++) {
        if (Math.abs(offsetOf(i) - left) < Math.abs(offsetOf(best) - left)) {
          best = i;
        }
      }
      return best;
    }

    function advance() {
      var max = list.scrollWidth - list.clientWidth;
      var next = currentIndex() + 1;
      // Setelah pasangan terakhir (atau ujung geser): kembali ke pasangan pertama.
      if (next >= cards.length || list.scrollLeft >= max - 2) {
        next = 0;
      }
      list.scrollTo({ left: Math.min(offsetOf(next), max), behavior: 'smooth' });
    }

    function stop() {
      window.clearTimeout(timer);
      timer = null;
    }

    function schedule() {
      stop();
      if (!mobile.matches || !inView || focused || document.hidden) {
        return;
      }
      timer = window.setTimeout(function () {
        timer = null;
        if (Date.now() >= holdUntil) {
          advance();
        }
        schedule();
      }, Math.max(DELAY, holdUntil - Date.now()));
    }

    function hold() {
      holdUntil = Date.now() + IDLE;
      schedule();
    }

    ['pointerdown', 'touchstart', 'touchend', 'wheel'].forEach(function (type) {
      list.addEventListener(type, hold, { passive: true });
    });

    list.addEventListener('focusin', function () {
      focused = true;
      stop();
    });

    list.addEventListener('focusout', function (event) {
      if (!list.contains(event.relatedTarget)) {
        focused = false;
        hold();
      }
    });

    document.addEventListener('visibilitychange', schedule);

    if (mobile.addEventListener) {
      mobile.addEventListener('change', schedule);
    } else if (mobile.addListener) {
      mobile.addListener(schedule);
    }

    if (hasIO) {
      new IntersectionObserver(function (entries) {
        inView = entries[entries.length - 1].isIntersecting;
        schedule();
      }, { threshold: 0.6 }).observe(list);
    }

    schedule();
  }

  function init() {
    initJourney(document.querySelector('[data-journey]'));
    initLineupAutoplay(document.querySelector('[data-lineup]'));
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
