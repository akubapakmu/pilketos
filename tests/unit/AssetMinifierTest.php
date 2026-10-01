<?php

use App\Libraries\AssetMinifier;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Minify;

/**
 * Stage 20 & 22: minify ke browser + watermark view-source (Config\Minify).
 * CSS/JS: sumber rapi di resources/assets, hasil build bernama sama di
 * public/assets (semua environment). HTML: hanya production.
 *
 * @internal
 */
final class AssetMinifierTest extends CIUnitTestCase
{
    private const MARK = "  ╔═╗\n  ╚═╗ uji\n  ╚═╝";

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'minify-' . bin2hex(random_bytes(4));

        foreach (['public', 'resources'] as $root) {
            mkdir($this->base . '/' . $root . '/assets/css', 0777, true);
            mkdir($this->base . '/' . $root . '/assets/js', 0777, true);
        }
    }

    protected function tearDown(): void
    {
        foreach (['public', 'resources'] as $root) {
            foreach (['css', 'js'] as $type) {
                array_map('unlink', glob($this->base . '/' . $root . '/assets/' . $type . '/*') ?: []);
                @rmdir($this->base . '/' . $root . '/assets/' . $type);
            }

            @rmdir($this->base . '/' . $root . '/assets');
            @rmdir($this->base . '/' . $root);
        }

        @rmdir($this->base);

        parent::tearDown();
    }

    private function config(array $values = []): Minify
    {
        $config            = new Minify();
        $config->watermark = self::MARK;

        foreach ($values as $key => $value) {
            $config->{$key} = $value;
        }

        return $config;
    }

    private function minifier(array $config = [], string $environment = 'production'): AssetMinifier
    {
        return new AssetMinifier($this->config($config), $environment, $this->base . '/public', $this->base . '/resources');
    }

    /** Tulis kode sumber di resources/. */
    private function put(string $path, string $content): string
    {
        file_put_contents($this->base . '/resources/' . $path, $content);

        return $this->base . '/resources/' . $path;
    }

    /** Path file hasil build di public/. */
    private function target(string $path): string
    {
        return $this->base . '/public/' . $path;
    }

    private function built(string $path): string
    {
        return (string) file_get_contents($this->target($path));
    }

    public function testCssAndJsInEveryEnvironmentHtmlOnlyInProduction(): void
    {
        foreach (['development', 'testing', 'production'] as $environment) {
            $minifier = $this->minifier([], $environment);
            $this->assertTrue($minifier->active('css'), $environment);
            $this->assertTrue($minifier->active('js'), $environment);
            $this->assertSame($environment === 'production', $minifier->active('html'), $environment);
        }

        $this->assertSame('<p>  a  </p>', $this->minifier([], 'development')->page('<p>  a  </p>'));

        $off = $this->minifier(['enabled' => false]);
        $this->assertFalse($off->active('css'));
        $this->assertFalse($off->active('js'));
        $this->assertFalse($off->active('html'));

        // Saklar per jenis: CSS dimatikan, JS tetap diperkecil.
        $noCss = $this->minifier(['css' => false]);
        $this->assertFalse($noCss->active('css'));
        $this->assertTrue($noCss->active('js'));
        $this->assertFalse($noCss->active('nonsense'));
    }

    public function testBuildWritesMinifiedFileWithTheSameNameAndWatermark(): void
    {
        $css    = "/* komentar sumber */\n@font-face {\n  font-family: 'X';\n  src: url('../fonts/x.woff2') format('woff2');\n}\n\n.a  >  .b {\n  margin: 0 auto;\n}\n";
        $source = $this->put('assets/css/app.css', $css);

        $result = $this->minifier([], 'development')->build('/assets/css/app.css');
        $this->assertSame('assets/css/app.css', $result['target']);
        $this->assertTrue($result['changed']);
        $this->assertSame(strlen($css), $result['before']);

        $min = $this->built('assets/css/app.css');
        $this->assertSame(strlen($min), $result['after']);
        $this->assertStringStartsWith("/*!\n" . self::MARK . "\n*/\n", $min);
        $this->assertStringNotContainsString('komentar sumber', $min);
        $this->assertStringContainsString('../fonts/x.woff2', $min, 'url() relatif tetap benar karena folder sama');
        $this->assertStringContainsString('.a>.b{margin:0 auto}', $min);
        $this->assertSame($css, file_get_contents($source), 'kode sumber tidak berubah');
        $this->assertFileDoesNotExist($this->target('assets/css/app.min.css'));
        $this->assertSame([], glob($this->base . '/public/assets/css/*.tmp'));
    }

    public function testJavascriptIsMinifiedWithWatermark(): void
    {
        $this->put('assets/js/app.js', "// komentar\n(function () {\n  var pesan = 'halo   dunia';\n  return pesan;\n})();\n");
        $this->minifier()->build('assets/js/app.js');

        $min = $this->built('assets/js/app.js');
        $this->assertStringStartsWith("/*!\n" . self::MARK . "\n*/\n", $min);
        $this->assertStringNotContainsString('komentar', $min);
        $this->assertStringContainsString("'halo   dunia'", $min, 'isi string tidak disentuh');
    }

    public function testCssValuesKeepTheirMeaning(): void
    {
        $this->put('assets/css/app.css', ".a {\n  flex: 1 1 0;\n  background: transparent;\n  margin: var(--x, 0px);\n  color: #ffffff;\n}\n");
        $this->minifier()->build('assets/css/app.css');

        $min = $this->built('assets/css/app.css');
        $this->assertStringContainsString('flex:1 1 0;', $min);
        $this->assertStringContainsString('background:transparent', $min);
        $this->assertStringContainsString('var(--x,0px)', $min);
        $this->assertStringContainsString('#ffffff', $min);
    }

    public function testAssetPathRebuildsStaleFilesExceptWhileTesting(): void
    {
        $source   = $this->put('assets/css/app.css', ".a { color: red; }\n");
        $target   = $this->target('assets/css/app.css');
        $minifier = $this->minifier([], 'development');

        // Belum ada di public/: dibuat, path yang sama dikembalikan.
        $this->assertSame('assets/css/app.css', $minifier->assetPath('/assets/css/app.css'));
        $this->assertStringContainsString('.a{color:red}', $this->built('assets/css/app.css'));

        // Hasil build lebih baru dari sumber & Config/Minify.php: dipakai apa adanya.
        file_put_contents($target, 'PENANDA');
        touch($source, time() - 100);
        touch($target, time() + 3600);
        clearstatcache();
        $this->assertSame('assets/css/app.css', $minifier->assetPath('assets/css/app.css'));
        $this->assertSame('PENANDA', $this->built('assets/css/app.css'));

        // Sumber diedit setelahnya: saat testing tidak disentuh, selain itu dibangun ulang.
        file_put_contents($source, ".b { color: blue; }\n");
        touch($source, time() + 7200);
        clearstatcache();
        $this->assertSame('assets/css/app.css', $this->minifier([], 'testing')->assetPath('assets/css/app.css'));
        $this->assertSame('PENANDA', $this->built('assets/css/app.css'));
        $this->assertSame('assets/css/app.css', $this->minifier([], 'production')->assetPath('assets/css/app.css'));
        $this->assertStringContainsString('.b{color:blue}', $this->built('assets/css/app.css'));
    }

    public function testSwitchedOffTypeIsCopiedAsIs(): void
    {
        $css = "/* komentar */\n.a { color: red; }\n";
        $this->put('assets/css/app.css', $css);
        $this->put('assets/js/app.js', "// komentar\nvar a = 1;\n");

        $minifier = $this->minifier(['css' => false]);
        $minifier->build('assets/css/app.css');
        $minifier->build('assets/js/app.js');

        $this->assertSame($css, $this->built('assets/css/app.css'));
        $this->assertStringStartsWith("/*!\n" . self::MARK, $this->built('assets/js/app.js'));
        $this->assertSame($css, $this->minifier(['enabled' => false])->expected('assets/css/app.css'));
    }

    public function testUnchangedOutputIsOnlyTouched(): void
    {
        $this->put('assets/css/app.css', ".a { color: red; }\n");
        $minifier = $this->minifier();
        $target   = $this->target('assets/css/app.css');

        $this->assertTrue($minifier->build('assets/css/app.css')['changed']);
        touch($target, time() - 100);
        clearstatcache();

        $again = $minifier->build('assets/css/app.css');
        $this->assertFalse($again['changed']);
        $this->assertGreaterThan(time() - 100, filemtime($target), 'waktu ubah diperbarui agar tidak dibangun ulang terus');
    }

    public function testOtherPathsAreServedAsIs(): void
    {
        $minifier = $this->minifier([], 'development');

        $this->assertSame('assets/img/logo.svg', $minifier->assetPath('assets/img/logo.svg'));
        $this->assertSame('assets/css/tidak-ada.css', $minifier->assetPath('assets/css/tidak-ada.css'));
        $this->assertNull($minifier->build('assets/css/tidak-ada.css'));
        $this->assertNull($minifier->expected('assets/css/tidak-ada.css'));
        $this->assertSame([], glob($this->base . '/public/assets/css/*'));
    }

    public function testWatermarkCanBeTurnedOffAndRejectsCommentTerminators(): void
    {
        $this->put('assets/css/app.css', ".a { color: red; }\n");
        $this->minifier(['watermark' => ''])->build('assets/css/app.css');
        $this->assertSame('.a{color:red}', $this->built('assets/css/app.css'));

        $this->assertSame('', $this->minifier(['watermark' => 'a */ b'])->watermark());
        $this->assertSame('', $this->minifier(['watermark' => 'a --> b'])->watermark());
        $this->assertSame('a', $this->minifier(['watermark' => "\r\na\r\n"])->watermark());
        $this->assertSame('<!DOCTYPE html><p>x</p>', $this->minifier(['watermark' => ''])->page("<!DOCTYPE html>\n<p>x</p>"));
    }

    public function testCharsetStaysFirstInCss(): void
    {
        $this->put('assets/css/app.css', "@charset \"UTF-8\";\n.a { content: \"é\"; }\n");
        $this->minifier()->build('assets/css/app.css');

        $this->assertStringStartsWith("@charset \"UTF-8\";\n/*!\n", $this->built('assets/css/app.css'));
    }

    public function testOutdatedComparesContentNotTimestamps(): void
    {
        $this->put('assets/css/app.css', '.a { color: red; }');
        $source   = $this->put('assets/js/app.js', 'var a = 1;');
        $minifier = $this->minifier();

        $this->assertSame(['assets/css/app.css', 'assets/js/app.js'], $minifier->sources());
        $this->assertSame(['assets/css/app.css', 'assets/js/app.js'], $minifier->outdated(), 'belum dibangun');

        $minifier->build('assets/css/app.css');
        $minifier->build('assets/js/app.js');

        // Sumber "lebih baru" (mis. hasil checkout) tetapi isi sama: tidak usang.
        touch($source, time() + 3600);
        clearstatcache();
        $this->assertSame([], $minifier->outdated());

        // Sumber diubah tanpa build ulang.
        file_put_contents($source, 'var b = 2;');
        $this->assertSame(['assets/js/app.js'], $minifier->outdated());

        // Salinan sumber padahal minify aktif.
        file_put_contents($this->target('assets/css/app.css'), '.a { color: red; }');
        $this->assertSame(['assets/css/app.css', 'assets/js/app.js'], $minifier->outdated());
    }

    public function testCleanRemovesOnlyStageTwentyLeftovers(): void
    {
        $this->put('assets/css/app.css', '.a{}');
        $this->put('assets/js/app.js', 'var a;');
        $minifier = $this->minifier();
        $minifier->build('assets/css/app.css');
        $minifier->build('assets/js/app.js');

        file_put_contents($this->target('assets/css/app.min.css'), '.a{}');
        file_put_contents($this->target('assets/css/app.min.css.0a1b2c3d.tmp'), '');
        file_put_contents($this->target('assets/js/app.js.0a1b2c3d.tmp'), '');
        file_put_contents($this->target('assets/js/pustaka.min.js'), 'var b;');

        $this->assertSame(3, $minifier->clean());
        $this->assertFileDoesNotExist($this->target('assets/css/app.min.css'));
        $this->assertFileExists($this->target('assets/css/app.css'), 'hasil build tidak dihapus');
        $this->assertFileExists($this->target('assets/js/app.js'));
        $this->assertFileExists($this->target('assets/js/pustaka.min.js'), 'file .min tanpa sumber bukan sisa buatan');
        $this->assertSame(0, $minifier->clean());
    }

    public function testHtmlIsCollapsedConservatively(): void
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html lang="id">
            <head>
              <meta charset="UTF-8">
              <!-- komentar halaman -->
              <title>  Judul   Halaman </title>
              <script nonce="abc123">
                // komentar skrip
                document.documentElement.className = 'js';
              </script>
              <script type="application/json" id="data">{ "a":  1 }</script>
              <style nonce="abc123">
                .a { color : red ; }
              </style>
            </head>
            <body class="x">
              <p>Halo,    <strong>dunia</strong>
                 !</p>
              <textarea id="r"
                 name="r"
                 required>  baris 1
                baris 2  </textarea>
              <pre>  a
               b</pre>
              <div data-text="a   b
              c" title='x > y'>z</div>
              <span>1</span>   <span>2</span>
            </body>
            </html>
            HTML;

        $out = $this->minifier()->html($html);

        $this->assertStringStartsWith('<!DOCTYPE html><html lang="id"><head><meta charset="UTF-8"><title>Judul Halaman</title><script nonce="abc123">', $out);
        $this->assertStringContainsString('document.documentElement.className=\'js\'', $out);
        $this->assertStringContainsString('<script type="application/json" id="data">{ "a":  1 }</script>', $out);
        $this->assertStringContainsString('<style nonce="abc123">.a{color:red}</style>', $out);
        $this->assertStringContainsString('</head><body class="x"><p>Halo, <strong>dunia</strong> !</p>', $out);
        $this->assertStringContainsString("<textarea id=\"r\" name=\"r\" required>  baris 1\n    baris 2  </textarea>", $out);
        $this->assertStringContainsString("<pre>  a\n   b</pre>", $out);
        $this->assertStringContainsString("<div data-text=\"a   b\n  c\" title='x > y'>z</div>", $out);
        $this->assertStringContainsString('<span>1</span> <span>2</span>', $out);
        $this->assertStringEndsWith('</body></html>', $out);
        $this->assertStringNotContainsString('komentar', $out);
    }

    public function testInlineCodeIsLeftAloneWhenItsTypeIsSwitchedOff(): void
    {
        $out = $this->minifier(['js' => false, 'css' => false])->html("<script>\n  var a = 1;\n</script><style>\n  .a { color: red; }\n</style>");

        $this->assertSame("<script>\n  var a = 1;\n</script><style>\n  .a { color: red; }\n</style>", $out);
    }

    public function testWatermarkOnlyFollowsTheDoctypeOfFullDocuments(): void
    {
        $minifier = $this->minifier();

        $page = $minifier->page("<!DOCTYPE html>\n<html>\n<body>\n  <p>x</p>\n</body>\n</html>\n");
        $this->assertSame("<!DOCTYPE html>\n<!--\n" . self::MARK . "\n-->\n<html><body><p>x</p></body></html>", $page);

        // Fragmen (pane analitik lewat fetch): diperkecil, tanpa watermark.
        $this->assertSame('<div class="pane"> <p>x</p> </div>', $minifier->page("<div class=\"pane\">\n  <p>x</p>\n</div>\n"));
    }
}
