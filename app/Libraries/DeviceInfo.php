<?php

namespace App\Libraries;

use CodeIgniter\HTTP\IncomingRequest;
use DeviceDetector\Cache\StaticCache;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Throwable;

/**
 * Ringkasan perangkat & browser pemilih dari header User-Agent (+ Client Hints).
 *
 * Nilai selalu dibaca SERVER dari request (bukan dari input client) lalu
 * disimpan pada device_info / browser_info baris suara untuk tabel detail
 * suara admin (Stage 3). User-Agent bisa dipalsukan, jadi nilai ini hanya
 * informasi audit, bukan dasar keputusan keamanan.
 *
 * Stage 11: parser bawaan CodeIgniter (daftar kata kunci) diganti pustaka
 * matomo/device-detector yang mengenali ribuan model HP, versi OS, dan
 * browser dalam aplikasi. Parser lama salah membaca Chromebook sebagai
 * "Mac OS X", Opera Mini sebagai tablet (versi 12), browser Vivo/Instagram
 * sebagai Chrome, dan tidak pernah menampilkan model HP maupun versi OS.
 *
 * Client Hints (Sec-CH-UA-*) diminta lewat header Accept-CH
 * (SecurityHeadersFilter) sehingga Chrome/Edge/Samsung Internet mengirim
 * model HP & versi OS asli yang disembunyikan dari User-Agent ("Android 10;
 * K", Windows 11 = "Windows NT 10.0"). Browser hanya mengirimnya lewat HTTPS
 * atau localhost; lewat http://IP-LAN nilai dari User-Agent saja yang dipakai
 * dan versi yang dibekukan browser tidak ditampilkan sebagai versi asli.
 *
 * Contoh hasil: device "HP / Android 14 / Samsung Galaxy A54 5G",
 * "HP / iOS 18 / Apple iPhone", "Komputer / Windows 11", "Komputer / ChromeOS";
 * browser "Chrome 140", "Samsung Internet 28", "Instagram (dalam aplikasi)".
 *
 * Stage 19: profile() mengembalikan hasil uraian yang sama per kolom (jenis,
 * merek, model, OS + versi + arsitektur, browser + versi lengkap + jenis
 * klien, mesin browser) untuk halaman Pengunjung online, termasuk penanda
 * bot dan pustaka HTTP (curl, Python, ...) yang tidak dihitung sebagai
 * pengunjung.
 */
final class DeviceInfo
{
    public const UNKNOWN = 'Tidak diketahui';

    /**
     * Client Hints entropi tinggi yang diminta dari browser (header Accept-CH).
     */
    public const ACCEPT_CH = 'Sec-CH-UA-Platform-Version, Sec-CH-UA-Model, Sec-CH-UA-Full-Version-List';

    /**
     * Header Client Hints yang dibaca (entropi rendah dikirim otomatis).
     */
    private const HINT_HEADERS = [
        'Sec-CH-UA',
        'Sec-CH-UA-Mobile',
        'Sec-CH-UA-Platform',
        'Sec-CH-UA-Platform-Version',
        'Sec-CH-UA-Model',
        'Sec-CH-UA-Full-Version-List',
    ];

    /**
     * Jenis perangkat device-detector -> label UI.
     */
    private const CATEGORIES = [
        'smartphone'            => 'HP',
        'phablet'               => 'HP',
        'feature phone'         => 'HP',
        'tablet'                => 'Tablet',
        'desktop'               => 'Komputer',
        'tv'                    => 'TV',
        'smart display'         => 'Layar pintar',
        'console'               => 'Konsol game',
        'portable media player' => 'Pemutar media',
        'car browser'           => 'Mobil',
        'wearable'              => 'Jam pintar',
        'camera'                => 'Kamera',
        'smart speaker'         => 'Speaker pintar',
        'peripheral'            => 'Perangkat lain',
    ];

    private const OS_NAMES = [
        'Mac'       => 'macOS',
        'GNU/Linux' => 'Linux',
        'Chrome OS' => 'ChromeOS',
    ];

    /**
     * Nama browser versi HP disamakan dengan nama umumnya (jenis perangkat
     * sudah tertulis di kolom perangkat).
     */
    private const BROWSER_NAMES = [
        'Chrome Mobile'     => 'Chrome',
        'Chrome Mobile iOS' => 'Chrome',
        'Mobile Safari'     => 'Safari',
        'Firefox Mobile'    => 'Firefox',
        'Firefox iOS'       => 'Firefox',
        'Opera Mobile'      => 'Opera',
        'Microsoft Edge'    => 'Edge',
        'Samsung Browser'   => 'Samsung Internet',
        'Chrome Webview'    => 'WebView Android',
    ];

