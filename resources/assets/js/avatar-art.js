/**
 * Avatar DiceBear bersama (Stage 26): kartu avatar dasbor
 * (dashboard-avatar.js), Living Election Wall (wall.js), dan detail pemilih
 * panel admin. Dimuat sebagai modul; DiceBear 9.4.2 disimpan lokal
 * (public/assets/vendor/dicebear-9.4.2, CSP script-src 'self').
 *
 * Markup avatar: app/Views/partials/avatar.php
 *   <span class="avatar" data-avatar data-avatar-style="adventurer" data-avatar-seed="1f0c...">
 *     <span class="avatar__initials">BP</span>      (tampilan tanpa JavaScript)
 *   </span>
 * Saat modul dimuat, setiap avatar DiceBear (bukan foto) digambar malas:
 * baru dibuat saat mendekati layar (IntersectionObserver; avatar di dalam
 * <details> tertutup menunggu dibuka). Hasil disimpan di cache memori per
 * gaya + seed, jadi avatar yang sama tidak pernah dibuat dua kali.
 *
 * Gaya & opsi dibatasi agar cocok untuk sekolah: ekspresi ramah (tanpa mata
 * silang, menangis, marah), tanpa kumis/jenggot, tanpa aksesori kostum.
 * Latar bulat dari palet lembut berkroma rendah (bukan warna paslon).
 */
import { createAvatar, adventurer, avataaars, bigSmile } from '../vendor/dicebear-9.4.2/dicebear.module.js';

const SEED = /^[a-f0-9]{16}$/;

/** Palet latar lembut ("Pagi Muria", kroma rendah). */
const BACKGROUNDS = ['e9e4d9', 'dfe6df', 'e6dfe7', 'dce3ea', 'ece2d4', 'e3e7da', 'e7e1dc'];

const STYLES = {
  adventurer: {
    style: adventurer,
    options: {
      features: ['blush', 'freckles', 'birthmark'],
      featuresProbability: 12
    }
  },
  avataaars: {
    style: avataaars,
    options: {
      accessories: ['kurt', 'prescription01', 'prescription02', 'round', 'wayfarers'],
      accessoriesProbability: 15,
      clothingGraphic: ['bear', 'deer', 'diamond', 'pizza'],
      eyebrows: ['default', 'defaultNatural', 'flatNatural', 'raisedExcited', 'raisedExcitedNatural', 'upDown', 'upDownNatural'],
      eyes: ['default', 'happy', 'side', 'squint', 'wink', 'surprised'],
      facialHairProbability: 0,
      mouth: ['default', 'smile', 'twinkle', 'serious']
    }
  },
  'big-smile': {
    style: bigSmile,
    options: {
      accessories: ['glasses', 'sunglasses'],
      accessoriesProbability: 20,
      eyes: ['cheery', 'normal', 'starstruck', 'winking'],
      mouth: ['openedSmile', 'gapSmile', 'teethSmile', 'awkwardSmile', 'braces', 'kawaii']
    }
  }
};

export const STYLE_NAMES = Object.keys(STYLES);

const cache = new Map();

/**
 * data:image/svg+xml avatar untuk { style, seed }; null bila tidak sah.
 */
export function avatarDataUri(avatar) {
  const entry = avatar ? STYLES[avatar.style] : null;
  if (!entry || !SEED.test(avatar.seed || '')) {
    return null;
  }

  const key = avatar.style + ':' + avatar.seed;
  if (!cache.has(key)) {
    const options = Object.assign({
      seed: avatar.seed,
      radius: 50,
      backgroundColor: BACKGROUNDS,
      backgroundType: ['solid']
    }, entry.options);
    cache.set(key, createAvatar(entry.style, options).toDataUri());
  }
  return cache.get(key);
}

/**
 * Gaya + seed acak dari crypto.getRandomValues (seed 16 heksa).
 */
export function randomAvatar() {
  const bytes = new Uint8Array(9);
  window.crypto.getRandomValues(bytes);
  let seed = '';
  for (let i = 0; i < 8; i += 1) {
    seed += bytes[i].toString(16).padStart(2, '0');
  }
  return { style: STYLE_NAMES[bytes[8] % STYLE_NAMES.length], seed: seed };
}

/**
 * Ganti isi satu elemen .avatar: { photo } (URL foto) atau { style, seed }.
 */
export function paint(el, avatar) {
  const src = avatar.photo || avatarDataUri(avatar);
  if (!src) {
    return false;
  }

  let img = el.querySelector('.avatar__img');
  if (!img) {
    img = document.createElement('img');
    img.className = 'avatar__img';
    img.alt = '';
    img.width = 320;
    img.height = 320;
    img.decoding = 'async';
    el.insertBefore(img, el.firstChild);
  }
  img.src = src;

  el.classList.toggle('avatar--photo', !!avatar.photo);
  el.classList.add('is-drawn');
  if (avatar.style && avatar.seed) {
    el.setAttribute('data-avatar-style', avatar.style);
    el.setAttribute('data-avatar-seed', avatar.seed);
  }
  return true;
}

/** Avatar dari atribut data-* elemen. */
function fromElement(el) {
  return { style: el.getAttribute('data-avatar-style'), seed: el.getAttribute('data-avatar-seed') };
}

let observer = null;

/**
 * Gambar semua avatar DiceBear yang belum digambar di dalam root (malas).
 */
export function hydrate(root) {
  const pending = Array.prototype.filter.call(
    (root || document).querySelectorAll('[data-avatar][data-avatar-style]'),
    (el) => !el.classList.contains('avatar--photo') && !el.classList.contains('is-drawn')
  );

  if (!('IntersectionObserver' in window)) {
    pending.forEach((el) => paint(el, fromElement(el)));
    return;
  }

  if (!observer) {
    observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          observer.unobserve(entry.target);
          paint(entry.target, fromElement(entry.target));
        }
      });
    }, { rootMargin: '240px 0px' });
  }
  pending.forEach((el) => observer.observe(el));
}

hydrate(document);
