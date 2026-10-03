<?php

namespace App\Libraries;

use App\Services\VoterType;
use Config\Encryption;

/**
 * Identitas avatar pemilih (Stage 26, Living Election Wall).
 *
 * Avatar BAWAAN (pemilih belum mengunggah foto dan belum memilih avatar)
 * dihitung deterministik tanpa menulis database:
 *
 *   hash = HMAC-SHA256(kunci rahasia aplikasi, "s:{id}" | "t:{id}")
 *   seed = 16 heksa pertama hash
 *   gaya = salah satu dari tiga gaya DiceBear, dipilih dari 4 heksa berikutnya
 *
 * Hasilnya sama di setiap muat ulang, tetapi seed tidak dapat dibalik menjadi
 * id tanpa kunci rahasia. Kunci rahasia: encryption.key di .env bila diisi;
 * bila tidak, file acak writable/avatar.key yang dibuat sekali secara
 * otomatis (tidak dapat dibuka browser).
 *
 * Gambar avatar dibuat di browser (DiceBear, public/assets/vendor/
 * dicebear-9.4.2, resources/assets/js/avatar-art.js); server hanya
 * menyimpan/mengirim gaya + seed.
 */
final class AvatarIdentity
{
    /**
     * Gaya DiceBear yang dipakai (nilai kolom avatar_style => label).
     * Urutan tetap: dipakai untuk memilih gaya avatar bawaan dari hash.
     */
    public const STYLES = [
        'adventurer' => 'Adventurer',
        'avataaars'  => 'Avataaars',
        'big-smile'  => 'Big Smile',
    ];

    public const SEED_PATTERN = '/^[a-f0-9]{16}$/';

    private const KEY_FILE = 'avatar.key';

    private static ?string $secret = null;

    /**
     * Avatar bawaan pemilih: gaya + seed.
     *
     * @return array{style: string, seed: string}
     */
    public static function default(VoterType $type, int $id): array
    {
        $hash   = hash_hmac('sha256', self::prefix($type) . ':' . $id, self::secret());
        $styles = array_keys(self::STYLES);

        return [
            'style' => $styles[hexdec(substr($hash, 16, 4)) % count($styles)],
            'seed'  => substr($hash, 0, 16),
        ];
    }

    /**
     * Kunci anggota di Living Election Wall: "s12" (siswa) / "t3" (guru).
     */
    public static function key(VoterType $type, int $id): string
    {
        return self::prefix($type) . $id;
    }

    public static function isStyle(mixed $style): bool
    {
        return is_string($style) && isset(self::STYLES[$style]);
    }

    public static function isSeed(mixed $seed): bool
    {
        return is_string($seed) && preg_match(self::SEED_PATTERN, $seed) === 1;
    }

    /**
     * Gaya + seed acak dari server (formulir tanpa JavaScript; dengan
     * JavaScript seed dibuat browser lewat crypto.getRandomValues).
     *
     * @return array{style: string, seed: string}
     */
    public static function random(): array
    {
        $styles = array_keys(self::STYLES);

        return [
            'style' => $styles[random_int(0, count($styles) - 1)],
            'seed'  => bin2hex(random_bytes(8)),
        ];
    }

    /**
     * Kunci rahasia HMAC (lihat keterangan kelas).
     */
    public static function secret(): string
    {
        if (self::$secret !== null) {
            return self::$secret;
        }

        $key = (string) config(Encryption::class)->key;

        if ($key !== '') {
            return self::$secret = 'encryption:' . $key;
        }

        return self::$secret = self::fileSecret(WRITEPATH . self::KEY_FILE);
    }

    /**
     * Ganti kunci rahasia (test) atau kosongkan cache (null).
     */
    public static function useSecret(?string $secret): void
    {
        self::$secret = $secret;
    }

    private static function prefix(VoterType $type): string
    {
        return $type === VoterType::Student ? 's' : 't';
    }

    /**
     * Baca kunci dari file; buat sekali (64 heksa acak, mode 0600) bila belum
     * ada. Dua request bersamaan: file dibuat eksklusif (mode "x"), yang kalah
     * membaca milik pemenang. Folder tidak dapat ditulis (php spark osis:check
     * sudah memeriksanya): kunci cadangan dari path aplikasi, dicatat di log.
     */
    private static function fileSecret(string $file): string
    {
        $read = static function () use ($file): ?string {
            $value = is_file($file) ? trim((string) @file_get_contents($file)) : '';

            return preg_match('/^[a-f0-9]{64}$/', $value) === 1 ? $value : null;
        };

        if (($value = $read()) !== null) {
            return $value;
        }

        $handle = @fopen($file, 'xb');

        if ($handle !== false) {
            $value = bin2hex(random_bytes(32));
            fwrite($handle, $value);
            fclose($handle);
            @chmod($file, 0600);

            return $value;
        }

        if (($value = $read()) !== null) {
            return $value;
        }

        log_message('warning', 'Avatar: writable/' . self::KEY_FILE . ' tidak dapat dibuat; kunci avatar bawaan memakai cadangan.');

        return hash('sha256', 'avatar:' . ROOTPATH);
    }
}
