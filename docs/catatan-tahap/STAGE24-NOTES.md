# Stage 24 — Teks Hero Diketik (Fitur Admin), Sekilas & Profil Paslon, Tombol Klik 3D, Menu Melayang Bilik Suara (hasil implementasi + handoff)

Dokumen ini adalah kontrak aktual Stage 24. Dibaca bersama
`00-MASTER-PROJECT.md`, `STAGE1-NOTES.md` s.d. `STAGE23-NOTES.md`, dan
`README.md`.

Ringkasan Stage 24:

- beranda: ikon **Dasbor** / **Keluar** pengguna login tanpa border & latar
  (HP + desktop); kalimat di tengah hero **diketik bergantian**, isinya diatur
  admin;
- admin: menu baru **Teks Halaman Utama** (`/admin/halaman-utama`), satu
  baris = satu kalimat;
- Sekilas paslon: teks "N poin misi" dihapus; semua pola SVG diganti latar
  SVG abstrak;
- profil paslon: `px-back` dihapus, peran (`px-finale__role`) di atas nama,
  nama penutup membungkus otomatis; tombol three.js **Pilih Paslon 0X**
  dengan tulisan di tengah dan animasi **kursor mengklik** (ala tombol
  Subscribe) menggantikan tangan;
- bilik suara: tombol kembali & navigasi situs diganti **menu melayang**
  (kiri ← Kembali, kanan ikon dasbor), tanpa countdown, kepala rata tengah,
  petunjuk miring, HP tanpa "· Siswa/Guru".

Satu migration baru (`settings`), satu route admin (GET + POST), tanpa
dependensi baru.

## 1. Teks hero diketik

### Penyimpanan

- Migration `2026-10-02-000001_CreateSettingsTable`: tabel `settings`
  (`name` VARCHAR(64) PK, `value` TEXT, `updated_at`). Tidak ditempel ke
  `elections`, karena pengaturan harus ada walaupun pemilihan belum dibuat.
- `up()` langsung mengisi `hero_lines` dengan 12 kalimat contoh, jadi
  production terisi setelah `php spark migrate`. "SMP 1 Dawe" ditulis
  **SMP 1 DAWE** (aturan Stage 6, dikunci `VisualIdentityTest`).
- `App\Models\SettingModel`: `value(name)`, `put(name, value)` (upsert).
- `App\Libraries\HeroText`:
  - `parse()` memecah per baris, merapatkan spasi, membuang karakter kontrol
    & zero-width, membuang baris kosong, menormalkan "SMP 1 DAWE";
  - `errors()`: maks. **20 kalimat**, maks. **80 karakter** per kalimat;
  - `lines()` aman bila tabel belum dimigrasi (beranda tetap tampil, hero
    tanpa teks);
  - `display()` memakai spasi tak terpisah di "SMP 1 DAWE" supaya tidak
    terpenggal di dua baris.

### Admin

`/admin/halaman-utama` (sidebar kelompok ke-3, setelah Jadwal Pemilihan; ikon
`type`): textarea satu kalimat per baris + daftar bernomor urutan tampil.
Kosongkan textarea = hero tanpa teks. Setiap perubahan tercatat di audit log
sebagai **Ubah teks halaman utama** (jumlah kalimat sebelum → sesudah +
isinya). Tidak dikunci saat pemilihan selesai (tidak memengaruhi hasil).

### Beranda

- Markup: `.hero-type > p.hero-type__text` (kalimat pertama, tampilan tanpa
  JS; `aria-hidden`) + `ul.visually-hidden` berisi semua kalimat untuk
  pembaca layar.
- Tipografi: Newsreader 400, desktop `clamp(2.25rem, 4.6vw, 4.75rem)`, HP
  `clamp(1.875rem, 8.4vw, 2.75rem)`, layar pendek `clamp(1.5rem, 6.5vh,
  2.25rem)`, `text-wrap: balance`, maks. ±15em. Bayangan hero sedikit lebih
  gelap bila ada teks (tanpa blur/gradient).
- Irama (`home.js`, komponen `HeroType`): ketik ±55 ms/huruf (+ jeda tanda
  baca) → tahan 2,6 dtk → hapus 24 ms/huruf → jeda 0,45 dtk → kalimat
  berikutnya. Setelah kalimat terakhir terhapus: **jeda 3 dtk**, lalu ulang
  dari awal (terukur di browser: 7225 → 10223 ms).
