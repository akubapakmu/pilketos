# Stage 21 — Angka Online Tiap Detik, Foto Pasangan Layar Penuh, Poster Bisa Diunduh, Profil Paslon Selebar Layar (hasil implementasi + handoff)

Dokumen ini adalah kontrak aktual Stage 21. Dibaca bersama
`00-MASTER-PROJECT.md`, `STAGE1-NOTES.md` s.d. `STAGE20-NOTES.md`, dan
`README.md`.

Ringkasan Stage 21 (permintaan revisi beranda, panel admin, dan profil paslon):

- **angka online** di beranda ("online" / "belum login" / "sudah login") dan
  **papan angka** halaman admin **Pengunjung online** diperbarui sendiri
  **tiap 1 detik** tanpa muat ulang halaman;
- **beranda HP** (< 768 px): teks jumlah pemilih "Suara masuk"
  (`.turnout__count`) 0.7rem, jadwal di panel status (`.dock__meta`)
  disembunyikan, kata "Online" di footer diganti **ikon siaran biru muda**;
- **foto pasangan** di scene perolehan suara bisa diklik dan tampil **ukuran
  penuh**; di atas foto ada tautan **Lihat profil pasangan** ke profil paslon
  di panel pengguna yang login (tamu: tanpa tautan);
- **profil paslon**:
  - poster kampanye: ikon **unduh** (kiri bawah) dan ikon **layar penuh**
    (kanan bawah) tanpa teks, tombol lama "Lihat fullscreen" dihapus, file
    unduhan bernama `paslon-osis-no-0X-spensada-2026.<ext>`;
  - navigasi bagian gelap, teks terang, tanpa titik; di desktop panel
    melayang di tepi kanan layar dan menempel di tengah tinggi layar;
  - kepala bagian tanpa ikon, rata tengah, di atas pita selebar layar
    berpola SVG khas tiap bagian;
  - kutipan "Pesan dari kami" miring dengan ketebalan 400, avatar sebaris nama;
  - video tanpa bilah keterangan (pemutar saja);
  - halaman selebar layar (batas 1240 px dihapus).

Tanpa migration dan tanpa dependensi baru. Dua route baru (`GET
kunjungan/online`, `GET admin/pengunjung/ringkas`), satu properti
konfigurasi baru (`visitors.counterRefreshSeconds`), bawaan
`visitors.countCacheSeconds` 5 → 1, tujuh file pola SVG baru.

"HP" = lebar < 768 px untuk beranda (sama dengan Stage 8–20); profil paslon
memakai breakpoint 720/1024/1280 px (Stage 15–16).

## 1. Angka online tiap detik

### 1.1 Beranda (`GET kunjungan/online`, `presence.js`)

- `PresenceController::online()` membalas `{online: {total, guests, users},
  interval}` dengan `Cache-Control: no-store`. Hanya membaca
  (`OnlineVisitors::publicCounts()`, cache singkat), tidak menulis ke
  database, dan tidak dihitung kunjungan (JSON). 404 bila `visitors.enabled`
  atau `visitors.publicCounter` mati; 503 bila hitungan gagal.
- `partials/presence.php` menambah `data-online-url` + `data-online-refresh`
  pada `<script presence.js>` hanya bila `publicCounter` aktif.
- `presence.js` membaca angka hanya di halaman yang punya
  `[data-online-count]` (beranda):
  - satu permintaan pada satu waktu, berikutnya dijadwalkan setelah balasan;
  - berhenti saat tab tersembunyi, dibaca lagi saat tab terlihat;
  - jeda 5 detik setelah galat jaringan atau server, berhenti pada 404;
  - teks ditulis hanya bila angkanya berubah.
- Detak 60 detik Stage 19 (`POST kunjungan/detak`) tidak berubah.

### 1.2 Cache angka

- `visitors.countCacheSeconds` bawaan **1** (dulu 5): ratusan layar yang
  membaca tiap detik tetap paling banyak satu hitungan MySQL per detik.
