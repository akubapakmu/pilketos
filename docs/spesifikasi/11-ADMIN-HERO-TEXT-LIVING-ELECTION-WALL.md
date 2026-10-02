# STAGE 25 & STAGE 26
## RENCANA DUA TAHAP: DATA PEMILIH, REKAP PENGUNJUNG, TEKS HALAMAN UTAMA 3D, RESET TOTAL, AVATAR PEMILIH & LIVING ELECTION WALL

Baca:
1. `00-MASTER-PROJECT.md`
2. `STAGE1-NOTES.md` s.d. `STAGE24-NOTES.md` (terutama 3, 4, 5, 11, 13, 19, 21, 23, 24)
3. `README.md` (bagian 8, 8.2, 17, 18, 19, 21)
4. dokumen desain `06`–`10` (terutama `07-TYPOGRAPHY…` dan `10-MOTION…`)
5. seluruh file aplikasi hasil Stage 1–24.

Jangan membuat project baru.

Dokumen ini adalah **rencana** dua tahap. Setiap tahap dikerjakan di **chat baru**:

| Tahap | Stage | Isi | Hasil aktual & handoff |
|---|---|---|---|
| Tahap 1 | Stage 25 | panel admin (tambah data pemilih, rekap pengunjung + grafik + PDF, teks halaman utama: font, ukuran, ulang, efek mengetik & tulisan tangan dengan huruf 3D, reset total) + perapian halaman pemilih | `STAGE25-NOTES.md` |
| Tahap 2 | Stage 26 | avatar pemilih (unggah foto & avatar acak) + **Living Election Wall** + tautan dari scene perolehan suara | `STAGE26-NOTES.md` |

Tahap 2 dikerjakan setelah Tahap 1 selesai dan di-merge. Prompt siap tempel untuk
kedua chat ada di bagian 7.

Nomor baris yang disebut di dokumen ini adalah posisi saat dokumen ditulis
(commit `586e475`, Stage 24); cari ulang dengan `grep` bila sudah bergeser.

---

## 0. PEMETAAN PERMINTAAN

| # | Permintaan pemilik proyek | Tahap | Bagian |
|---|---|---|---|
| 1 | Cara tambah data siswa & guru: langsung maupun impor file, hanya saat jadwal belum dimulai | 1 | 3.1 |
| 2 | Ekspor PDF rekap pengunjung + grafik perangkat, browser, sistem operasi; rentang hari ini / 7 hari / 30 hari; kolom Pengunjung, Halaman, Perangkat, Sistem · browser, Jaringan · lokal, Aktivitas; tanpa User-Agent | 1 | 3.2 |
| 3 | Pilihan font teks halaman utama (library font), ukuran kecil/sedang/besar untuk desktop & HP, teks diulang atau tidak, bisa dinonaktifkan tanpa menghapus teks, efek mengetik & tulisan tangan (tanpa tangan) dengan font tulisan tangan Latin, library 3D | 1 | 3.3 |
| 4 | Tombol reset total: pemilih siswa & guru, audit log, teks halaman utama, log pengunjung, paslon, jadwal | 1 (+2) | 3.4, 4.6 |
| 5 | Hapus `vote-card__text` di dasbor siswa & guru | 1 | 3.5 |
| 6 | Ikon dasbor & tombol keluar tanpa border dan latar (HP & desktop), ikon saja | 1 | 3.5 |
| 7 | "Sekilas Paslon", "Belum Memilih", "Sudah Memilih" (huruf kapital tiap kata) | 1 | 3.5 |
| 8 | Living Election Wall publik + tautan dari perolehan suara (desktop sejajar `site-footer__online`, HP di bawahnya) | 2 | 4.3, 4.5 |
| 9 | Pemilih mengunggah foto avatar (otomatis dikompres & dikonversi, bulat, keterangan dipakai di wall) atau avatar acak dari library SVG avatar | 2 | 4.1, 4.2 |

---

## 1. KEPUTUSAN YANG SUDAH DIAMBIL

Diputuskan pemilik proyek saat perencanaan:

1. **Perolehan publik tetap live.** Living Election Wall menampilkan siapa saja yang
   sudah berpartisipasi secara langsung, dan persentase per paslon di beranda
   (`PublicLiveCount`, Stage 5) **tetap tampil dan diperbarui seperti sekarang selama
   pemilihan berlangsung**. Pemilik proyek menerima bahwa pilihan seseorang kadang dapat
   ditebak dari perubahan kecil (risiko yang sudah disebut di `STAGE5-NOTES.md` bagian
   11), karena tebakan itu tidak jelas dan tidak gamblang. `PublicLiveCount` tidak
   diubah; risikonya cukup dicatat. (bagian 4.4)
2. **3D teks halaman utama: huruf 3D timbul** memakai **troika-three-text** di atas
   three.js r186 yang sudah ada. Efek tulisan tangan berupa **sapuan tinta per huruf**
   (bukan goresan kontur). (bagian 3.3)
3. **Avatar acak: kartun berwarna** dari **DiceBear 9.4.x**: Adventurer, Avataaars,
   Big Smile. (bagian 4.2)

Diputuskan dalam dokumen ini (boleh diubah pemilik proyek sebelum tahap dikerjakan):

4. "Jadwal belum dimulai" = belum ada pemilihan, atau status `UPCOMING` menurut jam
   server. Ubah / nonaktifkan / hapus pemilih tetap memakai aturan lama (dikunci hanya
   saat `FINISHED`).
5. Ukuran teks halaman utama diatur **terpisah** untuk desktop dan HP (masing-masing
   Kecil / Sedang / Besar).
6. Ikon Dasbor & Keluar menjadi **ikon saja di semua ukuran layar**, termasuk beranda
   desktop (label tetap untuk pembaca layar + `title`).
7. Label status "Sudah Memilih" / "Belum Memilih" ikut ditulis Title Case di panel admin
   bila berdiri sendiri (pil, filter, papan angka); di dalam kalimat tetap huruf kecil.
8. Reset total **tidak dapat dijalankan saat pemilihan berlangsung** (tutup dulu lewat
   Jadwal), wajib kata sandi admin + mengetik `RESET TOTAL`. Akun admin tidak dihapus.
9. Living Election Wall memuat **siswa dan guru** (guru satu bagian tersendiri).

---

## 2. ATURAN LINTAS TAHAP

- **Aset.** CSS/JS ditulis di `resources/assets/{css,js}` lalu `php spark osis:minify`;
  hasil di `public/assets` ikut di-commit (Stage 23, `tests/unit/BuiltAssetsTest.php`).
- **Library pihak ketiga** disimpan apa adanya di `public/assets/vendor/<nama>-<versi>/`
  beserta file lisensinya (pola `three-0.186.1/`). Paket npm yang punya dependensi
  dibundel **sekali** dengan esbuild menjadi satu modul ES; perintah build dicatat di
  `STAGE2X-NOTES.md`. Tidak ada CDN saat runtime (aplikasi berjalan di LAN, CSP
  `default-src 'self'`).
- **Font** self-host di `public/assets/fonts/` beserta lisensi (OFL / Apache 2.0).
- **CSP** (`app/Config/ContentSecurityPolicy.php`) tidak dilonggarkan kecuali disebut
  eksplisit. Skrip inline baru wajib `csp_script_nonce()`.
- **Ikon** lewat `icon()` (`app/Helpers/app_helper.php`); ikon baru ditambahkan ke
  daftar `$paths` di sana.
