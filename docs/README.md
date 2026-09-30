# Dokumentasi Proyek

Dokumentasi pendukung aplikasi Pemilihan Ketua & Wakil Ketua OSIS SMP 1 DAWE
2026. Panduan pemakaian, instalasi, dan deployment tetap ada di
[`README.md`](../README.md) di root proyek.

```
docs/
├── spesifikasi/     brief & spesifikasi per tahap (00–05)
├── desain/          identitas visual, tipografi, aset, gerak (06–10)
└── catatan-tahap/   catatan implementasi & handoff tiap tahap (STAGE1–18)
```

Dokumen di `spesifikasi/` dan `desain/` adalah **rencana** (apa yang harus
dibangun); `catatan-tahap/` adalah **hasil** (apa yang benar-benar dibangun,
keputusan, dan hasil test).

## Spesifikasi

| Dokumen | Isi |
|---|---|
| [`00-MASTER-PROJECT.md`](spesifikasi/00-MASTER-PROJECT.md) | Spesifikasi utama (source of truth) |
| [`01-FOUNDATION-DATABASE-AUTH.md`](spesifikasi/01-FOUNDATION-DATABASE-AUTH.md) | Stage 1: fondasi, database, autentikasi |
| [`02-STUDENT-TEACHER-VOTING.md`](spesifikasi/02-STUDENT-TEACHER-VOTING.md) | Stage 2: pengalaman voting siswa & guru |
| [`03-ADMIN-IMPORT-ANALYTICS.md`](spesifikasi/03-ADMIN-IMPORT-ANALYTICS.md) | Stage 3: panel admin, impor, tema, analitik, live count, unlock |
| [`04-FINAL-INTEGRATION-TESTING-DEPLOYMENT.md`](spesifikasi/04-FINAL-INTEGRATION-TESTING-DEPLOYMENT.md) | Stage 4: integrasi akhir, keamanan, hasil akhir, deployment |
| [`05-HOMEPAGE-REDESIGN.md`](spesifikasi/05-HOMEPAGE-REDESIGN.md) | Stage 5: redesign beranda |

## Desain

Identitas visual SMP 1 DAWE (Stage 6), disebut "dokumen 06–10" di kode dan
catatan tahap.

| Dokumen | Isi |
|---|---|
| [`06-VISUAL-DIRECTION-PILKETOS.md`](desain/06-VISUAL-DIRECTION-PILKETOS.md) | Arah visual "Surat Suara dari Lereng Muria" |
| [`07-TYPOGRAPHY-FONT-SYSTEM-PILKETOS.md`](desain/07-TYPOGRAPHY-FONT-SYSTEM-PILKETOS.md) | Tipografi & sistem font (Plus Jakarta Sans) |
| [`08-VISUAL-ASSET-SPECIFICATION-PILKETOS.md`](desain/08-VISUAL-ASSET-SPECIFICATION-PILKETOS.md) | Spesifikasi aset visual: ukuran, zona aman, anggaran file |
| [`09-IMAGE-GENERATION-PROMPTS-PILKETOS.md`](desain/09-IMAGE-GENERATION-PROMPTS-PILKETOS.md) | Prompt pembuatan gambar & spesifikasi SVG |
| [`10-MOTION-AND-INTERACTION-SYSTEM-PILKETOS.md`](desain/10-MOTION-AND-INTERACTION-SYSTEM-PILKETOS.md) | Sistem gerak & interaksi |

## Catatan tahap

