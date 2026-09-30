<?php

namespace App\Libraries;

use App\Models\CandidateModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use GdImage;

/**
 * Unggahan foto & asset tema pasangan calon (Stage 3).
 *
 * Validasi berlapis sebelum file disimpan:
 * 1. status unggah PHP, is_uploaded_file(), ukuran byte;
 * 2. ekstensi asli file harus jpg/jpeg/png/webp;
 * 3. MIME dari ISI file (finfo) harus gambar yang sama dengan ekstensinya;
 * 4. getimagesize(): tipe cocok, dimensi minimum & maksimum, jumlah piksel;
 * 5. gambar di-decode GD lalu DI-ENCODE ULANG di server (WebP bila tersedia):
 *    metadata EXIF (termasuk lokasi GPS foto HP) terbuang, konten sisipan
 *    (polyglot) tidak ikut, orientasi foto HP diluruskan, dan resolusi
 *    diperkecil sesuai kebutuhan tampilan agar ringan di HP siswa.
 * Nama file SELALU dibuat server (acak), nama asli tidak pernah dipakai.
 * Folder public/uploads/candidates dilindungi public/uploads/.htaccess
 * (Stage 1: tanpa eksekusi skrip, tanpa daftar isi folder).
 */
final class CandidateAssets
{
    /**
     * MIME yang diterima => ekstensi yang cocok.
     */
    public const MIME_EXTENSIONS = [
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png'  => ['png'],
        'image/webp' => ['webp'],
    ];

    private const IMAGE_TYPES = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG  => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    public const MAX_SIDE   = 8000;
    public const MAX_PIXELS = 40_000_000;

    private const MB = 1024 * 1024;

    /**
     * Slot unggahan (urutan = urutan form admin). column = kolom candidates;
     * asset = kunci JSON theme_asset. edge = sisi terpanjang hasil (px);
     * min = [lebar, tinggi] minimum sumber; landscape = lebar harus lebih
     * besar dari tinggi (diperiksa setelah orientasi EXIF diluruskan).
     *
     * Stage 16: halaman profil paslon dibuka "Gambar pembuka profil" (banner,
     * tampil utuh tanpa dipotong) dan ditutup "Latar penutup" (closing).
     * Latar panggung, artwork, dan tekstur tidak dipakai lagi (dihapus).
     *
     * @var array<string, array{label: string, column: string|null, asset: string|null, min: array{0: int, 1: int}, edge: int, bytes: int, hint: string, landscape?: bool}>
     */
    public const SLOTS = [
        'foto_ketua' => [
            'label'  => 'Foto calon ketua',
            'column' => 'foto_ketua',
            'asset'  => null,
            'min'    => [240, 240],
            'edge'   => 1000,
            'bytes'  => 5 * self::MB,
            'hint'   => 'Potret tegak (rasio 4:5 ideal), wajah jelas, minimal 240 px.',
        ],
        'foto_wakil' => [
            'label'  => 'Foto calon wakil',
            'column' => 'foto_wakil',
            'asset'  => null,
            'min'    => [240, 240],
            'edge'   => 1000,
            'bytes'  => 5 * self::MB,
            'hint'   => 'Potret tegak (rasio 4:5 ideal), wajah jelas, minimal 240 px.',
        ],
        'banner' => [
            'label'     => 'Gambar pembuka profil',
            'column'    => null,
            'asset'     => 'banner',
            'min'       => [1200, 400],
            'edge'      => 2000,
            'bytes'     => 5 * self::MB,
            'hint'      => 'Gambar mendatar (landscape) buatan paslon, tampil utuh tanpa dipotong di paling atas halaman profil, di atas tombol Lihat Visi & Misi. Disarankan 1920 × 1080 px (16:9), lebar minimal 1200 px.',
            'landscape' => true,
        ],
        'poster' => [
            'label'  => 'Poster kampanye',
            'column' => null,
            'asset'  => 'poster',
            'min'    => [400, 400],
            'edge'   => 1600,
            'bytes'  => 5 * self::MB,
            'hint'   => 'Poster tegak, tampil di bagian Poster kampanye dan dapat dibuka layar penuh.',
        ],
        'closing' => [
            'label'  => 'Latar penutup',
            'column' => null,
            'asset'  => 'closing',
            'min'    => [1000, 500],
            'edge'   => 2000,
            'bytes'  => 5 * self::MB,
            'hint'   => 'Gambar latar bagian penutup halaman profil (nomor, nama, slogan, tombol). Dipotong otomatis dan diberi lapisan gelap agar teks putih tetap terbaca. Disarankan mendatar, minimal 1000 × 500 px.',
        ],
        'hero' => [
            'label'  => 'Foto pasangan (beranda & hasil)',
            'column' => null,
            'asset'  => 'hero',
            'min'    => [600, 400],
            'edge'   => 1600,
            'bytes'  => 5 * self::MB,
            'hint'   => 'Foto berdua, mendatar 4:3. Tampil di perolehan suara beranda dan hasil akhir; tanpa foto ini dipakai dua foto potret.',
        ],
    ];

    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = rtrim($directory ?? FCPATH . CandidateModel::UPLOAD_DIR, '\\/');
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Format file hasil encode ulang: WebP bila GD server mendukungnya;
     * bila tidak, JPEG tetap JPEG dan sisanya PNG (transparansi aman).
     */
    public static function outputExtension(string $sourceMime): string
    {
        if (function_exists('imagewebp') && (imagetypes() & IMG_WEBP) !== 0) {
            return 'webp';
        }

        return $sourceMime === 'image/jpeg' ? 'jpg' : 'png';
    }