- **Audit**: aksi baru = konstanta + entri `LABELS` di `app/Models/AuditLogModel.php`.
- **Route publik baru** masuk `RoleMatrixTest::PUBLIC_ROUTES`; path halaman baru diberi
  label di `VisitorTracker::PAGES` (`app/Services/VisitorTracker.php:57-90`).
- **Halaman dengan layout khusus** memanggil `service('renderer')->resetData()` setelah
  render (pola `Home::index`, `VotingController::booth`).
- Tanpa emoji; "SMP 1 DAWE" ditulis literal; gerak mengikuti `10-MOTION…` (token durasi,
  tanpa bounce/overshoot, parallax hanya untuk pointer halus) dan
  `prefers-reduced-motion`.
- Data siswa/guru tidak disimpan di `localStorage`.
- **Setiap tahap ditutup dengan:** test PHPUnit lulus (MariaDB; di container baru:
  pasang MariaDB, `composer install`, buat database `smp1dawe_osis_2026_test`, isi
  `database.tests.*` di `.env`), `php spark osis:minify`, `php spark osis:check`, uji
  browser Playwright 1440×900 & 390×844 tanpa galat konsol/CSP, screenshot
  `shots/stage2X-*.png`, `docs/catatan-tahap/STAGE2X-NOTES.md`, pembaruan `README.md`
  dan `docs/README.md`, commit & push.

---

## 3. TAHAP 1 — STAGE 25

### 3.1 Tambah data siswa & guru: langsung atau impor, hanya sebelum jadwal dimulai

**Kondisi sekarang.** Satu-satunya kunci adalah `FINISHED`
(`AdminController::resultsLocked()`, `app/Controllers/Admin/AdminController.php:64-67`).
Tambah satu per satu (`VoterController::new/create`) dan impor
(`ImportController::upload/commit`) masih boleh saat pemilihan berlangsung. Di daftar
pemilih (`app/Views/admin/voters/index.php:42-47`) ada dua tombol terpisah: "Tambah
siswa" (`admin/siswa/tambah`) dan "Impor Excel" (`admin/siswa/impor`).

**Aturan baru.**

- Tambah langsung dan impor file **hanya boleh** bila belum ada pemilihan atau status
  `UPCOMING` (`ElectionModel::resolveStatus()`, jam server).
- Saat `ONGOING` / `FINISHED`: ditolak di server (redirect + flash `error`), tombol
  tambah tidak tampil, halaman tambah menampilkan `notice` dengan waktu mulai.
- Pratinjau impor yang dibuat sebelum mulai tetapi disimpan sesudah mulai **ditolak** di
  `commit()` dan pratinjaunya dilepas (`ImportStore::release`).
- Unduh template tetap boleh kapan saja.
- Ubah data, ubah status, dan hapus pemilih **tidak berubah** (dikunci hanya saat
  `FINISHED`).

**Implementasi.**

- `AdminController`: `protected function additionsLocked(): bool` (ONGOING atau
  FINISHED) + konstanta pesan, misalnya: "Data pemilih hanya dapat ditambah sebelum
  pemilihan dimulai. Perbaikan data yang sudah ada tetap dapat dilakukan lewat Ubah."
  `render()` meneruskan `additionsLocked` ke semua view admin.
- Dicek di `VoterController::new()`, jalur tambah di `save()` (`VoterController.php:217-265`),
  `ImportController::upload()` (69-117) dan `commit()` (167-228).
- Satu tombol **Tambah Siswa** / **Tambah Guru** (ikon `plus`) di kepala daftar; tombol
  "Impor Excel" dihapus dari kepala daftar. Teks kosong (`index.php:107`): "Belum ada data
  siswa. Tambah langsung atau impor file Excel sebelum pemilihan dimulai."
- Halaman gabungan baru `app/Views/admin/voters/add.php` dengan dua tab (pola pil admin,
  `role="tablist"`): **Isi Langsung** | **Impor File**.
  - Tab Isi Langsung: field dari `admin/voters/form.php` dipindah ke partial
    `admin/voters/partials/fields.php` (dipakai juga oleh halaman Ubah).
  - Tab Impor File: panel template + unggah dari `admin/import/index.php:47-81` dipindah ke
    partial `admin/import/partials/upload.php`.
  - `GET admin/{siswa,guru}/tambah` membuka tab Isi Langsung, `GET admin/{siswa,guru}/impor`
    membuka tab Impor File (route lama tetap hidup). Pindah tab tanpa muat ulang
    (`history.replaceState`), tanpa JavaScript berupa tautan biasa. Galat validasi kembali
    ke tab asalnya.
  - Pratinjau → simpan → hasil impor tidak berubah.
- `admin/voters/form.php` hanya untuk **Ubah**; tombol "Banyak sekaligus? Impor Excel"
  (43-47) dihapus. Breadcrumb: Beranda > Siswa > Tambah.

**Test.** Seed bawaan berstatus ONGOING, jadi test tambah/impor yang harus lolos perlu
memindah jadwal ke UPCOMING. Test baru: tambah & impor ditolak saat ONGOING dan FINISHED
(form, unggah, simpan pratinjau), boleh saat UPCOMING dan tanpa jadwal, ubah tetap boleh
saat ONGOING, satu tombol tambah + dua tab di markup. Perbarui `AdminImportTest`,
`AdminPanelTest`, `StageElevenRefinementTest`, `StageFourteenRefinementTest` sesuai hasil.

### 3.2 Rekap pengunjung, grafik, dan ekspor PDF

**Kondisi sekarang.** Tabel `visitors` = satu baris per browser (`first_seen_at`,
`last_seen_at`, `page_views`); baris lebih lama dari `Config\Visitors::$retentionDays = 7`
hari dihapus otomatis. `App\Services\OnlineVisitors` punya cakupan
`online | hari-ini | 7-hari` (`SCOPES`, baris 24-32), `breakdown()` (169) untuk sebaran
perangkat/browser/OS (5 teratas + Lainnya), dan `filtered()` untuk query. Halaman
`/admin/pengunjung` (`app/Views/admin/visitors/index.php`) menampilkan papan angka,
sebaran berupa daftar batang, dan tabel berkolom: Pengunjung, Halaman, Perangkat,
Sistem · browser, Jaringan · lokal, Aktivitas, User-Agent (115-213).

**Perubahan.**

1. `Config\Visitors::$retentionDays` 7 → **31** (komentar: rekap 30 hari). Data lama yang
   sudah terhapus tidak kembali; rekap 30 hari terisi seiring waktu (catat di README 8.2).
2. `OnlineVisitors`: cakupan baru `SCOPE_MONTH = '30-hari'` → "30 hari terakhir"
   (`filtered()`: `Time::now()->subDays(30)`); `RECAP_SCOPES = [hari-ini, 7-hari, 30-hari]`;
   method `recap(string $scope): array`:
   - `range`: label, tanggal awal & akhir;
   - `totals`: pengunjung (baris browser), tamu, login, per peran (siswa/guru/admin, akun
     unik), halaman dibuka (`SUM(page_views)`), rata-rata lama kunjungan;
   - `breakdown`: hasil `breakdown()` untuk cakupan yang sama.
