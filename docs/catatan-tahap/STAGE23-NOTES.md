# Stage 23 — CSS/JS Selalu Ter-minify + Watermark, Sumber Rapi di `resources/` (hasil implementasi + handoff)

Dokumen ini adalah kontrak aktual Stage 23. Dibaca bersama
`00-MASTER-PROJECT.md`, `STAGE1-NOTES.md` s.d. `STAGE22-NOTES.md`
(terutama `STAGE20-NOTES.md`), dan `README.md`.

Permintaan: catatan, komentar, dan dokumentasi di CSS/JS dibersihkan, semua
file di-minify dan diberi watermark seperti minify sebelumnya, karena file CSS
dan JS yang dibuka dari view-source browser masih menampilkan kode utuh.

Ringkasan Stage 23:

- browser **hanya** menerima CSS/JS yang ter-minify, tanpa komentar, dan
  ber-watermark **FAJAR / © SMP 1 DAWE 2026**, di **semua environment**
  (development maupun production);
- kode sumber rapi yang **tetap berkomentar** dipindah ke
  `resources/assets/{css,js}/` (di luar `public/`). Browser tidak bisa
  membukanya, sehingga komentar tetap ada untuk pengembangan berikutnya;
- `public/assets/{css,js}/` berisi **hasil build** bernama sama yang ikut
  di-commit. Server tidak butuh library minify maupun folder yang dapat
  ditulis;
- minify HTML **tidak berubah**: tetap hanya saat `CI_ENVIRONMENT =
  production`, dengan watermark setelah `<!DOCTYPE html>`.

Tanpa migration, route baru, atau dependensi baru.

## 1. Penyebab kode utuh terlihat (Stage 20)

| Penyebab | Akibat |
|---|---|
| CSS/JS hanya diperkecil bila `CI_ENVIRONMENT = production` | di development (bawaan `.env.example`), `app.css` / `home.js` asli yang disajikan |
| file `*.min.*` dibuat saat request dan butuh `matthiasmullie/minify` | bila `composer install` belum dijalankan ulang setelah Stage 20, build gagal diam-diam dan file asli yang disajikan. HTML tetap diperkecil karena tidak butuh library, jadi watermark tetap tampil di view-source halaman |
| file asli tetap ada di `public/assets` | walaupun `app.min.css` yang disajikan, `app.css` beserta komentarnya tetap bisa dibuka lewat URL-nya |

## 2. Alur baru

```
resources/assets/css/app.css   (sumber rapi, berkomentar; diedit developer)
        │  php spark osis:minify   (atau otomatis saat halaman dibuka, lihat bawah)
        ▼
public/assets/css/app.css      (/*! watermark */ + kode satu baris; di-commit)
```

- Nama file dan folder di `public/` sama dengan sebelumnya, jadi view,
  `asset_url()`, `url('../fonts/...')` di CSS, dan cache `?v=<waktu ubah>`
  tidak berubah. File `*.min.*` tidak dipakai lagi.
- **Build otomatis** di `asset_url()` (`AssetMinifier::assetPath()`): file
  di `public/` dibangun ulang bila lebih tua dari sumbernya atau dari
  `app/Config/Minify.php`. Syaratnya: environment bukan `testing`, folder dan
  file dapat ditulis, dan library terpasang (khusus jenis yang di-minify).
  Bila salah satu tidak terpenuhi, langkah ini dilewati tanpa log, dan file
  ter-commit yang disajikan apa adanya.
- **Isi sama tidak ditulis ulang**: bila hasil build identik dengan file yang
  ada, hanya waktu ubahnya yang diperbarui (`touch`), jadi `git status` tetap
  bersih setelah checkout.
- Penulisan tetap **atomik** (`*.tmp` lalu `rename()`).
- Aturan minify sama dengan Stage 20 (`STAGE20-NOTES.md` bagian 2): pada CSS
  hanya whitespace dan komentar yang dibuang (nilai seperti `flex: 1 1 0`,
  `transparent`, `var(--x, 0px)` tidak diubah), sedangkan JS memakai library
  bawaan. Banner watermark berupa `/*! ... */` di baris pertama, setelah
  `@charset` bila ada.

## 3. Saklar (`app/Config/Minify.php`)

