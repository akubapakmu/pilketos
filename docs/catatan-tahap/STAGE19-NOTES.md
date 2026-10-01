# Stage 19 — Minify HTML/CSS/JS ke Browser + Watermark View-Source (hasil implementasi + handoff)

Dokumen ini adalah kontrak aktual Stage 19. Dibaca bersama
`00-MASTER-PROJECT.md`, `STAGE1-NOTES.md` s.d. `STAGE18-NOTES.md`, dan
`README.md`.

Ringkasan Stage 19:

- yang diterima **browser** (HTML, CSS, JS) dikirim **ter-minify**, sedangkan
  **kode sumber di repo tetap asli** (rapi, berkomentar, tidak ada file build
  yang di-commit);
- saklar **hardcode ON/OFF** di `app/Config/Minify.php`, ditambah saklar per
  jenis (`html`, `css`, `js`);
- hanya aktif bila **`CI_ENVIRONMENT = production`**. Development dan testing
  selalu mengirim kode asli agar mudah di-debug, dan test lama tidak berubah;
- **watermark** banner ASCII di view-source (tepat setelah `<!DOCTYPE html>`)
  dan di baris pertama setiap file CSS/JS ter-minify. Baris 1 **FAJAR**
  memakai font figlet *ANSI Shadow*. Baris 2 **© SMP 1 DAWE 2026** lebih kecil,
  memakai font figlet *Calvin S*. Font itu tidak punya glyph angka dan ©,
  jadi `1`, `2026`, dan `©` digambar manual dengan gaya garis kotak yang sama.

Dependensi baru: `matthiasmullie/minify` ^1.3 (MIT, beserta
`matthiasmullie/path-converter`). Tanpa migration, tanpa route baru.

## 1. Alur

| Jenis | Kapan | Di mana |
|---|---|---|
| CSS/JS | `asset_url('assets/css/app.css')` | `App\Libraries\AssetMinifier::assetPath()` mengembalikan `assets/css/app.min.css` **di folder yang sama**, sehingga `url('../fonts/...')` di CSS tetap benar. `?v=` = waktu ubah file yang disajikan (cache browser 30 hari tetap aman) |
| HTML | setiap respons `text/html` | filter global `minifyhtml` (`App\Filters\MinifyHtmlFilter`, `Config\Filters::$globals['after']`) memanggil `AssetMinifier::page()` |

File `*.min.css` / `*.min.js`:

- dibuat **otomatis** saat belum ada, atau saat lebih tua dari sumbernya atau
  dari `app/Config/Minify.php` (misalnya watermark diubah). Perbandingannya
  memakai `>` (bukan `>=`), jadi sumber yang diedit pada detik yang sama tidak
  tertinggal;
- penulisan **atomik**: tulis ke `*.tmp` di folder yang sama, lalu `rename()`.
  Ratusan request bersamaan tidak pernah menerima file setengah jadi;
- bila folder tidak dapat ditulis PHP, **file asli** yang disajikan dan
  `log_message('warning')` dicatat. File `.min` yang usang tidak pernah
  disajikan;
- tidak masuk git (`.gitignore`: `/public/assets/**/*.min.css`, `*.min.js`, `*.tmp`).

`php spark osis:minify` membangun ulang semua file dan menampilkan ukuran
sebelum/sesudah. `--clean` menghapus semua file buatan, yaitu file yang punya
file sumber. `osis:check` punya baris **Minify HTML/CSS/JS**: PERINGATAN bila
ada file `.min` yang belum dibuat atau usang padahal foldernya tidak dapat
ditulis.

## 2. Aturan minify

**CSS**: hanya whitespace dan komentar yang dibuang. "Penghematan" nilai bawaan
library **dimatikan** (subclass anonim) karena mengubah arti:

| Bawaan library | Masalah |
|---|---|
| `flex: 1 1 0` menjadi `1 1 0%` | flex-basis persen berbeda di container yang tingginya/lebarnya tak tentu |
| `var(--x, 0px)` menjadi `var(--x,0)` | `calc(var(--x) + 10px)` menjadi tidak valid |
| `transparent` menjadi `#fff0` | warna lain (putih transparan, bukan hitam transparan) |
| `#ffffff` menjadi `#fff`, `rgb()` menjadi hex | aman, tetapi ikut dimatikan agar aturannya sederhana |

Gambar/font kecil juga tidak disisipkan sebagai `data:`, karena CSP
`font-src 'self'`.

**JS**: library bawaan (whitespace, komentar, `true` menjadi `!0`, `a['b']`
menjadi `a.b`). Isi string dan template literal tidak disentuh.

