# Stage 15 — Batas Pasangan Calon Diatur Admin & Halaman Profil Paslon (hasil implementasi + handoff)

Dokumen ini adalah kontrak aktual Stage 15. Dibaca bersama
`00-MASTER-PROJECT.md`, `STAGE1-NOTES.md` s.d. `STAGE14-NOTES.md`, dan
`README.md`.

Ringkasan Stage 15 (permintaan revisi):

- **batas pasangan**: jumlah pasangan calon aktif tidak lagi "dirancang untuk
  3", tetapi diatur admin di halaman **Pasangan calon** (2–10, bawaan 3, per
  pemilihan). Bilik suara, surat suara, dan pemeriksaan server menyesuaikan
  berapa pun jumlahnya;
- **halaman profil paslon**: `/siswa/paslon/0X` dan `/guru/paslon/0X`,
  redesain editorial yang menampilkan Ketua × Wakil sebagai satu tim (pembuka,
  Kenali pasangan, Visi, Misi, Program, Poster & video, Pesan + penutup,
  navigasi bagian). Pratinjau admin memakai partial yang sama;
- **field profil & kampanye** baru: slogan, bio, kata karakter, program
  unggulan, pesan, dan video YouTube (hanya ID);
- **bilik suara ringkas**: bab visi-misi per pasangan dipindah ke halaman
  profil; bilik suara = pembuka → Sekilas paslon → surat suara.

Dua migration baru; tanpa dependensi atau framework baru.

"HP" = lebar < 720 px (sama dengan Stage 8–14).

## 1. Schema

| Migration | Isi |
|---|---|
| `2026-09-25-000001_AddProfileToCandidates` | kolom `candidates`: `slogan` VARCHAR(160), `bio_ketua` / `bio_wakil` TEXT, `karakter_ketua` / `karakter_wakil` VARCHAR(120), `program` TEXT, `pesan_ketua` / `pesan_wakil` VARCHAR(300), `video_youtube` VARCHAR(11). Semua nullable. `CHECK chk_candidates_video_youtube`: NULL atau `^[A-Za-z0-9_-]{11}$` (hanya ID video, bukan URL bebas) |
| `2026-09-25-000002_AddBatasPasanganToElections` | `elections.batas_pasangan` TINYINT UNSIGNED NOT NULL DEFAULT 3, `CHECK chk_elections_batas_pasangan` BETWEEN 2 AND 10 |

Validasi model (`CandidateModel`): slogan ≤ 160, bio ≤ 600, karakter ≤ 120,
program ≤ 3.000, pesan ≤ 300 karakter, video = regex ID. `ElectionModel`:
konstanta `BATAS_PASANGAN_DEFAULT = 3`, `_MIN = 2`, `_MAX = 10`, dan
`ElectionModel::batasPasangan(?array $election)` (bawaan 3 bila belum ada
pemilihan / kolom belum dimigrasi).

## 2. Batas pasangan aktif

