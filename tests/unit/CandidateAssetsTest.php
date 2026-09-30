<?php

use App\Libraries\CandidateAssetException;
use App\Libraries\CandidateAssets;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use Tests\Support\UploadFixture;

/**
 * Stage 3: validasi & pemrosesan unggahan asset kandidat.
 * Stage 16: slot gambar pembuka profil (wajib landscape) & latar penutup;
 * latar panggung, artwork, dan tekstur dihapus.
 *
 * @internal
 */
final class CandidateAssetsTest extends CIUnitTestCase
{
    private string $dir;

    /**
     * @var list<string>
     */
    private array $sources = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cand-assets-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/{,.}*', GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($this->dir);

        foreach ($this->sources as $file) {
            @unlink($file);
        }

        UploadFixture::reset();
        parent::tearDown();
    }

    private function assets(): CandidateAssets
    {
        return new CandidateAssets($this->dir);
    }

    /**
     * Gambar uji: kiri merah, kanan biru (untuk memeriksa rotasi).
     */
    private function image(int $width, int $height, string $type, bool $alpha = false): string
    {
        $image = imagecreatetruecolor($width, $height);

        if ($alpha) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
            imagefilledrectangle($image, 10, 10, $width - 10, $height - 10, imagecolorallocatealpha($image, 200, 20, 20, 0));
        } else {
            imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, imagecolorallocate($image, 220, 20, 20));
            imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, imagecolorallocate($image, 20, 20, 220));
        }

        $path = tempnam(sys_get_temp_dir(), 'img');
        match ($type) {
            'jpg'  => imagejpeg($image, $path, 90),
            'png'  => imagepng($image, $path),
            'webp' => imagewebp($image, $path),
        };

        return $this->sources[] = $path;
    }

    /**
     * Sisipkan segmen EXIF minimal berisi tag Orientation (seperti foto HP).
     */
    private function withOrientation(string $jpegPath, int $orientation): string
    {
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"
            . "\x00\x01"
            . "\x01\x12\x00\x03\x00\x00\x00\x01" . pack('n', $orientation) . "\x00\x00"
            . "\x00\x00\x00\x00";
        $app1 = "Exif\x00\x00" . $tiff;
        $data = (string) file_get_contents($jpegPath);

        file_put_contents($jpegPath, substr($data, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($data, 2));

        return $jpegPath;
    }

    private function process(string $path, string $extension, string $slot = 'foto_ketua'): string
    {
        return $this->assets()->processFile($path, $extension, (int) filesize($path), $slot, 1);
    }

    private function assertRejected(string $message, callable $action): void
    {
        try {
            $action();
            $this->fail('Unggahan seharusnya ditolak: ' . $message);
        } catch (CandidateAssetException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }

        $this->assertSame([], glob($this->dir . '/*') ?: [], 'Tidak boleh ada file tersimpan');
    }

    public function testValidImageIsReencodedWithRandomNameAndDownscaled(): void
    {
        $name = $this->process($this->image(1600, 2000, 'png', true), 'PNG');

        // WebP bila GD mendukung; tanpa WebP, PNG tetap PNG.
        $ext = CandidateAssets::outputExtension('image/png');
        $this->assertMatchesRegularExpression('/^c01-foto-ketua-[a-f0-9]{16}\.' . $ext . '$/', $name);
        $stored = $this->dir . DIRECTORY_SEPARATOR . $name;
        $this->assertFileExists($stored);

        [$width, $height, $type] = getimagesize($stored);
        $this->assertSame($ext === 'webp' ? IMAGETYPE_WEBP : IMAGETYPE_PNG, $type);
        $this->assertSame([800, 1000], [$width, $height], 'Sisi terpanjang foto = 1000 px');

        // Transparansi dipertahankan.
        $image = imagecreatefromstring((string) file_get_contents($stored));
        $this->assertSame(127, (imagecolorat($image, 0, 0) >> 24) & 0x7F);
    }

    public function testExifOrientationIsAppliedAndMetadataStripped(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('Ekstensi exif tidak aktif: foto tetap diterima, hanya tanpa pelurusan orientasi otomatis.');
        }

        $source = $this->withOrientation($this->image(400, 300, 'jpg'), 6);
        $this->assertSame(6, exif_read_data($source)['Orientation']);

        $stored = $this->dir . DIRECTORY_SEPARATOR . $this->process($source, 'jpg');

        [$width, $height] = getimagesize($stored);
        $this->assertSame([300, 400], [$width, $height], 'Foto HP miring diluruskan');

        // Orientasi 6 = putar 90 derajat searah jarum jam: sisi kiri (merah) menjadi atas.
        $image = imagecreatefromstring((string) file_get_contents($stored));
        $top   = imagecolorsforindex($image, imagecolorat($image, 150, 20));
        $this->assertGreaterThan(150, $top['red']);
        $this->assertLessThan(100, $top['blue']);
        $this->assertStringNotContainsString('Exif', (string) file_get_contents($stored));
    }

    public function testPolyglotPayloadIsDiscarded(): void
    {
        $source = $this->image(300, 300, 'jpg');
        file_put_contents($source, '<?php system($_GET["c"]); ?>', FILE_APPEND);

        $stored = $this->dir . DIRECTORY_SEPARATOR . $this->process($source, 'jpeg');

        $this->assertStringNotContainsString('<?php', (string) file_get_contents($stored));
    }

    public function testRejectsDisguisedAndMismatchedFiles(): void
    {
        $php = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($php, '<?php echo "x"; ?>');
        $this->sources[] = $php;

        $this->assertRejected('bukan gambar JPG/PNG/WebP', fn () => $this->process($php, 'jpg'));
        $this->assertRejected('harus berupa JPG, PNG, atau WebP', fn () => $this->process($this->image(300, 300, 'png'), 'php'));
        $this->assertRejected('harus berupa JPG, PNG, atau WebP', fn () => $this->process($this->image(300, 300, 'png'), 'svg'));
        $this->assertRejected('Ekstensi .jpg tidak sesuai isi', fn () => $this->process($this->image(300, 300, 'png'), 'jpg'));

        $gif = tempnam(sys_get_temp_dir(), 'gif');
        imagegif(imagecreatetruecolor(300, 300), $gif);
        $this->sources[] = $gif;
        $this->assertRejected('terdeteksi image/gif', fn () => $this->process($gif, 'png'));
    }

    public function testRejectsWrongDimensionsAndSize(): void
    {
        $this->assertRejected('terlalu kecil (200 × 300 px). Minimal 240 × 240 px', fn () => $this->process($this->image(200, 300, 'jpg'), 'jpg'));
        $this->assertRejected('Latar penutup terlalu kecil', fn () => $this->process($this->image(700, 700, 'jpg'), 'jpg', 'closing'));
        $this->assertRejected('Gambar pembuka profil terlalu kecil (1000 × 562 px). Minimal 1200 × 400 px', fn () => $this->process($this->image(1000, 562, 'jpg'), 'jpg', 'banner'));

        $source = $this->image(300, 300, 'jpg');
        $this->assertRejected('maksimal', fn () => $this->assets()->processFile($source, 'jpg', 6 * 1024 * 1024, 'foto_ketua', 1));
        $this->assertRejected('kosong', fn () => $this->assets()->processFile($source, 'jpg', 0, 'foto_ketua', 1));
    }

    public function testSlotsMatchTheSingleProfileLayout(): void
    {
        $this->assertSame(['foto_ketua', 'foto_wakil', 'banner', 'poster', 'closing', 'hero'], array_keys(CandidateAssets::SLOTS));
        $this->assertTrue(CandidateAssets::SLOTS['banner']['landscape']);
        $this->assertSame('banner', CandidateAssets::SLOTS['banner']['asset']);
        $this->assertSame('closing', CandidateAssets::SLOTS['closing']['asset']);
    }

    public function testBannerMustBeLandscapeAndIsKeptWhole(): void
    {
        $this->assertRejected('Gambar pembuka profil harus mendatar (landscape): lebar lebih besar dari tinggi, mis. 1920 × 1080 px. File ini 1200 × 1500 px.', fn () => $this->process($this->image(1200, 1500, 'png'), 'png', 'banner'));
        $this->assertRejected('harus mendatar (landscape)', fn () => $this->process($this->image(1300, 1300, 'jpg'), 'jpg', 'banner'));

        $name = $this->process($this->image(2400, 1350, 'jpg'), 'jpg', 'banner');

        $this->assertMatchesRegularExpression('/^c01-banner-[a-f0-9]{16}\.(webp|jpg)$/', $name);
        // Tidak dipotong: rasio asli tetap, sisi terpanjang 2000 px.
        $this->assertSame([2000, 1125], array_slice(getimagesize($this->dir . DIRECTORY_SEPARATOR . $name), 0, 2));
    }

    public function testBannerOrientationIsCheckedAfterExifRotation(): void
    {
        if (! function_exists('exif_read_data')) {
            $this->markTestSkipped('Ekstensi exif tidak aktif.');
        }

        // Tersimpan tegak, tampil mendatar (orientasi 6): diterima.
        $name = $this->process($this->withOrientation($this->image(1350, 2400, 'jpg'), 6), 'jpg', 'banner');
        $this->assertSame([2000, 1125], array_slice(getimagesize($this->dir . DIRECTORY_SEPARATOR . $name), 0, 2));
        unlink($this->dir . DIRECTORY_SEPARATOR . $name);

        // Tersimpan mendatar, tampil tegak: ditolak.
        $this->assertRejected('File ini 1350 × 2400 px.', fn () => $this->process($this->withOrientation($this->image(2400, 1350, 'jpg'), 6), 'jpg', 'banner'));
    }

    public function testHttpUploadErrorsAreReported(): void
    {
        $path = $this->image(300, 300, 'jpg');

        $oversize = new UploadedFile($path, 'foto.jpg', 'image/jpeg', 0, UPLOAD_ERR_INI_SIZE);
        $this->assertRejected('melebihi batas ukuran unggah server', fn () => $this->assets()->store($oversize, 'foto_wakil', 2));

        // File yang bukan hasil unggahan HTTP (tidak terdaftar) ditolak isValid().
        $forged = new UploadedFile($path, 'foto.jpg', 'image/jpeg', (int) filesize($path), UPLOAD_ERR_OK);
        $this->assertRejected('bukan unggahan yang valid', fn () => $this->assets()->store($forged, 'foto_wakil', 2));

        UploadFixture::attach(['foto_wakil' => ['path' => $path, 'name' => 'foto.jpg']]);
        $this->assertMatchesRegularExpression('/^c02-foto-wakil-/', $this->assets()->store($forged, 'foto_wakil', 2));
    }

    public function testDeleteOnlyRemovesImageFilesInsideUploadFolder(): void
    {
        $name = $this->process($this->image(300, 300, 'jpg'), 'jpg');
        file_put_contents($this->dir . '/.htaccess', 'Require all denied');
        $outside = $this->dir . '.txt';
        file_put_contents($outside, 'x');
        $this->sources[] = $outside;

        $assets = $this->assets();
        $assets->delete('../' . basename($this->dir) . '.txt');
        $assets->delete('.htaccess');
        $assets->delete(null);
        $assets->delete($name);

        $this->assertFileDoesNotExist($this->dir . '/' . $name);
        $this->assertFileExists($this->dir . '/.htaccess');
        $this->assertFileExists($outside);
    }

    public function testByteHelpers(): void
    {
        $this->assertSame(2 * 1024 * 1024, CandidateAssets::iniBytes('2M'));
        $this->assertSame(512 * 1024, CandidateAssets::iniBytes('512K'));
        $this->assertSame(0, CandidateAssets::iniBytes('-1'));
        $this->assertSame('2 MB', CandidateAssets::formatBytes(2 * 1024 * 1024));
        $this->assertSame('1,5 MB', CandidateAssets::formatBytes((int) (1.5 * 1024 * 1024)));
        $this->assertLessThanOrEqual(CandidateAssets::iniBytes((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX, CandidateAssets::maxBytes('poster'));
    }
}