| Saklar | CSS/JS (file) | HTML |
|---|---|---|
| `$enabled = false` | build berikutnya **menyalin sumber apa adanya** (beserta komentar), untuk debug | tidak diperkecil |
| `$css` / `$js = false` | jenis itu disalin apa adanya | isi `<style>` / `<script>` inline tidak diperkecil |
| `$html = false` | tidak berpengaruh | tidak diperkecil |
| `$watermark = ''` | tanpa banner | tanpa banner |

Mengubah file `Minify.php` membuat build otomatis berjalan lagi (bila
syaratnya terpenuhi). Override lewat `.env` (`minify.enabled = false`)
langsung berlaku untuk HTML; untuk CSS/JS jalankan `php spark osis:minify`
sesudahnya.

## 4. Perintah

- `php spark osis:minify`: bangun ulang 22 file dari `resources/assets`,
  tampilkan ukuran sebelum/sesudah (`[OK]` = berubah, `[SAMA]` = isi sama),
  lalu hapus sisa `*.min.css` / `*.min.js` / `*.tmp` Stage 20. Bila library
  belum terpasang, perintah keluar dengan kode 1 dan pesan
  `jalankan "composer install"`. File di `public/` tidak disentuh.
- `php spark osis:minify --clean`: hanya menghapus sisa Stage 20.
  `pustaka.min.js` tanpa sumber tidak dihapus.
- `php spark osis:check`, baris **Minify HTML/CSS/JS**:
  - PERINGATAN bila **isi** file di `public/assets` bukan hasil build
    terbaru dari sumbernya. Pembandingnya isi, bukan waktu ubah, jadi hasil
    ekstrak zip atau checkout git tidak dianggap usang;
  - tanpa library, cukup diperiksa bahwa file berawalan watermark;
  - OK menyebut status CSS/JS dan HTML, plus catatan bila library belum
    terpasang (situs tetap aman; hanya build ulang yang butuh
    `composer install`).

## 5. Cara mengubah CSS/JS (untuk stage berikutnya)

1. Edit `resources/assets/css/*.css` atau `resources/assets/js/*.js`.
   **Jangan** mengedit `public/assets/css|js`, karena isinya tertimpa build
   berikutnya.
2. `php spark osis:minify`.
3. Commit sumber dan hasil build bersama. `tests/unit/BuiltAssetsTest.php`
   gagal bila langkah 2 terlewat.

File CSS/JS baru cukup dibuat di `resources/assets/{css,js}/`. Perintah di
atas membuat pasangannya di `public/`, lalu file itu dipanggil dengan
`asset_url('assets/js/baru.js')` seperti biasa. Test yang memeriksa isi
CSS/JS membaca sumber rapi lewat `Tests\Support\AssetSource::read('assets/css/home.css')`
(`resources/` untuk CSS/JS, `public/` untuk file lain).

## 6. Keamanan

`resources/` berada di luar DocumentRoot (`public/`). Bila DocumentRoot
terlanjur menunjuk folder proyek, `.htaccess` di root meneruskan semua request
ke `public/`. Dengan `php spark serve`, `/resources/assets/css/app.css`
menjawab 404. Minify tidak menyembunyikan logika JS (nama variabel tidak
diacak); tujuannya membuang komentar dan dokumentasi serta memperkecil file.

## 7. Penggabungan dengan Stage 22 & pustaka pihak ketiga

Stage 22 (bilik suara terpisah, tombol 3D) masuk ke `main` saat Stage 23
dikerjakan. Saat penggabungan:

- perubahan Stage 22 pada `app.css`, `paslon.css`, `voting.css`, dan
  `ballot.js` diterapkan ke sumber di `resources/assets/`;
- file baru `booth-steps.js` dan `pick3d.js` dipindah ke
  `resources/assets/js/`;
- `candidates.js`, yang dihapus Stage 22, ikut dihapus;
- hasil build di `public/` dibuat ulang.

`public/assets/vendor/three-0.186.1/` (three.js, MIT) **tidak** diproses:
file itu pustaka pihak ketiga yang sudah ter-minify, dan satu-satunya
komentarnya adalah header lisensi yang wajib dipertahankan. Folder `vendor/`
tidak termasuk `AssetMinifier::FOLDERS`.

## 8. File