- `VisitorTracker` membuang cache (`OnlineVisitors::CACHE_KEY`) saat:
  - baris pengunjung baru dibuat;
  - pengunjung lama kembali online (sebelumnya lewat batas online);
  - akun browser berganti (masuk/keluar).

  Pengunjung baru langsung terhitung, dan angka di beranda tidak sempat
  turun 1 lalu naik lagi: beranda menambah +1 untuk pengunjung tanpa cookie,
  sedangkan cache yang dibaca detik berikutnya belum memuat barisnya.

### 1.3 Admin (`GET admin/pengunjung/ringkas`, `admin-visitors.js`)

- Papan angka (`[data-live-region="ringkas"]`) dipindah ke partial
  `admin/visitors/summary.php`, dipakai halaman dan fragmen.
- `VisitorController::summary()` (adminauth) mengirim fragmen itu saja,
  selalu dihitung langsung, `no-store`.
- `admin-visitors.js` membaca fragmen tiap `data-visitors-tick` (1) detik:
  - `<dl>` papan angka diganti hanya bila isinya berubah;
  - jam "Diperbarui" selalu diperbarui;
  - dilewati saat tab tersembunyi atau region sedang `aria-busy`;
  - kolom saring yang sedang diketik tidak terganggu.
- Sebaran dan tabel pengunjung tetap dimuat ulang tiap 20 detik (query
  berat). Teks keterangan: "Angka ringkas diperbarui otomatis setiap detik,
  daftar pengunjung setiap 20 detik."

Catatan: definisi online tidak berubah. Pengunjung yang menutup tab baru
hilang dari hitungan setelah `onlineSeconds` (180 detik) tanpa detak.

## 2. Beranda HP (`home.css`, `partials/footer.php`)

| Elemen | Stage 21 (< 768 px) |
|---|---|
| `.turnout__count` | `font-size: 0.7rem` (dulu 0.9375rem) |
| `.dock__meta` (jadwal) | `display: none`; panel status satu baris (`'status line'`), perkiraan `--dock-h` 40 px (home.js tetap mengukur ulang) |
| label "Online" footer | `icon('live')` biru muda `#7DD3FC` 16 px, statis; kata "Online" (`.site-footer__online-text`) disembunyikan secara visual, tetap dibaca pembaca layar |

Desktop tidak berubah: kata "ONLINE" tampil, ikon disembunyikan.

## 3. Foto pasangan ukuran penuh

```
.pair__photo                               (home/partials/pair_photo)
  ... foto / monogram, .pair__no
  button.pair__zoom[data-pair-zoom]        menutupi foto; label "Lihat foto pasangan 0X ukuran penuh";
    span.pair__zoom-icon (expand)          ikon kecil kanan bawah (sentuh: selalu, desktop: saat disorot)

dialog.pair-view#pair-view-{id}            (home/partials/pair_view), di luar .scenes, sebelum .pager
  .pair-view__top
    p.pair-view__title                     chip nomor (aksen) + "Ketua & Wakil"
    a.pair-view__profile                   "Lihat profil pasangan →" (hanya bila ada URL)
  .pair-view__photo--hero > img            foto berdua utuh (object-fit: contain), atau
  .pair-view__photo--duo > figure × 2      foto ketua & wakil (monogram bila belum ada) + nama & peran
  button.pair-view__close
```

- Tautan profil (`Home::profileUrl()`, sesi dibaca seperti header):

  | Peran | Tautan |
  |---|---|
  | siswa | `siswa/paslon/0X` |
  | guru | `guru/paslon/0X` |
  | admin | `admin/paslon/{id}/intip` |
  | tamu | tanpa tautan |

  Untuk siswa dan guru hanya paslon aktif yang diberi tautan. Paslon
  nonaktif yang masih dihitung karena sudah punya suara sah tidak punya
  halaman profil pemilih.
- `home.js` (`initPairViews()`):
  - membuka dialog lewat `showModal()`: fokus terkunci, Escape menutup
    (bawaan);
  - klik latar dan tombol tutup juga menutup;
  - fokus kembali ke foto yang diklik;
  - `touchstart` diabaikan selama `dialog[open]`, jadi geser tidak memindahkan
    scene (keydown dan wheel sudah mengabaikan dialog sejak Stage 5);
  - tanpa dukungan `<dialog>` tombol disembunyikan, dan tanpa JavaScript
    `.no-js .pair__zoom` juga disembunyikan.
