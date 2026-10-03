# Stage 26 — Avatar Pemilih & Living Election Wall (hasil implementasi + handoff)

Dokumen ini adalah kontrak aktual Stage 26 (Tahap 2 rencana
`docs/spesifikasi/11-ADMIN-HERO-TEXT-LIVING-ELECTION-WALL.md` bagian 4).
Dibaca bersama `00-MASTER-PROJECT.md`, `STAGE1-NOTES.md` s.d.
`STAGE24-NOTES.md` (dan `STAGE25-NOTES.md` setelah Tahap 1 di-merge), serta
`README.md`.

**Catatan urutan kerja.** Tahap 1 (Stage 25) dikerjakan paralel di branch lain
dan belum di-merge saat Stage 26 dibangun, jadi Stage 26 tidak memakai kode
Tahap 1 dan tidak menyentuh area milik Tahap 1 (halaman tambah data/impor,
pengunjung & PDF, teks halaman utama, reset total, `vote-card__text`, ikon
`.site-nav__action`, Title Case). **Bagian 4.6 (reset total menghapus avatar)
ditunda** sampai Tahap 1 di-merge ke `main` (bagian 7 di bawah).

Ringkasan Stage 26:

- pemilih (siswa & guru) punya **avatar**: unggah foto (dipotong bulat di
  browser, dikompres, di-encode ulang server 320 × 320 WebP) atau **avatar
  acak** DiceBear 9.4.2 (Adventurer, Avataaars, Big Smile); tanpa keduanya
  tampil avatar bawaan yang deterministik (HMAC, tanpa menulis database);
- **Living Election Wall** (`/partisipasi`): galeri publik nama + avatar per
  rombel dan guru yang menandai siapa yang **sudah berpartisipasi**, tanpa
  NISN/NIP, waktu, maupun pilihan; diperbarui tiap 20 detik;
- tautan **Living Election Wall** di footer scene perolehan suara beranda
  (desktop sebaris angka online, HP tepat di bawahnya);
- admin dapat **menghapus foto avatar** yang tidak pantas (audit
  `AVATAR_REMOVE`); menghapus pemilih ikut menghapus fotonya.

Satu migration baru, 5 route pemilih (POST), 2 route publik (GET), 2 route
admin (POST); dependensi baru hanya vendor DiceBear lokal (tanpa CDN).

## 1. Data avatar (4.1)

- Migration `2026-10-03-000001_AddAvatarToVoters`: `students` **dan**
  `teachers` mendapat `avatar_file VARCHAR(80)`, `avatar_style VARCHAR(20)`,
  `avatar_seed VARCHAR(32)`, `avatar_updated_at DATETIME` (semua NULL). Kolom
  ini **tidak** ada di `allowedFields` `StudentModel`/`TeacherModel`, jadi
  impor dan form admin (`VoterEditor`) tidak dapat menyentuhnya; satu-satunya
  penulis adalah `App\Libraries\VoterAvatars`.
- Folder `public/uploads/avatars/` (+ `.gitkeep`; `.gitignore`:
  `/public/uploads/avatars/*`, `!/public/uploads/avatars/.gitkeep`).
  `public/uploads/.htaccess` (hanya jpg/png/webp bernama aman, tanpa eksekusi,
  `nosniff`, CSP `sandbox`) otomatis berlaku. `php spark osis:check` kini juga
  memeriksa folder ini dapat ditulis.
- **Avatar bawaan** `App\Libraries\AvatarIdentity::default(VoterType, id)`:
  `hash = HMAC-SHA256(kunci, "s:{id}" | "t:{id}")`, seed = 16 heksa pertama,
  gaya = salah satu dari tiga gaya dari 4 heksa berikutnya. Stabil di setiap
  muat ulang, tidak dapat dibalik menjadi id tanpa kunci.
  **Kunci rahasia:** `encryption.key` di `.env` bila diisi; bila tidak, file
  acak `writable/avatar.key` (64 heksa, mode 0600) dibuat sekali otomatis.
  File ini di-`.gitignore` dan tidak dapat dibuka browser (`writable/` di luar
  `public/`). Bila file hilang (instalasi baru tanpa backup), avatar bawaan
  berganti rupa; foto dan avatar pilihan tidak terpengaruh.

