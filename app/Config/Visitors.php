<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Pengunjung online (Stage 19, STAGE19-NOTES.md).
 *
 * Setiap halaman yang dibuka (tamu maupun siswa/guru/admin yang sudah masuk)
 * dicatat satu baris per browser di tabel `visitors`: perangkat, sistem
 * operasi, browser, mesin browser, User-Agent, Client Hints, IP, bahasa,
 * halaman, dan waktu aktif (matomo/device-detector, App\Libraries\DeviceInfo).
 * Selama halaman terbuka, presence.js mengirim detak berkala (layar, zona
 * waktu, layar sentuh, jaringan) agar pengunjung yang diam di satu halaman
 * tetap terhitung online.
 *
 * Hanya angka ringkas (tamu & pengguna login online) yang tampil publik di
 * beranda; rincian per pengunjung hanya di panel admin (admin/pengunjung).
 * Stage 21: angka di beranda & papan angka admin diperbarui tiap
 * $counterRefreshSeconds detik tanpa muat ulang halaman.
 * Alamat halaman pemilih disimpan tanpa nomor (siswa/paslon/02 menjadi
 * siswa/paslon) sehingga pilihan atau minat pemilih tidak terbaca.
 *
 * Semua nilai dapat diubah lewat .env, contoh: visitors.onlineSeconds = 300
 */
class Visitors extends BaseConfig
{
    /**
     * false = tidak ada pencatatan, detak, maupun angka online.
     */
    public bool $enabled = true;

    /**
     * Pengunjung dihitung online bila aktif (membuka halaman atau mengirim
     * detak) dalam sekian detik terakhir.
     */
    public int $onlineSeconds = 180;

    /**
     * Irama detak presence.js (detik) selama halaman terbuka & terlihat.
     * Minimal 30; sebaiknya kurang dari separuh $onlineSeconds.
     */
    public int $heartbeatSeconds = 60;

    /**
     * Baris pengunjung yang tidak aktif lebih lama dari ini (hari) dihapus
     * otomatis (sesekali, saat pengunjung baru tercatat).
     */
    public int $retentionDays = 7;

    /**
     * Cookie penanda browser (acak, HttpOnly). Database hanya menyimpan
     * hash SHA-256 nilainya.
     */
    public string $cookieName = 'osis_visitor';

    public int $cookieDays = 30;

    /**
     * Angka online di beranda publik: pojok kanan hero (desktop) dan di atas
     * footer scene perolehan suara. false = hanya di panel admin.
     */
    public bool $publicCounter = true;

    /**
     * Stage 21: irama pembaruan angka online (detik) tanpa muat ulang halaman:
     * beranda membaca GET kunjungan/online, halaman admin Pengunjung online
     * membaca papan angkanya. Minimal 1. Hanya berjalan selama tab terlihat.
     */
    public int $counterRefreshSeconds = 1;

    /**
     * Cache singkat angka online (detik) agar ratusan layar yang membaca
     * angka tiap detik tidak masing-masing menghitung ulang ke MySQL
     * (paling banyak satu hitungan per detik). Pengunjung baru, kembali
     * online, atau berganti akun langsung membuang cache. 0 = tanpa cache.
     */
    public int $countCacheSeconds = 1;
}
