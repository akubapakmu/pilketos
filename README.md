# Pemilihan Ketua & Wakil Ketua OSIS SMP 1 DAWE 2026

Aplikasi e-voting sekolah berbasis **CodeIgniter 4.7 + MySQL/MariaDB** untuk
siswa dan guru. Pemilih login dengan identitasnya sendiri (NISN atau NIP + kode
unik), membaca visi-misi tiga pasangan calon, lalu mencoblos dengan paku 3D
(WebGL) atau paku 2D. Satu pemilih hanya punya satu suara aktif; suara yang
sudah dikunci hanya dapat dibuka admin dengan alasan tercatat. Admin memantau
live count, analitik, dan hasil akhir yang aktif otomatis saat waktu pemilihan
habis menurut jam server.

Beranda publik tampil seperti aplikasi dengan identitas visual "Surat Suara
dari Lereng Muria": layar pembuka berkabut yang tersingkap menjadi pagi di
lereng Muria, tiga scene layar penuh yang berpindah satu per satu (lereng,
pintu masuk **Siswa/Guru**, perolehan suara langsung), dan panel status
bergaya terminal dengan countdown jam server (bagian 21).

Dokumen ini adalah panduan pemakaian dan deployment. Spesifikasi, identitas
visual, dan riwayat implementasi per tahap ada di folder [`docs/`](docs/README.md)
(bagian 20).

