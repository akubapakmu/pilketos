<?php

use App\Libraries\AssetMinifier;
use CodeIgniter\I18n\Time;

/**
 * Helper tampilan bersama aplikasi (dimuat global via app/Config/Autoload.php).
 */
if (! function_exists('format_waktu')) {
    /**
     * Format DATETIME database (zona Asia/Jakarta) ke teks Indonesia,
     * contoh: "22 September 2026, 08.00 WIB".
     */
    function format_waktu(?string $datetime, string $pattern = 'd MMMM yyyy, HH.mm'): string
    {
        if ($datetime === null || $datetime === '') {
            return '-';
        }

        return Time::parse($datetime)->toLocalizedString($pattern) . ' WIB';
    }
}

if (! function_exists('election_status_label')) {
    /**
     * Label status election untuk UI. Status selalu disertai teks,
     * tidak hanya warna (aksesibilitas).
     */
    function election_status_label(?string $status): string
    {
        return match ($status) {
            'UPCOMING' => 'Belum Dibuka',
            'ONGOING'  => 'Sedang Berlangsung',
            'FINISHED' => 'Sudah Selesai',
            default    => 'Belum Dijadwalkan',
        };
    }
}

if (! function_exists('election_clock')) {
    /**
     * Jam server untuk countdown (milidetik epoch). Browser hanya menghitung
     * mundur dari selisih waktu server ini; status tetap diputuskan server.
     *
     * @return array{status: string|null, now: int, start: int|null, end: int|null}
     */
    function election_clock(?array $election): array
    {
        $clock = [
            'status' => $election['status'] ?? null,
            'now'    => Time::now()->getTimestamp() * 1000,
            'start'  => null,
            'end'    => null,
        ];

        if ($election !== null) {
            $clock['start'] = Time::parse($election['start_at'])->getTimestamp() * 1000;
            $clock['end']   = Time::parse($election['end_at'])->getTimestamp() * 1000;
        }

        return $clock;
    }
}

if (! function_exists('asset_url')) {
    /**
     * URL file statis di public/ dengan penanda versi (?v=waktu ubah file),
     * contoh asset_url('assets/css/app.css') -> ".../assets/css/app.css?v=1790000000".
     *
     * Stage 4: public/.htaccess mengizinkan browser menyimpan asset lebih lama
     * (hemat Wi-Fi sekolah saat ratusan pemilih membuka halaman bersamaan);
     * penanda versi berubah otomatis setiap file diperbarui sehingga browser
     * tidak memakai CSS/JS lama setelah aplikasi di-update.
     *
     * Stage 23: CSS/JS di public/assets adalah hasil build ter-minify +
     * watermark dari resources/assets (App\Libraries\AssetMinifier, dibangun
     * ulang otomatis bila sumbernya lebih baru); penanda versi = waktu ubah
     * file yang benar-benar disajikan.
     */
    function asset_url(string $path): string
    {
        static $assets = [];
        static $minifier = null;

        $path = ltrim($path, '/');

        if (! array_key_exists($path, $assets)) {
            $minifier ??= new AssetMinifier();
            $served = $minifier->assetPath($path);
            $file   = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $served);

            $assets[$path] = [$served, is_file($file) ? (string) filemtime($file) : null];
        }

        [$served, $version] = $assets[$path];

        return base_url($served) . ($version === null ? '' : '?v=' . $version);
    }
}

if (! function_exists('asset_size')) {
    /**
     * Ukuran intrinsik gambar di public/ sebagai [lebar, tinggi] untuk atribut
     * width/height (mencegah layout bergeser saat gambar dimuat), atau null.
     * SVG dibaca dari viewBox; format raster lewat getimagesize(). Dipakai
     * logo beranda agar logo pengganti (Config\Homepage) tidak perlu ukuran manual.
     *
     * @return array{0: int, 1: int}|null
     */
    function asset_size(string $path): ?array
    {
        static $sizes = [];

        $path = ltrim($path, '/');

        if (array_key_exists($path, $sizes)) {
            return $sizes[$path];
        }

        $file = FCPATH . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $size = null;

        if (is_file($file)) {
            if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg') {
                $head = (string) file_get_contents($file, false, null, 0, 4096);

                if (preg_match('/viewBox\s*=\s*["\']\s*[-\d.]+[\s,]+[-\d.]+[\s,]+([\d.]+)[\s,]+([\d.]+)\s*["\']/i', $head, $m) === 1) {
                    $size = [(int) round((float) $m[1]), (int) round((float) $m[2])];
                }
            } elseif (($info = @getimagesize($file)) !== false) {
                $size = [(int) $info[0], (int) $info[1]];
            }
        }

        return $sizes[$path] = ($size !== null && $size[0] > 0 && $size[1] > 0) ? $size : null;
    }
}