| Dokumen | Isi |
|---|---|
| [`STAGE1-NOTES.md`](catatan-tahap/STAGE1-NOTES.md) | Stage 1: fondasi, database, autentikasi |
| [`STAGE2-NOTES.md`](catatan-tahap/STAGE2-NOTES.md) | Stage 2: pengalaman voting siswa & guru |
| [`STAGE3-NOTES.md`](catatan-tahap/STAGE3-NOTES.md) | Stage 3: panel admin, impor, tema, analitik, live count, unlock, audit |
| [`STAGE4-NOTES.md`](catatan-tahap/STAGE4-NOTES.md) | Stage 4: audit keamanan, integritas suara, hasil akhir & confetti, deployment, matriks route & hak akses, test akhir |
| [`STAGE5-NOTES.md`](catatan-tahap/STAGE5-NOTES.md) | Stage 5: redesign beranda (scene layar penuh, pintu masuk Siswa/Guru, live count publik, layar pembuka, panel status terminal, navigasi logo & footer) |
| [`STAGE6-NOTES.md`](catatan-tahap/STAGE6-NOTES.md) | Stage 6: identitas visual SMP 1 DAWE (arah visual "lereng Muria", font Plus Jakarta Sans, spesifikasi & prompt aset, sistem gerak, layar pembuka selalu tampil) |
| [`STAGE7-NOTES.md`](catatan-tahap/STAGE7-NOTES.md) | Stage 7: URL bahasa Indonesia santai, nama templat impor, redesain bilik suara siswa & guru |
| [`STAGE8-NOTES.md`](catatan-tahap/STAGE8-NOTES.md) | Stage 8: rapikan dasbor pemilih & bilik suara (navigasi ikon di HP, jam melayang, journey timeline, countdown terminal, kertas bolong + jeda konfirmasi, paku 3D selalu nyala) |
| [`STAGE9-NOTES.md`](catatan-tahap/STAGE9-NOTES.md) | Stage 9: bilik suara lebih padat di HP, login pemilih dua tahap + gembok terbuka, panel admin (brand, menu, breadcrumb stepper, keterangan di balik ikon, bar live count di HP) |
| [`STAGE10-NOTES.md`](catatan-tahap/STAGE10-NOTES.md) | Stage 10: Sekilas paslon bergeser sendiri di HP + panah ke navigasi bab, dasbor HP rata tengah + jam di atas footer, modal sukses & halaman pilihan saya (siswa "kamu"), scene perolehan suara rata tengah dengan "Suara masuk" sebagai baris penutup |
| [`STAGE11-NOTES.md`](catatan-tahap/STAGE11-NOTES.md) | Stage 11: analitik pill section header + bagian dimuat lewat fetch, detail suara siswa/guru terpisah, deteksi perangkat `matomo/device-detector` + Client Hints, CRUD siswa & guru, indikator "Live", countdown dasbor gaya terminal di HP, rekap kelas/rombel, istilah "rombel" seragam (label, impor, pesan, audit) |
| [`STAGE12-NOTES.md`](catatan-tahap/STAGE12-NOTES.md) | Stage 12: kerangka "memuat" analitik (pengganti garis progres), breadcrumb berikon di topbar / ikon saja di HP, kepala halaman rata tengah tanpa garis, "Selengkapnya" & kolom "Grafik", kartu paslon + timeline asset, timeline tahapan unlock, live search di semua pencarian admin |
| [`STAGE13-NOTES.md`](catatan-tahap/STAGE13-NOTES.md) | Stage 13: hasil akhir (PDF dompdf, cetak browser dengan logo & catatan kaki per halaman, tombol ikon di HP), sidebar Title Case + Halaman Utama/Keluar di bawah, Akun Admin (ganti nama pengguna & kata sandi), login admin layar terbelah + lihat kata sandi, istilah analitik Total/Pemilih/Jenis Kelamin |
| [`STAGE14-NOTES.md`](catatan-tahap/STAGE14-NOTES.md) | Stage 14: gambar layar pembuka tidak telat (preload di `<head>`, tampil tanpa menunggu skrip, diurai sebelum dihitung), aksi kartu paslon rata tengah di HP, halaman impor siswa & guru berbahasa ramah + contoh baru, tombol unduh template selebar layar di HP, tab saring pratinjau impor rata tengah / bergulir mendatar di HP dan dimuat tanpa muat ulang |
| [`STAGE15-NOTES.md`](catatan-tahap/STAGE15-NOTES.md) | Stage 15: batas pasangan calon aktif diatur admin (2–10, bawaan 3) + grid bilik suara & surat suara untuk berapa pun pasangan, halaman profil paslon editorial (`/siswa/paslon/0X`, `/guru/paslon/0X`: Ketua × Wakil satu tim, visi-misi, program, poster & video YouTube, pesan, navigasi bagian), field profil & kampanye baru, bab visi-misi keluar dari bilik suara |
| [`STAGE16-NOTES.md`](catatan-tahap/STAGE16-NOTES.md) | Stage 16: redesain halaman profil paslon mengikuti gambar referensi — satu gambar pembuka landscape buatan paslon + tombol Lihat Visi & Misi di bawahnya, navigasi bagian di kolom kanan dengan semua label (desktop), kepala bagian berikon, kartu & pill, Pesan terpisah dari penutup, video dengan thumbnail asli YouTube, penutup berlatar gambar khusus tanpa warna aksen; satu tampilan untuk semua pasangan (layout, latar panggung, artwork, tekstur/pola, dan garis dihapus; data & file lama dibersihkan migration) |
| [`STAGE17-NOTES.md`](catatan-tahap/STAGE17-NOTES.md) | Stage 17: tampilan panel admin — badge "Sedang Berlangsung" dihapus, topbar HP ikon menu tanpa teks/garis + logo khusus PILKETOS Panel Admin, ikon "i" kecil di pojok kanan atas judul, tanpa garis atas/bawah (termasuk `.pills::after`), dua foto pasangan dalam satu kotak persegi dengan nomor semi transparan saat hover (Beranda, Analitik, Pasangan calon), hasil akhir terkunci rata tengah, countdown compact lebih lega tanpa garis, tabel bergaya lembar kerja (Excel) |
| [`STAGE18-NOTES.md`](catatan-tahap/STAGE18-NOTES.md) | Stage 18: beranda — scene pertama hanya gambar hero (desktop & HP) + tombol **Masuk untuk memilih** / **Lihat perolehan suara** (kicker, judul terlihat, baris pendukung, kontur SVG, dan perforasi dihapus), "Kembali ke awal" di scene perolehan suara menjadi panah saja |

Catatan tahap adalah arsip: isinya menggambarkan kondisi proyek saat tahap itu
selesai, termasuk letak file saat itu (sebelum dokumen dipindah ke `docs/`).
Nama file dokumen tidak berubah, jadi rujukan seperti `STAGE4-NOTES.md` atau
`00-MASTER-PROJECT.md` di dalamnya tetap bisa dicari di folder ini.