Daftar isi:
[1 Ringkasan](#1-ringkasan) ·
[2 Arsitektur](#2-arsitektur) ·
[3 Kebutuhan](#3-kebutuhan) ·
[4 Instalasi](#4-instalasi) ·
[5 Konfigurasi .env](#5-konfigurasi-env) ·
[6 Database](#6-database) ·
[7 Deployment Laragon](#7-deployment-laragon) ·
[8 Admin](#8-admin) ·
[9 Siswa](#9-siswa) ·
[10 Guru](#10-guru) ·
[11 Pasangan calon](#11-kelola-pasangan-calon) ·
[12 Impor Excel](#12-impor-excel) ·
[13 Voting](#13-voting) ·
[14 Unlock](#14-unlock-hak-suara) ·
[15 Analitik](#15-analitik--live-count) ·
[16 Hasil akhir](#16-hasil-akhir) ·
[17 Backup](#17-backup--pemulihan) ·
[18 Troubleshooting](#18-troubleshooting) ·
[19 Test](#19-test-otomatis) ·
[20 Dokumen](#20-dokumen-proyek) ·
[21 Beranda](#21-beranda-imersif--aset-visual)

## 1. Ringkasan

| Bagian | Isi |
|---|---|
| Pemilih | Siswa (NISN + kode unik tanggal lahir DDMMYYYY) dan guru (NIP + kode unik), tabel & login terpisah, satu pemilihan yang sama |
| Beranda publik | lockup PILKETOS 2026 di tengah tanpa tombol masuk; layar pembuka "kabut tersingkap"; hero lereng Muria berlapis; scene layar penuh (roda mouse, trackpad, geser sentuh, keyboard); pintu masuk Siswa/Guru bergambar; perolehan suara (persentase per pasangan + partisipasi) diperbarui tiap 30 detik; panel status terminal dengan countdown jam server; angka pengunjung online (Stage 19) |
| Pengalaman memilih | halaman kandidat bertema per pasangan, visi-misi interaktif, surat suara dengan paku coblos 3D/2D, konfirmasi, suara terkunci, halaman "pilihan saya" |
| Admin | dasbor & live count, analitik (jenis pemilih, jenis kelamin, kelas, rombel, detail suara), pasangan calon + unggah tema, data siswa/guru (tambah, ubah, impor Excel), jadwal, unlock, audit log, pengunjung online (perangkat, browser, User-Agent), hasil akhir + confetti |
| Integritas | transaction + row lock, unique key satu suara aktif, CHECK & trigger database (suara/audit tidak dapat dihapus atau diubah), hasil dikunci setelah selesai |
| Jadwal | status UPCOMING / ONGOING / FINISHED dihitung dari `start_at`/`end_at` terhadap jam server (WIB); sejak `end_at` pencoblosan ditolak |

Tanpa emoji, tanpa gradient; warna hanya dari aksen solid tiap pasangan.
Semua animasi menghormati `prefers-reduced-motion`, dan setiap alur tetap
berjalan tanpa WebGL (paku 2D) maupun tanpa JavaScript (halaman konfirmasi
server; beranda menjadi halaman bergulir biasa).

## 2. Arsitektur

```
Browser (HP siswa / komputer lab / laptop admin)
  |  HTML + CSS + JS lokal (tanpa CDN), fetch JSON untuk voting & live count
  v
Apache 2.4 (DocumentRoot = public/, mod_rewrite)  ->  public/index.php
  v
CodeIgniter 4.7
  Filter global : postsize -> csrf -> invalidchars | secureheaders, appheaders, visitors, CSP
  Filter role   : studentauth / teacherauth / adminauth (401 JSON untuk AJAX)
  Controller    : Home (beranda + live count publik), Student\*, Teacher\*, VotingController,
                  ElectionController (jam), PresenceController (detak pengunjung),
                  Admin\* (Dashboard, LiveCount, Analytics, Result, Candidate, Student,
                  Teacher, Import, Election, Unlock, Audit, Account, Visitor)
  Service       : VoteService (castVote), UnlockService, AnalyticsService (satu definisi
                  angka), PublicLiveCount (proyeksi publik), FinalResult, VoterDirectory,
                  VisitorTracker & OnlineVisitors (pengunjung online), Import\* (PhpSpreadsheet)
  Library       : CandidateTheme, CandidateAssets (encode ulang gambar), DeviceInfo,
                  Grade, AdminAccount, SystemCheck, ResultPdf (PDF hasil akhir, dompdf)
  Model         : Admin, Student, Teacher, Candidate, Election, StudentVote, TeacherVote,
                  VoteUnlockLog, AuditLog (+ tabel visitors lewat query builder)
  v
MySQL 8.0.16+ / MariaDB 10.4+ (InnoDB, utf8mb4, strict mode, FK RESTRICT,
unique key suara aktif, CHECK + 8 trigger penjaga integritas)
```

Prinsip utama:

- **Server adalah sumber kebenaran.** Status pemilihan, waktu memilih,
  perangkat/browser, dan hasil dihitung di server. Countdown hanya visual.
- **Identitas dari sesi, bukan dari input.** Pemilih tidak pernah mengirim id
  dirinya; jenis pemilih ditentukan route/controller. Tidak ada id pemilih atau
  suara di URL pemilih.
- **Satu definisi angka.** Dasbor, live count, analitik, daftar pemilih,
  hasil akhir, dan perolehan suara di beranda memakai `AnalyticsService`:
  pemilih aktif = `status_aktif = 1`, suara sah = baris `LOCKED` milik
  pemilih aktif, dihitung dengan agregasi MySQL (`GROUP BY`), bukan di
  browser. Beranda hanya menerima persentase per pasangan dan partisipasi.
- **Riwayat tidak pernah dihapus.** Unlock mengubah `LOCKED` menjadi
  `UNLOCKED`; pilih ulang = baris baru. Database menolak DELETE suara/log.

State hak suara per pemilih per pemilihan:

```
NO_ACTIVE_VOTE --coblos--> LOCKED --unlock admin (alasan)--> NO_ACTIVE_VOTE (baris lama UNLOCKED)
                                                              --coblos lagi--> LOCKED (baris baru)
```

Struktur folder utama:

```
docs/                spesifikasi (00–05), desain (06–10), catatan-tahap (STAGE1–19)
app/Commands/        admin:create, admin:password, osis:check
app/Config/          Routes.php, Filters.php, ContentSecurityPolicy.php, App.php, Homepage.php, Visitors.php, ...
app/Controllers/     Home, VotingController, Student/*, Teacher/*, Admin/*
app/Database/        Migrations (16 file), Seeds (data contoh development)
app/Filters/         AuthFilter + per role, PostSizeFilter, SecurityHeadersFilter, VisitorFilter
app/Fonts/           Newsreader & Plus Jakarta Sans TTF statis (OFL) khusus PDF hasil akhir
app/Libraries/       CandidateTheme, CandidateAssets, DeviceInfo, Grade, AdminAccount, SystemCheck, ResultPdf
app/Models/          9 model tabel
app/Services/        VoteService, UnlockService, AnalyticsService, PublicLiveCount, FinalResult,
                     VisitorTracker, OnlineVisitors, Import/*
app/Views/           layouts, home (+ partials: splash, dock, pair_photo), student, teacher,
                     voting, admin/**, errors
public/              index.php, .htaccess, assets/{css,js,fonts,img}, uploads/candidates/
                     (assets/css & assets/js: hasil build ter-minify + watermark, Stage 22)
                     (img/brand: lockup acara + logo SMP 1 DAWE, img/home: latar hero & layar
                     pembuka, kontur, perforasi, ilustrasi pintu masuk; img/auth: foto login
                     admin; img/results: stempel hasil akhir)
resources/           assets/css, assets/js: kode sumber CSS/JS rapi & berkomentar (Stage 22),
                     tidak dapat dibuka browser; edit di sini lalu php spark osis:minify
tests/               unit, database, feature (PHPUnit)
writable/            cache (+ cache/dompdf: metrik font PDF), logs, session, uploads/imports (pratinjau impor)
```

Daftar file lengkap ada di [`STAGE4-NOTES.md`](docs/catatan-tahap/STAGE4-NOTES.md) bagian 12.

## 3. Kebutuhan

| Komponen | Versi / catatan |
|---|---|
| PHP | **8.2 atau lebih baru** (diuji 8.2.33 dan 8.4.19) |
| Ekstensi PHP wajib | intl, mbstring, mysqli, gd, zip, fileinfo (PhpSpreadsheet juga memakai xml, dom, xmlreader, xmlwriter, simplexml yang aktif bawaan) |
| Ekstensi disarankan | exif (foto HP yang miring diluruskan), GD dengan WebP |
| Database | **MySQL 8.0.16+** atau **MariaDB 10.4+** (diuji MySQL 8.0.46 dan MariaDB 10.11.14) |
| Web server | Apache 2.4 + mod_rewrite (mod_headers, mod_expires, mod_deflate disarankan); diuji Apache 2.4.58 + mod_php 8.2. nginx dapat dipakai dengan konfigurasi di bagian 7.4 |
| Composer | 2.x |
| Browser pemilih | Chrome/Edge/Firefox/Samsung Internet modern (diuji dengan Chromium; Safari iOS belum diuji); WebGL opsional |

`php.ini` yang disarankan (Laragon: Menu > PHP > php.ini):

```
upload_max_filesize = 5M     ; foto kandidat per file
post_max_size       = 40M    ; form kandidat membawa hingga 7 gambar
memory_limit        = 256M   ; decode foto HP beresolusi besar
expose_php          = Off    ; versi PHP tidak diumumkan
```

## 4. Instalasi

Ringkas (development di komputer sendiri):

```
composer install
copy .env.example .env            (Linux/macOS: cp .env.example .env)
```

Buat database kosong `smp1dawe_osis_2026` (collation `utf8mb4_unicode_ci`),
sesuaikan `.env`, lalu:

```
php spark migrate
php spark db:seed DatabaseSeeder  (HANYA development: data & akun contoh)
php spark serve                   (http://localhost:8080/)
```

Akun contoh dari `DatabaseSeeder` (tidak untuk hari pemilihan; seeder menolak
berjalan bila `CI_ENVIRONMENT = production`):

| Peran | Login | Kode unik / sandi |
|---|---|---|
| Siswa | NISN `0000000001` di `/siswa/masuk` | `05062013` |
| Guru | NIP `000000000000000004` di `/guru/masuk` | `01061992` |
| Admin | username `admin` di `/admin/masuk` | `admin123` |

Alamat halaman (Stage 7: bahasa Indonesia santai; alamat bahasa Inggris lama
seperti `/student/login` sudah tidak ada dan menjawab 404):

| Untuk | Alamat |
|---|---|
| Siswa | `/siswa/masuk`, `/siswa` (dasbor), `/siswa/coblos` (bilik suara), `/siswa/paslon/01` (profil paslon), `/siswa/pilihanku` |
| Guru | `/guru/masuk`, `/guru`, `/guru/coblos`, `/guru/paslon/01`, `/guru/pilihanku` |
| Admin | `/admin/masuk`, `/admin` (dasbor), `/admin/analitik` (+ `/jenis-pemilih`, `/jenis-kelamin`, `/kelas`, `/rombel`), `/admin/analitik/suara`, `/admin/hasil`, `/admin/paslon`, `/admin/siswa`, `/admin/siswa/tambah`, `/admin/guru`, `/admin/guru/tambah`, `/admin/siswa/impor`, `/admin/guru/impor`, `/admin/jadwal`, `/admin/buka-kunci`, `/admin/riwayat` |
| JSON (dipakai halaman) | `/jam-server` (countdown), `/hitung-suara` (beranda), `/admin/hitung-suara` (dasbor admin) |

Peta lengkap lama -> baru: [`STAGE7-NOTES.md`](docs/catatan-tahap/STAGE7-NOTES.md).

Instalasi server hari pemilihan: bagian 7.

## 5. Konfigurasi .env

`.env` berisi kredensial dan tidak pernah di-commit (ada di `.gitignore`).
Salin dari `.env.example`:

| Kunci | Nilai | Catatan |
|---|---|---|
| `CI_ENVIRONMENT` | `development` / `production` | production = Debug Toolbar mati, pesan error tanpa detail teknis, gagal CSRF diarahkan kembali |
| `app.baseURL` | mis. `'http://192.168.1.10/'` | **harus sama persis** dengan alamat yang dibuka semua perangkat (termasuk port/subfolder), diakhiri `/` |
| `database.default.*` | host, nama, user, sandi, port | database utama |
| `database.tests.*` | database terpisah | hanya untuk test otomatis, isinya dihapus setiap test |
| `session.cookieName`, `session.expiration` | `osis_session`, `7200` | sesi 2 jam; pemilih keluar otomatis setelah 15 menit tanpa aktivitas |
| `cookie.secure` | `false` / `true` | set `true` bila situs dibuka lewat HTTPS |
| `app.CSPEnabled` | (bawaan `true`) | Content-Security-Policy; boleh `false` sementara hanya untuk diagnosis |
| `homepage.publicLiveCount` | (bawaan `true`) | `false` = beranda tanpa angka perolehan suara, `GET hitung-suara` 404 (bagian 21) |
| `homepage.livePollSeconds`, `homepage.liveCacheSeconds` | `30`, `5` | irama pembaruan perolehan suara di beranda (min 10; 0 = mati) dan cache angka publik |
| `homepage.logoOnDark`, `logoOnLight`, `schoolEmblem`, `heroDesktop`, `heroMobile`, `heroForeground`, `introDesktop`, `introMobile`, `entryStudent`, `entryTeacher` | path di `public/` | mengganti lockup, memasang lambang resmi sekolah, latar hero & layar pembuka, lapisan depan, ilustrasi pintu masuk (bagian 21) |
| `homepage.identityAccent` | (bawaan `'#A8628F'`, parijoto) | warna bilah muat layar pembuka; otomatis netral bila mirip warna pasangan mana pun; `null` = selalu netral |
| `homepage.adminLogoMobile`, `homepage.adminLogoMobileHeight`, `homepage.adminLogoMobileAlt` | (bawaan `logo-admin-mobile.svg`, `34`, `'PILKETOS Panel Admin: Beranda'`) | logo header panel admin di HP (Stage 19, bagian 8); tinggi tampil 24–48 px |
| `visitors.enabled` | (bawaan `true`) | `false` = tanpa pencatatan pengunjung, detak, maupun angka online (bagian 8.2) |
| `visitors.onlineSeconds`, `visitors.heartbeatSeconds` | `180`, `60` | batas "online" (aktif dalam N detik terakhir) dan irama detak halaman yang terbuka |
| `visitors.publicCounter` | (bawaan `true`) | `false` = angka online tidak tampil di beranda (tetap ada di panel admin) |
| `visitors.retentionDays`, `visitors.countCacheSeconds` | `7`, `1` | baris pengunjung yang tidak aktif lebih lama dihapus otomatis; cache singkat angka online (dibuang otomatis saat ada pengunjung baru/berganti akun) |
| `visitors.counterRefreshSeconds` | `1` | Stage 21: angka online beranda & papan angka admin diperbarui tiap N detik tanpa muat ulang halaman (hanya selama tab terlihat) |

Zona waktu aplikasi tetap `Asia/Jakarta` (`app/Config/App.php`), jadi jam di
komputer server harus benar.

## 6. Database

`php spark migrate` membuat 10 tabel dan menjalankan 16 migration:

| Tabel | Isi |
|---|---|
| `admins` | admin panel (sandi `password_hash`) |
| `students` | NISN (unik, teks), nama, jenis kelamin L/P, kelas, nomor absen, kode unik, `status_aktif` |
| `teachers` | NIP (unik, teks), nama, kode unik, `status_aktif` |
| `candidates` | nomor urut (unik), ketua, wakil, visi, misi, foto, tema (nama, aksen, asset JSON: foto berdua, gambar pembuka, poster, latar penutup; Stage 16 tanpa layout & latar panggung), profil & kampanye (slogan, bio, karakter, program, pesan, ID video YouTube; Stage 15), `status_aktif` |
| `elections` | nama, tahun, `start_at`, `end_at`, status, `batas_pasangan` (2–10, bawaan 3; Stage 15) |
| `student_votes`, `teacher_votes` | election, pemilih, pasangan, `status` LOCKED/UNLOCKED, waktu, perangkat, browser |
| `vote_unlock_logs` | unlock: suara yang dibuka, admin, alasan, waktu |
| `audit_logs` | tindakan admin (unlock, jadwal, pasangan, impor, tambah/ubah/status/hapus pemilih) |
| `visitors` | Stage 19: satu baris per browser (hash cookie penanda): tamu atau akun yang login, perangkat, OS, browser, User-Agent, Client Hints, IP, bahasa, layar, zona waktu, halaman, waktu aktif. Tanpa foreign key; dihapus otomatis setelah 7 hari tidak aktif |

Penjaga integritas (migration `2026-04-01-000001_AddVoteIntegrityGuards`):

- unique key `uq_*_votes_active`: maksimal satu suara `LOCKED` per pemilih per
  pemilihan (Stage 1);
- CHECK `chk_*_votes_state`: `LOCKED` tanpa `unlocked_at`, `UNLOCKED` wajib
  `unlocked_at`;
- trigger: baris suara tidak dapat dihapus; satu-satunya perubahan sah
  `LOCKED -> UNLOCKED`; pilihan, pemilih, pemilihan, waktu, perangkat tidak
  dapat diubah; `UNLOCKED` final; `vote_unlock_logs` dan `audit_logs`
  append-only (tidak dapat diubah/dihapus), juga dari HeidiSQL/phpMyAdmin;
- semua foreign key `RESTRICT`: pemilih/pasangan/admin yang punya suara atau
  log tidak dapat dihapus.

Membuat trigger butuh hak `TRIGGER`. Di MySQL 8 dengan binary log aktif
(bawaan), user database selain root/SUPER juga butuh
`log_bin_trust_function_creators = 1`; tanpa itu migration berhenti dengan
pesan error 1419 yang menyebut perbaikannya, dan dapat diulang setelah
diperbaiki. User `root` Laragon tidak terkena masalah ini.

## 7. Deployment Laragon

Folder target: `C:\laragon\www\smp1dawe-osis-2026`.

### 7.1 Server (laptop/PC panitia)

1. Pasang **Laragon** (paket berisi Apache, MySQL, PHP). Pastikan PHP 8.2+:
   Menu > PHP > Version.
2. Aktifkan ekstensi: Menu > PHP > Extensions: `intl`, `mbstring`, `mysqli`,
   `gd`, `zip`, `fileinfo`, `exif`.
3. Ubah `php.ini` (bagian 3), lalu **Stop** dan **Start All**.
4. Pastikan jam Windows benar (Settings > Time & language > Sync now):
   pemilihan dibuka dan ditutup menurut jam komputer server.
5. Matikan sleep/hibernate selama pemilihan dan pakai daya listrik.

### 7.2 Aplikasi

Jalankan di **Laragon Terminal** (Menu > Terminal):

```
cd C:\laragon\www
git clone <url-repository> smp1dawe-osis-2026      (atau ekstrak zip ke folder ini)
cd smp1dawe-osis-2026
composer install --no-dev --optimize-autoloader    (untuk menjalankan test: composer install)
copy .env.example .env
```

Buat database (HeidiSQL dari tombol Database Laragon, atau):

```
mysql -u root -e "CREATE DATABASE smp1dawe_osis_2026 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Isi `.env` (Notepad): `CI_ENVIRONMENT = production`, `app.baseURL`, dan
`database.default.*` (Laragon bawaan: user `root`, sandi kosong). Lalu:

```
php spark migrate
php spark admin:create --username panitia --name "Panitia Pemilihan OSIS"
php spark osis:check
```

- `migrate` membuat tabel + trigger (14 migration).
- `admin:create` membuat admin production dan **menampilkan kata sandi acak
  satu kali**; simpan di tempat aman. Ganti/lupa: `php spark admin:password panitia`.
- **Jangan** menjalankan `php spark db:seed DatabaseSeeder` di database hari
  pemilihan (data & akun contoh; di production seeder menolak berjalan).
- `osis:check` memeriksa PHP & ekstensi, `.env`, `php.ini`, folder yang harus
  dapat ditulis, versi database, migration, 8 trigger, admin (GAGAL bila
  `admin/admin123` masih ada di production), jadwal, pasangan, dan data
  pemilih (GAGAL bila data contoh NISN `00000000xx` ada di production).
  Exit code 1 bila ada GAGAL. Di Laragon CLI dan Apache memakai `php.ini` yang
  sama; di Linux, `php.ini` CLI dan Apache terpisah.

Folder yang harus dapat ditulis PHP: `writable/` (cache, logs, session,
uploads) dan `public/uploads/candidates/` (di Windows biasanya sudah).
CSS/JS di `public/assets/` sudah ter-minify dan ikut di-commit (bagian 7.6),
jadi folder itu tidak perlu dapat ditulis dan server tidak butuh library minify.

### 7.3 Virtual host & akses dari HP

Web server harus menyajikan folder **`public/`**, bukan folder proyek.

- Laragon membuat virtual host otomatis `http://smp1dawe-osis-2026.test/`.
  Periksa Menu > Apache > sites-enabled > `auto.smp1dawe-osis-2026.test.conf`:
  `DocumentRoot` harus berakhir `.../smp1dawe-osis-2026/public/`. Alamat
  `.test` hanya dikenal komputer server itu sendiri.
- **HP/komputer lain di Wi-Fi sekolah** membuka alamat IP server. Pilihan
  paling sederhana untuk laptop khusus pemilihan: Menu > Preferences >
  General > Document Root = `C:\laragon\www\smp1dawe-osis-2026\public`,
  lalu `app.baseURL = 'http://<IP-server>/'` (lihat IP dengan `ipconfig`).
  Tanpa mengubah Document Root, aplikasi juga dapat dibuka di
  `http://<IP-server>/smp1dawe-osis-2026/` (file `.htaccess` di root proyek
  meneruskan ke `public/`); `app.baseURL` harus memakai alamat itu. Cara
  subfolder ini khusus Apache; di nginx dilarang (bagian 7.4).
- Izinkan Apache (atau nginx) di Windows Firewall untuk jaringan Private saat
  diminta, dan minta IP tetap (DHCP reservation) untuk laptop server.
- Semua perangkat harus membuka alamat yang **sama dengan `app.baseURL`**;
  alamat lain membuat CSS/JS ditolak Content-Security-Policy.

Lapisan pengaman web server (diuji pada Apache 2.4; nginx tidak membaca
`.htaccess`, padanannya ada di bagian 7.4):

- `.htaccess` di root proyek: bila DocumentRoot terlanjur menunjuk folder
  proyek/induknya, `.env`, `.git`, `app/`, `resources/`, `writable/`, `vendor/`,
  `composer.*` tidak dapat diunduh (403/404) dan request diteruskan ke
  `public/`; tanpa mod_rewrite semuanya ditolak.
- `public/uploads/.htaccess`: hanya gambar `jpg/jpeg/png/webp` bernama aman
  yang disajikan; PHP tidak pernah dieksekusi di folder ini (termasuk nama
  ganda seperti `x.php.webp`), dengan header `nosniff` dan CSP `sandbox`.
- `public/.htaccess`: URL bersih, kompresi, cache asset 30 hari (CSS/JS memakai
  `?v=<waktu ubah file>` sehingga update langsung terbaca), `X-Powered-By`
  dihapus. Opsional di `httpd.conf`: `ServerTokens Prod`.

### 7.4 Alternatif: nginx

Apache bawaan Laragon tidak butuh setting tambahan dan merupakan konfigurasi
yang diuji di Stage 4; pakai nginx hanya bila ada alasan khusus. nginx **tidak
membaca `.htaccess`**, sehingga semua lapisan pengaman di 7.3 tidak berlaku.
Dengan template vhost nginx bawaan Laragon (tanpa langkah di bawah):
`http://<IP-server>/smp1dawe-osis-2026/.env` dapat diunduh; file pratinjau
impor (NISN + kode unik siswa) dapat diunduh selama admin berada di halaman
pratinjau; isi folder ditampilkan (`autoindex on`); file `.php` yang terselip
di `uploads/` dijalankan; unggahan di atas 1 MB ditolak (413) bila
`client_max_body_size` masih bawaan nginx.

1. Menu > Preferences > Services & Ports: aktifkan Nginx, matikan Apache.
2. Menu > Preferences > General > Document Root =
   `C:\laragon\www\smp1dawe-osis-2026\public`. **Wajib** di nginx karena akses
   lewat IP dilayani dari Document Root. Cara subfolder
   `http://<IP-server>/smp1dawe-osis-2026/` (7.3) **tidak boleh** dipakai.
3. Di `C:\laragon\etc\nginx\sites-enabled\`, ubah nama
   `auto.smp1dawe-osis-2026.test.conf` menjadi `smp1dawe-osis-2026.test.conf`
   (tanpa awalan `auto.` agar tidak ditimpa Laragon; jangan ada dua file dengan
   `server_name` yang sama karena nginx hanya memakai yang pertama), lalu ganti
   isinya dengan konfigurasi di bawah; buat baru bila file auto tidak ada.
   Ganti `192.168.1.10` dengan IP server (sama dengan `app.baseURL`) dan
   samakan baris `fastcgi_pass` dengan file auto bawaan versi Laragon yang
   dipakai.
4. Stop lalu Start All. Dari HP, alamat `http://<IP-server>/.env`,
   `http://<IP-server>/smp1dawe-osis-2026/.env`, dan
   `http://<IP-server>/uploads/candidates/` harus ditolak (403/404).
   `osis:check` tidak memeriksa web server.

```
server {
    listen 80;
    # IP laptop server (cek dengan ipconfig), sama dengan app.baseURL di .env.
    # Nama .test hanya dikenal laptop server itu sendiri.
    server_name 192.168.1.10 smp1dawe-osis-2026.test;

    root "C:/laragon/www/smp1dawe-osis-2026/public";   # WAJIB folder public/
    charset utf-8;
    autoindex off;              # pengganti Options -Indexes
    server_tokens off;
    client_max_body_size 50m;   # bawaan nginx 1 MB; form kandidat s.d. 40 MB (post_max_size)

    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css text/javascript application/javascript application/json image/svg+xml;

    # URL bersih -> front controller CodeIgniter
    location / {
        try_files $uri /index.php$is_args$args;
    }

    # Hanya public/index.php yang dijalankan sebagai PHP
    location = /index.php {
        include snippets/fastcgi-php.conf;
        fastcgi_pass php_upstream;   # samakan dengan baris fastcgi_pass di file auto. bawaan
        fastcgi_hide_header X-Powered-By;
    }
    location ~* \.php$ { return 404; }

    # File tersembunyi (.htaccess, .gitkeep, file sementara unggahan)
    location ~ /\. { deny all; }

    # CSS/JS/gambar beranda (termasuk WebP/AVIF, Stage 6) dipanggil dengan
    # ?v=<waktu ubah file>, aman di-cache lama. AVIF: pastikan mime.types
    # nginx memuat "image/avif avif;" (nginx >= 1.21 sudah).
    location /assets/ {
        expires 30d;
        try_files $uri =404;
    }

    # Pengganti public/uploads/.htaccess: hanya gambar bernama aman, PHP tidak pernah jalan
    location ^~ /uploads/ {
        location ~* "^/uploads/candidates/[a-z0-9][a-z0-9._-]*\.(jpe?g|png|webp)$" {
            expires 7d;
            add_header X-Content-Type-Options "nosniff" always;
            add_header Content-Security-Policy "default-src 'none'; img-src 'self'; style-src 'none'; sandbox" always;
            add_header Cross-Origin-Resource-Policy "same-origin" always;
        }
        return 403;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
}
```

Proses PHP: nginx menjalankan PHP lewat beberapa proses `php-cgi`, dan satu
proses hanya melayani satu request dalam satu waktu. Atur di
`C:\laragon\usr\laragon.ini` bagian `[nginx]`:

- Laragon 8.6+: bawaan `PHP_FCGI_CHILDREN` (10) dan `PHP_FCGI_MAX_REQUESTS=0`
  sudah cukup.
- Laragon 6.x: naikkan `Upstream=` (mis. dari 2) menjadi sekitar 8. Di versi
  ini setiap proses php-cgi berhenti sendiri setelah 500 request (bawaan PHP)
  lalu dinyalakan ulang Laragon, sehingga dapat muncul `502 Bad Gateway` sesaat.

Uji beban sebelum hari H (`ab.exe` ikut terpasang bersama Apache Laragon);
hasilnya harus `Failed requests: 0`:

```
C:\laragon\bin\apache\<versi>\bin\ab.exe -n 2000 -c 20 http://<IP-server>/
```

Diuji pada nginx 1.24 + php-cgi 8.4 + MariaDB 10.11 (Linux, template vhost
meniru Laragon): alur siswa sampai suara terkunci, dasbor & live count, unggah
foto kandidat, impor Excel, batas ukuran (40-50 MB pesan aplikasi, di atasnya
413), dan semua penolakan di atas. Belum diuji di Windows/Laragon langsung.

### 7.5 Persiapan sebelum hari H

1. Admin login > **Siswa** dan **Guru** > Impor (bagian 12).
2. **Pasangan calon**: nama, visi, misi, foto, tema; periksa **Pratinjau**.
3. **Jadwal pemilihan**: nama, tahun, mulai, selesai (WIB).
4. `php spark osis:check` sampai tidak ada GAGAL (nginx: juga pemeriksaan
   alamat dan uji beban di bagian 7.4).
5. Uji coba alur memilih dari HP di **database terpisah**, karena suara uji
   coba di database hari H tidak dapat dihapus (trigger): buat database
   `smp1dawe_osis_2026_uji`, arahkan `database.default.database` ke sana
   sementara, `php spark migrate`, buat admin & impor data uji, coba memilih,
   lalu kembalikan `.env` ke database hari H.
6. Backup (bagian 17) sebelum pemilihan dimulai.
7. Beranda (bagian 21): pasang lambang resmi sekolah (`homepage.schoolEmblem`),
   ganti latar hero/layar pembuka dan ilustrasi Siswa/Guru bila aset final
   sudah ada; putuskan apakah perolehan suara boleh tampil publik selama
   pencoblosan (`homepage.publicLiveCount`).

Hari H: pantau **Beranda** panel admin (live count); bila perlu unlock, lihat bagian 14.
Setelah waktu selesai: buka **Hasil akhir**, lalu backup lagi.

### 7.6 Minify HTML/CSS/JS & watermark (Stage 20, Stage 22)

Browser hanya menerima CSS dan JS yang **sudah diperkecil, tanpa komentar**,
di semua environment (development maupun production). Baris pertama setiap
file CSS/JS berisi banner **FAJAR / © SMP 1 DAWE 2026**. HTML juga diperkecil
dengan banner yang sama tepat setelah `<!DOCTYPE html>`, tetapi khusus saat
`CI_ENVIRONMENT = production`.

- **Kode sumber rapi** (berkomentar) ada di `resources/assets/css/` dan
  `resources/assets/js/`, di luar `public/`, jadi tidak dapat dibuka browser.
  File bernama sama di `public/assets/css/` dan `public/assets/js/` adalah
  **hasil build** dan ikut di-commit. Server tidak butuh library minify
  maupun folder yang dapat ditulis.
- **Mengubah CSS/JS**: edit file di `resources/assets/`, jalankan
  `php spark osis:minify` (butuh `composer install`), lalu commit sumber dan
  hasilnya bersama. Saat halaman dibuka, file yang sumbernya lebih baru juga
  dibangun ulang otomatis bila library terpasang dan folder dapat ditulis.
  Jangan mengedit `public/assets/css|js` langsung: hasilnya tertimpa build
  berikutnya.
- **Saklar ON/OFF**: `app/Config/Minify.php`. `$enabled` adalah saklar utama;
  `$html`, `$css`, `$js` untuk mematikan satu jenis bila bermasalah;
  `$watermark` berisi teks banner (`''` = tanpa watermark). CSS/JS yang
  dimatikan disalin apa adanya dari `resources/` (beserta komentarnya) pada
  build berikutnya. Mematikan lewat `.env` (`minify.enabled = false`) langsung
  berlaku untuk HTML; untuk CSS/JS jalankan `php spark osis:minify` sesudahnya.
- `php spark osis:check` memberi PERINGATAN bila isi file di `public/assets`
  bukan hasil build terbaru dari `resources/assets`. Sisa file `*.min.css` /
  `*.min.js` dari Stage 20 dihapus oleh `php spark osis:minify` (atau
  `php spark osis:minify --clean`).

Rincian: [`STAGE22-NOTES.md`](docs/catatan-tahap/STAGE22-NOTES.md) (sumber di
`resources/`, build ter-commit) dan
[`STAGE20-NOTES.md`](docs/catatan-tahap/STAGE20-NOTES.md) (aturan minify).

## 8. Admin

Login: `/admin/masuk` (username + kata sandi, dibatasi 5 percobaan gagal per
akun lalu 1 per menit). Stage 13: halaman login layar terbelah (foto surat
suara di kiri / atas di HP, formulir "Panel Admin" di kanan) dengan tombol
mata untuk melihat kata sandi. Menu panel (Stage 9: tanpa nomor & judul
kelompok; brand "SMP 1 DAWE / Panel Admin"; Stage 13: label Title Case,
kelompok terakhir di bagian bawah sidebar berisi **Akun Admin**,
**Halaman Utama** (situs pemilih, tab baru), dan **Keluar**):

| Menu | Fungsi |
|---|---|
| Beranda | jadwal (mulai & berakhir) + countdown (di HP bergaya terminal selebar layar), ringkasan pemilih, suara per pasangan, rekap kelas (7/8/9) & rombel (7A, 8B, ...), live count; saat selesai tampil keadaan final + tautan hasil akhir |
| Analitik | deretan pil (Total, Pemilih, Jenis Kelamin, Kelas, Rombel, Detail Suara); isi bagian dimuat tanpa memuat ulang halaman. Detail suara: tabel siswa & guru terpisah (cari, filter, paginasi masing-masing) |
| Hasil akhir | hanya aktif saat pemilihan selesai (bagian 16) |
| Pasangan calon | tambah/ubah/nonaktifkan/hapus + unggah tema, pratinjau halaman pemilih |
| Siswa / Guru | daftar (status "Sudah/Belum memilih" + kolom waktu memilih), cari, filter, detail (kode unik tersamar), **tambah & ubah satu per satu** (aturan sama dengan impor), nonaktifkan, hapus data salah impor, impor Excel |
| Jadwal pemilihan | nama, tahun, mulai, selesai; tutup sekarang; buka sekarang |
| Unlock | cari pemilih, buka hak suara dengan alasan |
| Audit log | riwayat tindakan admin (hanya-baca) |
| Pengunjung Online | tamu & pengguna login yang sedang membuka situs, persentase pengguna online dari seluruh akun, perangkat/browser/OS, User-Agent lengkap (Stage 19, bagian 8.2) |
| Akun Admin | ganti nama pengguna dan kata sandi admin yang sedang masuk (Stage 13, bagian 8.1) |

### 8.1 Akun Admin (Stage 13)

`/admin/akun`: dua formulir, **Ganti Nama Pengguna** dan **Ganti Kata
Sandi**. Keduanya meminta kata sandi saat ini (salah 5 kali = tunggu, sama
seperti login). Nama pengguna 3-50 karakter (huruf kecil, angka, titik,
strip, garis bawah) dan tidak boleh dipakai admin lain; kata sandi baru
minimal 8 karakter (maksimal 72), berbeda dari yang lama, diulang dua kali.
Setelah kata sandi diganti, sesi ini tetap masuk sedangkan perangkat lain
yang masih masuk dengan akun yang sama otomatis keluar. Kedua perubahan
tercatat di audit log (tanpa kata sandi). `php spark admin:password` tetap
tersedia bila kata sandi terlupa.

Setiap halaman punya breadcrumb berikon (Beranda > ... > halaman ini): di
desktop berada di topbar (ikon + teks), di HP berupa ikon saja di atas judul
yang rata tengah (Stage 12; Stage 13: di Beranda tanpa ikon breadcrumb di
HP). Keterangan halaman muncul saat ikon "i" di samping judul ditekan (hanya
di layar lebar; Stage 13: HP tanpa tombol "i"). Semua kolom pencarian admin (siswa, guru, detail
suara, unlock, audit log) mencari langsung saat mengetik tanpa memuat ulang
halaman. Indikator live count
berikon siaran bertuliskan **Live** saat pemilihan berlangsung; di HP ikon,
status, waktu "diperbarui", dan tombol **Perbarui** menjadi satu bar di bawah
layar.

Stage 17 (tampilan panel): topbar tanpa badge status pemilihan (status
terbaca di indikator live & countdown); di HP tombol menu berupa ikon saja
dan header menampilkan logo khusus **PILKETOS Panel Admin**; ikon "i" kecil
di pojok kanan atas setelah judul; tanpa garis atas/bawah pemisah; hasil per
pasangan (Beranda & Analitik) dan kartu Pasangan calon menampilkan **dua foto
pasangan dalam satu kotak persegi** (nomor pasangan muncul semi transparan
saat diarahkan kursor, di HP nomor kecil di pojok); countdown lebih lega tanpa
garis; tabel bergaya lembar kerja (Excel). Rincian:
[`STAGE17-NOTES.md`](docs/catatan-tahap/STAGE17-NOTES.md).

Stage 19: banner final di Beranda (pemilihan selesai) berjudul dua baris
berhuruf kapital, **PASANGAN 0X TERPILIH** lalu nama ketua & wakil di baris
kedua, tanpa kalimat "Pencoblosan ditolak server ..."; logo header HP diatur
di `app/Config/Homepage.php` seperti logo beranda:

```
public string $adminLogoMobile       = 'assets/img/brand/logo-admin-mobile.svg';
public int    $adminLogoMobileHeight = 34;   // px, 24-48
public string $adminLogoMobileAlt    = 'PILKETOS Panel Admin: Beranda';
```

(atau `.env`: `homepage.adminLogoMobile = 'assets/img/brand/logo-baru.svg'`).
Simpan file di `public/assets/img/brand/`; ukuran asli dibaca otomatis.

Admin **tidak dapat memilih** atas nama pemilih dan tidak dapat mengubah
pilihan siapa pun. Setelah pemilihan selesai, tindakan yang dapat mengubah
hasil dikunci: tambah/ubah/ubah status/hapus pemilih, impor, tambah/hapus pasangan, nomor
urut & status aktif pasangan. Membuka kembali pemilihan yang sudah selesai
lewat Jadwal wajib dicentang konfirmasinya dan tercatat di audit log.

### 8.2 Pengunjung online (Stage 19)

`/admin/pengunjung` (menu **Pengunjung Online**): siapa saja yang sedang
membuka situs, tamu (belum login) maupun siswa/guru/admin yang login.

- **Ringkasan**: online sekarang, tamu, pengguna login + persentase terhadap
  seluruh pengguna sistem (siswa aktif + guru aktif + admin), serta per peran
  ("1 dari 11 siswa aktif, 9,1%"). Satu akun di dua perangkat dihitung satu.
- **Sebaran** perangkat (HP/Tablet/Komputer), browser, dan sistem operasi.
- **Tabel** per browser: nama & NISN/NIP/kelas (atau Tamu), halaman yang
  sedang dibuka, perangkat (merek & model), OS + versi + arsitektur, browser +
  versi + mesin (Blink, WebKit, Gecko), IP, bahasa, zona waktu, jaringan
  (4G/3G), layar & jendela, rasio piksel, layar sentuh, jumlah halaman, lama
  kunjungan, terakhir aktif. **Lihat** membuka User-Agent lengkap, Client
  Hints, halaman pertama, situs asal, dan tema perangkat. Baris browser Anda
  bertanda **Anda**.
- Saring: waktu (online sekarang / hari ini / 7 hari), jenis pengunjung,
  kata kunci (nama, NISN/NIP, IP, browser, perangkat). Tanpa muat ulang:
  papan angka diperbarui **setiap detik** (Stage 21,
  `GET admin/pengunjung/ringkas`), sebaran & tabel setiap 20 detik.

Cara kerja: setiap halaman yang dibuka dicatat (filter `visitors`), lalu
selama halaman terbuka browser mengirim detak tiap 60 detik
(`POST kunjungan/detak`, `presence.js`). Online = aktif dalam 3 menit
terakhir. Angka di beranda dibaca ulang tiap detik (`GET kunjungan/online`,
hanya angka, tanpa menulis apa pun). Perangkat & browser diurai pustaka Composer `matomo/device-detector`
(sudah dipakai sejak Stage 11) dari User-Agent + Client Hints, jadi berupa
perkiraan. Bot dan pustaka HTTP (curl, Python) tidak dihitung. Demi
kerahasiaan pilihan, halaman pemilih dicatat tanpa nomor paslon
(`siswa/paslon/02` menjadi `siswa/paslon`). Publik hanya melihat angka
ringkas di beranda (bagian 21). Pengaturan: `app/Config/Visitors.php`
(bagian 5).

## 9. Siswa

1. Buka alamat aplikasi, gulir/geser ke bagian **Masuk sebagai**, pilih
   **SISWA** (atau buka `/siswa/masuk`). Login dua tahap (Stage 9): isi NISN
   (10 digit, nol di depan tetap) > **Lanjut** > kode unik (tanggal lahir
   `DDMMYYYY`; `05-06-2013` juga diterima) > **Masuk**. Setelah diterima
   gembok di kartu terbuka, lalu dasbor terbuka. Tanpa JavaScript kedua isian
   tampil sekaligus.
2. Dasbor menampilkan identitas, status hak suara, dan jadwal.
3. **Lihat kandidat & coblos** (hanya saat pemilihan berlangsung).
4. Setelah memilih: pilihan terkunci; login ulang hanya menampilkan pilihan
   sendiri ("Kamu memilih Pasangan 0X"; guru: "Anda"). Tekan **Selesai &
   keluar** di komputer bersama.

Sesi pemilih berakhir otomatis setelah 15 menit tanpa aktivitas.

## 10. Guru

Sama dengan siswa: pilih **GURU** di bagian **Masuk sebagai** (atau buka
`/guru/masuk`) dengan NIP > **Lanjut** > kode unik (dua tahap, Stage 9). Guru
adalah pemilih biasa: tidak memiliki akses admin maupun analitik. Suara guru
dan siswa disimpan di tabel terpisah dan dihitung bersama pada hasil.

## 11. Kelola pasangan calon

Admin > **Pasangan calon** > Tambah / Ubah:

- nomor urut (1-99, unik), nama ketua & wakil, visi, misi (satu poin per
  baris), nama tema, warna aksen `#RRGGBB` (kontras teks dihitung otomatis),
  status aktif. Stage 16: tidak ada lagi pilihan layout; semua pasangan
  memakai satu tampilan yang sama;
- **Profil & kampanye** (Stage 15, semua opsional): slogan, bio ketua/wakil,
  kata karakter (dipisah koma, tampil maks. 3), program unggulan (satu per
  baris: `Judul — penjelasan`), pesan ketua/wakil, tautan video YouTube
  (disimpan sebagai ID, diputar lewat youtube-nocookie setelah diklik).
  Bagian yang kosong tidak tampil di halaman profil paslon;
- 6 slot gambar (Stage 16): foto ketua, foto wakil, **gambar pembuka profil**
  (landscape buatan paslon, tampil utuh di paling atas profil; disarankan
  1920 × 1080, lebar minimal 1200 px, gambar tegak/persegi ditolak), poster,
  **latar penutup** (latar bagian penutup profil, diberi lapisan gelap), dan
  foto pasangan (berdua, untuk perolehan suara beranda & hasil akhir). Hanya
  `jpg/jpeg/png/webp` sampai 5 MB; isi file diperiksa, gambar di-encode ulang
  server (WebP bila tersedia), metadata EXIF/GPS dibuang, nama file acak;
- **Pratinjau** menampilkan halaman profil paslon persis seperti yang dilihat
  pemilih.

Memperbarui dari Stage 15 ke Stage 16: jalankan `php spark migrate`. Migration
`SimplifyCandidateTheme` menghapus pilihan layout, latar panggung, artwork, dan
tekstur **beserta file gambarnya** di `public/uploads/candidates/` (tidak dapat
dikembalikan; cadangkan folder itu dulu bila file lama ingin disimpan); foto,
foto berdua, dan poster tetap. Unggah gambar pembuka & latar penutup baru lewat
form pasangan.

**Batas pasangan aktif** (Stage 15): panel di atas daftar pasangan, 2–10
pasangan (bawaan 3), tersimpan per pemilihan dan tercatat di audit log. Batas
tidak dapat diturunkan di bawah jumlah pasangan aktif; pasangan baru di atas
batas hanya dapat disimpan nonaktif. Pasangan nonaktif tidak dihitung. Setelah
pemilihan selesai batas dikunci.

Pasangan yang sudah menerima suara tidak dapat dihapus (gunakan nonaktif).
Setelah pemilihan selesai hanya teks & gambar yang dapat dirapikan.

## 12. Impor Excel

Siswa > Impor atau Guru > Impor:

1. **Unduh template** (`templat-impor-siswa.xlsx`: `no, NISN, nama,
   jenis_kelamin, rombel, nomor_absen, kodeunik`; `templat-impor-guru.xlsx`:
   `no, NIP, nama, kodeunik`). Kolom identitas & kode unik bertipe Teks.
   Stage 11: kolom `kelas` menjadi `rombel` (7A, 8B); file lama berkolom
   `kelas` tetap diterima.
2. Isi, simpan sebagai `.xlsx`, **unggah** (maks 5 MB, 3.000 baris).
3. **Pratinjau & validasi**: baris baru / diperbarui / tidak berubah /
   bermasalah dengan alasannya. NISN harus 10 digit; jenis kelamin L/P; rombel
   diawali kelas 7/8/9; kode unik tanggal valid; NISN/NIP ganda dalam file ditolak;
   nol depan yang hilang dipulihkan dengan peringatan; NIP yang sudah dibulatkan
   Excel ditolak.
4. **Impor**: satu transaction; NISN/NIP yang sudah ada diperbarui (tidak
   diduplikasi), pemilih yang tidak ada di file tidak dihapus. Pratinjau hanya
   dapat dipakai sekali; klik ganda tidak mengimpor dua kali.
5. **Hasil**: jumlah ditambahkan / diperbarui / tidak berubah / dilewati,
   tercatat di audit log.

## 13. Voting

- Hanya saat status **Sedang Berlangsung** (dicek ulang server saat simpan);
  tepat pada `end_at` sudah ditolak.
- Dasbor pemilih (Stage 8): nama rata tengah, di bawahnya NISN / rombel /
  nomor absen (guru: NIP) miring tanpa label; sisa waktu "Ditutup dalam"
  melayang di bawah layar. Di HP tombol Dasbor & Keluar cukup ikon; Stage 10:
  kartu hak suara rata tengah dan sisa waktu menjadi strip selebar layar
  tepat di atas footer (tidak melayang).
- Bilik suara (`/siswa/coblos`, `/guru/coblos`, Stage 7-8): pembuka rata
  tengah "Kenali, lalu coblos." dengan journey timeline tiga langkah (Kenali
  paslon, Coblos satu, Konfirmasi & kunci) dan countdown bergaya terminal
  (Stage 9: tanpa teks bilah judul/perintah, angka satu baris di HP); di HP
  pembuka + Sekilas paslon berbagi layar pertama, surat suara setinggi
  satu layar. Lalu **Sekilas paslon** (satu kartu per pasangan untuk membandingkan: nomor, foto, nama,
  tema, kutipan visi, jumlah misi; digeser di HP, Stage 10: bergeser sendiri
  tiap 2 detik 01 -> terakhir -> 01, berhenti saat disentuh; panah halus di
  bawahnya menuju surat suara). **Profil lengkap** menuju
  halaman profil paslon, **Pilih 0X** langsung ke kotak pasangan itu di surat suara
  (kotak disorot dan tombol Coblos-nya difokuskan). Jumlah kolom mengikuti
  jumlah pasangan (Stage 15, mis. 5 pasangan = 3 + 2).
- Profil paslon (`/siswa/paslon/0X`, `/guru/paslon/0X`, Stage 15, redesain
  Stage 16): satu tampilan untuk semua pasangan. Pembuka = satu **gambar
  landscape buatan paslon** (tampil utuh) dengan tombol **Lihat Visi & Misi**
  di bawahnya (tanpa gambar: judul sederhana); lalu Kenali pasangan (kartu
  foto, nama, peran, bio, pill karakter), Visi kami, Misi kami, Program
  unggulan, Poster kampanye, Pesan dari kami, Video kampanye
  (thumbnail asli YouTube + tombol putar), dan penutup berlatar gambar khusus
  (tanpa warna aksen) dengan **Pilih Paslon 0X** / kembali ke daftar dan deret
  nomor semua paslon. Stage 21: halaman selebar layar; kepala bagian tanpa
  ikon, rata tengah, di atas pita berpola SVG khas tiap bagian (warna aksen
  samar); poster dengan ikon **unduh** (kiri bawah, file
  `paslon-osis-no-0X-spensada-2026`) dan **layar penuh** (kanan bawah);
  kutipan pesan miring dengan avatar sebaris nama; video tanpa bilah
  keterangan. Navigasi bagian gelap, teks terang, tanpa titik: bar menempel
  di HP/tablet, panel di tepi kanan layar yang selalu di tengah tinggi layar
  + garis progres baca di desktop (≥ 1024 px). Rincian:
  [`STAGE21-NOTES.md`](docs/catatan-tahap/STAGE21-NOTES.md),
  [`STAGE16-NOTES.md`](docs/catatan-tahap/STAGE16-NOTES.md) (awal:
  [`STAGE15-NOTES.md`](docs/catatan-tahap/STAGE15-NOTES.md)).
- Surat suara: tekan **Coblos** di kotak pasangan, atau tekan-tahan paku,
  geser ke kotak pasangan, lepas untuk mencoblos; lalu **Konfirmasi pilihan**.
  Di HP kotak pasangan + paku muat satu layar (3 pasangan). Setelah dicoblos, kertas bolong
  terlihat 3 detik sebelum modal konfirmasi (latar diburamkan) muncul. Paku
  3D (WebGL, dimuat malas) selalu nyala tanpa tombol pengalih; paku 2D hanya
  cadangan bila WebGL tidak tersedia/gagal atau frame terlalu lambat;
  tombol "Coblos Pasangan 0X" untuk keyboard/pembaca layar; tanpa JavaScript
  memakai halaman konfirmasi biasa.
- Disimpan dalam transaction (`FOR UPDATE` baris pemilih + unique key), status
  `LOCKED`, perangkat & browser dibaca server. Klik ganda, tab ganda, atau POST
  diulang tetap satu suara.

## 14. Unlock hak suara

Hanya saat pemilihan berlangsung, untuk kasus seperti pemilih salah menekan:

1. Admin > **Unlock** > cari nama/NISN/NIP.
2. Periksa status suara, isi **alasan** (10-500 karakter), centang pernyataan,
   konfirmasi.
3. Baris suara lama menjadi riwayat `UNLOCKED`, dicatat di `vote_unlock_logs`
   dan audit log (admin, pemilih, pemilihan, alasan, waktu).
4. Pemilih login lagi dan **memilih sendiri**; admin tidak memilihkan.

## 15. Analitik & live count

- Angka dari satu definisi (bagian 2): sudah + belum memilih = total pemilih
  aktif (siswa, guru, gabungan); suara siswa + suara guru = total suara.
- Rekap: pemilih (siswa/guru), jenis kelamin (khusus siswa), kelas 7/8/9 (dibaca dari
  awal nama rombel, juga angka Romawi), rombel (7A, 8B, ...), per pasangan
  (jumlah & persen dari suara sah).
- Istilah (Stage 11, seragam di semua halaman, impor, pesan, dan audit):
  **rombel** = rombongan belajar (7A, 8B, VIII-C); **kelas** = tingkat 7, 8,
  9. Filter memakai `?rombel=` (`?kelas=` lama tetap diterima). Kolom
  database tetap `students.kelas`.
- Halaman analitik (Stage 11) memakai deretan pil; tiap bagian punya URL
  sendiri (`/admin/analitik/rombel`, ...) dan dimuat lewat fetch tanpa memuat
  ulang halaman (Back/Forward tetap berfungsi, tanpa JavaScript = pindah
  halaman biasa). Selama bagian diambil tampil kerangka "memuat" (Stage 12).
- Live count di dasbor diperbarui tiap 10 detik saat berlangsung (60 detik
  sebelum mulai), berhenti saat selesai atau tab tidak aktif; tombol
  **Perbarui** memaksa ambil data.
- Detail suara: cari, filter jenis/rombel/jenis kelamin/pasangan/status; tabel
  siswa dan guru terpisah, masing-masing 25 per halaman. Kolom perangkat
  dibaca server saat mencoblos dengan pustaka `matomo/device-detector`
  (model HP, versi OS, browser dalam aplikasi, Chromebook). Model HP & versi
  OS asli (mis. Windows 11) dari Client Hints hanya dikirim browser lewat
  HTTPS/localhost; lewat `http://IP-LAN` Chrome Android cukup "HP / Android".
- **Beranda publik** (`GET hitung-suara`): hanya persentase suara sah per
  pasangan dan partisipasi (sudah memilih / seluruh pemilih aktif) dari angka
  yang sama, diperbarui tiap 30 detik (cache 5 detik), berhenti saat tab tidak
  aktif atau pemilihan selesai. Rincian di atas tetap hanya untuk admin.
  Matikan dengan `homepage.publicLiveCount = false` bila aturan sekolah tidak
  membolehkan perolehan sementara tampil publik (bagian 21).

## 16. Hasil akhir

Admin > **Hasil akhir** (`/admin/hasil`):

- **Terkunci** (tanpa angka) sebelum waktu selesai; aktif otomatis sejak
  jam server >= `end_at` (atau setelah **Tutup pemilihan sekarang**).
- Menampilkan pasangan terpilih dengan tema pasangan itu, peringkat ketiga
  pasangan, jumlah & persen suara, selisih dengan peringkat 2, partisipasi
  siswa/guru, dan rekap jenis pemilih, jenjang, jenis kelamin. Angka sama
  dengan dasbor.
- Suara tertinggi sama: "Perolehan suara tertinggi sama" tanpa pemenang
  (panitia memutuskan sesuai aturan). Tanpa suara sah: tanpa pemenang.
- **Confetti** (canvas, tanpa emoji) hanya di halaman ini dan hanya bila ada
  satu pasangan terpilih: sekali per sesi browser, sekitar 5 detik, tidak
  menghalangi klik, tidak tampil bila reduced motion. Tidak pernah tampil di
  dasbor/analitik.
- Stage 19: panggung pasangan terpilih rata tengah; di desktop label
  **PASANGAN TERPILIH** di tengah, nomor besar & foto sebaris, nama ketua &
  wakil berhuruf kapital di baris berikutnya, lalu perolehan dua baris (suara
  & persen, lalu selisih dengan peringkat 2). Nama dan peran mengikuti warna
  teks di atas aksen pasangan (putih di aksen gelap, hitam di aksen terang).
  Catatan kaki halaman miring, rata kiri, diawali tanda bintang.
- Tombol **Layar penuh** (proyektor), **Cetak PDF**, dan **Analitik
  lengkap** (Stage 13: di HP berupa ikon bulat saja).
- **Cetak PDF** (Stage 13, `/admin/hasil/cetak`): PDF A4 dibuat server dengan
  `dompdf/dompdf` lalu dibuka di tab baru (cetak/simpan dari penampil PDF
  browser). Logo SMP 1 DAWE menggantikan judul halaman, kicker & judul rata
  tengah, rekap per pemilih/kelas/jenis kelamin di halaman kedua, catatan
  kaki miring rata tengah + "Halaman X dari Y" di setiap halaman. Catatan
  "perolehan suara tertinggi sama" tidak dicetak. Font memakai Newsreader &
  Plus Jakarta Sans (TTF di `app/Fonts`); metrik font disimpan di
  `writable/cache/dompdf` (dibuat ulang otomatis setelah `cache:clear`).
- Cetak lewat browser (Ctrl+P) mengikuti tata letak yang sama; catatan kaki
  & nomor halaman di margin bawah setiap halaman memakai `@page` margin box
  (Chrome/Edge 131+). Footer panel admin tidak ikut dicetak.
- Beranda publik saat selesai menampilkan perolehan akhir (persentase;
  status "Sudah Selesai" di panel status) tanpa pemenang dan tanpa confetti;
  pengumuman resmi tetap oleh panitia.

## 17. Backup & pemulihan

Backup database (termasuk trigger), dari Laragon Terminal:

```
mysqldump -u root --single-transaction --routines --triggers smp1dawe_osis_2026 > backup-osis-2026-YYYYMMDD-HHMM.sql
```

Salin juga `public/uploads/candidates/` (foto & tema) dan `.env` (simpan
terpisah, berisi kredensial). Waktu yang disarankan: setelah data & pasangan
siap, sebelum pemilihan mulai, beberapa kali selama pemilihan
(`--single-transaction` tidak mengunci pencoblosan), dan segera setelah
selesai.

Pemulihan ke database kosong:

```
mysql -u root -e "DROP DATABASE IF EXISTS smp1dawe_osis_2026; CREATE DATABASE smp1dawe_osis_2026 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root smp1dawe_osis_2026 < backup-osis-2026-YYYYMMDD-HHMM.sql
php spark osis:check
```

`osis:check` memastikan 8 trigger ikut terpulihkan. Karena suara dan log
tidak dapat dihapus, "mengosongkan" suara berarti memulihkan backup lama atau
memakai database baru.

## 18. Troubleshooting

| Gejala | Penyebab & solusi |
|---|---|
| Semua halaman selain beranda 404 | mod_rewrite mati atau `AllowOverride None`; DocumentRoot harus `public/` (nginx: `try_files`, bagian 7.4) |
| Tampilan tanpa CSS/JS, konsol "Content Security Policy" | alamat yang dibuka berbeda dengan `app.baseURL` (mis. `.test` vs IP). Samakan |
| HP tidak bisa membuka `...test` | domain `.test` hanya berlaku di laptop server; pakai IP server (bagian 7.3) |
| "Sesi formulir sudah kedaluwarsa" / 403 saat kirim form | token CSRF tidak cocok (sesi habis, cookie diblokir, form dibuka terlalu lama); muat ulang halaman lalu kirim lagi |
| Pemilih kembali ke halaman login | sesi idle 15 menit berakhir (disengaja) atau akun dinonaktifkan |
| "Terlalu banyak percobaan masuk" | 5 gagal per akun: tunggu sesuai detik yang disebut (sekitar 1 menit); periksa NISN/NIP dan kode unik |
| Unggah foto "terlalu besar" | naikkan `upload_max_filesize` / `post_max_size` (bagian 3), restart Apache |
| nginx: `413 Request Entity Too Large` | `client_max_body_size` belum diatur (bawaan nginx 1 MB); lihat bagian 7.4 |
| nginx: `502 Bad Gateway` | proses php-cgi mati atau kurang: Stop lalu Start All, naikkan jumlah proses PHP (bagian 7.4) |
| Impor ditolak | hanya `.xlsx`, maks 5 MB & 3.000 baris, header sesuai template (lihat alasan per baris di pratinjau) |
| Pencoblosan belum/tidak terbuka, jam selisih | jam Windows server salah; status mengikuti jam server WIB |
| Hasil akhir "belum tersedia" | waktu selesai belum lewat menurut jam server; gunakan Tutup pemilihan sekarang bila memang selesai |
| Confetti tidak muncul | sudah tampil di sesi browser ini, perangkat memakai reduced motion, atau hasil seri/tanpa suara (disengaja) |
| Tombol/menu "dikunci: pemilihan sudah selesai" | hasil akhir dilindungi; buka kembali lewat Jadwal hanya bila benar-benar perlu (tercatat) |
| `php spark migrate` error 1419 | MySQL + binary log + user bukan root: jalankan dengan root atau `SET GLOBAL log_bin_trust_function_creators = 1`, lalu ulangi |
| Migration "status dan unlocked_at tidak konsisten" | ada baris suara lama yang tidak konsisten; periksa id yang disebut sebelum melanjutkan |
| HeidiSQL/phpMyAdmin: "tidak boleh dihapus/diubah" pada suara/log | penjaga integritas bekerja (disengaja) |
| Halaman putih / 500 | lihat `writable/logs/log-*.log`; jalankan `php spark osis:check` (ekstensi, folder tulis, database) |
| Layar pembuka muncul setiap kali beranda dibuka | disengaja (Stage 6): selalu tampil 1,5–5,2 detik; tidak tampil hanya setelah muat ulang otomatis karena status pemilihan berubah |
| Beranda tidak pindah bagian dengan roda mouse/trackpad | satu gestur = satu bagian; tunggu transisi selesai lalu gulir lagi. Bagian yang lebih panjang dari layar (HP miring, zoom besar) digulir dulu isinya |
| Perolehan suara di beranda tidak berubah | diperbarui tiap 30 detik (+ cache 5 detik), berhenti saat tab tidak aktif atau pemilihan selesai; `homepage.publicLiveCount = false` menyembunyikannya |
| Logo/ilustrasi pengganti tidak tampil | path relatif ke `public/` (mis. `assets/img/home/siswa.webp`), bukan `public/uploads/`; format svg/webp/jpg/png/avif (bagian 21) |
| Pengunjung online kosong / angka online tidak tampil | tabel `visitors` belum dibuat: `php spark migrate`; atau `visitors.enabled` / `visitors.publicCounter` = `false`. Pengunjung yang menutup tab hilang dari daftar setelah 3 menit (disengaja) |
| Kolom perangkat hanya "HP / Android" tanpa model | Client Hints hanya dikirim lewat HTTPS/localhost; lewat `http://IP-LAN` model HP dari User-Agent saja (bagian 15) |

## 19. Test otomatis

Butuh database `smp1dawe_osis_2026_test` (`database.tests.*` di `.env`):

```
composer install
composer test                 (atau vendor\bin\phpunit --no-coverage)
```

Hasil terakhir (Stage 22): **522 test, 5.038 assertion, lulus** pada PHP
8.3.6 dengan MariaDB 10.11.14 (Stage 21: 516 test, Stage 20: 499 test, Stage 19: 484 test, Stage 18: 468 test, Stage 17: 465 test, Stage 16: 454 test, Stage 15: 444 test, Stage 13: 403 test, Stage 12: 383 test, Stage 11: 374 test, Stage 10: 348 test, Stage 9: 340 test, Stage 8: 331 test, Stage 7: 323 test). Stage 4 (289 test) juga lulus di MySQL
8.0.46; Stage 5 dan 6 tidak mengubah schema maupun query. Test paralel (race
condition) memakai `pcntl_fork` sehingga di-skip di Windows. Rincian dan uji
browser: [`STAGE4-NOTES.md`](docs/catatan-tahap/STAGE4-NOTES.md) bagian 9,
beranda: [`STAGE5-NOTES.md`](docs/catatan-tahap/STAGE5-NOTES.md) dan
[`STAGE6-NOTES.md`](docs/catatan-tahap/STAGE6-NOTES.md) bagian 9.

## 20. Dokumen proyek

Semua dokumentasi selain README ini ada di folder `docs/`. Daftar lengkap
beserta ringkasan tiap dokumen: [`docs/README.md`](docs/README.md).

| Folder | Isi |
|---|---|
| [`docs/spesifikasi/`](docs/spesifikasi) | `00-MASTER-PROJECT.md` (spesifikasi utama, source of truth) dan brief Stage 1–5 (`01`–`05`) |
| [`docs/desain/`](docs/desain) | Identitas visual SMP 1 DAWE, Stage 6 (`06`–`10`): arah visual, tipografi, spesifikasi aset, prompt gambar, sistem gerak |
| [`docs/catatan-tahap/`](docs/catatan-tahap) | Catatan implementasi & handoff tiap tahap (`STAGE1-NOTES.md` s.d. `STAGE22-NOTES.md`) |

## 21. Beranda imersif & aset visual

Beranda (`/`) terdiri dari tiga **scene** setinggi layar yang berpindah satu
per satu, bukan halaman bergulir panjang. Arah visual (Stage 6): "Surat Suara
dari Lereng Muria": tempat (pagi berkabut di lereng Muria, sekolah yang asri)
menjadi dunia visual, surat suara (perforasi, lubang coblos) menjadi bahasa
interaksi. Palet netral "Pagi Muria" + warna aksen masing-masing pasangan.

| Scene | Isi |
|---|---|
| 01 Lereng | hanya gambar hero (lereng Muria + atap sekolah; lanskap di desktop, potret di HP), lapisan depan opsional, tombol **Masuk untuk memilih** dan **Lihat perolehan suara**; titik "embun" di sekitar pointer. Stage 18: tanpa teks di atas gambar, tanpa garis kontur & perforasi. Stage 19: pojok kanan bawah (desktop), sebaris dengan tombol masuk: angka kecil "**12** online · **4** login". Tanpa warna/nomor/foto pasangan |
| 02 Masuk | "Masuk sebagai": portal **SISWA** (`/siswa/masuk`) dan **GURU** (`/guru/masuk`) bergambar |
| 03 Perolehan suara | judul & pasangan rata tengah (Stage 10: tanpa label status di samping judul); foto pasangan, persentase tepat di bawah foto; baris penutup "Suara masuk" (persentase partisipasi, jumlah pemilih, "Diperbarui [tanggal] [jam]", meter); footer + panah ke atas (kembali ke scene 01). Stage 19: di atas teks footer "ONLINE **8** belum login **4** sudah login" (Stage 21: di HP kata "Online" menjadi ikon biru muda). Stage 21: foto pasangan dapat diklik → foto ukuran penuh dengan tautan **Lihat profil pasangan** bagi siswa/guru/admin yang login |

- Berpindah scene: roda mouse/trackpad (satu gestur = satu scene), geser
  sentuh, panah/PageUp/PageDown/spasi/Home/End, garis navigasi di kanan
  layar, atau tombol di tiap scene. Alamat `/#masuk` dan `/#perolehan`
  membuka scene itu langsung.
- **Panel status** di bawah layar (gaya terminal): status pemilihan, hitung
  mundur jam server, jadwal (Stage 21: disembunyikan di HP), garis progres.
  Tidak pernah menutupi tombol.
- **Layar pembuka**: selalu tampil setiap kali beranda dimuat (1,5–5,2 detik,
  mengikuti aset layar pertama), tanpa tombol lewati: pemandangan hero yang
  sama berkabut pekat, lockup di tengah, bilah muat. Keluar: kabut memudar
  menjadi hero yang jernih sementara lockup berpindah ke navigasi. Tidak
  tampil setelah muat ulang otomatis karena status pemilihan berubah.
- Tanpa JavaScript atau dengan "kurangi gerakan" di perangkat, beranda tetap
  lengkap (bergulir biasa / tanpa animasi; layar pembuka tetap tampil statis).

Mengganti gambar (tanpa mengubah layout): simpan file di
`public/assets/img/...`, lalu isi path-nya (relatif ke `public/`) di
`app/Config/Homepage.php` atau `.env`. Ukuran, zona aman, dan prompt
pembuatan: [`08-VISUAL-ASSET-SPECIFICATION-PILKETOS.md`](docs/desain/08-VISUAL-ASSET-SPECIFICATION-PILKETOS.md)
dan [`09-IMAGE-GENERATION-PROMPTS-PILKETOS.md`](docs/desain/09-IMAGE-GENERATION-PROMPTS-PILKETOS.md).

```
# lambang resmi sekolah (file dari sekolah, dipasang apa adanya di kiri lockup)
homepage.schoolEmblem   = 'assets/img/brand/lambang-smp1dawe.svg'
# hero (A03 lanskap master, A04 potret) & layar pembuka (A01/A02 = hero berkabut, bingkai sama)
homepage.heroDesktop    = 'assets/img/home/hero-desktop.webp'
homepage.heroMobile     = 'assets/img/home/hero-mobile.webp'
homepage.introDesktop   = 'assets/img/home/intro-desktop.webp'
homepage.introMobile    = 'assets/img/home/intro-mobile.webp'
# opsional: lapisan depan transparan (ranting parijoto + daun kopi)
homepage.heroForeground = 'assets/img/home/hero-foreground.webp'
# portal Siswa/Guru (1:1) + versi HP tegak (3:2)
homepage.entryStudent       = 'assets/img/home/entry-student.webp'
homepage.entryStudentMobile = 'assets/img/home/entry-student-mobile.webp'
homepage.entryTeacher       = 'assets/img/home/entry-teacher.webp'
homepage.entryTeacherMobile = 'assets/img/home/entry-teacher-mobile.webp'
# lockup acara (latar gelap / terang), bila ingin mengganti bawaan
homepage.logoOnDark   = 'assets/img/brand/logo-light.svg'
homepage.logoOnLight  = 'assets/img/brand/logo-dark.svg'
```

| Aset | Ukuran disarankan | Catatan |
|---|---|---|
| Lambang resmi sekolah | SVG, atau PNG >= 1024 px transparan | tidak digambar ulang/diwarnai ulang; tampil setinggi lockup (24–40 px di navigasi) |
| Lockup acara | SVG (bawaan: tanda gugus parijoto + "PILKETOS 2026 / SMP 1 DAWE") | ukuran asli dibaca otomatis; tampil setinggi 24–40 px di navigasi |
| Latar hero | lanskap 1920×1080 (≤ 300 KB), potret 1080×1920 (≤ 250 KB), WebP | area kiri bawah (tombol) gelap & tenang; fokus (punggungan, atap sekolah) di kanan tengah |
| Latar layar pembuka | sama dengan latar hero, berkabut pekat (≤ 250/200 KB) | **bingkai harus identik** dengan latar hero; pusat layar rata untuk lockup |
| Lapisan depan | 1400×1400 WebP ber-alpha (≤ 150 KB) | pojok kanan bawah; disembunyikan di HP tegak |
| Portal Siswa/Guru | 1600×1600 (1:1) + 1200×800 (3:2) untuk HP | subjek di tengah, sepertiga bawah tenang (label di bawah-kiri); orang kecil/dari belakang |
| Foto pasangan | dari menu **Pasangan calon** (foto ketua, foto wakil, hero/foto berdua) | scene perolehan suara memakai foto berdua bila ada; tanpa foto tampil monogram inisial |

Warna identitas sekolah (parijoto, `homepage.identityAccent`) hanya dipakai
pada bilah muat layar pembuka, dan otomatis diganti warna netral bila mirip
(CIEDE2000 < 20) warna aksen salah satu pasangan calon.

Angka online di beranda (Stage 19) diperbarui tiap detik tanpa muat ulang
(Stage 21, `visitors.counterRefreshSeconds`); matikan dengan
`visitors.publicCounter = false` (bagian 8.2).

Perolehan suara publik dapat dimatikan: `homepage.publicLiveCount = false`
(scene ketiga menjadi "Pasangan calon" tanpa angka). Rincian teknis:
[`STAGE5-NOTES.md`](docs/catatan-tahap/STAGE5-NOTES.md),
[`STAGE6-NOTES.md`](docs/catatan-tahap/STAGE6-NOTES.md), dan
[`STAGE18-NOTES.md`](docs/catatan-tahap/STAGE18-NOTES.md) (hero tanpa teks), dan
[`STAGE19-NOTES.md`](docs/catatan-tahap/STAGE19-NOTES.md) (angka online), dan
[`STAGE21-NOTES.md`](docs/catatan-tahap/STAGE21-NOTES.md) (angka tiap detik, foto ukuran penuh).

## Lisensi

Framework CodeIgniter: MIT (`LICENSE`).
Deteksi perangkat `matomo/device-detector` (Stage 11, lewat Composer):
LGPL-3.0-or-later, dipakai sebagai pustaka tanpa diubah.
Font Plus Jakarta Sans, Newsreader, dan JetBrains Mono: SIL Open Font License
(`public/assets/fonts/`).
