<?php

namespace App\Filters;

use App\Libraries\DeviceInfo;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Header keamanan tambahan untuk semua respons aplikasi (Stage 4).
 *
 * Melengkapi filter bawaan "secureheaders" (X-Frame-Options SAMEORIGIN,
 * X-Content-Type-Options nosniff, Referrer-Policy same-origin, ...) dan
 * Content-Security-Policy (Config\ContentSecurityPolicy):
 * - Permissions-Policy: kamera, mikrofon, lokasi, pembayaran, USB dimatikan;
 *   layar penuh hanya untuk situs ini (halaman hasil akhir di proyektor) dan
 *   pemutar video kampanye youtube-nocookie.com (Stage 15; iframe itu hanya
 *   ada di profil paslon yang punya video, lihat CandidateTheme::allowVideo());
 * - Cross-Origin-Opener-Policy / Cross-Origin-Resource-Policy same-origin;
 * - Cache-Control no-store untuk setiap respons yang belum mengaturnya
 *   (komputer lab dipakai bergantian: halaman pilihan siswa sebelumnya tidak
 *   boleh muncul dari cache/tombol Back setelah keluar);
 * - X-Powered-By (versi PHP) dihapus agar versi server tidak diumumkan;
 * - Accept-CH (Stage 11): browser Chromium diminta mengirim model HP & versi
 *   OS asli (Client Hints) pada request berikutnya, dibaca DeviceInfo saat
 *   suara disimpan (kolom perangkat detail suara). Hanya berlaku lewat HTTPS
 *   atau localhost; browser lain mengabaikannya.
 */
class SecurityHeadersFilter implements FilterInterface
{
    public const PERMISSIONS_POLICY = 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), fullscreen=(self "https://www.youtube-nocookie.com")';

    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('Permissions-Policy', self::PERMISSIONS_POLICY);
        $response->setHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->setHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $response->setHeader('Accept-CH', DeviceInfo::ACCEPT_CH);

        if (! $response->hasHeader('Cache-Control')) {
            $response->setHeader('Cache-Control', 'no-store, max-age=0');
        }

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }
}