3. **Grafik di halaman.** Bagian sebaran (`visitor-split`) diganti **Rekap & grafik**: tiga
   donat SVG (**Perangkat**, **Browser**, **Sistem operasi**) memakai pola donat admin
   (`app/Views/admin/partials/candidate_results.php`: lingkaran `stroke-dasharray`,
   kelas `.donut`), angka total di tengah, legenda berisi label + jumlah + persen. Palet
   netral bertingkat (tinta → abu-abu), tanpa gradient; informasi tidak hanya lewat
   warna (legenda berangka). Ikut cakupan filter **Waktu** yang kini berisi: Online
   sekarang, Hari ini, 7 hari terakhir, 30 hari terakhir. Diperbarui bersama tabel (20 detik).
4. **Ekspor PDF.** Grup tombol **Ekspor PDF: Hari ini · 7 hari terakhir · 30 hari
   terakhir** (di HP: tombol ikon `download` + pilihan). Route
   `GET admin/pengunjung/cetak?waktu=hari-ini|7-hari|30-hari` →
   `VisitorController::pdf()`; nilai lain ditolak. View `app/Views/admin/visitors/pdf.php`
   (dompdf: CSS 2.1, tabel, tanpa flex/grid), **A4 lanskap**:
   - kepala: logo SMP 1 DAWE (`ResultPdf::imageData`), judul "Rekap Pengunjung",
     rentang + tanggal ("7 hari terakhir · 26 September – 2 Oktober 2026"), waktu cetak;
   - ringkasan angka dari `recap()`;
   - tiga grafik **batang horizontal** (Perangkat, Browser, Sistem operasi) dengan pola
     `.track/.fill` dari `admin/results/pdf.php` (dompdf tidak andal untuk SVG donat);
   - tabel kolom **Pengunjung | Halaman | Perangkat | Sistem · browser | Jaringan · lokal |
     Aktivitas**, isi sama dengan tabel web, **tanpa User-Agent, Client Hints, situs asal,
     halaman pertama, maupun tema perangkat**; urut `last_seen_at` terbaru; dibatasi
     `PDF_MAX_ROWS` (awal 1.000, sesuaikan setelah mengukur waktu render) dengan catatan
     "Menampilkan 1.000 pengunjung terbaru dari N";
   - catatan kaki + "Halaman X dari Y" di setiap halaman.
   Nama file `rekap-pengunjung-<waktu>-<YYYYMMDD>.pdf`, `inline` seperti hasil akhir.
5. `App\Libraries\ResultPdf::render()` menerima orientasi (`'portrait'` bawaan,
   `'landscape'`) tanpa mengubah PDF hasil akhir.
6. Pemformat baris tabel (`$joinParts`, `$deviceName`, perakitan sel di
   `visitors/index.php` ±25-150) dipindah ke `App\Libraries\VisitorPresenter`, dipakai
   tabel web **dan** PDF agar isinya identik.
7. Audit baru `VISITOR_EXPORT` "Ekspor rekap pengunjung" (rentang + jumlah baris), karena
   ekspor memuat data pribadi (nama, IP).

Catatan semantik (tulis di README): satu baris = satu browser; "Hari ini" = browser yang
aktif sejak 00.00 WIB; kolom Aktivitas tetap kumulatif sejak pertama tercatat.

**Test.** Cakupan `30-hari` diterima; `recap()` konsisten dengan `breakdown()` dan tabel;
`cetak` mengembalikan `application/pdf` dan HTML view-nya tidak memuat teks User-Agent
(mis. "Mozilla/") maupun Client Hints; nilai `waktu` tidak sah ditolak; audit tercatat;
`retentionDays` 31; halaman memuat tiga donat & tombol ekspor.

### 3.3 Teks halaman utama: tampil, ulang, font, ukuran, efek, huruf 3D

**Kondisi sekarang (Stage 24).** Kalimat di `settings.hero_lines` (`App\Libraries\HeroText`:
maks. 20 kalimat × 80 karakter). Admin `/admin/halaman-utama` (`HomepageController`).
Beranda: `.hero-type` (`app/Views/home/index.php:105-116`), komponen `HeroType` di
`resources/assets/js/home.js:1117-1345` (ketik 55 ms/huruf, tahan 2,6 dtk, hapus, jeda
3 dtk lalu ulang; reduced-motion: kalimat berganti 4,2 dtk), font Newsreader, ukuran di
`resources/assets/css/home.css` (562-612, HP 1883, layar pendek 2057).

#### Pengaturan (tabel `settings`, tanpa migration)

| Kunci | Nilai | Bawaan |
|---|---|---|
| `hero_enabled` | `1` / `0` | `1` |
| `hero_loop` | `1` / `0` | `1` |
| `hero_effect` | `ketik` / `tulis` | `ketik` |
| `hero_font` | kunci katalog font | `newsreader` |
| `hero_size_desktop` | `kecil` / `sedang` / `besar` | `sedang` |
| `hero_size_mobile` | `kecil` / `sedang` / `besar` | `sedang` |
| `hero_3d` | `1` / `0` | `1` |

- `App\Libraries\HeroStyle`: konstanta, `defaults()`, `load()` (aman bila tabel belum
  ada), `validate()`, `save()`, `diff()` untuk audit, kelas CSS & atribut data untuk view.
- `App\Libraries\HeroFonts`: katalog font (label, nama family CSS, kelompok, weight, file
  woff2 & woff, skala optik, line-height, lisensi).
- **Tampilkan teks = mati**: `.hero-type` tidak dirender; kalimat **tidak dihapus**.
- **Ulangi = hidup**: perilaku Stage 24. **Ulangi = mati**: kalimat tampil berurutan
  sekali; kalimat terakhir **tetap tampil** (tidak dihapus), caret hilang ±1,2 dtk
  kemudian; satu kalimat = diketik/ditulis sekali lalu diam. Reduced-motion: kalimat
  berganti 4,2 dtk lalu berhenti di kalimat terakhir.
- **Validasi teks** tambahan: karena huruf 3D digambar dari file font, hanya huruf Latin
  (termasuk aksen Latin-1), angka, spasi, dan tanda baca umum (`. , : ; ! ? ' " ( ) - – — / & % +`
  dan tanda kutip lengkung) yang diterima; karakter lain ditolak dengan pesan jelas
  (emoji juga ditolak, sesuai master spec).

#### Library font: Fontsource

Font diambil dari **Fontsource** (paket npm `@fontsource/<family>` 5.3.0, subset `latin`),
disimpan di `public/assets/fonts/hero/` sebagai `<family>-latin-<weight>-normal.woff2`
(untuk CSS) **dan** `.woff` (untuk troika, yang tidak membaca woff2), beserta file lisensi.

| Kelompok | Font | Weight | Lisensi |
|---|---|---|---|
| Serif editorial | **Newsreader** (bawaan, sudah dipakai) | 400 | OFL |
| | Fraunces | 500 | OFL |
| | Playfair Display | 500 | OFL |
| | Instrument Serif | 400 | OFL |
| Sans | Plus Jakarta Sans (sudah dipakai) | 600 | OFL |
| | Bricolage Grotesque | 500 | OFL |
| | Space Grotesk | 500 | OFL |
| Tulisan tangan (Latin) | Caveat | 500 | OFL |
| | Dancing Script | 500 | OFL |
| | Kalam | 400 | OFL |
| | Patrick Hand | 400 | OFL |
| | Satisfy | 400 | Apache 2.0 |
| | Great Vibes | 400 | OFL |
| | Sacramento | 400 | OFL |
| | Homemade Apple | 400 | Apache 2.0 |

Semua file di atas tersedia di Fontsource 5.3.0 (total woff2 ±400 KB, woff ±450 KB);
beranda hanya memuat satu font yang dipilih.

