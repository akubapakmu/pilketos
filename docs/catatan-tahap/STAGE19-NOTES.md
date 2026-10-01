# Stage 19 — Banner Final Dua Baris, Panggung Pemenang, Logo Admin HP dari Konfigurasi, Pengunjung Online (hasil implementasi + handoff)

Dokumen ini adalah kontrak aktual Stage 19. Dibaca bersama
`00-MASTER-PROJECT.md`, `STAGE1-NOTES.md` s.d. `STAGE18-NOTES.md`, dan
`README.md`.

Ringkasan Stage 19 (permintaan revisi panel admin, hasil akhir, dan beranda):

- **banner final dasbor** (`.final-banner--winner`): judul dua baris berhuruf
  kapital, **PASANGAN 0X TERPILIH** lalu nama ketua & wakil di baris kedua;
  kalimat "Pencoblosan ditolak server sejak waktu selesai; angka di dasbor
  ini adalah hasil akhir." dihapus;
- **panggung pemenang** (`section.victor`, halaman hasil akhir): rata tengah;
  nama & peran mengikuti warna teks di atas aksen (putih/hitam) dan berhuruf
  kapital; tally dua baris; di desktop label di tengah, nomor & foto sebaris,
  nama di baris berikutnya, lalu tally; **catatan kaki** miring, rata kiri,
  diawali tanda bintang;
- **logo header admin HP** (`.admin-top__logo`) diatur di
  `Config\Homepage` seperti logo beranda;
- **pengunjung online**: pencatatan tamu & pengguna login (perangkat, OS,
  browser, User-Agent, Client Hints, IP, bahasa, layar, zona waktu, halaman,
  durasi) dengan pustaka Composer `matomo/device-detector`, halaman admin
  **Pengunjung Online** dengan persentase pengguna online terhadap seluruh
  pengguna sistem, angka ringkas di beranda (pojok kanan hero desktop & di
  atas teks footer scene perolehan suara).

Satu migration baru (`visitors`), satu konfigurasi baru
(`app/Config/Visitors.php`), tiga properti baru `Config\Homepage`. Tanpa
dependensi Composer baru: `matomo/device-detector` sudah terpasang sejak
Stage 11 dan dipakai ulang (pustaka paling lengkap untuk perangkat, OS,
browser, mesin, dan bot).

"HP" = lebar < 720 px untuk panel admin, < 768 px untuk beranda (sama
dengan Stage 8–18).

## 1. Banner final dasbor

```
h2.final-banner__title            grid, uppercase (CSS), jarak 8 px
  span.final-banner__lead         "Pasangan 02 terpilih" + ":" visually-hidden
  span.final-banner__names        "Bagas Prayoga & Citra Maheswari" (0.72em, 500)
p.final-banner__text              "2 suara (50,0% dari 4 suara sah)." (hanya bila ada pemenang)
```

- Kapital lewat `text-transform: uppercase` (HTML tetap huruf biasa: pembaca
  layar tidak mengeja per huruf, test lama "Pasangan 02 terpilih" tetap
  berlaku). Berlaku juga untuk judul seri/tanpa suara sah.
- Kalimat "Pencoblosan ditolak server ..." dihapus; keadaan seri & tanpa suara
  sah kini tanpa paragraf teks (judul + tombol saja).

## 2. Panggung pemenang & catatan kaki (`results.css`)

| Lebar | Susunan |
|---|---|
| < 900 px (HP & tablet) | satu kolom rata tengah: label, nomor, foto (di tengah), nama ketua lalu wakil, tally dua baris |
| ≥ 900 px | `grid-template-areas: "label label" "no people" "names names" "tally tally"`, kolom `auto auto` di tengah: label di tengah, nomor besar & foto sebaris, nama ketua & wakil berdampingan di baris berikutnya, tally di bawahnya |
| cetak | tetap ringkas satu baris (nomor, foto, nama) rata kiri |

- **Warna**: `h1, h2, h3 { color: var(--ink) }` di app.css membuat
  `h3.victor__names` tetap hitam di atas aksen gelap. Kini `.victor__names`,
  `.victor__person-name`, `.victor__role` memakai `color: inherit` dari
  `.victor` (`--accent-ink` = putih atau tinta, mana yang kontrasnya lebih
  tinggi; `CandidateTheme::inkOn()`). Diuji di browser: aksen #1B3A6B → putih,
  aksen #F2C94C → #15141A.
