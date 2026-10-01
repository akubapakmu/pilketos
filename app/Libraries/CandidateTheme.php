<?php

namespace App\Libraries;

use App\Models\CandidateModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Mengubah baris `candidates` menjadi data tampilan dengan identitas visual
 * per pasangan (MASTER section 6, Stage 2 "candidate-specific theme").
 *
 * Sumber tema (semua diisi admin di Stage 3):
 * - theme_accent : warna solid #RRGGBB (tanpa gradient);
 * - theme_asset  : JSON {hero, banner, poster, closing} berisi nama file;
 * - foto_ketua / foto_wakil.
 * Stage 15 (halaman profil paslon): slogan, bio, karakter (maks. 3 kata),
 * program unggulan {title, text}, pesan ketua/wakil, dan video YouTube
 * (hanya ID; URL embed selalu youtube-nocookie.com).
 * Stage 16: satu tampilan untuk semua pasangan. Layout per pasangan, latar
 * panggung, tekstur/pola, dan artwork dihapus; profil dibuka gambar khusus
 * (banner) dan ditutup gambar latar (closing). Video memakai thumbnail asli
 * YouTube (i.ytimg.com).
 * Asset yang belum diunggah tidak membuat halaman rusak: foto diganti monogram
 * inisial, gambar pembuka diganti judul sederhana.
 *
 * Semua URL dibuat lewat CandidateModel::assetUrl() (basename + rawurlencode),
 * dan warna aksen divalidasi ketat sehingga aman dipakai di atribut style.
 */
final class CandidateTheme
{
    /**
     * Kunci theme_asset (Stage 16): hero = foto berdua (beranda & hasil akhir),
     * banner = gambar pembuka profil, poster, closing = latar penutup profil.
     */
    public const ASSET_KEYS = ['hero', 'banner', 'poster', 'closing'];

    /**
     * Stage 15: embed YouTube mode privasi (cookie baru dipasang saat diputar).
     * Satu-satunya asal frame yang diizinkan CSP, dan hanya di halaman yang
     * memuat video (lihat allowVideo()).
     */
    public const VIDEO_ORIGIN = 'https://www.youtube-nocookie.com';

    /**
     * Stage 16: thumbnail asli video YouTube, dimuat sebelum tombol putar
     * ditekan. Hanya halaman yang memuat video yang mengizinkan asal ini di
     * img-src (lihat allowVideo()).
     */
    public const THUMB_ORIGIN = 'https://i.ytimg.com';

    /**
     * Stage 21: nama file unduhan poster kampanye (nomor urut dua digit),
     * contoh paslon-osis-no-02-spensada-2026.webp. Ekstensi mengikuti file.
     */
    public const POSTER_DOWNLOAD = 'paslon-osis-no-%s-spensada-2026';

    /**
     * Kata karakter yang ditampilkan per orang.
     */
    public const MAX_TAGS = 3;

    /**
     * Porsi warna aksen pada --accent-soft (sisanya putih): tint solid untuk
     * panel, kartu, dan pill di halaman profil.
     */
    public const SOFT_MIX = 0.12;

    private const INK      = '#15141A';
    private const PAPER    = '#FAF9F6';
    private const WHITE    = '#FFFFFF';
    private const FALLBACK = self::INK;