- `resources/assets/css/hero-fonts.css`: `@font-face` semua font katalog dengan nama
  family khusus (mis. `'Hero Caveat'`), `font-display: swap`; dimuat di beranda dan di
  halaman admin Teks Halaman Utama (browser hanya mengunduh font yang dipakai).
- Beranda mem-`preload` woff2 font terpilih hanya bila teks tampil.
- Pengecualian aturan "maksimal tiga family" (`07-TYPOGRAPHY…` bagian 9) **hanya untuk
  teks hero** yang dipilih admin; seluruh UI tetap tiga family. Tambahkan catatan revisi
  di dokumen 07.

#### Ukuran

Kelas `hero-type--d-{kecil|sedang|besar}` dan `hero-type--m-{kecil|sedang|besar}`,
dikalikan `--hero-scale` (skala optik per font; contoh awal: Great Vibes & Sacramento 1,3,
Caveat 1,15, Dancing Script 1,1, lainnya 1,0 — kalibrasi di browser).

| | Kecil | Sedang (= Stage 24) | Besar |
|---|---|---|---|
| Desktop | `clamp(1.75rem, 3.6vw, 3.75rem)` | `clamp(2.25rem, 4.6vw, 4.75rem)` | `clamp(2.75rem, 5.8vw, 6rem)` |
| HP (≤ 767 px) | `clamp(1.5rem, 6.8vw, 2.25rem)` | `clamp(1.875rem, 8.4vw, 2.75rem)` | `clamp(2.25rem, 10.2vw, 3.375rem)` |
| Layar pendek | ±0,8 × sedang | `clamp(1.5rem, 6.5vh, 2.25rem)` | ±1,2 × sedang |

Pastikan kalimat 80 karakter dengan font terlebar tidak meluber di 360×760 dan 1440×900.

#### Efek

| Efek | 3D (bawaan) | Cadangan 2D |
|---|---|---|
| **Mengetik** | huruf muncul satu per satu sambil "turun" dari kedalaman (z +0,35 em → 0, 160–220 ms, ease-out), caret 3D tipis berkedip; hapus mundur cepat | `HeroType` Stage 24 apa adanya |
| **Tulisan tangan** (tanpa tangan) | **sapuan tinta per huruf**: tiap huruf terungkap kiri → kanan dengan tepi lembut sedikit berderau; garis tepi tipis muncul ±80 ms lebih dulu lalu isi "meresap"; durasi sebanding lebar huruf (90–260 ms), huruf berikut mulai di 65–75 % huruf sebelumnya, jeda ±140 ms di spasi dan ±260 ms di tanda baca; hilang dengan tinta memudar/larut | span per huruf dengan `clip-path: inset(0 100% 0 0)` → `inset(0 0 0 0)` berurutan + sedikit blur → tajam; hilang = pudar + naik sedikit |

Irama tahan/hapus/jeda/ulang memakai konstanta dan pengaturan yang sama untuk 2D dan 3D.
Efek tulisan tangan dapat dipakai dengan semua font, tetapi admin diberi petunjuk bahwa
kelompok **Tulisan tangan** paling cocok.

#### Huruf 3D timbul: troika-three-text

- **Library:** troika-three-text 0.52.5 (MIT) + dependensinya (troika-three-utils 0.52.5,
  troika-worker-utils 0.53.0, webgl-sdf-generator 1.1.1, bidi-js 1.1.0; semua MIT),
  dibundel sekali dengan esbuild menjadi
  `public/assets/vendor/troika-three-text-0.52.5/troika-three-text.module.js`; impor
  `three` diarahkan ke `../three-0.186.1/three.module.js`. Simpan lisensi tiap paket.
- **Modul** `resources/assets/js/hero-text3d.js` (`<script type="module">`), dimuat hanya
  bila teks tampil, `hero_3d = 1`, dan gerak tidak dikurangi (modul juga memeriksa
  sendiri).
- **Tanpa CDN & tanpa melonggarkan CSP:** `configureTextBuilder({ useWorker: false })`
  (worker blob diblokir CSP), properti `font` selalu diisi woff lokal (bawaan troika
  memuat Roboto dari CDN), dan pemuatan font cadangan Unicode dari CDN dimatikan
  (opsi `unicodeFontsURL` atau setara pada versi terpasang). Bila tanpa worker terbukti
  terlalu lambat, `workerSrc = ['self', 'blob:']` boleh ditambahkan setelah diukur dan
  dicatat.
- **Tampilan "timbul":** permukaan depan + 8–12 lapisan belakang (teks yang sama, mundur
  bertahap di sumbu z, warna makin gelap `#8C8E86` → `#3A3940`) membentuk ekstrusi;
  bayangan jatuh lembut (`outlineBlur`, opasitas rendah); tepi depan tipis opsional
  (`strokeWidth`). Warna dasar `--on-night` (`#F2F1EC`), tanpa gradient/neon. Semua lapisan
  berbagi atlas SDF yang sama (murah).
- **Kamera:** perspektif FOV ±20–28°, sedikit miring (±-0,08 rad), hanyut pelan ±1,5° saat
  diam, paralaks pointer maks. 3–4° hanya untuk `(hover: hover) and (pointer: fine)`.
- **Ukuran & tata letak:** `fontSize` dari ukuran CSS terhitung (preset ukuran tetap
  berlaku), `maxWidth` = lebar kontainer, rata tengah, `lineHeight` dari katalog.
- **Kinerja:** kanvas WebGL transparan menutup area `.hero-type` (`pointer-events: none`),
  DPR maks. 2 (desktop) / 1,5 (HP), `failIfMajorPerformanceCaveat: true`, render hanya saat
  ada animasi/gerak kamera, berhenti saat scene hero tidak aktif atau tab tersembunyi
  (tambahkan kontrak event kecil dari `home.js`, mis. `osis:hero` dengan `detail.active`),
  dilepas saat `pagehide`.
- **Cadangan:** bila modul/WebGL/font gagal (batas ±2,5 dtk) atau `hero_3d = 0`, efek 2D
  yang berjalan. Teks DOM selalu ada (`aria-hidden` + daftar pembaca layar Stage 24); saat
  3D aktif, teks DOM disembunyikan secara visual tanpa mengubah tata letak. Tanpa
  JavaScript: kalimat pertama tampil biasa dengan font terpilih.
- **Risiko kompatibilitas:** troika 0.52.x belum tentu diuji dengan three r186. **Uji
  dulu** di Chromium (Playwright) sebelum membangun efek. Rencana B: huruf diekstrusi
  sungguhan dengan `THREE.ExtrudeGeometry` dari jalur glyph opentype.js (MIT, vendor
  terpisah), efek tetap sama.

#### Admin `/admin/halaman-utama`

- Textarea kalimat tetap (bagian **Teks**). Bagian baru **Tampilan**:
  - sakelar **Tampilkan teks di halaman utama**, **Ulangi teks**, **Huruf 3D timbul**;
  - pilihan **Efek**: Mengetik / Tulisan tangan (kartu radio);
  - **Font**: daftar radio per kelompok, label tiap font ditulis dengan font itu sendiri
    (contoh kalimat pertama);
  - **Ukuran desktop** dan **Ukuran HP**: Kecil / Sedang / Besar (segmented);
  - **Pratinjau** gelap (mode desktop/HP) memutar kalimat pertama dengan pengaturan
    yang sedang dipilih (2D; tombol "Pratinjau 3D" opsional).