    private static ?DeviceDetectorCache $cache = null;

    /**
     * @return array{device: string, browser: string}
     */
    public static function fromRequest(IncomingRequest $request): array
    {
        return self::detect($request->getUserAgent()->getAgentString(), self::hints($request));
    }

    /**
     * Header Client Hints yang dikirim browser (nama huruf kecil => nilai).
     *
     * @return array<string, string>
     */
    public static function hints(IncomingRequest $request): array
    {
        $hints = [];

        foreach (self::HINT_HEADERS as $name) {
            $value = trim($request->getHeaderLine($name));

            if ($value !== '') {
                $hints[strtolower($name)] = mb_substr($value, 0, 500);
            }
        }

        return $hints;
    }

    /**
     * Stage 19: hasil uraian per kolom (null = tidak diketahui).
     *
     * @param array<string, string> $hints Header Client Hints (nama huruf kecil => nilai)
     *
     * @return array{
     *     bot: string|null, client_type: string|null, device_type: string|null,
     *     device_brand: string|null, device_model: string|null,
     *     os_name: string|null, os_version: string|null, os_platform: string|null,
     *     browser_name: string|null, browser_version: string|null,
     *     engine: string|null, engine_version: string|null,
     *     device: string, browser: string
     * }
     */
    public static function profile(string $userAgent, array $hints = []): array
    {
        $userAgent = trim($userAgent);
        $profile   = [
            'bot'             => null,
            'client_type'     => null,
            'device_type'     => null,
            'device_brand'    => null,
            'device_model'    => null,
            'os_name'         => null,
            'os_version'      => null,
            'os_platform'     => null,
            'browser_name'    => null,
            'browser_version' => null,
            'engine'          => null,
            'engine_version'  => null,
            'device'          => self::UNKNOWN,
            'browser'         => self::UNKNOWN,
        ];

        $detector = self::parse($userAgent, $hints);

        if ($detector === null) {
            return $profile;
        }

        if ($detector->isBot()) {
            $bot = $detector->getBot();

            $profile['bot']     = self::clip(is_array($bot) && ($bot['name'] ?? '') !== '' ? (string) $bot['name'] : 'Bot', 64);
            $profile['device']  = 'Bot';
            $profile['browser'] = $profile['bot'];

            return $profile;
        }

        $category = self::category($detector);
        [$osName, $osVersion] = self::osParts($detector, $userAgent, $hints);
        $client = $detector->getClient();
        $client = is_array($client) ? $client : [];
        $name   = (string) ($client['name'] ?? '');

        $profile['client_type']  = self::value($client['type'] ?? null, 32);
        $profile['device_type']  = $category;
        $profile['device_brand'] = self::value($detector->getBrandName(), 64);
        $profile['device_model'] = self::value($detector->getModel(), 100);
        $profile['os_name']      = self::value($osName, 64);
        $profile['os_version']   = self::value($osVersion, 32);
        $profile['os_platform']  = self::value($detector->getOs('platform'), 16);

        if ($name !== '' && $name !== DeviceDetector::UNKNOWN) {
            $profile['browser_name']    = self::value(self::BROWSER_NAMES[$name] ?? $name, 64);
            $profile['browser_version'] = self::value($client['version'] ?? null, 32);
            $profile['engine']          = self::value($client['engine'] ?? null, 64);
            $profile['engine_version']  = self::value($client['engine_version'] ?? null, 32);
        }

        $profile['device']  = self::device($detector, $userAgent, $hints);
        $profile['browser'] = self::browser($detector);

        return $profile;
    }

    /**
     * @param array<string, string> $hints Header Client Hints (nama huruf kecil => nilai)
     *
     * @return array{device: string, browser: string}
     */
    public static function detect(string $userAgent, array $hints = []): array
    {
        $userAgent = trim($userAgent);
        $unknown   = ['device' => self::UNKNOWN, 'browser' => self::UNKNOWN];
        $detector  = self::parse($userAgent, $hints);

        if ($detector === null) {
            return $unknown;
        }

        if ($detector->isBot()) {
            $bot = $detector->getBot();

            return [
                'device'  => 'Bot',
                'browser' => self::clip(is_array($bot) && ($bot['name'] ?? '') !== '' ? (string) $bot['name'] : self::UNKNOWN),
            ];
        }

        return [
            'device'  => self::device($detector, $userAgent, $hints),
            'browser' => self::browser($detector),
        ];
    }