## 2. Unggah foto & avatar acak di dasbor (4.2)

### Kartu avatar

`app/Views/partials/avatar_card.php` di dalam `aside.dash-aside` setelah
panel **Jadwal pemilihan** (aside kini berisi dua `section`, masing-masing
berjudul). Di HP kartu berada di bawah kartu status, jadi tombol memilih tetap
yang pertama; jam melayang tetap elemen terakhir `<main>`; setelah memilih
dasbor tetap tanpa tautan `coblos` (test Stage 2/8/10 tetap lulus).

- avatar besar dalam lingkaran (168 px) di atas cincin tipis; lencana
  centang (sama dengan wall) muncul setelah pemilih berpartisipasi;
- tombol **Unggah foto** (ikon `upload`), **Acak avatar** (`refresh`),
  **Pakai avatar ini** + **Batal** (setelah mengacak), **Hapus foto** (hanya
  bila ada foto);
- keterangan "Foto atau avatar ini akan dipakai di **Living Election Wall**.
  Wall hanya menandai partisipasi, tidak pernah menampilkan pilihanmu." (guru:
  "pilihan Anda") + atribusi DiceBear (CC BY 4.0);
- tidak dikunci status pemilihan; tidak tampil bila `homepage.electionWall =
  false`;
- "3D" cukup CSS: `perspective`, lapisan cincin / avatar / lencana di
  `translateZ` berbeda, miring maks. 6° mengikuti pointer halus
  (`(hover: hover) and (pointer: fine)`), tanpa gerak saat reduced-motion.

### Alur unggah (browser, `dashboard-avatar.js`)

`<input type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif">`
→ maks. 15 MB → `createImageBitmap(file, { imageOrientation: 'from-image' })`
(cadangan `<img>`) → `<dialog>` potong: masker lingkaran, geser
(pointer/sentuh), zoom 1–3× (penggeser, tombol +/−, roda mouse, cubit dua jari),
panah & +/- di keyboard → kanvas 512 × 512 → `toBlob('image/webp', 0.85)`
(cadangan `image/jpeg` 0.88) → `fetch` POST multipart dengan `X-CSRF-TOKEN` →
avatar di halaman langsung berganti. Foto HEIC yang tidak dapat dibaca browser
mendapat pesan jelas. Tanpa JavaScript (atau bila modul gagal dimuat, kelas
`.is-fallback`): input file biasa + **Kirim foto**, server memotong persegi di
tengah; **Acak avatar** diacak server.

`avatar-art.js` dimuat lewat `import()` dari URL ber-versi
(`data-art-module` = `asset_url(...)`) agar tidak tertahan cache 30 hari.
Formulir yang ditangani skrip memanggil `stopPropagation()` supaya penjaga
submit ganda `app.js` tidak menonaktifkan tombolnya.

### Server `App\Libraries\VoterAvatars`

- maks. 2 MB (dan ≤ `upload_max_filesize`); ekstensi + MIME isi file
  (`finfo`) + `getimagesize()` harus cocok (jpeg/png/webp); sisi terpendek
  min. 128 px; maks. 8000 px per sisi & 40 MP; memori GD diperkirakan dulu;
- orientasi EXIF diluruskan, dipotong persegi di tengah, diperkecil ke
  **320 × 320** di atas latar terang (PNG transparan tidak menjadi hitam),
  di-encode ulang **WebP q80** (JPEG q85 bila GD tanpa WebP); metadata hilang;
- nama acak `s{id}-{16hex}.webp` / `t{id}-…`, ditulis ke file sementara lalu
  `rename`, `chmod 0644`; baris pemilih dikunci `FOR UPDATE` dalam
  transaction; file lama dihapus setelah commit; `avatar_style`/`avatar_seed`
  dikosongkan;
- **10 perubahan per 10 menit per pemilih** (Throttler, kunci
  `avatar_{student|teacher}_{id}`; setiap POST avatar memakai satu jatah);
  lebih dari itu 429 + `Retry-After`.