- Mode teaser ("Pasangan calon", `homepage.publicLiveCount = false`) memakai
  partial yang sama.
- Gaya:
  - latar gelap pekat (dialog `rgba(11, 13, 12, 0.96)` + `::backdrop`),
    tanpa blur dan gradient;
  - masuk = pudar + skala 0,96 → 1 memakai token gerak;
  - reduced motion = tanpa animasi.

## 4. Profil paslon (`paslon/partials/profile.php`, `paslon.css`, `paslon.js`)

### 4.1 Selebar layar

- `.px-shell` tanpa `max-width`. Gutter: 16 px, 32 px (≥ 720), lalu
  `clamp(32px, 3.6vw, 72px)` (≥ 1280).
- `--px-pad-l` / `--px-pad-r` = padding kiri/kanan shell. Di desktop
  `--px-pad-r` mencakup panel navigasi: `--px-nav-edge + --px-nav-w (12.5rem) + --px-nav-gap`.
- Gambar pembuka (≥ 720 px) selebar kolom tetapi tidak lebih tinggi dari
  `100svh - 190px`; rasio dari ukuran asli file (`--px-cover-ratio`).
- Video selebar kolom, tetapi tidak lebih tinggi dari satu layar (16:9).

### 4.2 Navigasi bagian

- Tanpa titik (`.px-nav__dot` dihapus).
- `.px-nav__inner` berlatar ink (`#15141A`) dengan teks terang
  (`rgba(255,255,255,.7)`, aktif putih + pill transparan terang).
- **HP/tablet** (< 1024 px): bar gelap menempel di atas, selebar layar,
  digeser horizontal; garis progres baca 2 px di tepi bawah.
- **Desktop** (≥ 1024 px):
  - `.px-nav` adalah kolom absolut dekat tepi kanan layar (`--px-nav-edge`);
  - `.px-nav__rail` setinggi `100vh` dengan `position: sticky`, sehingga
    panel selalu di tengah tinggi layar dan ikut tergulir pergi di ujung isi
    (tidak menimpa penutup);
  - panel bersudut 22 px dengan bayangan halus dan hairline lewat
    box-shadow;
  - judul "PASLON" + chip nomor berwarna aksen dengan cincin terang;
  - garis progres baca 3 px di bawah daftar.
- `paslon.js` mengisi `--px-progress` (0..1, dari awal sampai ujung kolom
  isi). Elemen `[data-px-progress]` dirender `hidden` dan baru ditampilkan
  oleh skrip.

### 4.3 Kepala bagian

- Closure `$head($key, $title, $sub)` membuat
  `div.px-head.px-head--{key} > h2.px-head__title + p.px-head__sub`, tanpa
  ikon (`.px-head__icon` / `.px-head__text` dihapus).
- Ikon `target`, `bulb`, `message`, `video` di `icon()` ikut dihapus karena
  hanya dipakai kepala bagian Stage 16.
- Rata tengah. `::before` melebar ke tepi layar (`left/right:
  -var(--px-pad-l/-r)`), berlatar `var(--accent)` dengan opacity 0,13 dan
  di-*mask* pola SVG satu warna (warna mengikuti aksen pasangan, tanpa
  gradient):

  | Bagian | Pola | File | Ukuran ubin |
  |---|---|---|---|
  | Profil | titik | `px-dots.svg` | 20 × 20 |
  | Visi | plus | `px-plus.svg` | 28 × 28 |
  | Misi | garis miring | `px-diagonal.svg` | 16 × 16 |
  | Program | gelombang | `px-waves.svg` | 40 × 16 |
  | Poster | cincin | `px-rings.svg` | 32 × 32 |
  | Pesan | zigzag | `px-zigzag.svg` | 24 × 14 |
  | Video | segitiga | `px-triangles.svg` | 28 × 28 |

  File ada di `public/assets/img/patterns/`. Kata "pattern" tetap tidak
  muncul di HTML (test Stage 16).