if (! function_exists('angka')) {
    /**
     * Bilangan bulat format Indonesia, contoh 1234 menjadi "1.234".
     */
    function angka(int|float|string|null $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }
}

if (! function_exists('persen')) {
    /**
     * Persentase format Indonesia, contoh 45.25 menjadi "45,3%".
     */
    function persen(int|float|string|null $value, int $decimals = 1): string
    {
        return number_format((float) $value, $decimals, ',', '.') . '%';
    }
}

if (! function_exists('durasi')) {
    /**
     * Lama waktu singkat (Stage 19), contoh 45 -> "45 detik",
     * 1500 -> "25 menit", 7500 -> "2 jam 5 menit", 190000 -> "2 hari 4 jam".
     */
    function durasi(int $seconds): string
    {
        $seconds = max(0, $seconds);

        if ($seconds < 60) {
            return $seconds . ' detik';
        }

        $minutes = intdiv($seconds, 60);

        if ($minutes < 60) {
            return $minutes . ' menit';
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 24) {
            return $hours . ' jam' . ($minutes % 60 > 0 ? ' ' . ($minutes % 60) . ' menit' : '');
        }

        return intdiv($hours, 24) . ' hari' . ($hours % 24 > 0 ? ' ' . ($hours % 24) . ' jam' : '');
    }
}

if (! function_exists('waktu_lalu')) {
    /**
     * Waktu relatif dari jam server (Stage 19), contoh "baru saja",
     * "12 detik lalu", "5 menit lalu", "2 jam 5 menit lalu".
     */
    function waktu_lalu(?string $datetime): string
    {
        if ($datetime === null || $datetime === '') {
            return '-';
        }

        $seconds = \CodeIgniter\I18n\Time::now()->getTimestamp() - \CodeIgniter\I18n\Time::parse($datetime)->getTimestamp();

        return $seconds < 5 ? 'baru saja' : durasi($seconds) . ' lalu';
    }
}

if (! function_exists('device_summary')) {
    /**
     * Ringkasan perangkat untuk tabel admin (Stage 11) dari device_info
     * "Kategori / OS / Merek Model" (DeviceInfo) + browser_info.
     *
     * main: model HP bila ada (paling khas), selain itu kategori + OS;
     * sub : sisa keterangan + browser, contoh "HP · Android 14 · Chrome 140".
     * kind: phone|tablet|monitor|null untuk ikon.
     *
     * @return array{main: string, sub: string, kind: string|null}
     */
    function device_summary(?string $device, ?string $browser): array
    {
        $device  = trim((string) $device);
        $browser = trim((string) $browser);
        $parts   = $device === '' ? [] : array_values(array_filter(array_map('trim', explode(' / ', $device)), static fn (string $p): bool => $p !== ''));
        $kind    = match ($parts[0] ?? '') {
            'HP'       => 'phone',
            'Tablet'   => 'tablet',
            'Komputer' => 'monitor',
            default    => null,
        };

        if (count($parts) >= 3) {
            $main = implode(' / ', array_slice($parts, 2));
            $sub  = [$parts[0], $parts[1]];
        } else {
            $main = $parts === [] ? '-' : implode(' · ', $parts);
            $sub  = [];
        }

        if ($browser !== '') {
            $sub[] = $browser;
        }

        return ['main' => $main, 'sub' => implode(' · ', $sub), 'kind' => $kind];
    }
}

