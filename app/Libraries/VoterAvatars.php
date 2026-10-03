<?php

namespace App\Libraries;

use App\Services\VoterType;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\I18n\Time;
use Config\Database;
use GdImage;

/**
 * Avatar pemilih untuk Living Election Wall (Stage 26): foto unggahan atau
 * avatar DiceBear pilihan (gaya + seed). Satu-satunya penulis kolom avatar_*
 * di students/teachers (kolom itu tidak ada di allowedFields model, jadi impor
 * dan form admin tidak dapat menyentuhnya).
 *
 * Foto melewati validasi berlapis seperti foto kandidat (CandidateAssets):
 * ukuran byte, ekstensi, MIME dari isi file (finfo), getimagesize() (tipe,
 * dimensi min/maks, jumlah piksel), lalu di-decode GD, diluruskan menurut
 * EXIF, dipotong persegi di tengah, diperkecil ke 320 × 320, dan DI-ENCODE
 * ULANG (WebP q80; JPEG q85 bila GD tanpa WebP) sehingga metadata (lokasi GPS)
 * dan konten sisipan hilang. Nama file selalu dibuat server
 * (s{id}-{16hex}.webp / t{id}-...), ditulis ke file sementara lalu rename.
 * Folder public/uploads/avatars dilindungi public/uploads/.htaccess.
 *
 * Tidak dikunci status pemilihan: avatar tidak memengaruhi hasil.
 */
final class VoterAvatars
{
    public const UPLOAD_DIR = 'uploads/avatars';

    /**
     * Sisi hasil encode ulang (px, persegi).
     */
    public const EDGE = 320;

    /**
     * Sisi terpendek minimal foto sumber (px).
     */
    public const MIN_SIDE = 128;

    /**
     * Batas file yang diterima server (browser sudah mengompres ke ±512 px).
     */
    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * Batas file asli yang boleh dipilih di browser sebelum dikompres.
     */
    public const CLIENT_MAX_BYTES = 15 * 1024 * 1024;

    /**
     * Perubahan avatar per pemilih: 10 per 10 menit (Throttler).
     */
    public const THROTTLE_CAPACITY = 10;
    public const THROTTLE_SECONDS  = 600;

    /**
     * Nama file avatar yang sah (dibuat server).
     */
    public const FILE_PATTERN = '/^[st][1-9][0-9]{0,9}-[a-f0-9]{16}\.(webp|jpg)$/';

    /**
     * Isi file yang diterima di formulir (accept). HEIC/HEIF hanya dapat
     * dibuka browser yang mendukungnya; server tidak pernah menerima HEIC.
     */
    public const ACCEPT = 'image/jpeg,image/png,image/webp,image/heic,image/heif';

    /**
     * Latar untuk foto ber-transparansi (sama dengan latar kartu wall terang).
     */
    private const FLATTEN_RGB = [0xEC, 0xEB, 0xE6];

    private string $directory;

    private BaseConnection $db;

    public function __construct(?string $directory = null, ?BaseConnection $db = null)
    {
        $this->directory = rtrim($directory ?? FCPATH . self::UPLOAD_DIR, '\\/');
        $this->db        = $db ?? Database::connect();
    }

    public function directory(): string
    {
        return $this->directory;
    }

    /**
     * Batas efektif: 2 MB atau upload_max_filesize server, mana yang lebih kecil.
     */
    public static function maxBytes(): int
    {
        $server = ImageProcessor::iniBytes((string) ini_get('upload_max_filesize'));

        return $server > 0 ? min(self::MAX_BYTES, $server) : self::MAX_BYTES;
    }

    public static function isFileName(mixed $filename): bool
    {
        return is_string($filename) && preg_match(self::FILE_PATTERN, $filename) === 1;
    }

    /**
     * Avatar siap tampil untuk satu baris students/teachers.
     *
     * - photo   : URL foto, atau null (file tidak ada / tidak sah);
     * - style   : gaya DiceBear (pilihan pemilih atau bawaan);
     * - seed    : seed DiceBear (pilihan pemilih atau bawaan);
     * - initials: inisial nama (tampilan tanpa JavaScript);
     * - custom  : pemilih sudah mengunggah foto atau memilih avatar sendiri.
     *
     * @return array{photo: string|null, style: string, seed: string, initials: string, custom: bool}
     */
    public function present(VoterType $type, array $voter): array
    {
        $file   = $voter['avatar_file'] ?? null;
        $photo  = self::isFileName($file) && is_file($this->directory . DIRECTORY_SEPARATOR . $file)
            ? base_url(self::UPLOAD_DIR . '/' . $file)
            : null;
        $chosen = AvatarIdentity::isStyle($voter['avatar_style'] ?? null) && AvatarIdentity::isSeed($voter['avatar_seed'] ?? null);
        $art    = $chosen
            ? ['style' => (string) $voter['avatar_style'], 'seed' => (string) $voter['avatar_seed']]
            : AvatarIdentity::default($type, (int) $voter['id']);

        return [
            'photo'    => $photo,
            'style'    => $art['style'],
            'seed'     => $art['seed'],
            'initials' => CandidateTheme::initials((string) ($voter['name'] ?? '')),
            'custom'   => $photo !== null || $chosen,
        ];
    }

