# Stage 17 — Tampilan Panel Admin: Tanpa Badge Status, Topbar HP, Foto Pasangan, Tabel Lembar Kerja (hasil implementasi + handoff)

Dokumen ini adalah kontrak aktual Stage 17. Dibaca bersama
`00-MASTER-PROJECT.md`, `STAGE1-NOTES.md` s.d. `STAGE16-NOTES.md`, dan
`README.md`.

Ringkasan Stage 17 (permintaan revisi panel admin):

- **"Sedang Berlangsung" dihapus** dari panel admin, desktop maupun HP;
- **topbar HP**: tombol menu ikon saja (tanpa teks & garis, ikon lebih
  besar) + **logo khusus** header HP;
- **note tip** (ikon "i") lebih kecil, di pojok kanan atas tepat setelah teks;
- **tanpa garis atas/bawah** (`border-top`, `border-bottom`) dan tanpa
  `.pills::after`;
- **foto pasangan**: kotak nomor di `results__list` (Beranda & Analitik) dan
  kartu Pasangan calon diganti **dua foto pasangan dalam satu kotak persegi**;
  saat diarahkan kursor nomor pasangan muncul semi transparan;
- **hasil akhir**: keadaan terkunci (teks + countdown) rata tengah;
- **countdown**: keping angka lebih lega, tanpa garis (juga di Beranda admin);
- **tabel** bergaya lembar kerja (Excel).

Tanpa migration, tanpa dependensi atau framework baru. Satu aset baru:
`public/assets/img/brand/logo-admin-mobile.svg`.

"HP" = lebar < 720 px (sama dengan Stage 8–16).

## 1. Badge status dihapus

| Bagian | Perubahan |
|---|---|
| `admin/partials/topbar.php` | `a.admin-top__status` (badge "Sedang Berlangsung" / "Belum Dibuka" / ...) dihapus di semua lebar layar. Topbar = tombol menu, logo HP, breadcrumb |
| `admin-live.js` | pembaruan `[data-live-badge-*]` dihapus (elemennya sudah tidak ada) |
| `admin/results/index.php` | keadaan terkunci tanpa kalimat "Status sekarang **Sedang Berlangsung**"; keadaan terbaca dari label countdown (**Dibuka dalam** / **Ditutup dalam**). Variabel `$status` yang tidak terpakai ikut dihapus |
| tetap | halaman **Jadwal pemilihan** tetap menampilkan status (halaman itu memang mengelola status); indikator live (**Live**, "Menunggu dibuka", ...); dock beranda publik |

## 2. Topbar HP & logo khusus

```
header.admin-top                       tanpa garis bawah
  button.admin-top__menu               ikon menu 28 px, lingkaran 48 px tanpa garis; "Menu" visually-hidden
  a.admin-top__logo > img              logo-admin-mobile.svg (tinggi 34 px), hanya < 720 px, ke Beranda
  div.admin-top__title                 breadcrumb (>= 720 px, tetap)
```

- Logo di tengah layar: tombol menu di kiri (lebar efektif 38 px karena
  `margin-left: -10px`), penyeimbang `.js .admin-top::after` 38 px di kanan.
  Tanpa JavaScript tombol menu tidak tampil dan logo tetap di tengah.
- `logo-admin-mobile.svg` (246 × 64): simbol logo PILKETOS (diambil dari
  `logo-dark.svg`) + "PILKETOS" (Plus Jakarta Sans Bold) + "PANEL ADMIN"
  (JetBrains Mono 500, berjarak) — senada logo utama "PILKETOS 2026 /
  SMP 1 DAWE". Semua huruf berupa path (tidak bergantung font perangkat),
  `<title>PILKETOS Panel Admin</title>`, alt gambar "PILKETOS Panel Admin:
  Beranda".
- 720–1023 px: tombol menu (ikon saja) + breadcrumb, tanpa logo; ≥ 1024 px:
  sidebar tetap, tanpa tombol menu.

## 3. Note tip

