# Stage 18 — Beranda: Hero Tanpa Teks, Tanpa Kontur & Perforasi; "Kembali ke Awal" Panah Saja (hasil implementasi + handoff)

Dokumen ini adalah kontrak aktual Stage 18. Dibaca bersama
`00-MASTER-PROJECT.md`, `STAGE1-NOTES.md` s.d. `STAGE17-NOTES.md`, dan
`README.md`.

Ringkasan Stage 18 (permintaan revisi beranda publik `/`):

- **scene 01 (hero)**, desktop maupun HP: teks di atas gambar dihapus. Yang
  tersisa hanya **gambar hero** (lanskap `heroDesktop`, potret `heroMobile`),
  tombol **Masuk untuk memilih**, dan tautan **Lihat perolehan suara**;
- **garis kontur SVG** (`.hero-bg__contour`) dan **garis perforasi**
  (`.hero__perf`) dihapus beserta file SVG-nya;
- **scene 03 (Perolehan suara)**: "Kembali ke awal" menjadi **panah ke atas
  saja**, tanpa teks.

Tanpa migration, route, konfigurasi, atau dependensi baru. Layar pembuka
(`introDesktop` / `introMobile`, lockup, bilah muat) tidak berubah.

## 1. Hero tanpa teks

| Bagian | Perubahan |
|---|---|
| `home/index.php` | dihapus: `p.hero__kicker` ("PILKETOS 2026" + `span.hero__reg`), judul terlihat "SMP 1 DAWE" (`.hero__line` / `.hero__word`), `p.hero__support` ("Satu pemilih, satu suara."), `span.hero__perf`, `span.hero-bg__contour` |
| tetap | `picture.hero-bg__photo` (lanskap/potret), `.hero-bg__shade`, lapisan depan opsional, canvas "embun", `.hero__actions` (posisi sama: bawah-kiri di desktop, selebar layar di HP) |
| aksesibilitas | `<h1 class="visually-hidden" id="hero-title">Pemilihan Ketua & Wakil Ketua OSIS SMP 1 DAWE 2026</h1>`: scene tetap bernama (`aria-labelledby`) dan halaman tetap punya satu `<h1>`, tetapi tidak terlihat |
| `home.css` | aturan `.hero-bg__contour` (+ varian potret, keadaan reveal, reduced-motion), `.hero__kicker`, `.hero__reg`, `.hero__title`, `.hero__line`, `.hero__word`, `.hero__support`, `.hero__perf` dan override responsifnya dihapus. Urutan reveal: latar 0 → lapisan depan 120 → **CTA 240 ms** (sebelumnya 560 ms setelah teks) |
| `home.js` | hanya komentar paralaks (tanpa lapisan kontur); `Field` tetap memakai `.hero` (kini blok CTA) sebagai zona peredam titik |

Aset dihapus (tidak dipakai lagi): `public/assets/img/home/hero-contour.svg`,
`hero-contour-mobile.svg`, `hero-perforation.svg`, `hero-registration.svg`
(registration mark hanya dipakai kicker). Dokumen rencana `docs/desain/06`,
`08`, `09` tetap menyebut S01–S03 sebagai riwayat arah visual.

## 2. "Kembali ke awal" = panah saja

```
div.scene__end
  a.scene-top[href="#beranda"][aria-label="Kembali ke awal"]   ikon arrow-up 18 px, target 40x40
  footer.site-footer--scene
```

- Teks `<span>Kembali ke awal</span>` dihapus; nama tautan untuk pembaca
  layar lewat `aria-label` (ikon `aria-hidden`).
- `.scene-top` menjadi tombol ikon: `justify-content: center`,
  `min-width/min-height: 40px`, tanpa padding, tanpa gaya teks mono
  (`font-family`, `font-size`, `letter-spacing`, `text-transform`). Posisi
  tetap: kanan baris footer di desktop, di tengah atas footer di HP.

## 3. Test

Baru: `tests/feature/StageEighteenRefinementTest.php` (3 test): teks
terlihat di scene hero hanya dua tombol, judul `visually-hidden`, tanpa
kicker/judul/pendukung/kontur/perforasi; file SVG kontur, perforasi, dan
registration tidak ada dan tidak dirujuk `home.css`; `a.scene-top` hanya
ikon + `aria-label`, aturan CSS tombol ikon.

Diperbarui: `VisualIdentityTest` (hero = latar + CTA, daftar SVG kode tanpa
aset yang dihapus, token ukuran judul hero dihapus dari pemeriksaan CSS).

Hasil: **468 test, 4.420 assertion, lulus** (PHP 8.4.19, MariaDB 10.11.14).

Uji browser (Chromium, 1440×900 dan 390×844 HP): setelah layar pembuka,
scene 01 hanya menampilkan gambar hero + dua tombol; "Lihat perolehan suara"
membuka scene 03; panah di akhir scene (40×40, tanpa teks) kembali ke scene
01; tanpa galat konsol. Tangkapan layar: `shots/beranda-hero-desktop.png`,
`shots/beranda-hero-hp.png`, `shots/beranda-perolehan-desktop.png`,
`shots/beranda-perolehan-hp.png`.