**HTML** (tokenizer regex satu lintasan, konservatif):

- deret whitespace di teks menjadi **satu spasi**. Tampilannya identik untuk
  `white-space: normal`, dan spasi antar-elemen inline (`<a>x</a> <a>y</a>`)
  tetap ada. Whitespace di sekitar tag kepala dokumen (`<!DOCTYPE>`, `html`,
  `head`, `body`, `meta`, `link`, `title`, `base`) dibuang;
- di dalam tag, whitespace antar-atribut dirapatkan. **Nilai atribut tidak
  disentuh** (nonce CSP, `data-*`, `title='x > y'`);
- isi `<pre>` dan `<textarea>` utuh;
- isi `<script>` inline diperkecil dengan JS minifier bila `js` aktif. Isi
  `<style>` inline diperkecil dengan CSS minifier bila `css` aktif.
  `<script type="application/json">` dan type non-JS lain dibiarkan;
- komentar HTML dibuang;
- watermark hanya untuk **dokumen penuh** (diawali `<!DOCTYPE`). Fragmen
  lewat fetch (pane analitik) tidak diberi watermark;
- yang tidak disentuh: JSON (live count, login fetch), unduhan (PDF, Excel),
  redirect, dan respons kosong. Filter berjalan sebelum filter wajib
  `performance` / `toolbar` / `pagecache`.

## 3. Saklar

```php
// app/Config/Minify.php
public bool $enabled = true;   // utama (production saja)
public bool $html = true;      // per jenis
public bool $css  = true;
public bool $js   = true;
public string $watermark = <<<'TXT' ... TXT;   // '' = tanpa watermark
```

Override sementara lewat `.env` (bawaan BaseConfig): `minify.enabled = false`,
`minify.js = false`. Watermark yang memuat penutup komentar (`*/`, `-->`)
ditolak dan dianggap kosong.

## 4. Test

Baru:

- `tests/unit/AssetMinifierTest.php` (12 test) memeriksa:
  - hanya aktif di production, saklar utama dan per jenis;
  - file `.min` di folder yang sama, ber-watermark, sumber tidak berubah,
    tanpa sisa `.tmp`;
  - nilai CSS tetap (`flex:1 1 0`, `transparent`, `var(--x,0px)`, `#ffffff`);
  - dibangun ulang hanya bila sumber lebih baru, dan fallback ke path asli;
  - watermark kosong atau tidak aman ditolak, dan `@charset` tetap pertama;
  - `sources()`/`clean()` hanya menyentuh file buatan;
  - aturan HTML (textarea, pre, atribut, nonce, JSON, inline, komentar);
  - watermark hanya setelah DOCTYPE.
- `tests/unit/MinifyHtmlFilterTest.php` (3 test): HTML diperkecil dengan
  watermark. JSON dan redirect tidak berubah, dan filter mati di luar production.

Diperbarui: `DeploymentToolsTest` (baris `Minify HTML/CSS/JS` di `osis:check`).

Hasil: **483 test, 4.487 assertion, lulus** (PHP 8.3.6, MariaDB 10.11.14).

Verifikasi tambahan:

- **CSS**: CSSOM Chromium untuk 7 file sumber dibandingkan dengan versi
  `.min`, aturan demi aturan. Tidak ada selisih makna; sisanya hanya spasi
  di teks kondisi `@supports`.
- **JS**: AST acorn untuk 12 file sumber dibandingkan dengan versi `.min`.
  Hasilnya identik (setelah menormalkan `!0`/`!1` dan `a['b']`/`a.b`), dan
  `node --check` lolos.
- **Browser** (Chromium, production, 1440×900 dan 390×844 HP):
  - halaman yang dibuka: beranda, login siswa/admin, dasbor siswa, bilik
    suara, profil paslon, dasbor admin, analitik, hasil, daftar/pratinjau/ubah
    paslon;
  - minify ON dibandingkan OFF: teks halaman identik, dan 0 galat konsol
    maupun HTTP;
  - posisi dan ukuran semua elemen sama. Yang berbeda hanya elemen
    bergantung jam (odometer hitung mundur, "diperbarui hh.mm.ss"), dan
    selisih yang sama juga muncul antara dua run OFF.
- **Ukuran**:
  - CSS/JS turun 19–39% per file (sebelum gzip);
  - HTML beranda turun dari 305 baris menjadi 13 baris (12 di antaranya
    banner), dari 20,1 KB menjadi 18,2 KB, sudah termasuk banner sekitar 1,5 KB;
  - mengedit sumber mengubah `?v=` pada request berikutnya.
