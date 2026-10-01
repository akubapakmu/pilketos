<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Minify keluaran ke browser + watermark view-source (Stage 19).
 *
 * Kode sumber di repo TIDAK berubah (tetap rapi & berkomentar). Yang diperkecil
 * hanya yang diterima browser, dan HANYA bila CI_ENVIRONMENT = production
 * (development & testing selalu mengirim kode asli agar mudah di-debug):
 *
 * - HTML : dirapatkan saat respons dikirim (App\Filters\MinifyHtmlFilter).
 *          Isi <pre>/<textarea> & nilai atribut tidak disentuh.
 * - CSS/JS: asset_url('assets/css/app.css') menyajikan app.min.css di folder
 *          yang sama (url('../fonts/...') tetap benar). File *.min.* dibuat
 *          otomatis oleh App\Libraries\AssetMinifier saat sumber lebih baru,
 *          tidak masuk git (.gitignore). Buat ulang / hapus manual:
 *          php spark osis:minify   |   php spark osis:minify --clean
 *
 * Saklar ON/OFF cukup diubah di file ini (atau sementara lewat .env:
 * minify.enabled = false). Bila satu jenis bermasalah, matikan jenis itu saja.
 */
class Minify extends BaseConfig
{
    /**
     * Saklar utama. true = HTML/CSS/JS dikirim ter-minify (production saja),
     * false = browser menerima kode asli apa adanya.
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