- **Kapital**: nama (`.victor__names`, Newsreader, `letter-spacing: 0.01em`)
  dan peran (sudah kapital).
- **Tally** dua baris: `span.victor__tally-line` (perolehan "5 suara · 62,5%
  dari suara sah"; selisih "unggul 3 suara dari peringkat 2" bila ada).
- **Catatan kaki** (`.final__foot`): `font-style: italic`, `text-align: left`,
  diawali `span.final__star[aria-hidden]` "*" (tegak). PDF (dompdf) dan
  catatan kaki cetak browser (`@page`) tidak berubah.

## 3. Logo header admin HP

`Config\Homepage` (bisa juga `.env`):

| Properti | Bawaan | Fungsi |
|---|---|---|
| `$adminLogoMobile` | `assets/img/brand/logo-admin-mobile.svg` | file logo (path relatif `public/`) |
| `$adminLogoMobileHeight` | `34` | tinggi tampil (px), dibatasi 24–48 |
| `$adminLogoMobileAlt` | `PILKETOS Panel Admin: Beranda` | teks alternatif (logo = tautan ke Beranda admin) |

`admin/partials/topbar.php` membaca ukuran asli lewat `asset_size()` (atribut
width/height), tinggi lewat `style="--admin-logo-h: 34px"`; CSS
`height: var(--admin-logo-h, 34px)`, `max-width: min(60vw, 260px)`,
`object-fit: contain`.

## 4. Pengunjung online

### 4.1 Data (`visitors`, migration `2026-10-01-000001_CreateVisitorsTable`)

Satu baris per browser. Kunci: `visitor_hash` = SHA-256 cookie
`osis_visitor` (32 heksadesimal acak, HttpOnly, SameSite=Lax, 30 hari).
Kolom: `user_type` (student/teacher/admin, NULL = tamu) + `user_id`, IP,
User-Agent (512), `ua_hash`, Client Hints (JSON), jenis/merek/model
perangkat, nama/versi/arsitektur OS, nama/versi/jenis browser, mesin + versi,
bahasa (3 teratas Accept-Language), layar, jendela, rasio piksel, zona waktu,
titik sentuh, jaringan (effectiveType), tema, situs asal (tanpa query),
halaman pertama, halaman sekarang, jumlah halaman, `first_seen_at`,
`last_seen_at` (indeks), indeks `(user_type, user_id)`. Tanpa foreign key
(akun boleh dihapus; nama digabung saat ditampilkan, tidak disalin).

### 4.2 Pencatatan (`App\Services\VisitorTracker`)

- **Halaman** (`VisitorFilter`, filter global `visitors` setelah
  `appheaders`): GET berstatus 200, isi `text/html`, bukan fetch/AJAX
  (`X-Requested-With`). JSON (live count, jam server), fragmen analitik, live
  search, PDF, dan templat Excel tidak dihitung.
- **Detak** (`POST kunjungan/detak`, `PresenceController`, `presence.js`
  di ketiga layout setelah app.js): 1,5 detik setelah halaman tampil, lalu
  tiap `heartbeatSeconds` (60) selama tab terlihat; tab tersembunyi berhenti,
  kembali terlihat = detak segera. CSRF lewat header `X-CSRF-TOKEN`
  (token sesi tidak diregenerasi, Stage 4). 401/403/404 menghentikan detak.
  Isi detak divalidasi ketat (`clientInfo()`: pola `WxH`, rasio 0,25–8, zona
  `Area/Kota`, sentuh 0–255, jaringan slow-2g/2g/3g/4g, tema dark/light);
  nilai lain dibuang. Detak tanpa cookie tidak membuat baris.
- **Identitas dari sesi**: `user_type` + `<role>_id`. Sesi siswa/guru yang
  sudah melewati batas idle 15 menit (AuthFilter) dihitung tamu. Masuk/keluar
  di browser yang sama memindahkan baris yang sama (komputer lab).
- **Urai User-Agent** (`DeviceInfo::profile()`, baru): hanya saat baris baru
  atau `ua_hash` berubah. Bot (`isBot`) dan pustaka HTTP (jenis klien
  `library`: curl, Python, Go) serta User-Agent kosong tidak dicatat.
- **Hemat tulis**: detak dalam 15 detik dari tulisan terakhir tanpa perubahan
  (akun, User-Agent, data layar) tidak menulis ulang; detak pertama setelah
  halaman dibuka tetap ditulis karena membawa data layar.
- **Kerahasiaan pilihan**: path pemilih (`siswa/*`, `guru/*`) dibuang segmen
  angkanya (`siswa/coblos/yakin/2` → `siswa/coblos/yakin`, `guru/paslon/03` →
  `guru/paslon`); query string tidak pernah disimpan.
- **Bersih otomatis**: peluang 1:50 setiap pengunjung baru, baris yang tidak
  aktif > `retentionDays` (7) dihapus.
- **Tidak pernah menggagalkan halaman**: semua galat (tabel belum dimigrasi,
  database sibuk) ditangkap dan dicatat di log; insert ganda (dua request
  pertama bersamaan) jatuh ke update.

### 4.3 Angka (`App\Services\OnlineVisitors`)

- online = `last_seen_at` dalam `onlineSeconds` (180) terakhir;
- tamu = baris online tanpa akun; pengguna = akun berbeda yang online (satu
  akun di dua perangkat = satu); total = tamu + pengguna;
- persentase = pengguna online / (siswa aktif + guru aktif + admin), juga per
  peran;
- cache `countCacheSeconds` (5) untuk angka publik & balasan detak; halaman
  admin selalu dihitung langsung.

### 4.4 Halaman admin (`/admin/pengunjung`, menu **Pengunjung Online**, ikon mata)

```
header: Pengunjung online (+ keterangan di balik ikon "i")
[data-live-region="ringkas"]  papan angka: Online sekarang | Tamu | Pengguna login (x%, meter, "dari N pengguna sistem") | Siswa | Guru | Admin
form.filters (live search)    Cari (nama, NISN/NIP, IP, browser, perangkat) | Waktu (online / hari ini / 7 hari) | Pengunjung (semua / tamu / login / siswa / guru / admin)
[data-live-region="pengunjung"]
  section.visitor-split       sebaran Perangkat | Browser | Sistem operasi (5 teratas + Lainnya, garis 3 px)
  p.result-count
  table.data-table--visitors  Pengunjung | Halaman | Perangkat | Sistem · browser | Jaringan · lokal | Aktivitas | User-Agent (details "Lihat")
  pager (50 per halaman)
```

- Status online: titik tinta penuh; tidak aktif (cakupan hari ini/7 hari):
  lingkaran kosong + teks abu-abu; status tertulis untuk pembaca layar.
- Baris browser admin ini bertanda **Anda** (garis kiri tinta). Kunjungan
  halaman ini dicatat sebelum daftar dibuat (VisitorFilter tidak mencatat
  ulang request yang sama), jadi baris itu sudah berisi akun & halaman ini.
- **Lihat**: User-Agent lengkap, Client Hints, halaman pertama, situs asal,
  tema perangkat, waktu terakhir aktif.
- `admin-visitors.js`: muat ulang kedua region tiap 20 detik lewat fetch
  (tidak dihitung kunjungan), rincian yang terbuka tetap terbuka; dilewati
  saat tab tersembunyi, live search berjalan, atau kursor di kolom saring.

### 4.5 Beranda

| Tempat | Tampilan |
|---|---|
| hero, pojok kanan bawah (≥ 768 px, bukan layar pendek miring) | `p.hero-online`: "**7** online │ **3** login" — JetBrains Mono 0.75rem, angka terang, label `--on-night-2`, pemisah garis 1 px; tinggi 52 px = sebaris tengah dengan tombol **Masuk untuk memilih**. `div.hero-bar` (flex) membungkus `.hero` (CTA kiri) + angka (kanan) |
| scene 03, di atas `.site-footer__text` | `p.site-footer__online`: "ONLINE **4** belum login **3** sudah login" (mono 0.6875rem) |

- Tanpa titik berdenyut, badge, glow, gradient, atau animasi khusus (hanya
  ikut reveal scene, jeda 320 ms setelah CTA).
- Angka dirender server (`Home::onlineCounts()`; pengunjung yang belum punya
  cookie ikut dihitung) dan diperbarui `presence.js` dari balasan detak
  (`[data-online-count="total|guests|users"]`, format id-ID).
- `visitors.publicCounter = false` menyembunyikan keduanya; balasan detak
  tanpa angka.

## 5. Konfigurasi (`app/Config/Visitors.php`, `.env` `visitors.*`)

| Properti | Bawaan | Fungsi |
|---|---|---|
| `$enabled` | `true` | `false` = tanpa pencatatan, detak (404), presence.js, dan angka online |
| `$onlineSeconds` | `180` | batas online (min 30) |
| `$heartbeatSeconds` | `60` | irama detak (min 30) |
| `$retentionDays` | `7` | umur baris tidak aktif |
| `$cookieName`, `$cookieDays` | `osis_visitor`, `30` | cookie penanda browser |
| `$publicCounter` | `true` | angka online di beranda |
| `$countCacheSeconds` | `5` | cache angka online |

## 6. Route

| Method | URL | Akses |
|---|---|---|
| POST | `kunjungan/detak` | publik (CSRF); balasan `{ok, tracked, interval, online:{total, guests, users}}` tanpa identitas |
| GET | `admin/pengunjung?waktu=&siapa=&q=&page=` | adminauth |

`RoleMatrixTest`: `kunjungan/detak` ditambahkan ke daftar route publik.

## 7. Test

Baru: `tests/feature/StageNineteenRefinementTest.php` (16 test): banner final
dua baris kapital tanpa kalimat server; tally dua baris + catatan kaki
berbintang miring rata kiri; aturan CSS victor (warna inherit, kapital, rata
tengah, susunan desktop); logo admin HP dari `Config\Homepage` (tinggi
dibatasi, ukuran asli); kunjungan membuat satu baris per browser dengan
perangkat lengkap + cookie HttpOnly, kunjungan kedua menambah jumlah halaman;
bot, curl, User-Agent kosong, AJAX, dan JSON tidak dihitung; identitas dari
sesi, keluar = tamu, path pemilih tanpa nomor, label halaman; sesi pemilih
idle = tamu; detak menyimpan data layar tervalidasi & hanya membalas angka;
nilai detak tak terduga dibuang; detak tanpa cookie tidak tercatat, fitur
mati = 404 & tanpa presence.js; angka online (akun berbeda, persentase
16 pengguna, saringan, sebaran); halaman admin hanya untuk admin; halaman
admin menampilkan pengunjung, tracker, Client Hints, dan baris "Anda";
angka beranda (hero & footer) + CSS minimal + `publicCounter = false`;
`DeviceInfo::profile()` (bot, library, Windows 10/11 x64) & `durasi()`.

Diperbarui: `StageThirteenRefinementTest` (catatan kaki rata kiri, diawali
bintang), `StageSeventeenRefinementTest` (markup logo HP dengan
`--admin-logo-h`), `StageEighteenRefinementTest` (teks hero terlihat = dua
tombol + angka online), `RoleMatrixTest` (route publik `kunjungan/detak`).

Hasil: **484 test, 4.591 assertion, lulus** (PHP 8.3.6, MariaDB 10.11.14).

Uji browser (Playwright/Chromium, `spark serve`, data seeder): enam konteks
bersamaan (tamu desktop, tamu iPhone 13, siswa login di Galaxy S9+, guru login
di iPad, admin desktop, admin Pixel 7):
- beranda 1440 × 900: "7 online │ 3 login" di pojok kanan bawah sebaris dengan
  tombol masuk; scene perolehan suara: "ONLINE 4 belum login 3 sudah login"
  di atas teks footer (desktop & HP); hero HP tanpa angka;
- `/admin/pengunjung` desktop & HP: papan angka (18,8% dari 16 pengguna),
  sebaran, tabel dengan perangkat/OS/browser/layar/zona waktu, rincian
  User-Agent & Client Hints, baris **Anda**;
- detak menyimpan layar, jendela, rasio piksel, zona waktu, jaringan, tema;
  tanpa token CSRF = 403;
- banner final & panggung pemenang (aksen gelap #1B3A6B: teks putih; aksen
  terang #F2C94C: teks #15141A), catatan kaki miring berbintang rata kiri;
- logo header admin HP; konsol tanpa error JavaScript.

Screenshot: `shots/beranda-online-desktop.png`,
`shots/beranda-perolehan-online-desktop.png`,
`shots/beranda-perolehan-online-hp.png`, `shots/beranda-hero-online-hp.png`,
`shots/admin-pengunjung-desktop.png`, `shots/admin-pengunjung-hp.png`,
`shots/admin-banner-final-desktop.png`, `shots/admin-banner-final-hp.png`,
`shots/hasil-pemenang-desktop.png`, `shots/hasil-pemenang-hp.png`. Semua
pengunjung pada screenshot adalah browser uji (Headless Chrome, IP 127.0.0.1).
