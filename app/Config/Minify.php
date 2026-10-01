<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Minify keluaran ke browser + watermark view-source (Stage 20, Stage 22).
 *
 * - CSS/JS: kode sumber rapi & berkomentar ada di resources/assets/{css,js}/
 *           (di luar public/, tidak pernah sampai ke browser). File bernama
 *           sama di public/assets/{css,js}/ adalah HASIL BUILD: diperkecil +
 *           watermark, ikut di-commit, berlaku di SEMUA environment. Setelah
 *           mengedit sumber: php spark osis:minify, lalu commit keduanya
 *           (App\Libraries\AssetMinifier juga membangun ulang otomatis bila
 *           sumber lebih baru, library terpasang, dan folder dapat ditulis).
 * - HTML  : dirapatkan saat respons dikirim (App\Filters\MinifyHtmlFilter),
 *           HANYA bila CI_ENVIRONMENT = production. Isi <pre>/<textarea> &
 *           nilai atribut tidak disentuh.
 *
 * Saklar ON/OFF cukup diubah di file ini (atau sementara lewat .env:
 * minify.enabled = false). Bila satu jenis bermasalah, matikan jenis itu saja.
 * CSS/JS yang dimatikan disalin apa adanya dari resources/ (beserta
 * komentarnya) pada build berikutnya.
 */
class Minify extends BaseConfig
{
    /**
     * Saklar utama. true = CSS/JS dikirim ter-minify (semua environment) dan
     * HTML ter-minify (production saja), false = browser menerima kode asli.
     */
    public bool $enabled = true;

    /**
     * Per jenis (berlaku bila $enabled = true).
     */
    public bool $html = true;

    public bool $css = true;
    public bool $js  = true;

    /**
     * Watermark: komentar di baris kedua view-source (setelah <!DOCTYPE html>)
     * dan di baris pertama setiap file CSS/JS ter-minify. Ikut saklar $html
     * (HTML) dan $css/$js (file). '' = tanpa watermark. Jangan memuat
     * penutup komentar ("*" diikuti "/", atau "-->"): bagian sisanya akan
     * terbaca sebagai kode, jadi watermark seperti itu tidak dipasang.
     *
     * Baris 1 "FAJAR" (figlet ANSI Shadow), baris 2 "© SMP 1 DAWE 2026"
     * (figlet Calvin S; angka & © digambar dengan gaya yang sama).
     */
    public string $watermark = <<<'TXT'
           ███████╗ █████╗      ██╗ █████╗ ██████╗
           ██╔════╝██╔══██╗     ██║██╔══██╗██╔══██╗
           █████╗  ███████║     ██║███████║██████╔╝
           ██╔══╝  ██╔══██║██   ██║██╔══██║██╔══██╗
           ██║     ██║  ██║╚█████╔╝██║  ██║██║  ██║
           ╚═╝     ╚═╝  ╚═╝ ╚════╝ ╚═╝  ╚═╝╚═╝  ╚═╝

        ╭┌─┐╮  ╔═╗╔╦╗╔═╗  ╗  ╔╦╗╔═╗╦ ╦╔═╗  ╔═╗╔═╗╔═╗╔═╗
        ││  │  ╚═╗║║║╠═╝  ║   ║║╠═╣║║║║╣   ╔═╝║ ║╔═╝╠═╗
        ╰└─┘╯  ╚═╝╩ ╩╩    ╩  ═╩╝╩ ╩╚╩╝╚═╝  ╚═╝╚═╝╚═╝╚═╝
        TXT;
}