    /**
     * Sisa detik tunggu bila pemilih ini sudah 10 kali mengubah avatar dalam
     * 10 menit terakhir; 0 = boleh (satu jatah terpakai).
     */
    public function throttle(VoterType $type, int $id): int
    {
        $throttler = service('throttler');

        if ($throttler->check('avatar_' . $type->value . '_' . $id, self::THROTTLE_CAPACITY, self::THROTTLE_SECONDS)) {
            return 0;
        }

        return max(1, $throttler->getTokenTime());
    }

    /**
     * Validasi unggahan HTTP lalu simpan sebagai avatar pemilih.
     *
     * @throws AvatarException
     */
    public function upload(VoterType $type, int $id, UploadedFile $file): array
    {
        match ($file->getError()) {
            UPLOAD_ERR_OK => null,
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => throw new AvatarException(
                'Foto melebihi batas ukuran unggah server (' . ImageProcessor::formatBytes(self::maxBytes()) . ').',
            ),
            UPLOAD_ERR_NO_FILE => throw new AvatarException('Pilih foto lebih dulu.'),
            UPLOAD_ERR_PARTIAL => throw new AvatarException('Foto terunggah tidak lengkap. Coba unggah ulang.'),
            default            => throw new AvatarException('Foto gagal diunggah (kode ' . $file->getError() . ').'),
        };

        if (! $file->isValid()) {
            throw new AvatarException('Foto bukan unggahan yang valid.');
        }

        return $this->processFile($type, $id, $file->getTempName(), $file->getClientExtension(), (int) $file->getSize());
    }

    /**
     * Validasi isi file, encode ulang 320 × 320, simpan, lalu catat di
     * database (foto lama dihapus, pilihan avatar DiceBear dikosongkan).
     *
     * @throws AvatarException
     */
    public function processFile(VoterType $type, int $id, string $path, string $clientExtension, int $size): array
    {
        if ($size <= 0 || ! is_file($path)) {
            throw new AvatarException('Foto kosong.');
        }

        if ($size > self::maxBytes()) {
            throw new AvatarException(sprintf(
                'Foto maksimal %s (file ini %s). Perkecil foto lalu unggah ulang.',
                ImageProcessor::formatBytes(self::maxBytes()),
                ImageProcessor::formatBytes($size),
            ));
        }

        $extension = strtolower(trim($clientExtension));

        if (in_array($extension, ['heic', 'heif'], true)) {
            throw new AvatarException('Foto HEIC belum dapat diproses di server. Pilih foto JPG, PNG, atau WebP.');
        }

        if (! in_array($extension, array_merge(...array_values(ImageProcessor::MIME_EXTENSIONS)), true)) {
            throw new AvatarException('Foto harus berupa JPG, PNG, atau WebP.');
        }

        $mime = ImageProcessor::detectMime($path);

        if (! in_array($extension, ImageProcessor::MIME_EXTENSIONS[$mime] ?? [], true)) {
            throw new AvatarException('Isi file bukan foto JPG, PNG, atau WebP yang sesuai dengan namanya.');
        }

        [$width, $height] = ImageProcessor::dimensions($path, $mime)
            ?? throw new AvatarException('Foto rusak atau tidak dapat dibaca.');

        if (min($width, $height) < self::MIN_SIDE) {
            throw new AvatarException(sprintf('Foto terlalu kecil (%d × %d px). Minimal %d × %d px.', $width, $height, self::MIN_SIDE, self::MIN_SIDE));
        }

        if ($width > ImageProcessor::MAX_SIDE || $height > ImageProcessor::MAX_SIDE || $width * $height > ImageProcessor::MAX_PIXELS) {
            throw new AvatarException(sprintf('Foto terlalu besar (%d × %d px). Maksimal %s px per sisi.', $width, $height, angka(ImageProcessor::MAX_SIDE)));
        }

        if (! ImageProcessor::reserveMemory($width, $height, self::EDGE * self::EDGE)) {
            throw new AvatarException(sprintf('Foto beresolusi terlalu tinggi untuk diproses server (%d × %d px). Perkecil foto lalu unggah ulang.', $width, $height));
        }

        $image = ImageProcessor::decode($path, $mime);

        if (! $image instanceof GdImage) {
            throw new AvatarException('Foto rusak atau tidak dapat dibaca.');
        }

        $name = $this->save($this->square(ImageProcessor::orient($image, $path, $mime)), $type, $id);

        try {
            return $this->write($type, $id, ['avatar_file' => $name, 'avatar_style' => null, 'avatar_seed' => null]);
        } catch (AvatarException $e) {
            $this->deleteFile($name);

            throw $e;
        }
    }

