# Stage 16 — Redesain Halaman Profil Paslon & Satu Tampilan untuk Semua Pasangan (hasil implementasi + handoff)

Dokumen ini adalah kontrak aktual Stage 16. Dibaca bersama
`00-MASTER-PROJECT.md`, `STAGE1-NOTES.md` s.d. `STAGE15-NOTES.md`, dan
`README.md`.

Ringkasan Stage 16 (permintaan revisi halaman paslon + gambar referensi):

- **pembuka = satu gambar khusus**: foto berdua, artwork, tag "Paslon 01",
  nama tema, nama, latar bergaris, dan nomor raksasa dihapus. Diganti SATU
  gambar landscape buatan paslon (slot baru **Gambar pembuka profil**, tampil
  utuh tanpa dipotong). Tombol **Lihat Visi & Misi** pindah ke bawah gambar;
- **navigasi bagian di kanan**: di desktop (≥ 1024 px) berupa kolom kanan
  dengan **semua label** terlihat sejak awal (menempel saat digulir);
- **video**: selalu memuat thumbnail asli YouTube dengan tombol play;
- **satu tampilan**: pilihan layout per pasangan, latar panggung, artwork, dan
  tekstur/pola dihapus, begitu juga garis dekoratif di halaman paslon;
- **penutup (`px-section px-close`)**: berlatar gambar khusus (slot baru
  **Latar penutup**), tanpa warna aksen;
- selebihnya mengikuti gambar referensi: kepala bagian berikon (kapital +
  subjudul), kartu, pill karakter, Pesan terpisah dari penutup, urutan
  Profil → Visi → Misi → Program → Poster → Pesan → Video → Penutup.

Keputusan pemilik proyek saat perencanaan:

1. data lama (layout, latar panggung, artwork, tekstur) **dihapus bersih**
   lewat migration, termasuk file gambarnya;
2. layout & pola **diseragamkan di semua halaman** (bilik suara, beranda,
   hasil akhir), bukan hanya profil;
3. slot **Foto pasangan (hero) tetap** untuk beranda (perolehan suara) & hasil
   akhir; gambar pembuka profil adalah slot terpisah.

Satu migration baru; tanpa dependensi atau framework baru.

"HP" = lebar < 720 px (sama dengan Stage 8–15).

## 1. Schema

| Migration | Isi |
|---|---|
| `2026-09-30-000001_SimplifyCandidateTheme` | `up()`: kumpulkan nama file `theme_background` + kunci `texture`/`artwork` di `theme_asset`; tulis ulang `theme_asset` tanpa dua kunci itu (hanya nilai string; kosong → NULL; JSON rusak tidak disentuh); hapus CHECK `chk_candidates_theme_layout`, kolom `theme_layout` dan `theme_background`; SETELAH skema berhasil, hapus file lewat `service('candidateAssets')->delete()` (hanya basename gambar di folder upload). `down()`: kolom + CHECK dipasang kembali kosong; **file yang terhapus tidak dapat dikembalikan** |

`CandidateModel`: `theme_background` & `theme_layout` keluar dari
`allowedFields`, rule, dan pesan validasi. Field lama yang masih dikirim ke
form diabaikan (`textInput()` hanya membaca field yang dikenal).
`CandidateSeeder` tanpa kedua kolom itu.

Diuji nyata (MariaDB 10.11.14): database Stage 15 berisi data lama + file
dummy → `php spark migrate` (kolom & CHECK hilang, JSON bersih, file lama
terhapus, foto/hero/poster aman) → `migrate:rollback` (kolom & CHECK kembali)
→ `migrate` lagi.

## 2. Slot gambar (`CandidateAssets::SLOTS`, urutan = urutan form)

| Slot | Kunci | Aturan | Dipakai di |
|---|---|---|---|
| Foto calon ketua / wakil | kolom `foto_ketua` / `foto_wakil` | tetap (min 240, sisi 1000) | Kenali pasangan, Pesan, bilik suara, surat suara |
| **Gambar pembuka profil** (baru) | `theme_asset.banner` | **wajib landscape** (lebar > tinggi, diperiksa SETELAH orientasi EXIF diluruskan), min 1200 × 400, sisi terpanjang 2000, disarankan 1920 × 1080 (16:9) | pembuka profil, tampil utuh |
| Poster kampanye | `theme_asset.poster` | tetap | Poster kampanye (lightbox) |
| **Latar penutup** (baru) | `theme_asset.closing` | min 1000 × 500, sisi 2000, dipotong cover | penutup profil |
| Foto pasangan (beranda & hasil) | `theme_asset.hero` | tetap (label & keterangan diperbarui) | perolehan suara beranda, hasil akhir & PDF |
| ~~Latar panggung, Artwork, Tekstur / pola~~ | — | dihapus | — |

