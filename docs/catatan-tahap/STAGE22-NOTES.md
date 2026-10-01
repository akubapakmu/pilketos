# Stage 22 — Bilik Suara Terpisah, Pembuka Multi-Langkah, Sekilas Paslon, Profil Paslon 3D (hasil implementasi + handoff)

Dokumen ini adalah kontrak aktual Stage 22. Dibaca bersama
`00-MASTER-PROJECT.md`, `STAGE1-NOTES.md` s.d. `STAGE21-NOTES.md`, dan
`README.md`.

Ringkasan Stage 22 (permintaan revisi alur memilih & profil paslon):

- **bilik suara dipecah** menjadi halaman berurutan:
  pembuka (`coblos`) → **Sekilas paslon** (`sekilas-paslon`) → profil paslon
  (`paslon/0X`) → **bilik suara** (`bilik-suara`, surat suara + paku);
- **pembuka multi-langkah**: satu layar satu langkah, tulisan besar di tengah
  layar diketik ("Kenali Calonmu" → "Pilih Pasanganmu" → "Cek Lagi
  Pilihanmu"), lalu tombol **Selanjutnya** ke Sekilas paslon;
- **Sekilas paslon**: desain kartu sama, tanpa petunjuk (`lineup__hint`) dan
  tanpa tombol pilih (`pair-card__pick`); satu tombol selebar kartu
  **Profil Lengkap Paslon 0X** bermotif SVG unik tiap paslon; tanpa geser
  otomatis: HP satu kartu per baris, desktop maks. tiga per baris (lebih besar);
- **profil paslon**:
  - "Kembali ke daftar paslon" & "Semua paslon" → Sekilas paslon;
  - nama di penutup maks. dua baris (dua kata = kata kedua turun baris),
    selebar penutup di desktop tanpa terpotong;
  - deret nomor `px-others` dihapus, diganti bar pemilih paslon **menempel**:
    desktop di atas selebar layar (gelap, nomor + nama), HP/tablet di bawah
    ("Profil Paslon" + nomor kotak sama lebar);
  - tombol **Pilih Paslon 0X** = animasi **3D tangan menekan tombol**
    (three.js r186, disimpan lokal) → `bilik-suara#coblos-0X`;
  - nomor program di kiri sejajar judul; pola kepala **Misi** disamarkan
    lapisan putih buram; judul kepala bagian HP 1,5×, desktop 3×; subjudul
    miring (font italic asli); kata karakter (`px-tags`) di HP selebar kartu
    di bawah.

Tanpa migration. Dua route GET baru per jenis pemilih. Dependensi baru:
**three.js 0.186.1** (MIT) sebagai file statis di
`public/assets/vendor/three-0.186.1/` (bukan Composer/npm; tidak ada build
step), font **Plus Jakarta Sans Italic** (OFL, fontsource 5.3.0, sama dengan
file normal yang sudah ada), tiga SVG motif baru.

Breakpoint: alur memilih 720/1024 px (Sekilas paslon), profil paslon
720/1024 px (bar pemilih: < 1024 px di bawah, ≥ 1024 px di atas).

## 1. Route & controller

| Method | URL (siswa/guru sama) | Controller | Isi |
|---|---|---|---|
| GET | `siswa/coblos` | `VotingController::index` | pembuka multi-langkah (`voting/index`) |
| GET | `siswa/sekilas-paslon` | `VotingController::lineup` | **baru**: Sekilas paslon (`voting/sekilas`) |
| GET | `siswa/bilik-suara` | `VotingController::booth` | **baru**: surat suara + paku (`voting/booth`) |
| GET | `siswa/paslon/0X` | `candidate` | profil paslon (tetap) |
| POST | `siswa/coblos` | `submit` | simpan suara (tetap; `data-submit-url` surat suara) |
| GET | `siswa/coblos/yakin/N` | `confirm` | konfirmasi tanpa JS (tetap) |

- Ketiga halaman alur memakai helper privat `boothPage()`: pemilih yang sudah
  memilih **dialihkan ke `pilihanku`** (perilaku `coblos` lama).
- Redirect galat (`confirm()`: jadwal tutup / pasangan tidak valid;
  `respondVote()` non-JSON; `respondThrottled()`) kini ke **`bilik-suara`**
  (pesan flash tampil di surat suara). "Batal, pilih ulang" di halaman
  konfirmasi → `bilik-suara#surat-suara`.
- `VisitorTracker::PAGES`: `siswa|guru/coblos` = "Langkah memilih (siswa|guru)",
  `…/sekilas-paslon` = "Sekilas paslon (…)", `…/bilik-suara` = "Bilik suara
  siswa|guru".
- Dasbor tetap menaut "Lihat kandidat & coblos" ke `coblos` (pembuka).

## 2. Pembuka multi-langkah (`voting/index.php`, `booth-steps.js`, `voting.css` `.steps`)

- Markup selalu berisi teks lengkap ketiga langkah (h1 tersembunyi "Langkah
  memilih", tiap langkah `h2.steps__title` + `p.steps__desc`, nomor
  `01 / 03`), lalu tombol **Selanjutnya →** (`sekilas-paslon`). Langkah 3
  menebalkan **tidak dapat diubah** (`<strong>`).
- Belum dibuka / sudah ditutup / tanpa jadwal: pemberitahuan status
  (`.notice.steps__notice`) di bawah tombol.
- `booth-steps.js` (IIFE):
  - menyalin daftar langkah ke panggung visual `aria-hidden`; daftar asli
    menjadi `visually-hidden` (pembaca layar membaca teks utuh);
  - mengetik per text node (`<strong>` utuh); huruf yang belum diketik tetap
    ada sebagai `.steps__rest` transparan (baris tidak bergeser), kursor
    berkedip `.steps__caret`;
  - judul 62 ms/huruf, jeda 320 ms, deskripsi 19 ms/huruf, tahan 1,9 dtk,
    langkah memudar (380 ms) lalu langkah berikutnya; indikator tiga garis;
  - selesai: `.is-done` → tombol Selanjutnya muncul (sebelumnya
    `visibility: hidden`, tidak dapat difokus);
  - ketuk/klik layar = selesaikan ketikan / lanjut; **Lewati** = langsung ke
    akhir + fokus Selanjutnya;
  - `sessionStorage` `osis2026.stepsSeen` (non-sensitif): kunjungan berikutnya
    di sesi yang sama tanpa animasi.
- Tanpa JS, `prefers-reduced-motion`, atau sudah ditonton: ketiga langkah
  bertumpuk + tombol (kontrol animasi `.steps__bar` tetap `hidden`).
- Dihapus: journey timeline, panel terminal pembuka, `.booth-open`,
  `candidates.js` (journey + geser otomatis).

## 3. Sekilas paslon (`voting/sekilas.php`, `voting.css` `.lineup`/`.pair-card`)

- h1 "Sekilas paslon"; kartu sama (garis aksen atas, nomor Newsreader,
  potret/monogram, tema, jumlah misi, nama + peran, visi 3 baris).
- Satu tombol `.pair-card__profile` selebar kartu, teks rata tengah
  **Profil Lengkap Paslon 0X** → `paslon/0X`; latar `--accent`, motif
  `::before` (mask SVG, `--accent-ink`, opacity 0,2; hover 0,32).
- Motif unik per nomor urut: `CandidateTheme::MOTIFS` (10 = batas admin
  maks.): diagonal, dots, waves, plus, zigzag, rings, triangles, **grid**,
  **checker**, **cross** (tiga terakhir file baru `px-*.svg`).
  `CandidateTheme::present()` menambah kunci `motif`; kelas
  `.pair-card__profile--{motif}`. HTML memakai kata "motif" (tes profil
  melarang kata "pattern").
- Tata letak flex-wrap rata tengah: `--row` 1 (HP), `min(2, --cols-md)`
  (≥ 720 px), `--cols-md` (≥ 1024 px, maks. 3 seimbang:
  4 → 2+2, 5 → 3+2). Kartu, potret, nomor, nama, visi lebih besar di desktop.
- Dihapus: `lineup__hint`, `pair-card__actions/read/pick`, `data-pick`,
  `data-lineup`, `lineup__next`, carousel scroll-snap.
- Tanpa tautan langsung ke bilik suara (keputusan pengguna): pemilih masuk
  lewat tombol 3D di profil.

## 4. Bilik suara (`voting/booth.php`, `voting/partials/ballot.php`)

- Isi & perilaku surat suara tidak berubah (paku 3D/2D, kertas bolong
  3 detik, modal konfirmasi, POST JSON, tanpa JS ke halaman konfirmasi).
- Judul "Surat Suara" menjadi h1; tautan "← Sekilas paslon" di kepala.
- Countdown terminal pindah ke kepala surat suara (`.ballot__term`, desktop
  kolom kanan ≥ 960 px, angka `--term-size` diperkecil agar satu baris).
- `ballot.js`: `#coblos-0X` di URL tetap menyorot kotak & memfokuskan tombol
  Coblos (tujuan tombol 3D); kode `osis:journey` & klik `[data-pick]` di
  halaman yang sama dihapus.

## 5. Profil paslon (`paslon/show.php`, `paslon/partials/profile.php`, `paslon.css`)

### 5.1 Tautan
- `px-back` "Semua paslon" & tombol "Kembali ke daftar paslon" →
  `sekilas-paslon`; "Pilih Paslon 0X" → `bilik-suara#coblos-0X`.

### 5.2 Bar pemilih paslon `.px-switch` (pengganti `px-others`)
- `<nav class="px-switch" aria-label="Profil paslon lain">` di awal
  `<article>`, hanya bila paslon aktif > 1. Tiap tautan: nomor kotak,
  nama "Ketua & Wakil" (desktop), teks pembaca layar, `aria-current="page"`.
- < 1024 px: `position: fixed; bottom: 0` (+ safe-area), label "Profil
  Paslon" di kiri, nomor `flex: 1 1 0` bersudut tegas (tidak di-center),
  paslon aktif berlatar kertas; `body:has(.px-switch)` mendapat ruang bawah.
- ≥ 1024 px: `position: sticky; top: 0` selebar layar, gelap, tab sama
  lebar (nomor kotak + nama). `--px-switch-h: 60px` lewat
  `.paslon:has(> .px-switch)` (tanpa kelas varian di `<article>`): rail
  navigasi bagian kanan & `scroll-margin-top` digeser di bawah bar.
- Pratinjau admin ikut menampilkan bar (tautan ke pratinjau lain).

### 5.3 Nama di penutup
- `CandidateTheme::nameLines()`: 1 kata = 1 baris; 2 kata = kata kedua
  turun baris; ≥ 3 kata = dua baris paling seimbang. Markup
  `<strong style="--px-chars: N;"><span class="px-finale__line">…</span> …</strong>`.
- ≥ 1024 px: `.px-close__inner` tanpa batas lebar, `.px-finale__names`
  100%, `.px-finale__person { container-type: inline-size }`, ukuran
  `min(4.5rem, 100cqi / (--px-chars × 0.64))`, tiap baris `nowrap` →
  tidak terpotong, maks. dua baris. HP: baris boleh membungkus (tetap).

### 5.4 Tombol 3D "Pilih Paslon 0X" (`pick3d.js`, `.px-pick`)
- Hanya saat pemilih dapat mencoblos (`$action['pick3d']`); dimuat
  `<script type="module">` setelah `paslon.js`.
- three.js 0.186.1 (`three.module.js` + `three.core.js`, diminify esbuild
  dari paket npm resmi, lisensi MIT disertakan) di folder versi; import
  relatif sehingga `pick3d.min.js` (folder sama, `osis:minify`) tetap valid.
  CSP `script-src 'self'` tidak berubah.
- Adegan: dudukan gelap + keycap kertas (ExtrudeGeometry ber-bevel),
  tulisan "Pilih Paslon 0X" (CanvasTexture, Plus Jakarta Sans 800), tangan
  sarung tangan putih bergaris tepi gelap (CapsuleGeometry + sisi belakang
  digembungkan) yang menekan tombol tiap 2,6 dtk: keycap turun & memantul
  (pegas), riak cahaya dari tepi. Peta cahaya dibuat dari ruangan sederhana
  (PMREM), tanpa file luar. Tanpa warna aksen (aturan penutup Stage 16).
- Hover/fokus: tekan tiap 1,45 dtk, keycap bersinar, adegan miring
  mengikuti pointer. Klik/Enter: satu tekanan cepat lalu pindah (380 ms);
  Ctrl/Cmd/Shift/Alt/klik tengah dibiarkan ke browser.
- Render berhenti saat tidak terlihat (IntersectionObserver) / tab
  tersembunyi; DPR maks. 2; `pagehide` melepas GPU, `pageshow` (bfcache)
  memasang ulang.
- Cadangan: tanpa JS/WebGL/gagal dimuat → tombol 2D timbul berwarna kertas
  + ikon tangan SVG; `.is-3d` dipasang setelah frame pertama.
  `prefers-reduced-motion`: satu frame diam.

### 5.5 Isi profil
- Program: `.px-programs__item` grid `auto | 1fr`, nomor (lebih besar) sebaris
  baseline dengan judul, judul & teks di kolom kanan.
- Kepala Misi: `.px-head--misi::after` putih 60% + `backdrop-filter: blur(3px)`
  di atas pola diagonal, di bawah teks.
- `.px-head__title`: HP 1.575rem (1,5× 1.05rem), 720 px
  `clamp(1.95rem, 4.2vw, 2.6rem)`, ≥ 1024 px 3.9rem (3× 1.3rem);
  `.px-head__sub` italic. `@font-face` italic baru di `app.css`
  (`plus-jakarta-sans-latin-wght-italic.woff2`), juga dipakai kutipan pesan.
- `px-tags` kini anak `.px-meet__card`: HP `grid-column: 1 / -1` (baris
  bawah selebar kartu, pill mengisi baris); ≥ 720 px tetap di kolom teks di
  bawah bio (`:has(> .px-tags)` → foto merentang dua baris).

## 6. Test

Baru: `tests/feature/StageTwentyTwoRefinementTest.php` (19 test): tiga
halaman alur per peran, filter login, pengalihan sudah memilih, redirect galat
ke bilik suara, label pengunjung, teks & urutan tiga langkah + tombol +
notice, kontrak `booth-steps.js`/CSS, tombol profil bermotif & hal yang
dihapus, 10 motif unik + file SVG, tata letak maks. tiga per baris, bilik
suara (h1, terminal, paku, konfirmasi), tautan profil & tombol 3D, vendor
three.js + cadangan, bar pemilih (HP bawah, desktop atas), satu paslon tanpa
bar, `nameLines()` + CSS nama, kepala/program/misi/tags/italic, pratinjau admin.

Diperbarui (markup lama yang dikunci): `VotingTest`, `BoothRefinementTest`,
`StageNineRefinementTest`, `StageTenRefinementTest`, `PaslonProfileTest`,
`CandidateLimitTest`, `AdminCandidateTest`, `RoleMatrixTest`,
`StageNineteenRefinementTest` (label "Bilik suara siswa" = `bilik-suara`),
`StageTwentyOneRefinementTest` (rail di bawah bar).

Hasil: **535 test, 5.111 assertion, lulus** (PHP 8.3.6, MariaDB 10.11.14).

## 7. Uji browser (Chromium/Playwright, WebGL SwiftShader)

HP 390×844 & desktop 1440×900, screenshot `shots/stage22-*.png`:
- pembuka: mengetik, Lewati, tombol Selanjutnya; reduced-motion & tanpa JS
  bertumpuk;
- Sekilas paslon: HP satu kolom, desktop tiga kolom, motif tiap tombol;
- bilik suara: countdown satu baris, surat suara, dok paku;
- profil: bar atas (desktop) & bawah (HP), misi tersamar, program bernomor
  kiri, tags HP, nama penutup dua baris, tombol 3D menekan
  (`stage22-tombol-3d-tekan.png`), klik → `bilik-suara#coblos-01` kotak
  tersorot; tanpa JS tombol 2D;
- mode production (`*.min.js`, `pick3d.min.js` + vendor) tanpa galat konsol.