    /**
     * Simpan avatar DiceBear pilihan pemilih; foto (bila ada) dihapus.
     *
     * @throws AvatarException
     */
    public function choose(VoterType $type, int $id, mixed $style, mixed $seed): array
    {
        if (! AvatarIdentity::isStyle($style) || ! AvatarIdentity::isSeed($seed)) {
            throw new AvatarException('Pilihan avatar tidak valid. Acak avatar lalu coba lagi.');
        }

        return $this->write($type, $id, ['avatar_file' => null, 'avatar_style' => $style, 'avatar_seed' => $seed]);
    }

    /**
     * Kembali ke avatar bawaan: foto & pilihan avatar dihapus.
     *
     * @throws AvatarException
     */
    public function reset(VoterType $type, int $id): array
    {
        return $this->write($type, $id, ['avatar_file' => null, 'avatar_style' => null, 'avatar_seed' => null]);
    }

    /**
     * Hapus satu file avatar (hanya nama file avatar yang sah di folder ini).
     */
    public function deleteFile(?string $filename): bool
    {
        if (! self::isFileName($filename)) {
            return false;
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . $filename;

        return is_file($path) && @unlink($path);
    }

    /**
     * Ubah kolom avatar dalam transaction (baris pemilih dikunci FOR UPDATE
     * agar dua perubahan bersamaan tidak meninggalkan file yatim), lalu
     * hapus foto lama setelah commit.
     *
     * @param array{avatar_file: string|null, avatar_style: string|null, avatar_seed: string|null} $values
     *
     * @throws AvatarException
     */
    private function write(VoterType $type, int $id, array $values): array
    {
        $table = $type->voterTable();

        $this->db->transBegin();

        try {
            $row = $this->db->query(
                'SELECT id, name, avatar_file FROM ' . $this->db->escapeIdentifiers($this->db->prefixTable($table)) . ' WHERE id = ? FOR UPDATE',
                [$id],
            )->getRowArray();

            if ($row === null) {
                throw new AvatarException('Akun pemilih tidak ditemukan.');
            }

            $this->db->table($table)->where('id', $id)->update($values + [
                'avatar_updated_at' => Time::now()->toDateTimeString(),
            ]);

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();

            throw $e instanceof AvatarException ? $e : new AvatarException('Avatar gagal disimpan. Coba lagi sebentar lagi.', 0, $e);
        }

        if (($row['avatar_file'] ?? null) !== null && $row['avatar_file'] !== $values['avatar_file']) {
            $this->deleteFile($row['avatar_file']);
        }

        return $this->present($type, $values + $row);
    }

    /**
     * Potong persegi di tengah lalu perkecil ke 320 × 320 di atas latar
     * terang (foto ber-transparansi tidak menjadi hitam).
     */
    private function square(GdImage $image): GdImage
    {
        $width  = imagesx($image);
        $height = imagesy($image);
        $side   = min($width, $height);

        $canvas = imagecreatetruecolor(self::EDGE, self::EDGE);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, ...self::FLATTEN_RGB));
        imagealphablending($canvas, true);
        imagecopyresampled(
            $canvas,
            $image,
            0,
            0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            self::EDGE,
            self::EDGE,
            $side,
            $side,
        );

        return $canvas;
    }

    private function save(GdImage $image, VoterType $type, int $id): string
    {
        if (! is_dir($this->directory) && ! @mkdir($this->directory, 0775, true) && ! is_dir($this->directory)) {
            throw new AvatarException('Folder foto avatar tidak dapat dibuat di server.');
        }

        $webp = ImageProcessor::webp();
        $name = sprintf('%s-%s.%s', AvatarIdentity::key($type, $id), bin2hex(random_bytes(8)), $webp ? 'webp' : 'jpg');
        $tmp  = $this->directory . DIRECTORY_SEPARATOR . '.tmp-' . bin2hex(random_bytes(6));

        $written = $webp ? imagewebp($image, $tmp, 80) : imagejpeg($image, $tmp, 85);

        if (! $written || ! rename($tmp, $this->directory . DIRECTORY_SEPARATOR . $name)) {
            @unlink($tmp);

            throw new AvatarException('Foto gagal disimpan di server. Coba lagi sebentar lagi.');
        }

        @chmod($this->directory . DIRECTORY_SEPARATOR . $name, 0644);

        return $name;
    }
}