- Satu tombol simpan. Audit: `HOMEPAGE_TEXT` (sudah ada) untuk kalimat, aksi baru
  `HOMEPAGE_STYLE` "Ubah tampilan teks halaman utama" berisi perbedaan, mis.
  "Font: Newsreader → Caveat; Efek: Mengetik → Tulisan tangan; Ulangi: ya → tidak".
- Tidak dikunci saat pemilihan selesai (tidak memengaruhi hasil).

#### Beranda

- `Home::index()` meneruskan gaya dari `HeroStyle::load()`.
- `home/index.php`: `.hero-type` hanya bila tampil & ada kalimat; atribut
  `data-hero-effect`, `data-hero-loop`, `data-hero-3d`, `data-hero-font` (URL woff),
  `style="--hero-font: …; --hero-scale: …"`, kelas ukuran; `<script type="module">`
  modul 3D hanya bila 3D aktif.

**Test.** `HeroStyle` (bawaan, nilai tidak sah kembali ke bawaan, validasi), file font &
lisensi katalog ada, simpan + audit `HOMEPAGE_STYLE`, nonaktif menyembunyikan teks tanpa
menghapus `hero_lines`, markup beranda per pengaturan (kelas ukuran, flag loop, modul 3D
hanya saat aktif), teks berisi emoji/aksara non-Latin ditolak. Perbarui
`StageTwentyFourRefinementTest`.

### 3.4 Reset total

**Kendala database.** Trigger Stage 4 (`2026-04-01-000001_AddVoteIntegrityGuards.php`)
menolak DELETE/UPDATE pada `student_votes`, `teacher_votes`, `vote_unlock_logs`, dan
`audit_logs`; foreign key RESTRICT. **`TRUNCATE TABLE` tidak memicu trigger**, sehingga
reset memakai TRUNCATE dengan `FOREIGN_KEY_CHECKS = 0`; kedelapan trigger tetap terpasang
(`SystemCheck::TRIGGERS`, `php spark osis:check`).

**Halaman** `/admin/reset`, menu **Reset Total** (ikon `trash`) di grup bawah sidebar setelah
Akun Admin (`app/Views/admin/partials/sidebar.php:65-87`; tiga grup menu utama tidak
berubah).

- **Yang dihapus** (dengan jumlah saat ini): data siswa, data guru, semua suara, log unlock,
  pasangan calon + semua file unggahannya, jadwal pemilihan, semua audit log, semua log
  pengunjung, teks halaman utama + pengaturan tampilannya (kembali bawaan), pratinjau
  impor yang tertunda. (Tahap 2 menambah: foto avatar pemilih.)
- **Yang tetap:** akun admin, `.env`/konfigurasi, file aplikasi & aset.
- Anjuran backup lebih dulu (perintah `mysqldump` dari README bagian 17).
- **Syarat:** pemilihan tidak sedang berlangsung (bila ONGOING: formulir nonaktif + petunjuk
  "Tutup pemilihan lewat Jadwal"), kata sandi admin saat ini (dibatasi seperti Akun Admin:
  `loginBlockedSeconds` / `recordLoginFailure`, scope `admin_reset`), mengetik
  `RESET TOTAL` persis, mencentang "Saya mengerti semua data di atas dihapus permanen", lalu
  modal konfirmasi bahaya (`data-confirm-danger`).

**`App\Services\TotalReset::run(int $adminId): array`**

1. `GET_LOCK('osis_reset', 0)` (cegah dua permintaan bersamaan).
2. `SET FOREIGN_KEY_CHECKS = 0` → `TRUNCATE` berurutan: `vote_unlock_logs`,
   `student_votes`, `teacher_votes`, `audit_logs`, `visitors`, `students`, `teachers`,
   `candidates`, `elections` → `SET FOREIGN_KEY_CHECKS = 1` (di `finally`).
3. Hapus baris `settings` berawalan `hero_` (kalimat + tampilan).
4. Hapus file di `public/uploads/candidates/` kecuali `.gitkeep` (hanya file biasa dengan
   pola nama aman); kosongkan pratinjau impor (`ImportStore::purgeAll()` baru);
   `cache()->clean()`.
5. `RELEASE_LOCK`, lalu tulis audit `RESET_TOTAL` sebagai **entri pertama** log baru, mis.
   "Reset total: 312 siswa, 41 guru, 298 suara, 3 pasangan calon (12 file), 1 jadwal,
   87 log audit, 1.204 pengunjung, 12 kalimat halaman utama dihapus."
6. Kembali ke Beranda admin dengan pesan sukses.

Sifat yang dicatat di README: TRUNCATE tidak transaksional (bila terputus, jalankan ulang;
operasi idempoten); butuh hak `DROP` pada user database (root Laragon sudah); sesi siswa/guru
otomatis berakhir karena akunnya tidak ada lagi (`AuthFilter::accountIsValid`); sesi admin
tetap.

**Test** (MariaDB): reset berhasil saat UPCOMING/FINISHED/tanpa jadwal → semua tabel di
atas kosong, `admins` utuh, 8 trigger masih ada, `audit_logs` berisi tepat satu baris
`RESET_TOTAL`, `hero_*` hilang, file uji di folder sementara terhapus; ditolak saat ONGOING,
kata sandi salah (dan terkunci setelah 5 kali), frasa salah, centang tidak ada; menu tampil.

### 3.5 Perapian halaman pemilih

1. **`vote-card__text` dihapus** dari keempat cabang `app/Views/partials/vote_status.php`
   (31-43) beserta aturan CSS-nya (`resources/assets/css/app.css:1064` dan `:1105`). Jarak
   judul → tombol dirapikan. Penjelasan cukup di bagian lain (panel Jadwal pemilihan dan
   pembuka multi-langkah `coblos`).
2. **Ikon Dasbor & Keluar tanpa border dan latar, ikon saja** di HP dan desktop:
   `.site-nav__action` (`app.css:284-327`) tanpa `border` dan `background`, lebar 44 px di
   semua ukuran, `.site-nav__label` disembunyikan secara visual di semua ukuran, `title`
   ("Dasbor Siswa", "Keluar") di elemen (`app/Views/partials/header.php:28-35, 52-57`),
   hover = opasitas 0,72, `:focus-visible` tetap jelas. Berlaku di dasbor, `coblos`,
   `sekilas-paslon`, profil paslon, `pilihanku`, `coblos/yakin`, dan login peran lain;
   aturan beranda (`home.css:125-138`) disederhanakan agar desktop juga ikon saja. Tombol
   teks "Selesai & keluar" di `pilihanku` **tidak** diubah (CTA, bukan ikon navigasi).
   `booth_nav` sudah sesuai.
3. **Title Case:**
   - `app/Views/voting/sekilas.php:35` → **Sekilas Paslon**;
   - `vote_status.php` → **Sudah Memilih · Terkunci**, **Belum Memilih**;
   - label status berdiri sendiri di admin: `admin/voters/index.php` (filter & pil),
     `admin/voters/show.php`, `admin/partials/scoreboard.php`, `admin/unlock/index.php`,
     `admin/analytics/pane.php`, `admin/dashboard.php` (periksa konteks: di dalam kalimat
     tetap huruf kecil);
   - `VisitorTracker::PAGES`: "Sekilas Paslon (siswa)" / "(guru)".
   Perbarui test yang mencocokkan teks lama (`VotingTest`, `StageTwentyTwoRefinementTest`,
   `AdminPanelTest`, `StageElevenRefinementTest`, `BoothRefinementTest`, dll.).