    private static function parse(string $userAgent, array $hints): ?DeviceDetector
    {
        if ($userAgent === '') {
            return null;
        }

        try {
            $detector = new DeviceDetector($userAgent, ClientHints::factory($hints));
            $detector->setCache(self::cache());
            $detector->parse();
        } catch (Throwable) {
            return null;
        }

        return $detector;
    }

    private static function category(DeviceDetector $detector): ?string
    {
        $category = self::CATEGORIES[$detector->getDeviceName()] ?? null;

        // Jenis tidak dikenali (mis. Opera Mini): tebak dari OS & browser.
        return $category ?? ($detector->isDesktop() ? 'Komputer' : ($detector->isMobile() ? 'HP' : null));
    }

    private static function device(DeviceDetector $detector, string $userAgent, array $hints): string
    {
        $category = self::category($detector);

        $parts = array_filter(
            [$category, self::os($detector, $userAgent, $hints), self::model($detector, $category)],
            static fn (?string $part): bool => $part !== null && $part !== '',
        );

        return $parts === [] ? self::UNKNOWN : self::clip(implode(' / ', $parts));
    }

    private static function os(DeviceDetector $detector, string $userAgent, array $hints): ?string
    {
        [$name, $version] = self::osParts($detector, $userAgent, $hints);

        return $name === null ? null : trim($name . ' ' . $version);
    }

    /**
     * [nama tampilan, versi rilis ('' bila tidak dapat dipercaya)].
     *
     * @return array{0: string|null, 1: string}
     */
    private static function osParts(DeviceDetector $detector, string $userAgent, array $hints): array
    {
        $name = (string) $detector->getOs('name');

        if ($name === '' || $name === DeviceDetector::UNKNOWN) {
            return [null, ''];
        }

        $version = (string) $detector->getOs('version');

        // Versi yang dibekukan browser di User-Agent bukan versi asli; tanpa
        // Client Hints tidak ditampilkan (Windows NT 10.0 = Windows 10 atau 11).
        if (trim($hints['sec-ch-ua-platform-version'] ?? '', "\" \t") === '') {
            if ($name === 'Android' && preg_match('/Android 10; K[;)]/', $userAgent) === 1) {
                $version = '';
            } elseif ($name === 'Mac' && str_starts_with($version, '10.15')) {
                $version = '';
            } elseif ($name === 'Windows' && $version === '10') {
                $version = '10/11';
            }
        }

        // ChromeOS di User-Agent hanya nomor build (14541.0), Linux lewat Client
        // Hints hanya versi kernel (6.8.0): keduanya bukan versi rilis.
        if ($name === 'Chrome OS' || $name === 'GNU/Linux') {
            $version = '';
        }

        $version = (string) preg_replace('/(\.0)+$/', '', $version);

        return [self::OS_NAMES[$name] ?? $name, $version === DeviceDetector::UNKNOWN ? '' : $version];
    }

    private static function model(DeviceDetector $detector, ?string $category): ?string
    {
        $brand = trim($detector->getBrandName());
        $model = trim($detector->getModel());

        if ($model === '') {
            // Merek saja hanya berarti untuk HP/tablet (komputer Mac = "Apple").
            return in_array($category, ['HP', 'Tablet'], true) && $brand !== '' ? $brand : null;
        }

        if ($brand === '' || stripos($model, $brand) === 0) {
            return $model;
        }

        return $brand . ' ' . $model;
    }

    private static function browser(DeviceDetector $detector): string
    {
        $client = $detector->getClient();

        if (! is_array($client) || ($client['name'] ?? '') === '' || $client['name'] === DeviceDetector::UNKNOWN) {
            return self::UNKNOWN;
        }

        $name = self::BROWSER_NAMES[$client['name']] ?? (string) $client['name'];

        // Browser bawaan aplikasi (Instagram, Facebook, TikTok, ...).
        if (($client['type'] ?? '') === 'mobile app') {
            return self::clip($name . ' (dalam aplikasi)');
        }

        return self::clip(trim($name . ' ' . self::majorVersion((string) ($client['version'] ?? ''))));
    }

    private static function cache(): DeviceDetectorCache|StaticCache
    {
        try {
            return self::$cache ??= new DeviceDetectorCache(service('cache'));
        } catch (Throwable) {
            return new StaticCache();
        }
    }

    private static function majorVersion(string $version): string
    {
        return preg_match('/^\d+/', $version, $match) === 1 ? $match[0] : '';
    }

    private static function clip(string $value, int $length = 255): string
    {
        return mb_substr($value, 0, $length);
    }

    /**
     * Nilai kolom profil: teks dipangkas, kosong / "UNK" menjadi null.
     */
    private static function value(mixed $value, int $length): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' || $value === DeviceDetector::UNKNOWN ? null : self::clip($value, $length);
    }
}