| Bagian | Perilaku |
|---|---|
| `POST admin/paslon/batas` (`CandidateController::limit`) | angka 2–10; ditolak bila lebih kecil dari jumlah pasangan **aktif** saat ini ("Saat ini ada X pasangan aktif…"), bila pemilihan sudah selesai (hasil dikunci), atau bila jadwal pemilihan belum dibuat. Tercatat di audit log `CANDIDATE_LIMIT` ("Ubah batas pasangan calon") |
| simpan pasangan (`save()`) | hanya saat pasangan **baru diaktifkan** (tambah aktif, nonaktif → aktif): ditolak bila pasangan aktif lain ≥ batas; kesalahan di field `status_aktif`, dilaporkan bersama kesalahan file (satu kali kirim). Mengubah pasangan yang sudah aktif selalu boleh (termasuk data lama yang melebihi batas) |
| tambah pasangan (`new()`) | bila batas penuh, form terisi **nonaktif** + keterangan "Batas N pasangan aktif sudah penuh" |
| `admin/candidates/index.php` | notice "dirancang untuk 3 pasangan" diganti panel **Batas pasangan aktif**: "X dari N pasangan aktif", deret kotak (terisi = aktif, putus-putus merah = melebihi batas), keterangan sisa tempat, form ubah batas. Terkunci setelah pemilihan selesai; tanpa jadwal menampilkan tautan ke Jadwal |
| `SystemCheck` (`php spark osis:check`) | OK bila 2 ≤ aktif ≤ batas, selain itu PERINGATAN "X pasangan (batas N)" |
| grid N pasangan | `CandidateTheme::columns($n, $max = 4)` = baris sesedikit mungkin dengan isi serata mungkin (5 → 3+2, 7 → 4+3, 9 → 3+3+3); `gridStyle()` = `--cols` (≥ 1100 px) + `--cols-md` (720–1099 px, maks. 3). Dipakai Sekilas paslon (grid) dan surat suara (flex: kotak sama lebar, baris terakhir rata tengah, garis kotak = outline 2px agar tetap 2px di baris mana pun). `ballot.js` tidak berubah |
| teks | "ketiga pasangan" / "ketiganya" → "semua pasangan" (dasbor pemilih, journey, Sekilas paslon) |

## 3. Halaman profil paslon

Route (grup `studentauth` / `teacherauth` yang sudah ada):
`GET siswa/paslon/(:num)`, `GET guru/paslon/(:num)` →
`VotingController::candidate()`. Nomor urut "01" atau "1"; hanya pasangan
**aktif**, selain itu 404. Tetap dapat dibuka setelah memilih (tanpa tombol
pilih, tanpa data suara).

File: `app/Views/paslon/show.php` (pemilih), `app/Views/paslon/partials/profile.php`
(isi, dipakai juga `admin/candidates/preview.php`),
`app/Views/paslon/partials/avatar.php`, `public/assets/css/paslon.css`,
`public/assets/js/paslon.js`. Potret memakai `voting/partials/portraits.php`
(ukuran baru `xl`, `$rolePrefix` "Calon ", `$priority`).

| Bagian (id) | Isi |
|---|---|
| pembuka | nomor urut raksasa sebagai tipografi latar (parallax halus), label `Paslon 01` + nama tema (bila bukan "Pasangan 0X"), duo potret bertumpuk + tanda × (atau foto berdua bila asset hero ada), satu `<h1>`: nama ketua × nama wakil ("dan" untuk pembaca layar) + peran, slogan, tombol "Lihat Visi & Misi ↓". Latar/tekstur/artwork tetap dipakai. Varian mengikuti `theme_layout`: split (kertas + pola), poster (bidang aksen penuh), column (bingkai garis + nomor tegak) |
| navigasi bagian | hanya bagian yang ada. < 1280 px: bar tipis menempel (46 px), bergeser mendatar, tautan aktif digeser ke tengah. ≥ 1280 px: rel vertikal di kanan (titik + garis progres), muncul setelah pembuka; label tampil saat disorot/difokus (≥ 1440 px juga bagian aktif) |
| `#profil` Kenali pasangan | dua profil dalam satu bagian, dipisah × (HP: bertumpuk). Peran, nama, bio, kata karakter (teks mono dipisah titik tengah, bukan pill). Tampil bila ada bio/karakter, atau bila pembuka memakai foto berdua dan foto masing-masing tersedia |
| `#visi` Visi kami | satu pernyataan besar (Newsreader); kata menyala mengikuti scroll (kata yang belum menyala tetap terbaca, gray-500). Visi > 180 karakter memakai ukuran lebih kecil |
| `#misi` Misi kami | daftar editorial bernomor besar, garis tipis, muncul bertahap, hover ringan; semua poin tampil (tanpa buka-tutup) |
| `#program` Program unggulan kami | butir bernomor (judul + penjelasan), dua kolom ≥ 900 px, garis atas tanpa kartu |
| `#media` Poster & video | poster rasio asli (maks. 86svh), bayangan halus, **Lihat fullscreen** → `<dialog>` lightbox (tanpa JS: tautan ke file). Video: facade berwarna aksen + tombol putar; iframe youtube-nocookie baru dibuat setelah diklik; tautan "Buka di YouTube" |
| `#pesan` Pesan dari kami + penutup | bidang aksen penuh: kutipan ketua & wakil (avatar kecil), lalu `Paslon 01` besar, nama × nama, slogan, tombol, dan deret nomor semua paslon (setara, urut nomor, yang dibuka `aria-current="page"`) |