Pesan tolak banner tegak/persegi: "Gambar pembuka profil harus mendatar
(landscape): lebar lebih besar dari tinggi, mis. 1920 × 1080 px. File ini
W × H px." Pratinjau slot banner di form memakai `object-fit: contain`
(`.slot--whole`) karena gambarnya tampil utuh di halaman.

## 3. Tema (`CandidateTheme`)

- Dihapus: `LAYOUTS`, `PATTERNS`, `layout()`, kunci `layout`, `pattern`,
  `background`, `texture`, `artwork`.
- `ASSET_KEYS = ['hero', 'banner', 'poster', 'closing']`.
- `banner_size`: `[lebar, tinggi]` file gambar pembuka (dibaca dari folder
  service `candidateAssets`) untuk atribut `width`/`height` → ruang disiapkan
  sebelum gambar dimuat; null bila file tidak ada.
- `--accent-soft` (`soft()`, `SOFT_MIX = 0.12`): tint solid 12 % aksen di
  atas putih, dihitung di PHP (tanpa `color-mix`), untuk panel, kartu, pill.
- `video_thumb` = `https://i.ytimg.com/vi/ID/hqdefault.jpg` (selalu ada),
  `video_thumb_hd` = `…/maxresdefault.jpg`; konstanta `THUMB_ORIGIN`.
- `allowVideoFrame()` → **`allowVideo()`**: frame-src youtube-nocookie + img-src
  `https://i.ytimg.com`, hanya untuk respons halaman yang memuat video.

## 4. Halaman profil paslon (`paslon/partials/profile.php`)

```
article.paslon[style = --accent…, --accent-soft]
  div.px-shell[.px-shell--nav]          kolom isi; ≥ 1024 px menyisakan kolom kanan untuk nav
    a.px-back                           Semua paslon / Dasbor (halaman pemilih)
    header.px-cover[.px-cover--image]
      banner ada : h1.visually-hidden "Paslon 01: Ketua dan Wakil" + img.px-cover__img (utuh, width/height, fetchpriority high)
      tanpa      : div.px-cover__plain (panel --accent-soft): Paslon 01, h1 nama × nama + peran, slogan
      a.px-cta   "Lihat Visi & Misi" (#visi; tanpa visi: "Kenali lebih dekat" → #profil) — DI BAWAH gambar
    nav.px-nav (hanya bila > 1 bagian) > .px-nav__inner > p.px-nav__title "Paslon 01" + ol.px-nav__list
    section#profil  Kenali pasangan     SELALU ada: kartu foto/monogram, nama, peran; bio & pill karakter bila diisi
    section#visi    Visi kami           panel tint, kutipan besar tebal (statis)
    section#misi    Misi kami           bernomor, dua kolom urut ke bawah (--rows), tanpa garis
    section#program Program unggulan    kartu tint, kolom mengikuti jumlah (CandidateTheme::gridStyle)
    section#poster  Poster kampanye     poster + "Lihat fullscreen" (<dialog> lightbox)
    section#pesan   Pesan dari kami     kartu kutipan + avatar kotak (terpisah dari penutup)
    section#video   Video kampanye      thumbnail asli YouTube + tombol play
  section.px-close[.px-close--image]    selebar layar, di luar kolom isi
    img.px-close__bg (alt="", lazy) + lapisan gelap rgba; h2.visually-hidden;
    Paslon 01, nama + peran, slogan, tombol penutup, deret nomor paslon
```