if (! function_exists('icon')) {
    /**
     * Ikon SVG inline (garis 1.75px, currentColor) agar gaya ikon konsisten
     * tanpa library ikon eksternal dan tanpa emoji. Dekoratif (aria-hidden);
     * makna selalu disampaikan juga lewat teks di sebelahnya.
     */
    function icon(string $name, string $class = ''): string
    {
        $paths = [
            'arrow-right' => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
            'arrow-down'  => '<path d="M12 5v14"/><path d="m6 13 6 6 6-6"/>',
            'arrow-left'  => '<path d="M19 12H5"/><path d="m11 6-6 6 6 6"/>',
            'lock'        => '<rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
            'unlock'      => '<rect x="5" y="11" width="14" height="9" rx="1.5"/><path d="M8 11V8a4 4 0 0 1 7.6-1.8"/>',
            'check'       => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
            'alert'       => '<path d="M12 4 2.8 19.5h18.4Z"/><path d="M12 10v4.5"/><path d="M12 17.2v.3"/>',
            'info'        => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5"/><path d="M12 8v.01"/>',
            'clock'       => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
            'nail'        => '<path d="M6.5 4.5h11"/><path d="M9 4.5 10 7h4l1-2.5"/><path d="M11 7v10.5L12 21l1-3.5V7"/>',
            'layers'      => '<path d="m12 4 8.5 4.5L12 13 3.5 8.5Z"/><path d="m3.5 12.5 8.5 4.5 8.5-4.5"/>',
            // Panel admin (Stage 3)
            'grid'     => '<rect x="4" y="4" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="4" width="6.5" height="6.5" rx="1"/><rect x="4" y="13.5" width="6.5" height="6.5" rx="1"/><rect x="13.5" y="13.5" width="6.5" height="6.5" rx="1"/>',
            'chart'    => '<path d="M4 20h16"/><path d="M7 16v-5"/><path d="M12 16V6"/><path d="M17 16v-8"/>',
            'list'     => '<path d="M9 6h11"/><path d="M9 12h11"/><path d="M9 18h11"/><path d="M4.5 6h.01"/><path d="M4.5 12h.01"/><path d="M4.5 18h.01"/>',
            'flag'     => '<path d="M5 21V4"/><path d="M5 4.5h11.5l-2 4 2 4H5"/>',
            'users'    => '<circle cx="9" cy="8.5" r="3.5"/><path d="M3 19.5c.8-3.2 3.2-5 6-5s5.2 1.8 6 5"/><path d="M15.5 5.3a3.5 3.5 0 0 1 0 6.4"/><path d="M17.5 14.8c1.8.6 3 2.2 3.5 4.7"/>',
            'user'     => '<circle cx="12" cy="8" r="3.75"/><path d="M4.5 20c.9-3.7 3.9-6 7.5-6s6.6 2.3 7.5 6"/>',
            'calendar' => '<rect x="4" y="5.5" width="16" height="14.5" rx="1.5"/><path d="M4 10h16"/><path d="M8.5 3.5v4"/><path d="M15.5 3.5v4"/>',
            'file'     => '<path d="M14 3.5H7A1.5 1.5 0 0 0 5.5 5v14A1.5 1.5 0 0 0 7 20.5h10a1.5 1.5 0 0 0 1.5-1.5V8Z"/><path d="M14 3.5V8h4.5"/><path d="M9 12.5h6"/><path d="M9 16h6"/>',
            'upload'   => '<path d="M12 15V4"/><path d="m7 8.5 5-5 5 5"/><path d="M4.5 15.5v3a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-3"/>',
            'download' => '<path d="M12 4v11"/><path d="m7 10.5 5 5 5-5"/><path d="M4.5 15.5v3a1.5 1.5 0 0 0 1.5 1.5h12a1.5 1.5 0 0 0 1.5-1.5v-3"/>',
            'search'   => '<circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.4-4.4"/>',
            'menu'     => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
            'close'    => '<path d="M6 6l12 12"/><path d="M18 6 6 18"/>',
            'trash'    => '<path d="M4.5 7h15"/><path d="M9.5 7V4.5h5V7"/><path d="M6.5 7l1 13h9l1-13"/>',
            'edit'     => '<path d="M4.5 19.5l1-4L15 6a2.1 2.1 0 0 1 3 3l-9.5 9.5Z"/><path d="m13.5 7.5 3 3"/>',
            'eye'      => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>',
            'image'    => '<rect x="3.5" y="4.5" width="17" height="15" rx="1.5"/><circle cx="9" cy="9.5" r="1.75"/><path d="m20.5 16-5-5-9 8.5"/>',
            'refresh'  => '<path d="M19.5 12a7.5 7.5 0 1 1-2.2-5.3"/><path d="M19.5 4.5v4h-4"/>',
            'external' => '<path d="M14 4.5h5.5V10"/><path d="M19.5 4.5 11 13"/><path d="M17 14v4.5a1 1 0 0 1-1 1H5.5a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1H10"/>',
            'plus'     => '<path d="M12 5v14"/><path d="M5 12h14"/>',
            'logout'   => '<path d="M14 4.5H6.5A1.5 1.5 0 0 0 5 6v12a1.5 1.5 0 0 0 1.5 1.5H14"/><path d="M10 12h10"/><path d="m16.5 8.5 3.5 3.5-3.5 3.5"/>',
            // Hasil akhir (Stage 4)
            'award'    => '<circle cx="12" cy="9" r="5.5"/><path d="m8.6 13.4-1.6 7.1 5-2.6 5 2.6-1.6-7.1"/>',
            'expand'   => '<path d="M4.5 9V4.5H9"/><path d="M15 4.5h4.5V9"/><path d="M19.5 15v4.5H15"/><path d="M9 19.5H4.5V15"/>',
            'printer'  => '<path d="M7 9V4.5h10V9"/><rect x="4" y="9" width="16" height="7.5" rx="1.5"/><path d="M7 14h10v5.5H7Z"/>',
            // Stage 24: menu Teks Halaman Utama
            'type'     => '<path d="M5 7.5V5h14v2.5"/><path d="M12 5v14"/><path d="M9 19h6"/>',
            // Beranda imersif (redesign beranda)
            'arrow-up-right' => '<path d="M7 17 17 7"/><path d="M8.5 7H17v8.5"/>',
            'arrow-up'       => '<path d="M12 19V5"/><path d="m6 11 6-6 6 6"/>',
            'chevron-down'   => '<path d="m6 9.5 6 6 6-6"/>',
            // Stage 11: indikator live count (siaran) & jenis perangkat
            'live'    => '<circle class="icon__core" cx="12" cy="12" r="2" fill="currentColor"/><path class="icon__wave icon__wave--in" d="M8.6 8.6a4.8 4.8 0 0 0 0 6.8"/><path class="icon__wave icon__wave--in" d="M15.4 8.6a4.8 4.8 0 0 1 0 6.8"/><path class="icon__wave icon__wave--out" d="M5.6 5.6a9 9 0 0 0 0 12.8"/><path class="icon__wave icon__wave--out" d="M18.4 5.6a9 9 0 0 1 0 12.8"/>',
            'phone'   => '<rect x="7" y="3" width="10" height="18" rx="2"/><path d="M11 17.5h2"/>',
            'tablet'  => '<rect x="4.5" y="3" width="15" height="18" rx="2"/><path d="M11 17.5h2"/>',
            'monitor' => '<rect x="3" y="4.5" width="18" height="12" rx="1.5"/><path d="M9 20h6"/><path d="M12 16.5V20"/>',
            // Stage 13: menu Halaman Utama, akun admin, lihat kata sandi
            'home'    => '<path d="M4 10.5 12 4l8 6.5"/><path d="M6 9v10.5h12V9"/><path d="M10 19.5V14h4v5.5"/>',
            'key'     => '<circle cx="8" cy="15" r="3.5"/><path d="m10.5 12.5 9-9"/><path d="m16 7 2.5 2.5"/><path d="m18.5 4.5 2 2"/>',
            'eye-off' => '<path d="M4 4l16 16"/><path d="M10.6 5.6A10 10 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-2.6 3.4"/><path d="M6.6 6.9C3.9 8.6 2.5 12 2.5 12S6 18.5 12 18.5a9.6 9.6 0 0 0 4.4-1"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
            // Stage 15: putar video kampanye (profil paslon)
            'play'    => '<path d="M8 5.5v13l10.5-6.5Z" fill="currentColor"/>',
        ];

        if (! isset($paths[$name])) {
            return '';
        }

        $classAttr = trim('icon icon--' . $name . ' ' . $class);

        return '<svg class="' . esc($classAttr, 'attr') . '" viewBox="0 0 24 24" width="24" height="24" fill="none" '
            . 'stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" '
            . 'aria-hidden="true" focusable="false">' . $paths[$name] . '</svg>';
    }
}
