<?php

namespace App\Libraries;

use GdImage;

/**
 * Pemrosesan gambar unggahan bersama (Stage 26, diekstrak dari
 * CandidateAssets Stage 3): deteksi isi file, decode GD, orientasi EXIF foto
 * HP, perkiraan memori, dan satuan ukuran. Pesan galat tetap milik pemanggil
 * (CandidateAssets untuk admin, VoterAvatars untuk siswa/guru).
 */
final class ImageProcessor
{
    /**
     * MIME yang diterima => ekstensi yang cocok.
     */
    public const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    /**
     * Tipe getimagesize() => MIME.
     */
    public const IMAGE_TYPES = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG  => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    public const MAX_SIDE   = 8000;
    public const MAX_PIXELS = 40_000_000;

    private const MB = 1024 * 1024;

    /**
     * GD server dapat menulis WebP.
     */
    public static function webp(): bool
    {
        return function_exists('imagewebp') && (imagetypes() & IMG_WEBP) !== 0;
    }

    /**
     * MIME dari ISI file (finfo), bukan dari nama file.
     */
    public static function detectMime(string $path): string
    {
        return (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);
    }

    /**
     * Ukuran [lebar, tinggi] dari getimagesize() bila tipenya sama dengan
     * $mime; null bila rusak atau tipe berbeda.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function dimensions(string $path, string $mime): ?array
    {
        $info = @getimagesize($path);

        if ($info === false || (self::IMAGE_TYPES[$info[2]] ?? null) !== $mime) {
            return null;
        }

        return [(int) $info[0], (int) $info[1]];
    }

    public static function decode(string $path, string $mime): GdImage|false
    {
        @ini_set('gd.jpeg_ignore_warning', '1');

        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default      => false,
        };
    }

    /**
     * Terapkan orientasi EXIF foto HP (setelah encode ulang EXIF hilang,
     * jadi rotasi harus dilakukan pada pikselnya).
     */
    public static function orient(GdImage $image, string $path, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $image;
        }

        $exif        = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $steps = match ($orientation) {
            2       => [null, IMG_FLIP_HORIZONTAL],
            3       => [180, null],
            4       => [null, IMG_FLIP_VERTICAL],
            5       => [270, IMG_FLIP_HORIZONTAL],
            6       => [270, null],
            7       => [90, IMG_FLIP_HORIZONTAL],
            8       => [90, null],
            default => [null, null],
        };

        [$angle, $flip] = $steps;

        if ($angle !== null) {
            $rotated = imagerotate($image, $angle, 0);

            if ($rotated instanceof GdImage) {
                $image = $rotated;
            }
        }

        if ($flip !== null) {
            imageflip($image, $flip);
        }

        return $image;
    }

    /**
     * Perkiraan memori GD (4 byte/piksel sumber + hasil, ditambah cadangan).
     * Bila memory_limit terlalu kecil, dinaikkan sementara bila diizinkan.
     * false = tetap tidak cukup (pemanggil menolak dengan pesan jelas, bukan
     * fatal error).
     */
    public static function reserveMemory(int $width, int $height, int $outputPixels): bool
    {
        $limit = self::iniBytes((string) ini_get('memory_limit'));

        if ($limit <= 0) {
            return true;
        }

        $needed = (int) (($width * $height + $outputPixels) * 4 * 1.8) + 16 * self::MB;
        $usage  = memory_get_usage(true);

        if ($usage + $needed <= $limit) {
            return true;
        }

        $target = (int) ceil(($usage + $needed) / self::MB) + 16;

        return @ini_set('memory_limit', $target . 'M') !== false
            && self::iniBytes((string) ini_get('memory_limit')) >= $usage + $needed;
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes >= self::MB) {
            return rtrim(rtrim(number_format($bytes / self::MB, 1, ',', '.'), '0'), ',') . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
    }

    /**
     * "2M" / "512K" / "1G" (php.ini) menjadi byte.
     */
    public static function iniBytes(string $value): int
    {
        $value = trim($value);

        if ($value === '' || $value === '-1') {
            return 0;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g'     => $number * 1024 * self::MB,
            'm'     => $number * self::MB,
            'k'     => $number * 1024,
            default => $number,
        };
    }
}