    /**
     * @return array<string, mixed>
     */
    public static function present(array $candidate): array
    {
        $number = (int) ($candidate['nomor_urut'] ?? 0);
        $accent = self::accent($candidate['theme_accent'] ?? null);
        $assets = is_array($candidate['theme_asset'] ?? null) ? $candidate['theme_asset'] : [];
        $ketua  = trim((string) ($candidate['nama_ketua'] ?? ''));
        $wakil  = trim((string) ($candidate['nama_wakil'] ?? ''));

        $theme = [
            'id'             => (int) ($candidate['id'] ?? 0),
            'number'         => $number,
            'label'          => sprintf('%02d', $number),
            'ketua'          => $ketua,
            'wakil'          => $wakil,
            'ketua_initials' => self::initials($ketua),
            'wakil_initials' => self::initials($wakil),
            'visi'           => trim((string) ($candidate['visi'] ?? '')),
            'misi'           => self::misiItems($candidate['misi'] ?? null),
            'theme_name'     => trim((string) ($candidate['theme_name'] ?? '')) ?: sprintf('Pasangan %02d', $number),
            'accent'         => $accent,
            'accent_ink'     => self::inkOn($accent),
            'accent_text'    => self::textOnPaper($accent),
            'accent_soft'    => self::soft($accent),
            'photo_ketua'    => self::url($candidate['foto_ketua'] ?? null),
            'photo_wakil'    => self::url($candidate['foto_wakil'] ?? null),
            'slogan'         => self::text($candidate['slogan'] ?? null),
            'bio_ketua'      => self::text($candidate['bio_ketua'] ?? null),
            'bio_wakil'      => self::text($candidate['bio_wakil'] ?? null),
            'karakter_ketua' => self::tags($candidate['karakter_ketua'] ?? null),
            'karakter_wakil' => self::tags($candidate['karakter_wakil'] ?? null),
            'program'        => self::programItems($candidate['program'] ?? null),
            'pesan_ketua'    => self::text($candidate['pesan_ketua'] ?? null),
            'pesan_wakil'    => self::text($candidate['pesan_wakil'] ?? null),
            'video'          => self::youtubeId((string) ($candidate['video_youtube'] ?? '')),
        ];

        $theme['video_url']   = $theme['video'] === null ? null : 'https://www.youtube.com/watch?v=' . $theme['video'];
        $theme['video_embed'] = $theme['video'] === null ? null : self::VIDEO_ORIGIN . '/embed/' . $theme['video'] . '?autoplay=1&rel=0';
        // Stage 16: hqdefault selalu tersedia (480x360); maxresdefault (1280x720)
        // hanya untuk video beresolusi tinggi, dicoba paslon.js setelah halaman dimuat.
        $theme['video_thumb']    = $theme['video'] === null ? null : self::THUMB_ORIGIN . '/vi/' . $theme['video'] . '/hqdefault.jpg';
        $theme['video_thumb_hd'] = $theme['video'] === null ? null : self::THUMB_ORIGIN . '/vi/' . $theme['video'] . '/maxresdefault.jpg';

        // Stage 8: nama tema kosong jatuh ke "Pasangan 0X"; tampilan pemilih
        // tidak menulisnya dua kali di samping label "Pasangan 0X".
        $theme['theme_distinct'] = strcasecmp($theme['theme_name'], 'Pasangan ' . $theme['label']) !== 0;

        foreach (self::ASSET_KEYS as $key) {
            $theme[$key] = self::url($assets[$key] ?? null);
        }

        // Ukuran asli gambar pembuka untuk atribut width/height (tampil utuh,
        // tanpa dipotong): ruang disiapkan sebelum gambar selesai dimuat.
        $theme['banner_size'] = $theme['banner'] === null ? null : self::imageSize($assets['banner']);

        // Stage 21: nama file saat poster diunduh (atribut download).
        $theme['poster_download'] = $theme['poster'] === null ? null : self::posterDownload($theme['label'], (string) $assets['poster']);

        $theme['style'] = self::style($theme);

        return $theme;
    }

    /**
     * @param list<array<string, mixed>> $candidates
     *
     * @return list<array<string, mixed>>
     */
    public static function presentAll(array $candidates): array
    {
        return array_values(array_map(self::present(...), $candidates));
    }

    /**
     * Izinkan video paslon HANYA untuk respons halaman yang memuatnya:
     * - Stage 15: iframe YouTube mode privasi (frame-src youtube-nocookie.com);
     * - Stage 16: thumbnail asli video (img-src i.ytimg.com).
     * Halaman lain tetap frame-src 'none' dan img-src tanpa asal luar
     * (Config\ContentSecurityPolicy).
     */
    public static function allowVideo(ResponseInterface $response): void
    {
        $csp = $response->getCSP();
        $csp->clearDirective('frame-src');
        $csp->addFrameSrc(self::VIDEO_ORIGIN);
        $csp->addImageSrc(self::THUMB_ORIGIN);
    }

    /**
     * Custom property CSS untuk elemen bertema kandidat. Nilai sudah tervalidasi
     * (#RRGGBB), sehingga aman ditaruh di atribut style. --accent-soft (Stage 16)
     * dihitung dari aksen: tint solid untuk panel, kartu, dan pill.
     */
    public static function style(array $theme): string
    {
        return sprintf(
            '--accent: %s; --accent-ink: %s; --accent-text: %s; --accent-soft: %s;',
            $theme['accent'],
            $theme['accent_ink'],
            $theme['accent_text'],
            self::soft($theme['accent']),
        );
    }