- Kepala **Visi** dipindah keluar dari `.px-visi__panel`; kutipan visi
  berada di tengah panel.

### 4.4 Poster kampanye

```
figure.px-poster__figure
  div.px-poster__frame                      bingkai seukuran poster (bayangan, naik 3 px saat disorot)
    img.px-poster__img[data-px-zoom]
    a.px-poster__tool--download[download]   kiri bawah, ikon download
    a.px-poster__tool--full[data-px-zoom]   kanan bawah, ikon expand → lightbox (tanpa JS: file poster)
  figcaption.visually-hidden
```

- Tombol ikon 44 px, lingkaran gelap transparan + blur, tanpa teks
  terlihat. Label untuk pembaca layar: "Unduh poster kampanye pasangan 0X"
  dan "Lihat poster kampanye pasangan 0X layar penuh".
- Nama file unduhan: `CandidateTheme::posterDownload()` →
  `POSTER_DOWNLOAD` (`paslon-osis-no-%s-spensada-2026`) + ekstensi file asli
  (jpg/jpeg/png/webp, lainnya jpg). Nomor dua digit sesuai label "Paslon
  02". Tersedia di `present()['poster_download']`.
- Atribut `download` berlaku karena poster satu asal dengan halaman
  (`app.baseURL` harus sama dengan alamat yang dibuka, README bagian 5).
- Dihapus: `.px-poster__bar`, tombol "Lihat fullscreen", `.px-btn--dark`.

### 4.5 Pesan dari kami & video

- `.px-quote__text p`: `font-style: italic; font-weight: 400` (dulu 600).
- Avatar dipindah ke `figcaption.px-quote__who`:
  `flex, align-items: center` = avatar 52 px + `span.px-quote__id`
  (nama + peran). Kartu `align-content: space-between`, jadi nama selalu
  di bawah.
- Video: `.px-video__bar` (keterangan + "Buka di YouTube") dan
  `.px-btn--quiet` dihapus; tinggal pemutar (thumbnail asli + tombol putar,
  tanpa JS = tautan ke YouTube).

Batasan test lama tetap dijaga:
- tidak ada `border…solid` di `paslon.css` (garis tipis lewat box-shadow);
- aturan penutup (`.px-close/.px-finale/.px-others`) tanpa warna aksen;
- `home.css` tanpa `gradient(`.

## 5. Konfigurasi (`app/Config/Visitors.php`, `.env` `visitors.*`)

| Properti | Bawaan | Fungsi |
|---|---|---|
| `$counterRefreshSeconds` | `1` | **baru**: irama pembaruan angka online beranda & papan angka admin (min 1) |
| `$countCacheSeconds` | `1` (dulu `5`) | cache angka online; dibuang otomatis saat ada pengunjung baru/kembali/berganti akun |

## 6. Route

| Method | URL | Akses |
|---|---|---|
| GET | `kunjungan/online` | publik; `{online:{total,guests,users}, interval}`, no-store, tanpa identitas |
| GET | `admin/pengunjung/ringkas` | adminauth; fragmen papan angka (HTML), no-store |

`RoleMatrixTest`: `kunjungan/online` ditambahkan ke daftar route GET publik.

## 7. Test

Baru: `tests/feature/StageTwentyOneRefinementTest.php` (17 test):
- endpoint online:
  - hanya angka, no-store, tidak menulis/mencatat kunjungan;
  - 404 bila angka publik atau fitur mati;
- atribut presence & loop GET di `presence.js`;
- cache angka dibuang saat pengunjung baru dan saat login di browser yang
  sama;
- fragmen papan angka admin: khusus admin, partial sama dengan halaman,
  `data-visitors-tick="1"`, teks keterangan, `admin-visitors.js`;
- beranda HP: turnout 0.7rem, dock tanpa jadwal satu baris, ikon biru muda,
  teks Online tersembunyi visual;
