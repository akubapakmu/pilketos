<?php

namespace App\Libraries;

use App\Models\SettingModel;
use Throwable;

/**
 * Stage 24: kalimat yang diketik bergantian di tengah hero halaman utama
 * (home.js HeroType). Diatur admin di menu "Teks Halaman Utama"
 * (admin/halaman-utama): satu baris textarea = satu kalimat. Disimpan di
 * tabel settings (kunci hero_lines) sebagai teks dipisah baris baru; isi
 * awalnya diisi migration 2026-10-02-000001.
 */
final class HeroText
{
    public const SETTING = 'hero_lines';

    /**
     * Batas isian admin: jumlah kalimat dan panjang satu kalimat (karakter).
     * Kalimat hero sengaja singkat agar tetap besar di layar HP.
     */
    public const MAX_LINES  = 20;
    public const MAX_LENGTH = 80;

    /**
     * Kalimat tersimpan, siap ditampilkan. [] = hero tanpa teks (dikosongkan
     * admin, atau tabel settings belum dimigrasi: beranda tetap tampil).
     *
     * @return list<string>
     */
    public static function lines(): array
    {
        try {
            return self::parse(model(SettingModel::class)->value(self::SETTING));
        } catch (Throwable $e) {
            log_message('error', 'Teks hero halaman utama tidak dapat dibaca: {message}', ['message' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * Isi textarea admin menjadi daftar kalimat: satu baris = satu kalimat,
     * spasi dirapatkan, karakter kontrol & tak terlihat dibuang, baris kosong
     * diabaikan. Nama sekolah selalu "SMP 1 DAWE" (07-TYPOGRAPHY §3).
     *
     * @return list<string>
     */
    public static function parse(mixed $text): array
    {
        if (! is_string($text) || trim($text) === '') {
            return [];
        }

        $lines = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $line = (string) preg_replace('/\s+/u', ' ', $line);
            $line = (string) preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $line);
            $line = trim((string) preg_replace('/\bSMP\s*1\s*DAWE\b/iu', 'SMP 1 DAWE', $line));

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * Pesan galat isian admin; [] = valid (termasuk kosong).
     *
     * @param list<string> $lines Hasil parse()
     *
     * @return list<string>
     */
    public static function errors(array $lines): array
    {
        $errors = [];

        if (count($lines) > self::MAX_LINES) {
            $errors[] = sprintf('Maksimal %d kalimat (terisi %d).', self::MAX_LINES, count($lines));
        }

        foreach ($lines as $i => $line) {
            $length = mb_strlen($line);

            if ($length > self::MAX_LENGTH) {
                $errors[] = sprintf('Kalimat ke-%d terlalu panjang: %d karakter (maks. %d).', $i + 1, $length, self::MAX_LENGTH);
            }
        }

        return $errors;
    }

    /**
     * Kalimat untuk ditampilkan di hero: "SMP 1 DAWE" tidak dipenggal di
     * tengah baris (spasi tak terpisah). Simpanan tetap memakai spasi biasa.
     */
    public static function display(string $line): string
    {
        return str_replace('SMP 1 DAWE', "SMP\u{00A0}1\u{00A0}DAWE", $line);
    }

    /**
     * Bentuk simpanan (dan isi textarea): kalimat dipisah baris baru.
     *
     * @param list<string> $lines
     */
    public static function text(array $lines): string
    {
        return implode("\n", $lines);
    }
}