### 3.6 Penutup Tahap 1

- `STAGE25-NOTES.md`: hasil, keputusan, perintah build vendor (esbuild), daftar font &
  lisensi, angka test, screenshot.
- README: tabel menu admin (bagian 8: Siswa/Guru, Teks Halaman Utama, Reset Total),
  bagian 8.2 (rekap 30 hari, PDF, retensi 31 hari), 17 (reset total vs backup), 18
  (troubleshooting: TRUNCATE butuh hak DROP, 3D tidak muncul → cadangan 2D), 19 (jumlah
  test), 21 (teks hero).
- `docs/README.md`: baris Stage 25.
- Screenshot: admin tambah (dua tab), rekap & grafik pengunjung, PDF (halaman 1), admin
  teks halaman utama, beranda efek mengetik 3D & tulisan tangan (desktop & HP), reset
  total, dasbor tanpa `vote-card__text`, ikon navigasi.
- **Handoff ke Tahap 2:** `TotalReset` harus diperluas untuk folder avatar; `HeroStyle`
  sudah stabil; `VisitorPresenter` siap dipakai.

---

## 4. TAHAP 2 — STAGE 26

Prasyarat: Tahap 1 sudah di-merge; baca `STAGE25-NOTES.md`.

### 4.1 Data avatar

- Migration `2026-10-03-000001_AddAvatarToVoters` (tanggal setelah migration terakhir):
  tambah ke `students` **dan** `teachers`:
  `avatar_file VARCHAR(80) NULL`, `avatar_style VARCHAR(20) NULL`,
  `avatar_seed VARCHAR(32) NULL`, `avatar_updated_at DATETIME NULL`. Kolom ini **tidak**
  masuk `allowedFields` yang dipakai impor/`VoterEditor`; ditulis hanya oleh layanan avatar.
- Folder `public/uploads/avatars/` (+ `.gitkeep`; `.gitignore`:
  `/public/uploads/avatars/*` dan `!/public/uploads/avatars/.gitkeep`). Aturan
  `public/uploads/.htaccess` (hanya jpg/png/webp bernama aman, tanpa eksekusi) otomatis
  berlaku.
- **Avatar bawaan** (belum unggah foto & belum memilih avatar): deterministik tanpa menulis
  database, `App\Libraries\AvatarIdentity::default(VoterType, int $id)` →
  seed = 16 heksa pertama HMAC-SHA256(kunci rahasia aplikasi, `"s:{id}"` / `"t:{id}"`),
  gaya = salah satu dari tiga gaya berdasarkan hash. Stabil di setiap muat ulang, tidak bisa
  dibalik menjadi id.

### 4.2 Unggah foto & pilih avatar di dasbor

**Letak.** Kartu baru `app/Views/partials/avatar_card.php` di dalam `aside.dash-aside`
setelah panel Jadwal pemilihan (`student/dashboard.php` & `teacher/dashboard.php`). Di HP
kartu berada di bawah kartu status, jadi tombol memilih tetap yang pertama. Jam melayang
tetap elemen terakhir `<main>` (`StageTenRefinementTest.php:144`,
`BoothRefinementTest.php:126-127`); setelah memilih, dasbor tetap tanpa tautan ke `coblos`
(`VotingTest.php:495-496`).

**Isi kartu.**

- Avatar saat ini dalam **lingkaran** besar.
- Tombol: **Unggah foto** (ikon `upload`), **Acak avatar** (ikon `refresh`, pratinjau gaya &
  seed baru), **Pakai avatar ini** (muncul setelah mengacak), **Hapus foto** (bila ada foto).
- Keterangan: "Foto atau avatar ini akan dipakai di **Living Election Wall**. Wall hanya
  menandai partisipasi, tidak pernah menampilkan pilihanmu." (guru: "Anda", "pilihan Anda").
- Atribusi kecil: "Ilustrasi avatar: DiceBear — Adventurer oleh Lisa Wischofsky dan Big
  Smile oleh Ashley Seo (CC BY 4.0), Avataaars oleh Pablo Stanley."
- Tidak dikunci status pemilihan (tidak memengaruhi hasil).

**Alur unggah (browser).** `<input type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif">`
→ `createImageBitmap` (orientasi EXIF) → dialog potong `<dialog>`: masker lingkaran, geser
untuk memposisikan, slider zoom 1–3×, panah keyboard & +/- → kanvas 512×512 →
`toBlob('image/webp', 0.85)` (cadangan `image/jpeg` 0.88) → `fetch` POST multipart dengan
header `X-CSRF-TOKEN` → avatar di halaman langsung berganti. Batas sisi klien: file asli
maks. 15 MB. Tanpa JavaScript: formulir unggah biasa, server memotong persegi di tengah.

**Server `App\Libraries\VoterAvatars`** (pola `CandidateAssets`; boleh mengekstrak
pemrosesan gambar bersama, mis. `App\Libraries\ImageProcessor`):

- maks. 2 MB setelah kompres (dan ≤ `upload_max_filesize`), ekstensi + MIME (`finfo`) +
  `getimagesize` cocok (jpeg/png/webp), sisi min. 128 px, maks. 8000 px & 40 MP;
- orientasi EXIF, potong persegi di tengah, ubah ke **320×320**, encode ulang **WebP q80**
  (JPEG q85 bila GD tanpa WebP), metadata hilang;
- nama acak `s{id}-{16hex}.webp` / `t{id}-…`, tulis ke file sementara lalu `rename`,
  `chmod 0644`; file lama dihapus; `avatar_style`/`avatar_seed` dikosongkan;
- dibatasi 10 perubahan per 10 menit per pemilih (Throttler).

**Route** (grup `siswa` dan `guru`, filter `studentauth` / `teacherauth`):
`POST avatar` (unggah), `POST avatar/acak` (`style` ∈ 3 gaya, `seed` = `/^[a-f0-9]{16}$/`
dibuat `crypto.getRandomValues`; menyimpan pilihan dan menghapus foto bila ada),
`POST avatar/hapus` (kembali ke bawaan). Balasan JSON untuk `fetch`, redirect untuk
formulir biasa.