- Baris tidak bergeser saat mengetik: pemenggalan baris diukur sekali dari
  kalimat utuh, lalu tiap baris dibekukan (`nowrap`); sisa kalimat transparan.
  Caret selalu di baris yang benar.
- Mulai setelah layar pembuka, berhenti saat scene hero tidak aktif atau tab
  tersembunyi. Reduced-motion: kalimat berganti tanpa diketik (4,2 dtk per
  kalimat) + jeda 3 dtk. Hanya `textContent`.

## 2. Sekilas paslon & latar abstrak

- `voting/sekilas.php`: "N poin misi" dihapus (beserta `.pair-card__meta`).
- `public/assets/img/abstract/{orbit,tide,arc,petal,shard,ripple,stream,cluster,ribbon,hills}.svg`:
  komposisi abstrak satu warna (viewBox 1200×240, < 1 KB, tanpa
  gradient/filter/teks), dipakai sebagai mask `cover` / `no-repeat`.
- `CandidateTheme::MOTIFS` = 10 nama di atas (unik per nomor urut).
- Kepala bagian profil: profil=orbit, visi=arc, misi=stream, program=shard,
  poster=cluster, pesan=ripple, video=tide.
- `public/assets/img/patterns/px-*.svg` dihapus; `ticks.svg` tetap.

## 3. Profil paslon

- `px-back` dihapus (HP & desktop).
- Penutup: `px-finale__role` (kicker kecil kapital) **di atas** nama; nama
  membungkus alami (`text-wrap: balance`). `CandidateTheme::nameLines()`
  dihapus.
- Tombol three.js (`pick3d.js`): tulisan tepat di tengah keycap (kamera
  hampir ortografis, FOV 13); `buildCursor()` (panah kursor diekstrusi,
  bergaris tepi) menggantikan tangan. Siklus ±3,2 dtk: kursor masuk → klik di
  kanan-bawah tulisan (kursor mengecil, tombol turun, cincin klik + riak tepi)
  → tombol memantul → kursor keluar. Pointer sungguhan menyembunyikan kursor
  tiruan. Ukuran ringkas: maks. 340 px HP / 420 px desktop. Cadangan 2D
  memakai ikon kursor SVG dengan animasi ketuk CSS.
- Label tombol tidak berubah menjadi "terpilih" setelah klik (supaya tidak
  terkesan suara sudah masuk).

## 4. Bilik suara

- `VotingController::booth()` mengirim `boothNav`; `layouts/main.php` memakai
  `partials/booth_nav.php` (bukan `partials/header`): kiri ← **Kembali** (ke
  Sekilas paslon), kanan ikon dasbor (label tersembunyi + `title`). Tanpa
  logo dan tanpa Keluar.
- Menu menempel di atas (sticky, latar `--ink`, tinggi 60 px). Ikon tanpa
  border/latar; container 1360 px sama dengan surat suara, jadi glyph tepat
  sejajar tepi lembar (desktop 72/1368 px, HP 16/374 px).
- `ballot__back`, `.ballot__top`, dan countdown (`term`, `countdown.js`)
  dihapus. **Akibat:** bilik suara tidak lagi memuat ulang otomatis saat
  jadwal buka/tutup; server tetap penentu (pencoblosan ditolak setelah
  selesai).
- Kicker, judul, petunjuk, dan judul lembar rata tengah (HP & desktop);
  petunjuk miring; di HP kicker hanya "Bilik suara digital".

## 5. Test

Baru `tests/feature/StageTwentyFourRefinementTest.php` (19 test). Diperbarui:
`StageTwentyTwoRefinementTest`, `StageTwentyOneRefinementTest`,
`StageNineRefinementTest`, `BoothRefinementTest`,
`StageEighteenRefinementTest`, `PaslonProfileTest`.

Hasil: **559 test, 5.602 assertion, lulus** (PHP 8.3, MariaDB 10.11).

Uji browser (Playwright Chromium, 1440×900 & 390×844, 0 galat konsol/HTTP):
`shots/stage24-*.png` (beranda tamu/siswa + mengetik, admin, Sekilas, profil +
tombol 3D, bilik suara).

## 6. Deploy

```
git pull
php spark migrate        (membuat tabel settings + 12 kalimat hero)
php spark osis:check
```

Teks hero selanjutnya diubah dari menu **Teks Halaman Utama**.
