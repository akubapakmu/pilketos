<?php

use App\Libraries\AssetMinifier;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Minify;

/**
 * Stage 20: minify HTML/CSS/JS ke browser + watermark view-source, tanpa
 * mengubah kode sumber (Config\Minify, hanya production).
 *
 * @internal
 */
final class AssetMinifierTest extends CIUnitTestCase
{
    private const MARK = "  ╔═╗\n  ╚═╗ uji\n  ╚═╝";

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'minify-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/assets/css', 0777, true);
        mkdir($this->root . '/assets/js', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/assets/*/*') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->root . '/assets/css');
        @rmdir($this->root . '/assets/js');
        @rmdir($this->root . '/assets');
        @rmdir($this->root);

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
        return new AssetMinifier($this->config($config), $environment, $this->root);
    }

    private function put(string $path, string $content): string
    {
        file_put_contents($this->root . '/' . $path, $content);

        return $this->root . '/' . $path;
    }

    public function testOnlyActiveInProductionAndWhenSwitchedOn(): void
    {
        $this->put('assets/css/app.css', "body {\n  color: red;\n}\n");
        $this->put('assets/js/app.js', "var a = 1;\n");

        foreach (['development', 'testing'] as $environment) {
            $minifier = $this->minifier([], $environment);
            $this->assertFalse($minifier->active('html'));
            $this->assertSame('assets/css/app.css', $minifier->assetPath('assets/css/app.css'));
            $this->assertSame('<p>  a  </p>', $minifier->page('<p>  a  </p>'));
        }

        $off = $this->minifier(['enabled' => false]);
        $this->assertSame('assets/css/app.css', $off->assetPath('assets/css/app.css'));
        $this->assertSame('assets/js/app.js', $off->assetPath('assets/js/app.js'));
        $this->assertFalse($off->active('html'));

        // Saklar per jenis: CSS dimatikan, JS tetap diperkecil.
        $noCss = $this->minifier(['css' => false]);
        $this->assertSame('assets/css/app.css', $noCss->assetPath('assets/css/app.css'));
        $this->assertSame('assets/js/app.min.js', $noCss->assetPath('/assets/js/app.js'));
        $this->assertFileDoesNotExist($this->root . '/assets/css/app.min.css');
    }

    public function testBuildsMinifiedCopyNextToSourceWithWatermark(): void
    {
        $css    = "/* komentar sumber */\n@font-face {\n  font-family: 'X';\n  src: url('../fonts/x.woff2') format('woff2');\n}\n\n.a  >  .b {\n  margin: 0 auto;\n}\n";
        $source = $this->put('assets/css/app.css', $css);

        $this->assertSame('assets/css/app.min.css', $this->minifier()->assetPath('assets/css/app.css'));

        $min = (string) file_get_contents($this->root . '/assets/css/app.min.css');
        $this->assertStringStartsWith("/*!\n" . self::MARK . "\n*/\n", $min);
        $this->assertStringNotContainsString('komentar sumber', $min);
        $this->assertStringContainsString('../fonts/x.woff2', $min, 'url() relatif tetap benar karena satu folder');
        $this->assertStringContainsString('.a>.b{margin:0 auto}', $min);
        $this->assertSame($css, file_get_contents($source), 'kode sumber tidak berubah');
        $this->assertSame([], glob($this->root . '/assets/css/*.tmp'));
    }

    public function testJavascriptIsMinifiedWithWatermark(): void
    {
        $this->put('assets/js/app.js', "// komentar\n(function () {\n  var pesan = 'halo   dunia';\n  return pesan;\n})();\n");

        $this->assertSame('assets/js/app.min.js', $this->minifier()->assetPath('assets/js/app.js'));

        $min = (string) file_get_contents($this->root . '/assets/js/app.min.js');
        $this->assertStringStartsWith("/*!\n" . self::MARK . "\n*/\n", $min);
        $this->assertStringNotContainsString('komentar', $min);
        $this->assertStringContainsString("'halo   dunia'", $min, 'isi string tidak disentuh');
    }

    public function testCssValuesKeepTheirMeaning(): void
    {
        $this->put('assets/css/app.css', ".a {\n  flex: 1 1 0;\n  background: transparent;\n  margin: var(--x, 0px);\n  color: #ffffff;\n}\n");
        $this->minifier()->assetPath('assets/css/app.css');

        $min = (string) file_get_contents($this->root . '/assets/css/app.min.css');
        $this->assertStringContainsString('flex:1 1 0;', $min);
        $this->assertStringContainsString('background:transparent', $min);
        $this->assertStringContainsString('var(--x,0px)', $min);
        $this->assertStringContainsString('#ffffff', $min);
    }

    public function testRebuildsOnlyWhenSourceIsNewer(): void
    {
        $source   = $this->put('assets/css/app.css', ".a { color: red; }\n");
        $target   = $this->root . '/assets/css/app.min.css';
        $minifier = $this->minifier();
        $minifier->assetPath('assets/css/app.css');

        // Versi min lebih baru dari sumber: dipakai apa adanya.
        file_put_contents($target, 'PENANDA');
        touch($source, time() - 100);
        touch($target, time() - 50);
        clearstatcache();
        $this->assertSame('assets/css/app.min.css', $minifier->assetPath('assets/css/app.css'));
        $this->assertSame('PENANDA', file_get_contents($target));

        // Sumber diedit setelahnya: dibuat ulang.
        file_put_contents($source, ".b { color: blue; }\n");
        touch($source, time() - 10);
        clearstatcache();
        $this->assertSame('assets/css/app.min.css', $minifier->assetPath('assets/css/app.css'));
        $this->assertStringContainsString('.b{color:blue}', (string) file_get_contents($target));
    }

    public function testFallsBackToTheOriginalPath(): void
    {
        $this->put('assets/css/app.min.css', 'sudah-min');
        $minifier = $this->minifier();

        $this->assertSame('assets/img/logo.svg', $minifier->assetPath('assets/img/logo.svg'));
        $this->assertSame('assets/css/tidak-ada.css', $minifier->assetPath('assets/css/tidak-ada.css'));
        $this->assertSame('assets/css/app.min.css', $minifier->assetPath('assets/css/app.min.css'));
        $this->assertNull($minifier->build('assets/css/tidak-ada.css'));
    }

    public function testWatermarkCanBeTurnedOffAndRejectsCommentTerminators(): void
    {
        $this->put('assets/css/app.css', ".a { color: red; }\n");
        $this->minifier(['watermark' => ''])->assetPath('assets/css/app.css');
        $this->assertSame('.a{color:red}', file_get_contents($this->root . '/assets/css/app.min.css'));

        $this->assertSame('', $this->minifier(['watermark' => 'a */ b'])->watermark());
        $this->assertSame('', $this->minifier(['watermark' => 'a --> b'])->watermark());
        $this->assertSame('a', $this->minifier(['watermark' => "\r\na\r\n"])->watermark());
        $this->assertSame('<!DOCTYPE html><p>x</p>', $this->minifier(['watermark' => ''])->page("<!DOCTYPE html>\n<p>x</p>"));
    }

    public function testCharsetStaysFirstInCss(): void
    {
        $this->put('assets/css/app.css', "@charset \"UTF-8\";\n.a { content: \"é\"; }\n");
        $this->minifier()->assetPath('assets/css/app.css');

        $this->assertStringStartsWith("@charset \"UTF-8\";\n/*!\n", (string) file_get_contents($this->root . '/assets/css/app.min.css'));
    }

    public function testSourcesAndCleanOnlyTouchGeneratedFiles(): void
    {
        $this->put('assets/css/app.css', '.a{}');
        $this->put('assets/js/app.js', 'var a;');
        $this->put('assets/js/pustaka.min.js', 'var b;');
        $minifier = $this->minifier();

        $this->assertSame(['assets/css/app.css', 'assets/js/app.js'], $minifier->sources());

        $minifier->build('assets/css/app.css');
        $minifier->build('assets/js/app.js');
        $this->assertSame([], $minifier->unbuildable());
        $this->assertSame(2, $minifier->clean());
        $this->assertFileDoesNotExist($this->root . '/assets/css/app.min.css');
        $this->assertFileExists($this->root . '/assets/js/pustaka.min.js', 'file .min tanpa sumber bukan hasil buatan');
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