    /**
     * Batas ukuran efektif: batas slot atau upload_max_filesize server, mana yang lebih kecil.
     */
    public static function maxBytes(string $slot): int
    {
        $server = self::iniBytes((string) ini_get('upload_max_filesize'));

        return $server > 0 ? min(self::SLOTS[$slot]['bytes'], $server) : self::SLOTS[$slot]['bytes'];
    }

    /**
     * Validasi unggahan HTTP lalu proses. Mengembalikan nama file tersimpan.
     *
     * @throws CandidateAssetException
     */
    public function store(UploadedFile $file, string $slot, int $candidateNumber): string
    {
        $label = self::SLOTS[$slot]['label'];

        match ($file->getError()) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => throw new CandidateAssetException(sprintf(
                '%s melebihi batas ukuran unggah server (%s).',
                $label,
                self::formatBytes(self::maxBytes($slot)),
            )),
            UPLOAD_ERR_PARTIAL => throw new CandidateAssetException($label . ' terunggah tidak lengkap. Coba unggah ulang.'),
            default            => throw new CandidateAssetException($label . ' gagal diunggah (kode ' . $file->getError() . ').'),
        };

        if (! $file->isValid()) {
            throw new CandidateAssetException($label . ' bukan unggahan yang valid.');
        }

        return $this->processFile($file->getTempName(), $file->getClientExtension(), (int) $file->getSize(), $slot, $candidateNumber);
    }

    /**
     * Validasi isi file gambar, encode ulang, dan simpan dengan nama acak.
     *
     * @throws CandidateAssetException
     */
    public function processFile(string $path, string $clientExtension, int $size, string $slot, int $candidateNumber): string
    {
        $config = self::SLOTS[$slot] ?? throw new CandidateAssetException('Slot asset tidak dikenal.');
        $label  = $config['label'];

        if ($size <= 0 || ! is_file($path)) {
            throw new CandidateAssetException($label . ' kosong.');
        }

        if ($size > self::maxBytes($slot)) {
            throw new CandidateAssetException(sprintf('%s maksimal %s (file ini %s).', $label, self::formatBytes(self::maxBytes($slot)), self::formatBytes($size)));
        }

        $extension = strtolower(trim($clientExtension));
        $allowed   = array_merge(...array_values(self::MIME_EXTENSIONS));

        if (! in_array($extension, $allowed, true)) {
            throw new CandidateAssetException($label . ' harus berupa JPG, PNG, atau WebP.');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! isset(self::MIME_EXTENSIONS[$mime])) {
            throw new CandidateAssetException($label . ' bukan gambar JPG/PNG/WebP (isi file terdeteksi ' . $mime . ').');
        }

        if (! in_array($extension, self::MIME_EXTENSIONS[$mime], true)) {
            throw new CandidateAssetException(sprintf('Ekstensi .%s tidak sesuai isi %s (%s).', $extension, strtolower($label), $mime));
        }

        $info = @getimagesize($path);

        if ($info === false || (self::IMAGE_TYPES[$info[2]] ?? null) !== $mime) {
            throw new CandidateAssetException($label . ' rusak atau tidak dapat dibaca sebagai gambar.');
        }

        [$width, $height] = $info;

        if ($width < $config['min'][0] || $height < $config['min'][1]) {
            throw new CandidateAssetException(sprintf(
                '%s terlalu kecil (%d × %d px). Minimal %d × %d px.',
                $label,
                $width,
                $height,
                $config['min'][0],
                $config['min'][1],
            ));
        }

        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE || $width * $height > self::MAX_PIXELS) {
            throw new CandidateAssetException(sprintf('%s terlalu besar (%d × %d px). Maksimal %d px per sisi.', $label, $width, $height, self::MAX_SIDE));
        }

        $this->ensureMemory($width, $height, $config['edge'], $label);

        $image = $this->decode($path, $mime);

        if (! $image instanceof GdImage) {
            throw new CandidateAssetException($label . ' rusak atau tidak dapat dibaca sebagai gambar.');
        }

        // GdImage dibebaskan otomatis saat tidak dipakai lagi (PHP 8+);
        // imagedestroy() sengaja tidak dipakai (deprecated sejak PHP 8.5).
        $image = $this->orient($image, $path, $mime);

        if (($config['landscape'] ?? false) && imagesx($image) <= imagesy($image)) {
            throw new CandidateAssetException(sprintf(
                '%s harus mendatar (landscape): lebar lebih besar dari tinggi, mis. 1920 × 1080 px. File ini %d × %d px.',
                $label,
                imagesx($image),
                imagesy($image),
            ));
        }

        $image = $this->downscale($image, $config['edge']);

        return $this->save($image, $mime, $slot, $candidateNumber);
    }

    /**
     * Hapus file asset lama (hanya nama file gambar di folder upload).
     */
    public function delete(?string $filename): void
    {
        if ($filename === null || $filename === '' || basename($filename) !== $filename || str_starts_with($filename, '.')) {
            return;
        }

        if (preg_match('/\.(jpe?g|png|webp)$/i', $filename) !== 1) {
            return;
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;

        if (is_file($path)) {
            @unlink($path);
        }
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

    private function decode(string $path, string $mime): GdImage|false
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
    private function orient(GdImage $image, string $path, string $mime): GdImage
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

    private function downscale(GdImage $image, int $edge): GdImage
    {
        $width  = imagesx($image);
        $height = imagesy($image);
        $scale  = min(1, $edge / max($width, $height));

        $targetWidth  = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        // Selalu digambar ulang ke kanvas truecolor baru (juga untuk PNG
        // palet), dengan kanal alpha dipertahankan.
        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    private function save(GdImage $image, string $sourceMime, string $slot, int $candidateNumber): string
    {
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw new CandidateAssetException('Folder upload kandidat tidak dapat dibuat.');
        }

        $ext  = self::outputExtension($sourceMime);
        $name = sprintf('c%02d-%s-%s.%s', max(0, min(99, $candidateNumber)), str_replace('_', '-', $slot), bin2hex(random_bytes(8)), $ext);
        $tmp  = $this->directory . DIRECTORY_SEPARATOR . '.tmp-' . bin2hex(random_bytes(6));

        $written = match ($ext) {
            'webp'  => imagewebp($image, $tmp, 82),
            'jpg'   => imagejpeg($image, $tmp, 85),
            default => imagepng($image, $tmp, 6),
        };

        if (! $written || ! rename($tmp, $this->directory . DIRECTORY_SEPARATOR . $name)) {
            @unlink($tmp);

            throw new CandidateAssetException('Gambar gagal disimpan di server. Periksa izin tulis folder public/uploads/candidates.');
        }

        @chmod($this->directory . DIRECTORY_SEPARATOR . $name, 0644);

        return $name;
    }

    /**
     * Perkiraan memori GD (4 byte/piksel sumber + hasil, ditambah cadangan).
     * Bila memory_limit terlalu kecil, dinaikkan sementara bila diizinkan;
     * bila tidak bisa, gambar ditolak dengan pesan jelas (bukan fatal error).
     */
    private function ensureMemory(int $width, int $height, int $edge, string $label): void
    {
        $limit = self::iniBytes((string) ini_get('memory_limit'));

        if ($limit <= 0) {
            return;
        }

        $scale  = min(1, $edge / max($width, $height));
        $needed = (int) (($width * $height + $width * $scale * $height * $scale) * 4 * 1.8) + 16 * self::MB;
        $usage  = memory_get_usage(true);

        if ($usage + $needed <= $limit) {
            return;
        }

        $target = (int) ceil(($usage + $needed) / self::MB) + 16;

        if (@ini_set('memory_limit', $target . 'M') === false || self::iniBytes((string) ini_get('memory_limit')) < $usage + $needed) {
            throw new CandidateAssetException(sprintf(
                '%s beresolusi terlalu tinggi untuk diproses server (%d × %d px). Perkecil gambar lalu unggah ulang.',
                $label,
                $width,
                $height,
            ));
        }
    }
}