| Elemen | Stage 17 |
|---|---|
| `.admin-head__hint` (ikon "i" judul halaman) | 22 px (ikon 16 px), tanpa garis, `vertical-align: top` + `margin-top: 0.2em` → menempel di pojok kanan atas baris terakhir judul. Aktif: lingkaran tinta |
| `.note-tip` (ikon "i" kepala panel, `admin/partials/note`) | 20 px (ikon 15 px), `align-self: flex-start`, jarak ke judul 4 px → pojok kanan atas judul panel |

Area sentuh keduanya diperluas lewat `::after` (inset negatif) meski ikonnya
kecil. Perilaku tooltip & keterangan tidak berubah (HP tetap tanpa tombol "i"
judul, Stage 13).

## 4. Tanpa garis atas/bawah

Semua `border-top` / `border-bottom` pemisah di `admin.css` dan
`results.css` dihapus; pemisah diganti jarak (atau bayangan lembut pada bar
simpan formulir yang menempel). Yang tersisa hanya `border-top-color`
spinner (bukan garis) dan penghapus `border-bottom: 0`. Dijaga test.

- admin.css: brand, antarkelompok menu & kaki sidebar; topbar; bar live HP;
  papan angka (atas & bawah); kepala panel; **`.pills::after`**; daftar hasil
  & baris hasil; ringkasan pemilih; `.empty`; bagian formulir & bar simpan;
  log unlock; panel jam Jadwal; modal konfirmasi bahaya; footer; garis atas
  tebal banner final pemenang; tab pratinjau impor (tab aktif sekarang pil
  tinta, senada `.pills`); `.table-scroll` (garis atas tebal).
- results.css: keadaan terkunci, masthead (garis ganda), stempel, judul
  bagian, tally pemenang, klasemen, partisipasi, catatan kaki; catatan kaki
  cetak browser (`@page @bottom-center`) tanpa garis atas.
- `.admin .kv-list__row` (komponen app.css di detail pemilih & Jadwal):
  tanpa garis bawah di panel admin.
- PDF hasil akhir (`admin/results/pdf.php`, dompdf) tidak berubah (dokumen
  resmi, bukan tampilan panel).

## 5. Foto pasangan (`admin/partials/pair_thumb.php`)

```
span.pair-thumb.{result__thumb|cand-card__thumb}[aria-hidden]   persegi, radius 6 px
  span.pair-thumb__half--ketua > img (cover, fokus 20 % atas) | span.pair-thumb__mono (inisial)
  span.pair-thumb__half--wakil > img | span.pair-thumb__mono
  span.pair-thumb__no "01"                                     lapisan nomor
```

- Dua foto berdampingan (grid 1fr 1fr, celah 2 px), latar setiap separuh =
  aksen pasangan; foto kosong → inisial (`CandidateTheme::initials()`).
- **Hover** (`@media (hover: hover)`): nomor tampil di atas lapisan aksen
  `opacity: 0.72` (foto tetap sedikit terlihat), foto sedikit membesar (1.06),
  inisial meredup (0.2) agar nomor terbaca.
- **Tanpa hover** (`@media (hover: none)`, HP): nomor kecil berbentuk pil di
  pojok kanan bawah (lapisan 0.85), karena hover tidak ada di layar sentuh.
- Dekoratif: nomor & nama tetap tertulis untuk pembaca layar
  ("Pasangan 01: ...") di baris hasil / judul kartu.
- Ukuran: hasil 64 px (HP) / 72 px (≥ 720 px); kartu Pasangan calon 72 px
  (< 520 px), 84 px, 128 px (≥ 960 px), pasangan nonaktif `opacity: 0.4`.
- Data: `AnalyticsService` (snapshot `candidates`) kini membawa
  `photo_ketua`, `photo_wakil` (`CandidateModel::assetUrl()`, basename +
  rawurlencode) dan `ketua_initials`, `wakil_initials`; ikut di JSON live
  count `admin/hitung-suara` sehingga `admin-live.js` (`buildThumb`) membangun
  struktur yang sama saat daftar pasangan berubah. `PublicLiveCount` tidak
  berubah (hanya id, label, persen).
