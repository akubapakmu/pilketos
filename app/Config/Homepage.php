<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Beranda imersif (Stage 5, STAGE5-NOTES.md) dengan identitas visual SMP 1
 * DAWE (Stage 6, STAGE6-NOTES.md & dokumen 06-10).
 *
 * Semua gambar beranda dapat diganti panitia TANPA mengubah layout: taruh
 * file baru di public/assets/img/..., lalu ubah path di sini atau di .env:
 *
 *   homepage.heroDesktop     = 'assets/img/home/hero-desktop.webp'
 *   homepage.entryStudent    = 'assets/img/home/entry-student.webp'
 *   homepage.schoolEmblem    = 'assets/img/brand/lambang-smp1dawe.svg'
 *   homepage.publicLiveCount = false
 *
 * Path relatif terhadap folder public/ (tanpa "/" di depan). Format: svg,
 * webp, jpg/jpeg, png, avif. Jangan taruh di public/uploads/: folder itu
 * hanya menyajikan foto kandidat (public/uploads/.htaccess). Ukuran, zona
 * aman, dan anggaran file: 08-VISUAL-ASSET-SPECIFICATION-PILKETOS.md.
 */
class Homepage extends BaseConfig
{
    /**
     * Lockup acara untuk latar gelap (navigasi beranda, layar pembuka), B02.
     */
    public string $logoOnDark = 'assets/img/brand/logo-light.svg';

    /**
     * Lockup acara untuk latar terang (login, dasbor, halaman voting), B03.
     */
    public string $logoOnLight = 'assets/img/brand/logo-dark.svg';

    /**
     * Stage 19: logo header panel admin di HP (< 720 px, .admin-top__logo),
     * diatur di sini seperti logo beranda. Ganti file di
     * public/assets/img/brand/ lalu ubah path (atau .env
     * homepage.adminLogoMobile). Disarankan SVG berlatar transparan untuk
     * latar terang (tinta gelap), rasio lebar kira-kira 4:1. Ukuran asli
     * dibaca otomatis (asset_size) untuk atribut width/height.
     */
    public string $adminLogoMobile = 'assets/img/brand/logo-admin-mobile.svg';

    /**
     * Tinggi tampil logo header admin HP dalam piksel (lebar mengikuti
     * rasio gambar). Dibatasi 24-48 agar header tetap satu baris.
     */
    public int $adminLogoMobileHeight = 34;

    /**
     * Teks alternatif logo header admin HP (logo juga tautan ke Beranda admin).
     */
    public string $adminLogoMobileAlt = 'PILKETOS Panel Admin: Beranda';

    /**
     * Lambang resmi SMP 1 DAWE (B01) dari sekolah: SVG, atau PNG >= 1024 px
     * berlatar transparan. Dipasang APA ADANYA di kiri lockup (navigasi &
     * layar pembuka), dipisah garis tipis; tidak digambar ulang, tidak
     * diwarnai ulang. null = lockup acara saja (sampai file resmi tersedia).
     */
    public ?string $schoolEmblem = null;

    /**
     * Latar layar pembuka lanskap (A01): pemandangan hero yang SAMA berkabut
     * pekat, bingkai identik dengan $heroDesktop (transisi "kabut tersingkap").
     */
    public string $introDesktop = 'assets/img/home/intro-desktop.svg';

    /**
     * Latar layar pembuka potret (A02): versi berkabut dari $heroMobile.
     */
    public string $introMobile = 'assets/img/home/intro-mobile.svg';

    /**
     * Latar hero (scene 01) untuk layar lanskap (A03, master): lereng Muria
     * pagi + atap sekolah. Disarankan 1920x1080 WebP <= 300 KB; area kiri
     * bawah gelap & tenang untuk tombol.
     */
    public string $heroDesktop = 'assets/img/home/hero-desktop.svg';

    /**
     * Latar hero untuk layar potret, HP & tablet tegak (A04). 1080x1920.
     */
    public string $heroMobile = 'assets/img/home/hero-mobile.svg';

    /**
     * Lapisan depan transparan (A05: ranting parijoto + daun kopi) di pojok
     * kanan bawah hero, 1400x1400 WebP ber-alpha. null = tanpa lapisan depan.
     */
    public ?string $heroForeground = null;

    /**
     * Ilustrasi pintu masuk Siswa & Guru (A06/A08, 1:1, subjek di tengah;
     * dipotong "cover" dan diberi lapisan gelap agar teks terbaca). Versi
     * *Mobile opsional (A07/A09, 3:2): dipakai di HP tegak, null = sama.
     */
    public string $entryStudent = 'assets/img/home/entry-student.svg';

    public ?string $entryStudentMobile = null;
    public string $entryTeacher        = 'assets/img/home/entry-teacher.svg';
    public ?string $entryTeacherMobile = null;

    /**
     * Warna aksen identitas sekolah (parijoto, #RRGGBB): hanya bilah muat
     * layar pembuka. Bila mirip warna aksen salah satu pasangan calon
     * (CIEDE2000 < $identityMinDeltaE), beranda otomatis memakai warna
     * netral (Kabut) agar tetap netral. null = selalu netral.
     */
    public ?string $identityAccent = '#A8628F';

    public float $identityMinDeltaE = 20.0;

    /**
     * Live count publik di beranda (persentase per pasangan + partisipasi).
     * false = beranda hanya menampilkan pasangan calon tanpa angka dan
     * GET hitung-suara menjawab 404. Rincian analitik tetap hanya untuk admin.
     */
    public bool $publicLiveCount = true;

    /**
     * Irama pembaruan otomatis live count (detik) saat pemilihan belum
     * dibuka atau sedang berlangsung. Minimal 10; 0 = tanpa pembaruan otomatis.
     */
    public int $livePollSeconds = 30;

    /**
     * Cache singkat angka publik (detik) agar ratusan layar yang terbuka
     * bersamaan tidak masing-masing menghitung ulang ke MySQL. 0 = tanpa cache.
     */
    public int $liveCacheSeconds = 5;
}