- Pemrosesan gambar bersama diekstrak ke `App\Libraries\ImageProcessor`
  (deteksi isi, decode, orientasi EXIF, memori, satuan ukuran);
  `CandidateAssets` memakainya tanpa mengubah API maupun pesannya.

### Route (grup `siswa` / `guru`, filter `studentauth` / `teacherauth`)

| Route | Fungsi |
|---|---|
| `POST siswa/avatar`, `POST guru/avatar` | unggah foto (multipart `avatar`) |
| `POST siswa/avatar/acak`, `POST guru/avatar/acak` | simpan avatar DiceBear (`style` ∈ adventurer/avataaars/big-smile, `seed` = `/^[a-f0-9]{16}$/` dari `crypto.getRandomValues`); kosong = diacak server; foto dihapus |
| `POST siswa/avatar/hapus`, `POST guru/avatar/hapus` | kembali ke avatar bawaan |

Controller `App\Controllers\AvatarController` (+ `Student\`/`Teacher\` yang
hanya menetapkan jenis pemilih; identitas selalu dari sesi). Balasan JSON
(`{status, message, avatar}`) untuk fetch, redirect `siswa#avatar` + pesan
untuk formulir biasa.

### DiceBear (vendor lokal)

`public/assets/vendor/dicebear-9.4.2/dicebear.module.js` (satu modul ES,
±446 KB, gzip ±173 KB, dimuat hanya di dasbor, wall, dan detail pemilih admin)
+ `LICENSE` (gabungan lisensi keempat paket). Perintah bundel (sekali, di
folder kerja sementara, bukan di proyek):

```
npm init -y
npm install @dicebear/core@9.4.2 @dicebear/adventurer@9.4.2 @dicebear/avataaars@9.4.2 @dicebear/big-smile@9.4.2 esbuild@0.25
cat > entry.js <<'EOF'
export { createAvatar } from '@dicebear/core';
export * as adventurer from '@dicebear/adventurer';
export * as avataaars from '@dicebear/avataaars';
export * as bigSmile from '@dicebear/big-smile';
EOF
npx esbuild entry.js --bundle --format=esm --minify --target=es2019 --legal-comments=inline \
  --banner:js="/*! DiceBear 9.4.2 (...) - lihat LICENSE */" --outfile=dicebear.module.js
```

(esbuild 0.25.12; DiceBear tanpa dependensi runtime lain.) Lisensi: kode
MIT (Florian Körner); desain **Adventurer** (Lisa Wischofsky) dan **Big Smile**
(Ashley Seo) **CC BY 4.0, wajib atribusi** — atribusi tampil di kartu avatar
dasbor dan kaki halaman wall; **Avataaars** (Pablo Stanley) "free for personal
and commercial use".

`resources/assets/js/avatar-art.js` (modul bersama): `avatarDataUri({style,
seed})` dengan cache memori, latar bulat dari palet lembut berkroma rendah
(bukan warna paslon), `randomAvatar()`, `paint()`, `hydrate()` (malas lewat
IntersectionObserver; otomatis saat modul dimuat). Opsi gaya dibatasi agar
cocok untuk sekolah: ekspresi ramah (tanpa mata silang/menangis/marah), tanpa
kumis/jenggot, tanpa aksesori kostum.

## 3. Moderasi admin

`admin/voters/show.php` menampilkan panel **Avatar Living Election Wall**
(avatar + jenis: foto unggahan / avatar pilihan / avatar bawaan). Bila ada
foto: tombol **Hapus foto avatar** (POST `admin/{siswa,guru}/{id}/avatar/hapus`,
`data-confirm-danger`) → pemilih kembali ke avatar bawaan, audit baru
`AVATAR_REMOVE` "Hapus foto avatar pemilih". Tidak dikunci saat pemilihan
selesai (tidak memengaruhi hasil). `VoterController::delete()` ikut menghapus
file foto pemilih yang dihapus.

## 4. Living Election Wall (4.3)

### Route & konfigurasi

| Route | Isi |
|---|---|
| `GET partisipasi` | halaman (`WallController::index`) |
| `GET partisipasi/status` | JSON `{status, label, notice, totals, voted: ["s12", ...], poll: {interval}}`, `Cache-Control: no-store` |

Keduanya 404 bila `Config\Homepage::$electionWall = false` (bawaan `true`,
`.env`: `homepage.electionWall`). `$wallPollSeconds = 20` (min. 10, 0 = mati;
interval 0 saat FINISHED atau tanpa jadwal), `$wallCacheSeconds = 5` (cache
server per id + status pemilihan). Keduanya terdaftar di
`RoleMatrixTest::PUBLIC_ROUTES`; `VisitorTracker::PAGES['partisipasi']` =
"Living Election Wall".

### Data `App\Services\ParticipationWall`

Definisi sama dengan `AnalyticsService`: pemilih aktif (`status_aktif = 1`),
berpartisipasi = punya suara `LOCKED` pada pemilihan berjalan; jadi total,
sudah, dan belum di wall selalu sama dengan dasbor admin (diuji). Kelompok per
rombel (`Grade::normalizeKelas`, urut jenjang lalu natural lewat
`AnalyticsService::compareClass` yang kini publik; rombel tanpa jenjang =
"Lainnya" sebelum Guru), lalu kelompok **Guru**. Anggota **hanya**: kunci
(`s{id}`/`t{id}`), nama, avatar (URL foto atau gaya + seed), sudah/belum.
**Tidak pernah:** NISN/NIP, jenis kelamin, nomor absen, waktu memilih,
perangkat, paslon, maupun urutan siapa yang terakhir memilih (daftar `voted`
urut kunci, bukan waktu).

### Tata letak (`app/Views/wall/index.php`, `wall.css`, `wall.js`)

Layout `main.php` dengan flag `wallNav` → `partials/wall_nav.php`,
`body.page-wall`, latar gelap (token `--night*`/`--on-night*` sama dengan
beranda), `theme-color` gelap.

1. **Header sticky** 60 px: kiri ← (ke `/#perolehan`), tengah lockup
   PILKETOS (`logoOnDark`), kanan ikon dasbor (siswa → `siswa`, guru →
   `guru`, admin → `admin`; tamu → `/#masuk` dengan teks **Masuk**).
2. **Blok partisipasi**: kicker JetBrains Mono kapital "PARTISIPASI
   PEMILIHAN", angka Newsreader besar (`tabular-nums`), "dari 256 siswa"
   (kata benda mengikuti filter: pemilih / siswa / siswa 7A / guru), bar 2 px +
   persen (desimal koma), "218 sudah berpartisipasi / 38 belum berpartisipasi",
   baris miring "Halaman ini hanya menandai partisipasi. Pilihan setiap pemilih
   tetap rahasia.", dan pil status ("Belum dijadwalkan", "Pemilihan dibuka 1
   Okt 2026, 07.00 WIB", "Berlangsung hingga …", "Pemilihan selesai …").
3. **Toolbar sticky** di bawah header: cari nama (langsung saat mengetik, tanpa
   membedakan huruf besar & diakritik; jumlah hasil `aria-live` di dalam kolom
   cari), **Kelas** (Semua, Siswa, grup Kelas 7/8/9/Lainnya berisi rombel,
   Guru), **Urutkan** (Nama A–Z, Sudah berpartisipasi, Belum
   berpartisipasi = yang sudah/belum lebih dulu; label spesifikasi "... dulu"
   dipendekkan agar muat di HP), **Buka semua / Tutup semua**. Filter di query URL
   (`?kelas=7A&q=...&urut=sudah`); tanpa JavaScript toolbar adalah formulir GET
   yang disaring server (tombol **Terapkan**).
4. **Bagian per rombel**: `<details open>` dengan `summary` (kicker "Kelas 7",
   rombel Newsreader, "28/32" + garis progres, chevron), pemisah jenjang
   ("KELAS 7 —— 3 ROMBEL"), bagian Guru di akhir. Bagian yang ditutup diingat
   di `sessionStorage` (`osis2026.wall.closed`, hanya nama rombel).
5. **Kartu** (galeri): avatar bulat 112 px desktop / 84 px HP + nama (maks. 2
   baris). Belum: avatar `saturate(.6) brightness(.85)`, opasitas .85, tanpa
   tanda. Sudah: lencana centang bulat monokrom + cahaya sangat halus. Teks
   tersembunyi "Sudah/Belum berpartisipasi". Tanpa warna paslon.
6. **Gerak mikro**: kartu muncul bertahap 70 ms (maks. 12 per bagian) dari
   sedikit kedalaman, angka bergulir 800 ms, bar terisi, miring 3D maks. 8°
   saat diarahkan (pointer halus, satu listener terdelegasi), lencana tergambar
   + satu denyut cahaya saat status berubah, buka/tutup halus
   (`::details-content`, Chromium 131+; browser lain langsung).
   Reduced-motion: semua langsung.
7. **Pembaruan langsung**: polling tiap 20 detik selama belum selesai & tab
   terlihat; kartu hanya pindah bila urutan = status (FLIP 260 ms); unlock
   (sudah → belum) diam-diam.
8. **Kinerja**: kartu dirender server (avatar DiceBear = inisial tanpa
   JavaScript, foto tetap tampil); avatar DiceBear dibuat malas
   (IntersectionObserver + cache, ±0,2 ms/avatar); foto `loading="lazy"`,
   `decoding="async"`, `width/height`; `content-visibility: auto` per bagian
   dengan perkiraan tinggi dari jumlah kartu. Terukur: 263 siswa + 18 guru,
   halaman dimuat ±250 ms di localhost.

## 5. Privasi (4.4) & Known limitations

- `App\Services\PublicLiveCount`, `GET hitung-suara`, dan scene perolehan
  suara **tidak diubah** (keputusan pemilik proyek, bagian 1 butir 1):
  persentase per paslon tetap tampil & diperbarui selama pemilihan
  berlangsung (diuji ulang di `ElectionWallTest`).
- Wall tidak pernah memuat pilihan, waktu memilih, maupun urutan siapa yang
  terakhir berpartisipasi.
- **Known limitations:**
  - gabungan wall + perolehan live membuat pilihan seseorang **kadang dapat
    ditebak** saat pemilih sepi (mis. hanya satu kartu berubah menjadi "sudah"
    di antara dua pembaruan dan persentase satu paslon naik). Diterima pemilik
    proyek. Bila aturan sekolah berubah: `homepage.publicLiveCount = false`
    (Stage 5) atau `homepage.electionWall = false`;
  - kunci wall `s{id}`/`t{id}` memperlihatkan id internal (urutan impor);
  - wall membuka nama & status partisipasi ke publik di jaringan sekolah;
    foto yang tidak pantas dihapus admin (bagian 3);
  - DiceBear menambah ±173 KB (gzip) saat pertama dibuka (di-cache 30 hari).

## 6. Tautan dari scene perolehan suara (4.5)

`partials/footer.php`: `p.site-footer__online` dan tautan baru
`a.site-footer__wall` ("Living Election Wall" + ikon `arrow-up-right`)
dibungkus `div.site-footer__row`, hanya di footer scene beranda dan hanya bila
wall aktif (bila angka online dimatikan, baris berisi tautan saja).
Desktop (> 767 px): sebaris dengan angka online, dipisah garis tipis, gaya
mono kecil yang sama; HP: tepat di bawah baris online, rata tengah.

## 7. Reset total diperluas (4.6) — DITUNDA

Menunggu Tahap 1 (Stage 25, `App\Services\TotalReset` + halaman `/admin/reset`)
di-merge ke `main`. Rencana setelah merge: merge `main` ke branch ini (tanpa
rebase), selesaikan konflik di file sumber, bangun ulang aset dengan
`php spark osis:minify` (file ter-minify tidak digabung manual), lalu
`TotalReset` ikut mengosongkan `public/uploads/avatars/` (kecuali `.gitkeep`,
hanya nama file avatar yang sah — `VoterAvatars::isFileName()`), halaman reset
menampilkan jumlah foto avatar yang akan dihapus, dan seluruh test dijalankan
ulang. Kolom avatar ikut kosong karena tabel `students`/`teachers`
di-TRUNCATE.

## 8. File

Baru:

```
app/Database/Migrations/2026-10-03-000001_AddAvatarToVoters.php
app/Libraries/AvatarIdentity.php, AvatarException.php, VoterAvatars.php, ImageProcessor.php
app/Services/ParticipationWall.php
app/Controllers/AvatarController.php, Student/AvatarController.php, Teacher/AvatarController.php, WallController.php
app/Views/partials/avatar.php, avatar_card.php, wall_nav.php
app/Views/wall/index.php
resources/assets/css/avatar.css, wall.css
resources/assets/js/avatar-art.js, dashboard-avatar.js, wall.js   (+ hasil build di public/assets)
public/assets/vendor/dicebear-9.4.2/dicebear.module.js, LICENSE
public/uploads/avatars/.gitkeep
tests/unit/AvatarIdentityTest.php, tests/feature/VoterAvatarTest.php, tests/feature/ElectionWallTest.php
```

Diubah: `Config/Routes.php`, `Config/Homepage.php`, `Config/Services.php`,
`Controllers/Admin/VoterController.php`, `Controllers/{Student,Teacher}/DashboardController.php`,
`Helpers/app_helper.php` (ikon `minus`), `Libraries/CandidateAssets.php`
(memakai `ImageProcessor`), `Libraries/SystemCheck.php` (folder avatar),
`Models/AuditLogModel.php` (`AVATAR_REMOVE`), `Services/AnalyticsService.php`
(`compareClass` publik), `Services/VisitorTracker.php`,
`Views/{student,teacher}/dashboard.php`, `Views/admin/voters/show.php`,
`Views/layouts/main.php` (`wallNav`), `Views/partials/footer.php`,
`resources/assets/css/home.css`, `.gitignore`.

## 9. Test & uji browser

Baru: `AvatarIdentityTest` (6), `VoterAvatarTest` (17), `ElectionWallTest`
(14). Diperbarui: `RoleMatrixTest` (route publik), `StageNineteenRefinementTest`
(baris footer), `BuiltAssetsTest` (27 sumber CSS/JS).

Hasil: **596 test, 6.061 assertion, lulus** (PHP 8.3.6, MariaDB 10.11.14;
Stage 24: 559 test). `php spark osis:minify` tanpa sisa build,
`php spark osis:check` 0 gagal (21 OK; peringatan hanya karena container
development: `CI_ENVIRONMENT`, `baseURL`, php.ini, akun & data contoh).

Uji browser (Playwright Chromium 1440×900 & 390×844, 0 galat konsol / CSP /
HTTP 4xx-5xx): wall (atas, galeri, semua tertutup, pencarian, filter + urut,
sessionStorage), pembaruan langsung (suara baru di database → kartu berubah
"sudah" dengan animasi lencana setelah polling, angka ikut naik; unlock →
kembali "belum" tanpa animasi), reduced-motion (tanpa animasi, angka langsung), tanpa
JavaScript (wall terbaca, filter dari URL, tombol Terapkan), dasbor (acak →
pakai, potong → unggah, hapus foto, kemiringan), beranda (tautan footer
desktop & HP), admin (panel avatar + konfirmasi hapus foto). Screenshot:
`shots/stage26-*.png`.

## 10. Deploy

```
git pull
php spark migrate        (kolom avatar di students & teachers)
php spark osis:check     (termasuk folder public/uploads/avatars dapat ditulis)
```

Backup kini juga mencakup `public/uploads/avatars/` dan `writable/avatar.key`
(README bagian 17). Wall dapat dimatikan dengan `homepage.electionWall = false`.

## 11. Handoff

- Setelah Tahap 1 di-merge: kerjakan bagian 7 (4.6) di branch ini.
- Ukuran, gaya, dan opsi avatar dipusatkan di `VoterAvatars` (server) dan
  `avatar-art.js` (browser); gaya baru = tambah ke `AvatarIdentity::STYLES`,
  bundel ulang DiceBear, dan tambah entri `STYLES` di `avatar-art.js`.
- Data wall hanya lewat `ParticipationWall`; jangan menambah kolom pemilih ke
  query wall tanpa menimbang privasi (test `ElectionWallTest` memeriksa NISN,
  NIP, jenis kelamin, waktu, perangkat, dan nama paslon tidak bocor).