Tombol penutup: pencoblosan dibuka → **Pilih Paslon 0X** (`coblos#coblos-0X`,
kotaknya disorot) + **Kembali ke daftar paslon** (`coblos#sekilas`); sudah
memilih → **Lihat pilihanku**; belum dibuka / sudah ditutup → kembali ke
daftar; pratinjau admin → **Ubah data paslon** + daftar pasangan calon.

Data kosong disembunyikan diam-diam (tanpa tulisan "belum diisi"), begitu juga
butir navigasinya. Struktur sama untuk semua paslon; yang berbeda hanya warna
aksen, asset, dan varian pembuka.

## 4. Keputusan desain (atas usulan awal)

1. **Pembuka di HP tidak ditumpuk vertikal**: dua potret tetap berdampingan
   dan bertumpuk sedikit, nama ketua × wakil satu baris dua kolom, sehingga
   nomor + dua nama + dua peran muat di layar pertama (terverifikasi 390×844).
2. **Setiap foto tampil besar sekali**: pembuka dengan dua potret → "Kenali
   pasangan" & "Pesan" memakai avatar kecil; pembuka dengan foto berdua →
   potret masing-masing tampil di "Kenali pasangan".
3. **Pesan digabung dengan penutup** agar nama tidak diulang empat kali dan
   penutup punya isi; navigasi cukup 6 butir.
4. **Tombol penutup mengikuti status pemilihan** dan deret nomor paslon
   diperlakukan setara (adil untuk semua pasangan).
5. **Tipografi & palet sistem yang ada** (07-TYPOGRAPHY): Newsreader untuk
   nomor/nama/visi/slogan/judul bagian, Plus Jakarta Sans untuk isi & tombol,
   JetBrains Mono untuk label kecil kapital. Kedua nama memakai perlakuan yang
   sama (satu tim). Nama tidak dikapitalkan (aturan §4 dokumen 07).
6. Monogram (foto belum ada): ketua warna aksen, wakil ink (layout poster:
   ketua kertas) agar dua bidang tidak menyatu.

## 5. Keamanan & gerak

- **CSP**: `frame-src 'none'` tetap untuk semua halaman; hanya respons profil /
  pratinjau paslon yang punya video diganti `frame-src
  https://www.youtube-nocookie.com` (`CandidateTheme::allowVideoFrame()`).
- **Permissions-Policy**: `fullscreen=(self "https://www.youtube-nocookie.com")`
  agar tombol layar penuh pemutar video berfungsi (sebelumnya `(self)`).
- **Referrer**: header aplikasi `Referrer-Policy: same-origin` membuat pemutar
  YouTube menolak (error 153), jadi iframe video diberi
  `referrerpolicy="strict-origin-when-cross-origin"` (asal situs saja).
- Tautan YouTube dari admin diubah menjadi ID lewat `CandidateTheme::youtubeId()`
  (watch, youtu.be, shorts, embed, nocookie, atau ID); tautan lain ditolak
  ("Tautan YouTube tidak dikenali"). URL embed selalu dibuat server; `paslon.js`
  hanya menerima awalan `https://www.youtube-nocookie.com/embed/`.