    /**
     * Tint solid aksen di atas putih (SOFT_MIX aksen), format #RRGGBB.
     */
    public static function soft(string $accent): string
    {
        $channels = [];

        foreach ([1, 3, 5] as $offset) {
            $channels[] = (int) round(hexdec(substr($accent, $offset, 2)) * self::SOFT_MIX + 255 * (1 - self::SOFT_MIX));
        }

        return vsprintf('#%02X%02X%02X', $channels);
    }

    /**
     * Warna aksen solid tervalidasi, atau ink bila kosong/tidak valid.
     */
    public static function accent(mixed $value): string
    {
        if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', trim($value)) === 1) {
            return strtoupper(trim($value));
        }

        return self::FALLBACK;
    }

    /**
     * Warna teks di atas bidang aksen: putih atau ink, mana yang kontrasnya lebih tinggi.
     */
    public static function inkOn(string $accent): string
    {
        return self::contrast($accent, self::WHITE) >= self::contrast($accent, self::INK)
            ? self::WHITE
            : self::INK;
    }

    /**
     * Warna teks aksen di atas kertas: aksen bila kontras >= 3:1, selain itu ink.
     */
    public static function textOnPaper(string $accent): string
    {
        return self::contrast($accent, self::PAPER) >= 3.0 ? $accent : self::INK;
    }

    /**
     * Rasio kontras WCAG 2.x antara dua warna #RRGGBB.
     */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * Beda warna yang dirasakan mata (CIEDE2000) antara dua warna #RRGGBB:
     * 0 = identik, < ±2 hampir tidak terlihat, >= 20 jelas berbeda.
     * Dipakai aturan netralitas beranda (06-VISUAL-DIRECTION §5.1): warna
     * identitas sekolah tidak boleh mirip warna aksen pasangan mana pun.
     */
    public static function deltaE(string $a, string $b): float
    {
        return self::deltaE2000(self::lab($a), self::lab($b));
    }

    /**
     * CIEDE2000 (Sharma, Wu & Dalal 2005) antara dua warna CIELAB [L, a, b].
     *
     * @param array{0: float, 1: float, 2: float} $lab1
     * @param array{0: float, 1: float, 2: float} $lab2
     */
    public static function deltaE2000(array $lab1, array $lab2): float
    {
        [$l1, $a1, $b1] = $lab1;
        [$l2, $a2, $b2] = $lab2;

        $cBar = (hypot($a1, $b1) + hypot($a2, $b2)) / 2;
        $g    = 0.5 * (1 - sqrt($cBar ** 7 / ($cBar ** 7 + 25 ** 7)));
        $a1p  = (1 + $g) * $a1;
        $a2p  = (1 + $g) * $a2;
        $c1p  = hypot($a1p, $b1);
        $c2p  = hypot($a2p, $b2);
        $h1p  = $c1p == 0.0 ? 0.0 : fmod(rad2deg(atan2($b1, $a1p)) + 360, 360);
        $h2p  = $c2p == 0.0 ? 0.0 : fmod(rad2deg(atan2($b2, $a2p)) + 360, 360);

        $dLp = $l2 - $l1;
        $dCp = $c2p - $c1p;
        $dhp = 0.0;

        if ($c1p * $c2p != 0.0) {
            $dhp = $h2p - $h1p;
            $dhp += $dhp > 180 ? -360 : ($dhp < -180 ? 360 : 0);
        }

        $dHp = 2 * sqrt($c1p * $c2p) * sin(deg2rad($dhp / 2));

        $lBarP = ($l1 + $l2) / 2;
        $cBarP = ($c1p + $c2p) / 2;
        $hBarP = $h1p + $h2p;

        if ($c1p * $c2p != 0.0) {
            $hBarP = abs($h1p - $h2p) <= 180
                ? ($h1p + $h2p) / 2
                : (($h1p + $h2p < 360) ? ($h1p + $h2p + 360) / 2 : ($h1p + $h2p - 360) / 2);
        }

        $t = 1 - 0.17 * cos(deg2rad($hBarP - 30)) + 0.24 * cos(deg2rad(2 * $hBarP))
            + 0.32 * cos(deg2rad(3 * $hBarP + 6)) - 0.20 * cos(deg2rad(4 * $hBarP - 63));
        $dTheta = 30 * exp(-((($hBarP - 275) / 25) ** 2));
        $rc     = 2 * sqrt($cBarP ** 7 / ($cBarP ** 7 + 25 ** 7));
        $sl     = 1 + (0.015 * ($lBarP - 50) ** 2) / sqrt(20 + ($lBarP - 50) ** 2);
        $sc     = 1 + 0.045 * $cBarP;
        $sh     = 1 + 0.015 * $cBarP * $t;
        $rt     = -sin(deg2rad(2 * $dTheta)) * $rc;

        return sqrt(
            ($dLp / $sl) ** 2 + ($dCp / $sc) ** 2 + ($dHp / $sh) ** 2
            + $rt * ($dCp / $sc) * ($dHp / $sh),
        );
    }

    /**
     * Pecah teks misi per baris dan buang penomoran manual ("1.", "2)", "-", "*").
     *
     * @return list<string>
     */
    public static function misiItems(mixed $misi): array
    {
        if (! is_string($misi) || trim($misi) === '') {
            return [];
        }

        $items = [];

        foreach (preg_split('/\R/u', $misi) as $line) {
            $line = trim((string) preg_replace('/^\s*(?:\d{1,2}\s*[.)]|[-*\x{2022}\x{2013}])\s*/u', '', $line));

            if ($line !== '') {
                $items[] = $line;
            }
        }

        return $items;
    }

    /**
     * Program unggulan (Stage 15): satu program per baris, judul dan
     * penjelasan dipisah "—", " – ", " - ", ": " atau "|". Baris tanpa
     * pemisah menjadi judul saja. Penomoran manual dibuang seperti misi.
     *
     * @return list<array{title: string, text: string}>
     */
    public static function programItems(mixed $program): array
    {
        $items = [];

        foreach (self::misiItems($program) as $line) {
            $parts = preg_split('/\s*\x{2014}\s*|\s+[\x{2013}-]\s+|\s*\|\s*|:\s+/u', $line, 2);
            $title = trim((string) ($parts[0] ?? ''));
            $text  = trim((string) ($parts[1] ?? ''));

            if ($title === '') {
                [$title, $text] = [$text, ''];
            }

            if ($title !== '') {
                $items[] = ['title' => $title, 'text' => $text];
            }
        }

        return $items;
    }

    /**
     * Kata karakter (Stage 15): dipisah koma, titik tengah, titik koma, atau
     * baris baru; duplikat dibuang; maksimal MAX_TAGS kata.
     *
     * @return list<string>
     */
    public static function tags(mixed $value): array
    {
        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $tags = [];

        foreach (preg_split('/[,;\x{00B7}\x{2022}\r\n]+/u', $value) as $tag) {
            $tag = trim((string) preg_replace('/\s+/u', ' ', $tag));

            if ($tag !== '' && ! in_array(mb_strtolower($tag), array_map('mb_strtolower', $tags), true)) {
                $tags[] = $tag;
            }

            if (count($tags) === self::MAX_TAGS) {
                break;
            }
        }

        return $tags;
    }

    /**
     * ID video YouTube (11 karakter) dari tautan yang ditempel admin:
     * youtube.com/watch?v=ID, youtu.be/ID, youtube.com/shorts/ID,
     * youtube.com/embed/ID, youtube-nocookie.com/embed/ID, atau ID saja.
     * Tautan lain (situs lain, playlist tanpa v=, teks bebas) = null.
     */
    public static function youtubeId(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input) === 1) {
            return $input;
        }

        if (! str_contains($input, '://')) {
            $input = 'https://' . $input;
        }

        $parts = parse_url($input);
        $host  = strtolower((string) ($parts['host'] ?? ''));
        $path  = (string) ($parts['path'] ?? '');

        if (! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }

        $host = preg_replace('/^(?:www\.|m\.|music\.)/', '', $host);
        $id   = null;

        if ($host === 'youtu.be') {
            $id = explode('/', ltrim($path, '/'))[0] ?? null;
        } elseif ($host === 'youtube.com' || $host === 'youtube-nocookie.com') {
            if (preg_match('#^/(?:shorts|embed|live|v)/([^/?]+)#', $path, $match) === 1) {
                $id = $match[1];
            } elseif ($path === '/watch' || $path === '/watch/') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $id = is_string($query['v'] ?? null) ? $query['v'] : null;
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) === 1 ? $id : null;
    }

    /**
     * Jumlah kolom grid yang seimbang untuk n pasangan (Stage 15, batas
     * pasangan dapat diatur 2-10): n <= $max tampil satu baris; selebihnya
     * baris sesedikit mungkin dengan isi baris serata mungkin
     * (maks. 4: 5 -> 3+2, 6 -> 3+3, 7 -> 4+3, 9 -> 3+3+3).
     */
    public static function columns(int $count, int $max = 4): int
    {
        $max = max(1, $max);

        if ($count <= $max) {
            return max(1, $count);
        }

        return (int) ceil($count / (int) ceil($count / $max));
    }

    /**
     * Custom property grid untuk daftar pasangan: --cols (layar lebar, maks. 4)
     * dan --cols-md (tablet, maks. 3).
     */
    public static function gridStyle(int $count): string
    {
        return sprintf('--cols: %d; --cols-md: %d;', self::columns($count), self::columns($count, 3));
    }

    /**
     * Inisial dua kata pertama, contoh "Arka Wibisana" menjadi "AW".
     */
    public static function initials(string $name): string
    {
        $letters = '';

        foreach (preg_split('/\s+/u', trim($name)) as $word) {
            if (preg_match('/\p{L}/u', $word, $match) === 1) {
                $letters .= mb_strtoupper($match[0]);
            }

            if (mb_strlen($letters) === 2) {
                break;
            }
        }

        return $letters === '' ? '?' : $letters;
    }

    /**
     * Teks bebas yang dirapikan (spasi di tepi, baris baru seragam).
     */
    private static function text(mixed $value): string
    {
        return is_string($value) ? trim(str_replace(["\r\n", "\r"], "\n", $value)) : '';
    }

    private static function url(mixed $filename): ?string
    {
        return is_string($filename) ? CandidateModel::assetUrl($filename) : null;
    }

    /**
     * Nama file unduhan poster: POSTER_DOWNLOAD + ekstensi file asli
     * (jpg/jpeg/png/webp hasil unggah; selain itu jpg).
     */
    public static function posterDownload(string $label, string $filename): string
    {
        $extension = strtolower(pathinfo(basename($filename), PATHINFO_EXTENSION));

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        return sprintf(self::POSTER_DOWNLOAD, $label) . '.' . $extension;
    }

    /**
     * [lebar, tinggi] file gambar di folder upload kandidat, atau null bila
     * file tidak ada / tidak terbaca. Nama file selalu lewat basename().
     *
     * @return array{0: int, 1: int}|null
     */
    private static function imageSize(mixed $filename): ?array
    {
        if (! is_string($filename) || basename($filename) === '') {
            return null;
        }

        $file = service('candidateAssets')->directory() . DIRECTORY_SEPARATOR . basename($filename);
        $info = is_file($file) ? @getimagesize($file) : false;

        return $info === false || $info[0] <= 0 || $info[1] <= 0 ? null : [(int) $info[0], (int) $info[1]];
    }

    private static function luminance(string $hex): float
    {
        $channels = [];

        foreach ([1, 3, 5] as $offset) {
            $c          = hexdec(substr($hex, $offset, 2)) / 255;
            $channels[] = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * #RRGGBB (sRGB) -> CIELAB [L, a, b] dengan titik putih D65.
     *
     * @return array{0: float, 1: float, 2: float}
     */
    private static function lab(string $hex): array
    {
        $rgb = [];

        foreach ([1, 3, 5] as $offset) {
            $c     = hexdec(substr($hex, $offset, 2)) / 255;
            $rgb[] = $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }

        [$r, $g, $b] = $rgb;
        $xyz         = [
            (0.4124564 * $r + 0.3575761 * $g + 0.1804375 * $b) / 0.95047,
            (0.2126729 * $r + 0.7151522 * $g + 0.0721750 * $b) / 1.0,
            (0.0193339 * $r + 0.1191920 * $g + 0.9503041 * $b) / 1.08883,
        ];
        $f = static fn (float $t): float => $t > 216 / 24389 ? $t ** (1 / 3) : (24389 / 27 * $t + 16) / 116;

        [$fx, $fy, $fz] = array_map($f, $xyz);

        return [116 * $fy - 16, 500 * ($fx - $fy), 200 * ($fy - $fz)];
    }
}