- foto pasangan ukuran penuh:
  - tombol, dialog di luar scene, monogram + keterangan, foto berdua utuh;
  - tamu tanpa tautan; siswa/guru/admin dengan tautan masing-masing;
  - paslon nonaktif yang dihitung tanpa tautan pemilih (admin tetap);
  - mode teaser;
- poster:
  - ikon unduh/layar penuh tanpa teks terlihat, tombol lama hilang;
  - nama file unduhan (dua digit, ekstensi, `present()`);
- kepala bagian: tanpa ikon, rata tengah, pita selebar layar, 7 pola
  berbeda, file pola ada, ikon Stage 16 dihapus;
- kutipan miring 400 dengan avatar di figcaption;
- video tanpa bilah;
- navigasi gelap tanpa titik, rail sticky 100vh di kanan, progres baca,
  shell tanpa `max-width`.

Diperbarui:
- `PaslonProfileTest`: kepala bagian tanpa ikon dengan modifier
  `px-head--{key}`;
- `StageNineteenRefinementTest`: label Online berikon;
- `RoleMatrixTest`.

Hasil: **516 test, 4.863 assertion, lulus** (PHP 8.3.6, MariaDB 10.11.14).
Mode production (minify Stage 20) juga diuji: `php spark osis:minify`,
`node --check` untuk `presence`, `home`, `paslon`, dan `admin-visitors`
`.min.js`, lalu beranda, profil, dan admin di browser memakai aset `.min`
tanpa galat konsol. Yang diperiksa: baca angka tiap detik, dialog foto,
unduhan poster, dan papan angka admin.

Uji browser (Playwright/Chromium, `spark serve`, data seeder + gambar uji:
poster, banner, foto berdua 01, foto ketua & wakil 02, 03 tanpa foto, video
01):
- beranda desktop 1440 × 900 + iPhone 13:
  - `kunjungan/online` terbaca tiap ±1 detik, berhenti saat tab tersembunyi;
  - angka online naik dari 1 ke 2 sekitar 2 detik setelah browser kedua
    membuka beranda;
  - HP: `.turnout__count` 11,2 px, `.dock__meta` tersembunyi (dock 41 px),
    ikon online `rgb(125, 211, 252)`;
- foto pasangan:
  - foto berdua, ketua+wakil, dan monogram terbuka di dialog;
  - Escape dan klik latar menutup, scene tetap di "perolehan";
  - tautan profil: siswa → `siswa/paslon/0X` (diklik, sampai di profil),
    guru → `guru/paslon/0X`, admin → `admin/paslon/{id}/intip`, tamu tanpa
    tautan;
- `/admin/pengunjung`:
  - fragmen terbaca 5 kali dalam ±5 detik, jam berganti tiap detik;
  - "Online sekarang" naik 4 → 5 begitu browser lain membuka situs;
- `siswa/paslon/01`:
  - lebar penuh tanpa gulir horizontal (1440 & 390 px);
  - panel navigasi gelap 200 × 404 px di kanan, tetap di tengah layar saat
    digulir sampai video, progres 0,96, hilang bersama kolom isi di
    penutup; bar gelap menempel di HP;
  - 7 pola kepala berbeda; kutipan italic 400; tanpa titik, ikon kepala, dan
    bilah video;
  - unduhan poster bernama `paslon-osis-no-01-spensada-2026.webp`, ikon
    layar penuh membuka lightbox;
- pratinjau admin `admin/paslon/2/intip` sama;
- konsol tanpa error JavaScript (satu-satunya galat: thumbnail YouTube
  diblokir proxy lingkungan uji).

Screenshot:
- `shots/beranda-online-ikon-hp.png`
- `shots/beranda-foto-penuh-desktop.png`
- `shots/beranda-foto-penuh-hp.png`
- `shots/admin-pengunjung-tiap-detik.png`
- `shots/paslon-lebar-desktop.png`
- `shots/paslon-kepala-pola-desktop.png`
- `shots/paslon-poster-ikon.png`
- `shots/paslon-pesan-hp.png`

Semua gambar paslon pada screenshot adalah gambar uji.