- Semua teks lewat `esc()` / `textContent`; tanpa handler inline.
- Gerak: animasi pembuka dipicu kelas `.js` dari `<head>` (tanpa kedip, tetap
  selesai walau skrip gagal); reveal saat scroll, parallax, dan kata menyala
  hanya dengan `.px-motion` dari `paslon.js`. `prefers-reduced-motion`
  mematikan semuanya; konten langsung tampil.

## 6. Bilik suara & file yang dihapus

- `voting/index.php`: navigasi bab + loop bab dihapus.
- `voting/partials/lineup.php`: "Baca visi & misi" → **Profil lengkap**
  (`<siswa|guru>/paslon/0X`), panah HP → `#surat-suara`, grid `--cols`.
- Dihapus: `voting/partials/chapter.php`; CSS `.chapter*`, `.chapter-nav*`,
  `.vm*`, `.misi*`, `.portraits--lg` di `voting.css`; bagian bab di
  `candidates.js` (tersisa journey + Sekilas paslon). Swatch pratinjau admin
  pindah ke `admin.css` (`.preview-bar__swatch`); bilah pratinjau tidak lagi
  menempel (navigasi bagian profil yang menempel).
- Form admin: bagian baru **03 Profil & kampanye** (Tema visual → 04, Foto &
  asset → 05). Audit mencatat perubahan field baru.
- `CandidateSeeder`: contoh isi profil (pasangan 02 sengaja tanpa bio & pesan).

## 7. Test

Baru:
- `tests/feature/PaslonProfileTest.php` (13 test): wajib login peran yang
  sesuai, 404 nomor tak dikenal / nonaktif, satu `<h1>` Ketua × Wakil, bagian
  & navigasi hanya bila ada data, kelas teks panjang, escape XSS, tombol
  menurut status (dibuka / belum-ditutup / sudah memilih), poster + lightbox,
  video klik-untuk-muat + CSP `frame-src` hanya di halaman ber-video,
  pratinjau admin (termasuk pasangan nonaktif).
- `tests/feature/CandidateLimitTest.php` (10 test): bawaan 3, naikkan batas +
  audit, rentang 2–10 & tidak di bawah jumlah aktif, CHECK database, terkunci
  setelah selesai, tanpa jadwal, tambah/aktifkan melewati batas ditolak (boleh
  sebagai nonaktif), form tambah nonaktif saat penuh, pasangan aktif lama tetap
  dapat diubah, bilik suara 5 pasangan (3+2), `SystemCheck`.
- `AdminCandidateTest`: simpan field profil + tautan YouTube → ID, tautan
  tidak dikenali & teks terlalu panjang ditolak, fieldset "Profil & kampanye".
- `CandidateThemeTest`: field profil di `present()`, `programItems()`,
  `tags()`, `youtubeId()`, `columns()` / `gridStyle()`.

Diperbarui (markup bab → profil): `VotingTest`, `AdminCandidateTest` (fixture
batas 4 untuk pasangan ke-4), `BoothRefinementTest`, `StageNineRefinementTest`,
`StageTenRefinementTest`, `RoleMatrixTest` (`admin/paslon/batas` terlarang
untuk pemilih), `SecurityHardeningTest` (Permissions-Policy). Tidak ada test
yang dilewati.

Hasil: **444 test, 4.084 assertion, lulus** (PHP 8.4.19, MariaDB 10.11.14).

Uji browser (Playwright/Chromium, `spark serve`, data seeder + gambar uji):
profil 01/02/03 pada 390 & 1440 px (dua potret, foto berdua, nama & visi
panjang, data minim, poster + video, varian split/poster/column), rel
navigasi aktif mengikuti scroll, lightbox buka/tutup (Escape), tombol putar
membuat iframe youtube-nocookie dengan `referrerpolicy`, `prefers-reduced-motion`
(konten langsung tampil), panel batas admin 390 & 1440, bilik suara 5
pasangan pada 768 & 1440 (Sekilas paslon & surat suara 3+2). Tanpa gulir
mendatar, tanpa error JavaScript/CSP di konsol.