- Kelas lama `result__no` dan `cand-card__no` dihapus.

## 6. Hasil akhir terkunci & countdown

- `.final-locked`: satu kolom rata tengah (segel, judul, teks, countdown,
  tombol), tanpa garis; unit countdown di tengah.
- `.countdown--compact` (app.css; dipakai Beranda admin desktop dan hasil
  akhir terkunci): tanpa garis, latar `--paper-dim`, padding 16 × 24 px;
  keping angka `--h: 1.55em`, lebar `1.05em`, radius 6 px, jarak antarkeping
  0.14em, jarak antarunit 24 px. < 480 px: angka 1.5rem & jarak 12 px agar
  empat unit muat. Countdown terminal Beranda admin di HP (`.admin-clock`) dan
  dock beranda publik tidak berubah (tanpa keping berlatar).

## 7. Tabel lembar kerja

Token di `.admin`: `--sheet-grid #D9D5CA`, `--sheet-head #EEECE6`,
`--sheet-cell #FFFFFF`, `--sheet-hover #F4F3EF`.

- `.data-table` (rekap, detail suara, pemilih, audit, unlock, pratinjau
  impor): kisi 1 px di setiap sel, sel putih, padding rapat
  (0.45rem 0.65rem), 0.875rem; baris judul kolom abu-abu, tebal 700, tanpa
  huruf kapital berjarak; baris diarahkan kursor disorot lembut; kolom
  pertama tidak lagi tanpa padding kiri. Angka tetap rata kanan tabular.
- `.spec-table` (halaman impor): kisi & judul kolom yang sama.

## 8. Test

Baru: `tests/feature/StageSeventeenRefinementTest.php` (11 test): topbar
tanpa badge status di beberapa halaman + JS/CSS badge hilang; tombol menu ikon
saja + logo HP (file SVG ber-title, huruf path); ukuran & posisi note tip;
tidak ada `border-top`/`border-bottom` di admin.css & results.css (kecuali
spinner / `: 0`), tanpa `.pills::after`; foto pasangan di Beranda & Analitik
(foto + inisial + nomor), kartu Pasangan calon, JSON live count membawa foto
& inisial, aturan hover semi transparan; hasil akhir terkunci rata tengah
tanpa "Status sekarang"; countdown compact tanpa garis & lebih lega; tabel
kisi lembar kerja.

Diperbarui: `AdminPanelTest` (Beranda tidak lagi menampilkan
"Sedang Berlangsung"; countdown "Ditutup dalam"), `FinalResultTest` (hasil
akhir terkunci: "Ditutup dalam" / "Dibuka dalam", bukan label status).

Hasil: **465 test, 4.421 assertion, lulus** (PHP 8.4.19, MariaDB 10.11.14).

Uji browser (Playwright/Chromium, `spark serve`, data seeder + foto uji
untuk pasangan 01 & 02, pasangan 03 tanpa foto, 8 suara siswa), 390 & 1440 px:
- topbar desktop tanpa badge; HP: ikon menu + logo di tengah;
- ikon "i" kecil di pojok kanan atas judul halaman & judul panel;
- hasil per pasangan & kartu paslon: dua foto dalam satu persegi, inisial bila
  foto kosong; hover → nomor di atas lapisan aksen semi transparan; HP → pil
  nomor di pojok;
- hasil akhir terkunci rata tengah, countdown lega tanpa garis (juga Beranda);
- tabel rekap & detail suara berkisi; tanpa gulir mendatar halaman;
- konsol tanpa error JavaScript.

Screenshot: `shots/admin-beranda-desktop.png`, `shots/admin-beranda-hp.png`,
`shots/admin-foto-pasangan-hover.png` (foto pasangan saat diarahkan kursor:
hasil per pasangan dan kartu Pasangan calon, termasuk pasangan tanpa foto).
Foto pada screenshot adalah gambar uji, bukan foto calon sebenarnya.