**DiceBear.** `@dicebear/core` 9.4.x + `@dicebear/adventurer`, `@dicebear/avataaars`,
`@dicebear/big-smile` 9.4.2 dibundel menjadi
`public/assets/vendor/dicebear-9.4.2/dicebear.module.js` + file lisensi (kode MIT; desain
Adventurer & Big Smile **CC BY 4.0 — wajib atribusi**; Avataaars "free for personal and
commercial use"). Modul bersama `resources/assets/js/avatar-art.js`:
`avatarDataUri({ style, seed })` dengan cache memori, latar bulat dari palet lembut
bawaan. Dipakai dasbor (`dashboard-avatar.js`) dan wall (`wall.js`).

**Animasi 3D avatar** cukup CSS: kartu `perspective`, lapisan avatar & lencana di
`translateZ` berbeda, kemiringan halus (maks. 6°, pointer halus saja), tanpa library
tambahan.

**Moderasi admin.** `admin/voters/show.php` menampilkan avatar pemilih + tombol **Hapus foto
avatar** (POST `admin/{siswa,guru}/{id}/avatar/hapus`, `data-confirm-danger`, audit baru
`AVATAR_REMOVE` "Hapus foto avatar pemilih"). Menghapus pemilih ikut menghapus filenya.

### 4.3 Living Election Wall

**Route publik.** `GET partisipasi` (halaman) dan `GET partisipasi/status` (JSON), controller
`App\Controllers\WallController`. Keduanya 404 bila `Config\Homepage::$electionWall = false`
(bawaan `true`, dapat diubah lewat `.env`), plus `$wallPollSeconds = 20` dan
`$wallCacheSeconds = 5`. Tambahkan ke `RoleMatrixTest::PUBLIC_ROUTES` dan
`VisitorTracker::PAGES` ("Living Election Wall").

**Data `App\Services\ParticipationWall`** memakai definisi `AnalyticsService`: pemilih aktif
(`status_aktif = 1`), berpartisipasi = punya suara `LOCKED` pada pemilihan berjalan.

- Isi: status pemilihan; total (semua/siswa/guru: total, sudah, belum, persen); kelompok per
  **rombel** (kolom `kelas`, urut jenjang `Grade::fromKelas` lalu urutan alami, tanpa jenjang
  = "Lainnya" di akhir) berisi anggota; kelompok **Guru**. Anggota hanya: kunci (`s{id}` /
  `t{id}`), nama, avatar (URL foto atau gaya + seed), sudah/belum.
- **Tidak pernah**: NISN/NIP, jenis kelamin, nomor absen, waktu memilih, perangkat, paslon,
  urutan siapa yang terakhir memilih.
- `partisipasi/status`: `{status, label, totals, voted: ["s12", …], poll: {interval}}`,
  cache server 5 dtk, `Cache-Control: no-store`, interval 20 dtk saat UPCOMING/ONGOING,
  0 saat FINISHED atau tanpa jadwal.

**Tata letak** (halaman khusus, latar gelap, tanpa sidebar). Layout `main.php` dengan flag
`wallNav` → `partials/wall_nav.php` (pola `boothNav`), `body.page-wall`, CSS baru
`resources/assets/css/wall.css` (token gelap `--night*`, `--on-night*` dari `home.css`
33-71), JS `resources/assets/js/wall.js` (modul).

1. **Header sticky** (tinggi ±60 px, garis tipis bawah): kiri ikon kembali (`arrow-left`,
   ke scene perolehan suara beranda), tengah logo PILKETOS (`Config\Homepage::$logoOnDark`),
   kanan ikon dasbor (`grid`): siswa → `siswa`, guru → `guru`, admin → `admin`, tamu →
   portal masuk beranda (`/#masuk`, label "Masuk").
2. **Blok partisipasi** (bukan sekadar angka):

   ```text
   PARTISIPASI PEMILIHAN

         218
     dari 256 siswa

   ████████████████░░░░  85,2%

   218 sudah berpartisipasi
    38 belum berpartisipasi
   ```

   Kicker JetBrains Mono kapital (tracking 0,18em); angka Newsreader besar
   (`tabular-nums`); bar 2 px; desimal koma; angka selalu konsisten (sudah + belum =
   total). Kata benda mengikuti cakupan filter: "pemilih" (semua), "siswa", "siswa 7A",
   "guru". Satu baris kecil miring: "Halaman ini hanya menandai partisipasi. Pilihan setiap
   pemilih tetap rahasia." Bahasa netral: **"Belum berpartisipasi"**, bukan "yang belum
   memilih".
3. **Toolbar sticky** di bawah header: cari nama (langsung saat mengetik), filter **Kelas**
   (Semua; grup Kelas 7/8/9 berisi rombel; Guru), **Urutkan** (Nama A–Z [bawaan], Sudah
   berpartisipasi dulu, Belum berpartisipasi dulu), jumlah hasil (`aria-live="polite"`),
   **Buka semua / Tutup semua**. Penyaringan di tempat tanpa merusak galeri: kartu tidak
   cocok disembunyikan, bagian kosong ikut tersembunyi. Kondisi filter di query URL; status
   buka/tutup bagian boleh di `sessionStorage` (bukan data pemilih).
4. **Bagian per rombel**: `<details open>` dengan `summary` besar (rombel Newsreader, kicker
   "Kelas 7", mini "28/32" + garis progres tipis, chevron); pemisah jenjang; bagian **Guru**
   di akhir.
5. **Kartu** (galeri, bukan tabel): avatar bulat besar (±112 px desktop, ±84 px HP), nama
   (maks. 2 baris).
   - **Belum berpartisipasi:** avatar normal, sedikit redup (mis. `saturate(.6)
     brightness(.85)`, opasitas ±0,85), tanpa tanda khusus.
   - **Sudah berpartisipasi:** avatar sama, lencana centang kecil bulat di sudut
     (monokrom: lingkaran `--on-night`, centang tinta), glow sangat halus, nama normal.
   - Teks tersembunyi untuk pembaca layar: "Sudah berpartisipasi" / "Belum berpartisipasi"
     (status tidak hanya lewat warna/visual).
   - **Tidak ada warna paslon** di seluruh halaman, dan tidak pernah ada teks "memilih
     pasangan 0X".
6. **Gerak mikro (sinematik, sedikit 3D):** kartu muncul bertahap 60–80 ms (dibatasi per
   bagian) dari sedikit kedalaman, angka bergulir 600–900 ms, bar terisi, kemiringan 3D
   halus saat diarahkan (pointer halus; satu listener terdelegasi), lencana centang
   tergambar + satu denyut glow saat status berubah karena pembaruan langsung, buka/tutup
   bagian halus. Reduced-motion: semua langsung tanpa gerak.
7. **Pembaruan langsung:** polling `partisipasi/status` tiap 20 dtk selama belum selesai &
   tab terlihat; kartu tidak berpindah posisi kecuali urutan = status (pindah dengan FLIP
   260 ms). Unlock (sudah → belum) ditangani diam-diam.
8. **Kinerja:** kartu dirender server (tanpa JavaScript tetap terbaca; avatar DiceBear
   diganti inisial dalam lingkaran, foto tetap tampil); avatar DiceBear dibuat malas dengan
   `IntersectionObserver` + cache; foto `loading="lazy"`, `decoding="async"`, `width/height`;
   `content-visibility: auto` per bagian.
9. Status kosong: tanpa jadwal → "Belum dijadwalkan", semua kartu netral; sebelum mulai →
   "Pemilihan dibuka {waktu}".

### 4.4 Privasi: perolehan publik tidak diubah

- `App\Services\PublicLiveCount`, `GET hitung-suara`, dan scene perolehan suara beranda
  **tidak diubah**: persentase per paslon tetap tampil dan diperbarui selama pemilihan
  berlangsung (keputusan pemilik proyek, bagian 1 butir 1).
- Wall tetap tidak pernah memuat pilihan, waktu memilih, maupun urutan siapa yang terakhir
  berpartisipasi (bagian 4.3), karena data itu memang tidak dibutuhkan.
- Catat di `STAGE26-NOTES.md` bagian Known limitations: gabungan wall + perolehan live
  membuat pilihan seseorang kadang dapat ditebak saat pemilih sepi; diterima pemilik
  proyek. Bila aturan sekolah berubah, `homepage.publicLiveCount = false` (Stage 5) atau
  `homepage.electionWall = false` tetap tersedia.

### 4.5 Tautan dari scene perolehan suara

- `app/Views/partials/footer.php:17-26`: `p.site-footer__online` dan tautan baru
  `a.site-footer__wall` ("Living Election Wall" + ikon `arrow-up-right`) dibungkus
  `div.site-footer__row`; hanya bila wall aktif.
- **Desktop** (> 767 px): satu baris dengan angka online (di kanannya, dipisah garis tipis),
  gaya mono kecil yang sama (`home.css:1383-1409`).
- **HP** (≤ 767 px): tepat di bawah baris online, rata tengah (`home.css:1953-1989`).
- Perbarui `StageNineteenRefinementTest.php:520` dan `StageTwentyOneRefinementTest.php:238-244`.

### 4.6 Reset total diperluas

`TotalReset` ikut mengosongkan `public/uploads/avatars/` (kecuali `.gitkeep`) dan halaman
reset menampilkan jumlah foto avatar yang akan dihapus.

### 4.7 Test Tahap 2

Migration naik/turun; `AvatarIdentity` deterministik; unggah (jenis/ukuran/dimensi salah
ditolak, hasil 320×320 WebP, file lama terhapus, throttle), acak & hapus; kartu avatar tidak
mengubah urutan dasbor (test Stage 9/10 tetap lulus); admin hapus foto + audit; wall: tanpa
NISN/NIP/waktu/paslon di HTML dan JSON, hitungan sama dengan `AnalyticsService`, siswa
nonaktif tidak tampil, rombel berurutan, `electionWall = false` → 404 & tautan hilang;
`hitung-suara` tetap memuat persentase per paslon saat ONGOING (test Stage 5 tetap lulus);
tautan footer; reset total menghapus avatar.

### 4.8 Penutup Tahap 2

- `STAGE26-NOTES.md` (perintah bundel DiceBear, lisensi & atribusi, keputusan privasi).
- README: bagian 9/10 (avatar), 8 (moderasi avatar), 21 (tautan Living Election Wall di
  scene perolehan suara), bagian baru **Living Election Wall**, 17 (backup
  `public/uploads/avatars/`), 19 (jumlah test).
- `docs/README.md`: baris Stage 26.
- Screenshot: wall desktop & HP (bagian terbuka/tertutup, pencarian, filter), kartu avatar
  di dasbor, dialog potong foto, scene perolehan suara dengan tautan, admin hapus foto.

---

## 5. RISIKO & CADANGAN

| Risiko | Penanganan |
|---|---|
| troika-three-text tidak cocok dengan three r186 | uji pertama di Tahap 1; rencana B `ExtrudeGeometry` + opentype.js; terakhir cadangan 2D |
| Huruf 3D berat di HP lemah | `failIfMajorPerformanceCaveat`, DPR dibatasi, render saat perlu saja, admin bisa mematikan "Huruf 3D timbul" |
| CSP memblokir worker troika | `useWorker: false`; buka `blob:` hanya bila terukur perlu |
| Karakter di luar font | validasi karakter Latin di admin |
| dompdf lambat untuk tabel besar | batas baris PDF + catatan |
| TRUNCATE tidak transaksional / butuh hak DROP | idempoten, anjuran backup, catatan troubleshooting |
| Rekap pengunjung per browser, bukan per hari | dijelaskan di README; retensi 31 hari |
| Wall membuka nama & partisipasi ke publik | sakelar `electionWall`, tanpa data sensitif (NISN/NIP, waktu, paslon), moderasi foto, keterangan di kartu avatar |
| Pilihan kadang dapat ditebak dari wall + perolehan live saat pemilih sepi | diterima pemilik proyek (bagian 1 butir 1); dicatat di Known limitations; `publicLiveCount` / `electionWall` dapat dimatikan bila aturan sekolah berubah |
| Unggahan foto disalahgunakan | batas ukuran & frekuensi, encode ulang, hapus oleh admin |
| Lisensi CC BY 4.0 avatar | atribusi di kartu avatar & kaki halaman wall + file lisensi di folder vendor |

---

## 6. CHECKLIST VERIFIKASI PER TAHAP

- [ ] `composer test` lulus (sebutkan jumlah test & assertion di README dan STAGE-NOTES).
- [ ] `php spark osis:minify` lalu `git status` bersih dari build yang tertinggal;
      `php spark osis:check` OK (termasuk 8 trigger).
- [ ] Playwright 1440×900 & 390×844: 0 galat konsol, 0 pelanggaran CSP, 0 HTTP 4xx/5xx aset.
- [ ] Reduced-motion diuji (emulasi) untuk efek baru.
- [ ] Tanpa JavaScript: halaman tetap terbaca (beranda, admin tambah, wall).
- [ ] Screenshot `shots/stage2X-*.png` di-commit.
- [ ] `STAGE2X-NOTES.md`, `README.md`, `docs/README.md` diperbarui.

---

## 7. PROMPT SIAP TEMPEL

Dokumen ini dibuat di branch `claude/student-teacher-voting-features-gngbys`. Sebaiknya
branch itu di-merge ke `main` lebih dulu. Bila belum, chat baru dapat mengambil dokumennya
dengan:

```
git fetch origin claude/student-teacher-voting-features-gngbys
git checkout origin/claude/student-teacher-voting-features-gngbys -- docs/spesifikasi/11-ADMIN-HERO-TEXT-LIVING-ELECTION-WALL.md
```

### Chat baru — Tahap 1 (Stage 25)

```
Implementasikan TAHAP 1 (Stage 25) sesuai docs/spesifikasi/11-ADMIN-HERO-TEXT-LIVING-ELECTION-WALL.md
bagian 3, dengan memperhatikan bagian 1, 2, 5, dan 6. Baca dulu 00-MASTER-PROJECT.md,
STAGE24-NOTES.md, dan README.md. Jangan kerjakan Tahap 2.

Urutan kerja yang disarankan: 3.5 (perapian kecil) -> 3.1 (tambah data) -> 3.2 (rekap
pengunjung & PDF) -> 3.4 (reset total) -> 3.3 (teks halaman utama; uji kompatibilitas
troika-three-text dengan three r186 lebih dulu).

Selesaikan dengan: test PHPUnit lulus di MariaDB, php spark osis:minify, php spark osis:check,
uji browser Playwright desktop & HP tanpa galat konsol/CSP, screenshot shots/stage25-*.png,
docs/catatan-tahap/STAGE25-NOTES.md, README.md & docs/README.md diperbarui, lalu commit dan push.
```

### Chat baru — Tahap 2 (Stage 26)

```
Implementasikan TAHAP 2 (Stage 26) sesuai docs/spesifikasi/11-ADMIN-HERO-TEXT-LIVING-ELECTION-WALL.md
bagian 4, dengan memperhatikan bagian 1, 2, 5, dan 6. Tahap 1 (Stage 25) sudah selesai: baca
docs/catatan-tahap/STAGE25-NOTES.md, 00-MASTER-PROJECT.md, dan README.md lebih dulu.

Urutan kerja yang disarankan: 4.1 (migration avatar) -> 4.2 (unggah & acak avatar di dasbor)
-> 4.3 (Living Election Wall) -> 4.5 (tautan di scene perolehan suara) -> 4.6 (reset total
diperluas). Perolehan suara publik (PublicLiveCount) tidak diubah (bagian 4.4).

Selesaikan dengan: test PHPUnit lulus di MariaDB, php spark osis:minify, php spark osis:check,
uji browser Playwright desktop & HP tanpa galat konsol/CSP, screenshot shots/stage26-*.png,
docs/catatan-tahap/STAGE26-NOTES.md, README.md & docs/README.md diperbarui, lalu commit dan push.
```