| File | Perubahan |
|---|---|
| `resources/assets/css/*.css` (7), `resources/assets/js/*.js` (15) | dipindah dari `public/assets` (git mv), isi tidak berubah; `booth-steps.js` & `pick3d.js` (Stage 22) ikut dipindah saat penggabungan |
| `public/assets/css/*.css`, `public/assets/js/*.js` | hasil build ter-minify + watermark (baru di git) |
| `app/Libraries/AssetMinifier.php` | `$sourceRoot`; `active()` CSS/JS tanpa syarat environment; `assetPath()` path sama + build otomatis; `build()` resources→public, isi sama hanya di-touch; `expected()`, `outdated()`, `available()`; `clean()` sisa Stage 20; `minifiedPath()` & `unbuildable()` dihapus |
| `app/Commands/MinifyAssets.php` | build dari `resources/`, pesan library belum terpasang, `--clean` untuk sisa Stage 20 |
| `app/Libraries/SystemCheck.php` | baris Minify memakai `outdated()` |
| `app/Config/Minify.php`, `app/Helpers/app_helper.php`, `.gitignore` | dokumentasi/komentar |
| `tests/_support/AssetSource.php` | baru: baca sumber CSS/JS untuk test |
| `tests/unit/AssetMinifierTest.php` | disesuaikan (15 test) |
| `tests/unit/BuiltAssetsTest.php` | baru (3 test) |
| 14 test feature (termasuk `StageTwentyTwoRefinementTest`) | membaca CSS/JS lewat `AssetSource::read()` |

## 9. Test & verifikasi

Hasil: **541 test, 5.293 assertion, lulus** (PHP 8.3.6, MariaDB 10.11.14), termasuk test Stage 22.

- `BuiltAssetsTest`:
  - 22 sumber, masing-masing punya file `public/` yang isinya persis hasil
    build;
  - setiap file berawalan watermark dan hanya memuat satu komentar (banner);
  - lebih kecil dari sumbernya, sedangkan sumbernya tetap berkomentar;
  - tidak ada sisa `.min` / `.tmp` atau file tanpa sumber, dan tidak ada
    `public/resources`.
- `AssetMinifierTest`:
  - CSS/JS aktif di semua environment, HTML hanya production;
  - nama file sama, url relatif tetap benar, nilai CSS tetap;
  - build otomatis bila usang, kecuali saat testing;
  - saklar mati = salinan sumber, dan isi sama hanya di-touch;
  - `outdated()` berbasis isi, `clean()` hanya menghapus sisa Stage 20;
  - test HTML Stage 20 tetap.
- **JS**: AST acorn 15 file sumber dan hasil identik, termasuk modul ES
  `pick3d.js` (setelah menormalkan
  `!0`/`!1`, `a['b']`/`a.b`), hanya tersisa satu komentar (banner), dan
  `node --check` lolos.
- **CSS**: CSSOM Chromium 7 file, aturan demi aturan, sama (530/173/83/232/
  196/77/218 aturan). Selisihnya hanya spasi setelah koma di nilai `var()`
  dan spasi di teks kondisi `@supports (width:1cqi)`, sama seperti catatan
  Stage 20.
- **Browser** (Chromium, `php spark serve`, development **dan** production,
  1440×900 dan 390×844):
  - halaman yang dibuka: beranda, login siswa/guru/admin, dasbor admin,
    analitik, hasil, paslon, pengunjung, siswa, jadwal, dasbor siswa, alur
    Stage 22 (`coblos`, `sekilas-paslon`, profil paslon, `bilik-suara`),
    pilihanku;
  - tombol 3D profil paslon tampil (`.is-3d`) dari `pick3d.js` ter-minify,
    yang mengimpor `../vendor/three-0.186.1/three.module.js` (path relatif
    tetap benar karena folder sama);
  - 0 galat konsol maupun HTTP;
  - 21 file CSS/JS yang dimuat semuanya diawali banner (`confetti.js` hanya
    dimuat di hasil akhir);
  - `/resources/assets/css/app.css` menjawab 404.
- **Tanpa library** (`vendor/matthiasmullie` dipindah sementara, autoload
  dibuat ulang):
  - situs tetap menyajikan file ter-minify, 0 galat, dan sumber yang
    di-`touch` tidak memicu galat;
  - `osis:minify` keluar dengan kode 1 dan pesan `composer install`;
  - `osis:check` OK dengan catatan;
  - sumber yang isinya diubah tanpa build menghasilkan PERINGATAN
    `1 file ... (auth.css)`.
- Ukuran (sebelum gzip): CSS turun 19–34%, JS turun 21–40% per file.