- Kepala bagian `div.px-head`: ikon (`users`, `eye`, `target`, `bulb`,
  `image`, `message`, `video`; empat terakhir kecuali `image` baru di
  `icon()`), `h2` (teks tetap "Kenali pasangan" dst., dikapitalkan CSS), dan
  subjudul ("Dua individu, satu tujuan untuk SMP 1 DAWE.", "Langkah nyata
  untuk mewujudkan visi kami.", …).
- Label navigasi: Profil, Visi, Misi, Program, Poster, Pesan, Video; hanya
  bagian yang ada. Tombol penutup mengikuti status pemilih (tidak berubah dari
  Stage 15, `paslon/show.php`).
- Dihapus dari profil: duo potret, nomor raksasa, parallax, nama tema, varian
  layout, pola/tekstur/latar/artwork, efek kata menyala pada visi, serta
  `data-px-hero` / `data-px-words` / `data-px-parallax`. Partial
  `voting/partials/portraits.php` tidak lagi dipakai profil (ukuran `xl`,
  `$rolePrefix`, `$priority` dihapus).

### Video

`a.px-video__facade[href=youtube watch][data-px-play]` berisi
`img.px-video__thumb` (hqdefault, `data-hd` = maxresdefault, lazy) dan
`span.px-video__play` (lingkaran gelap transparan + ikon play putih). Selalu
terlihat; tanpa JS membuka YouTube di tab baru. Dengan JS: `role="button"`
(Spasi juga memutar), klik membuat iframe youtube-nocookie seperti Stage 15
(`autoplay`, `referrerpolicy="strict-origin-when-cross-origin"`, fullscreen).
Setelah hqdefault dimuat, paslon.js mencoba maxresdefault dan memakainya bila
tersedia (YouTube membalas gambar 120 × 90 bila tidak ada). Tanpa warna aksen.

### Penutup tanpa warna aksen

Semua aturan `.px-close*`, `.px-finale*`, `.px-others*` tidak memakai
`--accent…` (dijaga test). Gambar `object-fit: cover` + lapisan
`rgba(12, 12, 16, 0.58)`; tanpa gambar latar jatuh ke ink. Teks putih, tombol
utama kertas + teks ink, tombol lain putih transparan, nomor paslon dalam
lingkaran putih transparan (aktif: kertas), outline fokus kertas.

## 5. Gaya (`paslon.css`, ditulis ulang)

- Satu tampilan, tanpa gradient, **tanpa garis dekoratif** (garis antarbagian,
  strip judul, garis daftar, rel & progres navigasi, border kutipan, border
  tombol outline): pemisah = ruang, kartu (putih + bayangan lembut), atau tint
  `--accent-soft`. Test memastikan tidak ada `border…solid` di paslon.css.
- Tipografi profil: Plus Jakarta Sans untuk semuanya (judul bagian 800
  kapital berjarak, kutipan visi 700); `.paslon h1–h3` menimpa Newsreader
  global app.css.
- Pembuka: HP selebar layar (tanpa sudut), ≥ 720 px kartu radius 16 px.
- Navigasi: < 1024 px bar menempel di atas (kertas + bayangan, item aktif pill
  tint, rata tengah bila muat dan dapat digeser bila tidak — margin auto,
  bukan `justify-content: center`); ≥ 1024 px `nav` absolute setinggi kolom
  isi, kartu `.px-nav__inner` sticky (top 24 px), titik + semua label, aktif =
  titik aksen + label tebal. `.px-nav .px-nav__list` sengaja lebih spesifik dari
  reset `ol[class] { padding: 0 }` app.css.
- Pill karakter: teks ink di atas tint (kontras aman); nomor & ikon
  `--accent-text`; `px-cta` solid aksen; "Lihat fullscreen" solid ink.
- Gerak: reveal saat scroll (`.px-motion`) tetap; gambar/judul pembuka & tombol
  fade-up hanya dengan `.js` + no-preference; `prefers-reduced-motion`
  mematikan semuanya.

## 6. Interaksi (`paslon.js`)

- `initNav`: bagian aktif = bagian terakhir yang melewati garis baca
  `min(30 % tinggi layar, 260 px)` (bawaan bagian pertama; ujung halaman =
  bagian terakhir). Garis ini lebih tinggi dari Stage 15 (40 %) agar bagian
  pendek yang dituju lewat navigasi (mis. Misi) tetap aktif, bukan bagian
  sesudahnya. Bar HP menggeser tautan aktif ke tengah. Rel progres dan logika
  "muncul setelah pembuka" dihapus (navigasi desktop selalu tampil).
- `initReveal`, `initLightbox`: tetap.
- `initVideo`: facade → iframe, upgrade thumbnail HD, `role="button"` + Spasi.
- Dihapus: `splitWords` / `initWords` / `initScroll` (parallax & kata menyala).

## 7. Halaman lain (diseragamkan)

- `voting/partials/portraits.php` + `voting.css`: monogram polos warna aksen,
  tanpa `portrait__pattern`.
- `home/partials/pair_photo.php` + `home.css`: tanpa `pair__pattern`.
- `admin/results/index.php` + `results.css`: panggung "Pasangan terpilih"
  memakai satu susunan (nomor | foto | nama) untuk pemenang mana pun; kelas
  `victor--{layout}`, `victor__pattern`, varian poster/column, dan selektor
  cetaknya dihapus.
- `admin/candidates/form.php`: pemilih "Layout halaman kandidat" dihapus;
  keterangan warna aksen menyebut pemakaiannya di profil (penutup memakai
  gambar). `admin/candidates/index.php`: baris "Layout" dihapus (kelengkapan
  asset otomatis 6 slot). `admin.css`: `.layout-pick*` dihapus.
- File dihapus: `public/assets/img/patterns/{grid,hatch,dots}.svg`
  (`ticks.svg` tetap dipakai beranda).

## 8. Keamanan

- **CSP**: img-src `https://i.ytimg.com` dan frame-src
  `https://www.youtube-nocookie.com` HANYA di respons profil / pratinjau paslon
  yang punya video (`CandidateTheme::allowVideo()`); halaman lain tetap
  frame-src `'none'` dan img-src tanpa asal luar.
- Thumbnail dimuat sebelum tombol putar ditekan (permintaan pemilik proyek);
  header aplikasi `Referrer-Policy: same-origin` membuat i.ytimg.com tidak
  menerima referrer. paslon.js hanya menerima `data-hd` berawalan
  `https://i.ytimg.com/vi/` dan embed berawalan
  `https://www.youtube-nocookie.com/embed/` (dibuat server).
- Semua teks lewat `esc()`; nama file asset selalu lewat `basename()`
  (`assetUrl()`, `imageSize()`, `CandidateAssets::delete()`).

## 9. Test

Baru:
- `tests/database/SimplifyCandidateThemeTest.php` (menggantikan
  `Stage2SchemaTest`, yang seluruhnya menguji `theme_layout`): kolom lama
  hilang; `down()` memasang kembali kolom kosong; `up()` membuang
  texture/artwork (hero/poster tetap, JSON rusak tidak disentuh) dan hanya
  menghapus file latar/tekstur/artwork (service `candidateAssets` di folder
  sementara; nama `../../.env` tidak menyentuh file di luar folder upload).
- `PaslonProfileTest` (18 test): pembuka tanpa gambar (satu h1, slogan, tombol
  SETELAH pembuka) & dengan gambar (h1 tersembunyi, gambar utuh, tanpa
  width/height bila file tidak ada), satu tampilan tanpa varian/pola/nama
  tema, kepala bagian berikon, urutan bagian & label navigasi (termasuk
  Poster → Pesan → Video), Kenali pasangan tetap tampil walau bio kosong,
  Pesan terpisah dari penutup, penutup bergambar / fallback, aturan CSS
  penutup tanpa `--accent` + tanpa garis, video (thumbnail hqdefault +
  `data-hd`, tautan watch tanpa `hidden`, tanpa iframe), CSP frame-src &
  img-src i.ytimg.com hanya di halaman ber-video, pratinjau admin.
- `CandidateAssetsTest`: urutan slot, banner tegak/persegi ditolak, banner
  landscape disimpan utuh (2400 × 1350 → 2000 × 1125), orientasi dicek setelah
  rotasi EXIF, "Latar penutup terlalu kecil".
- `CandidateThemeTest`: tanpa kunci layout/pola/latar/tekstur/artwork,
  kunci aset baru, `banner_size` dari file, `--accent-soft`, `video_thumb*`.

Diperbarui: `AdminCandidateTest` (unggah banner/penutup, banner tegak
ditolak, form tanpa layout & slot lama, field lama diabaikan, pratinjau),
`VotingTest` (monogram polos, satu tampilan, aset profil, hook interaksi),
`BoothRefinementTest` (nama tema di Sekilas paslon, bukan di profil),
`FinalResultTest` (victor satu susunan), `StageTwelveRefinementTest` (6 slot
per pasangan), `SchemaIntegrityTest` (contoh `theme_asset`). Tidak ada test
yang dilewati.

Hasil: **454 test, 4.312 assertion, lulus** (PHP 8.4.19, MariaDB 10.11.14).

Uji browser (Playwright/Chromium, `spark serve`, data seeder + gambar uji yang
diunggah lewat form admin: banner 1920 × 1080 bertulisan, latar penutup,
poster, foto; video `dQw4w9WgXcQ`), 390 / 768 / 1024 / 1440 px, paslon 01
(lengkap) & 03 (tanpa gambar pembuka dan latar penutup):
- gambar pembuka utuh, tombol tepat di bawahnya; tanpa gambar: panel judul;
- navigasi desktop di kolom kanan dengan 7/7 label, menempel (top 24 px),
  klik Misi → Misi aktif; bar HP menempel & menggeser tautan aktif;
- tanpa gulir mendatar di semua lebar; tanpa garis;
- thumbnail asli YouTube dimuat lalu naik ke maxresdefault (1280 × 720),
  klik play → iframe youtube-nocookie ber-`referrerpolicy`, fokus ke iframe;
- lightbox poster buka/tutup (Escape); penutup bergambar dengan tombol
  kertas + teks ink (tanpa aksen); fallback penutup ink;
- `prefers-reduced-motion`: semua konten langsung tampil;
- form admin (6 slot, tanpa layout), bilik suara (8 monogram, 0 pola),
  hasil akhir (victor satu susunan + foto berdua), beranda (foto berdua);
- konsol tanpa error JavaScript/CSP (hanya peringatan bawaan Chromium
  "Allow attribute will take precedence over 'allowfullscreen'" yang sudah ada
  sejak Stage 15).

Screenshot: `shots/paslon-desktop.png`, `shots/paslon-hp.png`,
`shots/paslon-tanpa-gambar.png` (`nav-rail.png` Stage 15 dihapus karena rel
navigasinya sudah tidak ada).
