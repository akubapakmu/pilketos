<?php

use App\Libraries\AssetMinifier;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Stage 23: CSS/JS yang dikirim ke browser (public/assets) adalah hasil build
 * terbaru dari sumber rapi di resources/assets: ter-minify, ber-watermark,
 * tanpa komentar sumber. Gagal = lupa menjalankan "php spark osis:minify"
 * setelah mengedit resources/assets.
 *
 * @internal
 */
final class BuiltAssetsTest extends CIUnitTestCase
{
    public function testEveryPublicFileIsTheLatestBuildOfItsSource(): void
    {
        $minifier = new AssetMinifier();
        $sources  = $minifier->sources();

        $this->assertCount(22, $sources);
        $this->assertTrue(AssetMinifier::available(), 'composer install (matthiasmullie/minify)');
        $this->assertSame([], $minifier->outdated(), 'jalankan php spark osis:minify');

        foreach ($sources as $path) {
            $this->assertSame($minifier->expected($path), file_get_contents(FCPATH . $path), $path);
        }
    }

    public function testBrowserNeverReceivesSourceComments(): void
    {
        $banner = "/*!\n" . (new AssetMinifier())->watermark() . "\n*/\n";

        foreach ((new AssetMinifier())->sources() as $path) {
            $public = (string) file_get_contents(FCPATH . $path);
            $source = (string) file_get_contents(ROOTPATH . AssetMinifier::SOURCE_DIR . '/' . $path);

            $this->assertStringStartsWith($banner, $public, $path);
            $this->assertSame(1, substr_count($public, '/*'), $path . ': hanya banner watermark');
            $this->assertLessThan(strlen($source), strlen($public), $path);
            $this->assertMatchesRegularExpression('~/\*|//~', $source, $path . ': sumber rapi tetap berkomentar');
        }
    }

    public function testPublicAssetsHaveASourceAndNoLeftovers(): void
    {
        $minifier = new AssetMinifier();
        $sources  = $minifier->sources();

        foreach (AssetMinifier::FOLDERS as $type => $folder) {
            foreach (glob(FCPATH . $folder . '/*') ?: [] as $file) {
                $path = $folder . '/' . basename($file);

                $this->assertDoesNotMatchRegularExpression('/\.(min\.(css|js)|tmp)$/', $path, 'sisa Stage 20: php spark osis:minify --clean');
                $this->assertContains($path, $sources, $path . ' tanpa sumber di resources/');
            }
        }

        $this->assertDirectoryDoesNotExist(FCPATH . AssetMinifier::SOURCE_DIR, 'sumber rapi tidak boleh di public/');
    }
}
