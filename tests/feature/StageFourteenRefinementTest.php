<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Services\Import\ImportStore;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\SpreadsheetFactory;
use Tests\Support\UploadFixture;
use Tests\Support\AssetSource;

/**
 * Stage 14: gambar layar pembuka tidak telat (preload di <head>, tampil tanpa
 * menunggu home.js, diurai sebelum dihitung), aksi kartu paslon rata tengah
 * di HP, halaman impor berbahasa ramah + contoh baru, tombol unduh template
 * selebar layar di HP, tab saring pratinjau impor rata tengah / bergulir
 * mendatar di HP dan dimuat tanpa muat ulang halaman.
 *
 * @internal
 */
final class StageFourteenRefinementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private string $storeDir;

    /**
     * @var list<string>
     */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->storeDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import-s14-' . bin2hex(random_bytes(4));
        Services::injectMock('importStore', new ImportStore($this->storeDir));
        service('renderer')->resetData();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storeDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->storeDir);

        foreach ($this->files as $file) {
            @unlink($file);
        }

        UploadFixture::reset();
        parent::tearDown();
    }

    private function asAdmin()
    {
        return $this->withSession(['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true]);
    }

    private function asset(string $path): string
    {
        return AssetSource::read($path);
    }

    /** Isi satu aturan CSS (selektor persis, sampai kurung kurawal tutup pertama). */
    private function rule(string $css, string $selector): string
    {
        $this->assertMatchesRegularExpression('/(^|\n)\s*' . preg_quote($selector, '/') . ' \{/', $css, $selector);
        preg_match('/(?:^|\n)\s*' . preg_quote($selector, '/') . ' \{([^}]*)\}/', $css, $match);

        return $match[1];
    }

    /** Isi blok @media persis (sampai kurung kurawal penutup blok). */
    private function mediaBlocks(string $css, string $query): string
    {
        preg_match_all('/@media ' . preg_quote($query, '/') . ' \{(.*?)\n\}/s', $css, $matches);

        return implode("\n", $matches[1]);
    }

    private function previewToken(): string
    {
        $path          = SpreadsheetFactory::students([
            [1, '0012345678', 'Rizky Amelia', 'P', '7C', 3, '01032013'],
            [2, 'ABC', 'Salah NISN', 'X', '12Z', 1, '99999999'],
        ]);
        $this->files[] = $path;
        UploadFixture::attach(['file' => ['path' => $path, 'name' => 'data.xlsx']]);

        $upload   = $this->asAdmin()->post('admin/siswa/impor', [csrf_token() => csrf_hash()]);
        $location = $upload->response()->getHeaderLine('Location');
        $this->assertMatchesRegularExpression('#/impor/cek/([a-f0-9]{32})$#', $location);

        return substr($location, -32);
    }

    // ------------------------------------------------------------------
    // Layar pembuka
    // ------------------------------------------------------------------

    public function testSplashImagesArePreloadedFromTheHead(): void
    {
        $html = html_entity_decode((string) $this->get('/')->response()->getBody(), ENT_QUOTES | ENT_HTML5);
        $head = substr($html, 0, (int) strpos($html, '</head>'));

        $preload = static fn (string $file, string $media): string => '#<link rel="preload" href="' . preg_quote(base_url($file), '#') . '\?v=\d+" as="image"' . $media . ' fetchpriority="high">#';

        // Latar pembuka: media sama dengan <source> di <picture> (potret -> HP).
        $this->assertMatchesRegularExpression($preload('assets/img/home/intro-mobile.svg', ' media="\(orientation: portrait\)"'), $head);
        $this->assertMatchesRegularExpression($preload('assets/img/home/intro-desktop.svg', ' media="\(orientation: landscape\)"'), $head);
        $this->assertMatchesRegularExpression($preload('assets/img/brand/logo-light.svg', ''), $head);
        // Latar hero tertutup layar pembuka: tidak ikut berebut prioritas.
        $this->assertStringNotContainsString('hero-desktop.svg', $head);

        // URL preload = URL <img> (asset_url ?v=) agar unduhan dipakai ulang.
        preg_match('#<img class="splash__img" src="([^"]+)"#', $html, $img);
        $this->assertStringContainsString('<link rel="preload" href="' . $img[1] . '"', $head);
        $this->assertDoesNotMatchRegularExpression('#<img class="splash__img"[^>]*decoding="async"#', $html);
    }

    public function testSplashBackgroundDoesNotWaitForTheScript(): void
    {
        $css = $this->asset('assets/css/home.css');
        $js  = $this->asset('assets/js/home.js');

        $this->assertStringNotContainsString('opacity', $this->rule($css, '.splash__img'));
        $this->assertStringNotContainsString('.splash__img.is-loaded', $css);
        $this->assertStringNotContainsString("classList.add('is-loaded')", $js);

        // Gambar dihitung selesai setelah diurai, bukan sekadar "load".
        $this->assertStringContainsString('img.decode().then(done, done);', $js);
        // Gambar scene lain diurai lebih dulu, tanpa menahan layar pembuka.
        $this->assertStringContainsString("document.querySelectorAll('.portal__img, .pair__img')", $js);
        $this->assertStringContainsString('warmImages();', $js);
    }

    // ------------------------------------------------------------------
    // Panel admin
    // ------------------------------------------------------------------

    public function testCandidateCardActionsAreCenteredOnPhones(): void
    {
        $mobile = $this->mediaBlocks($this->asset('assets/css/admin.css'), '(max-width: 719px)');

        $this->assertMatchesRegularExpression('/\.cand-card__actions \{\s*justify-content: center;\s*align-items: center;\s*\}/', $mobile);
        $this->assertMatchesRegularExpression('/\.cand-card__actions \.cand-card__lock \{[^}]*justify-content: center;[^}]*text-align: center;/', $mobile);

        $this->asAdmin()->get('admin/paslon')->assertSee('class="cand-card__actions"');
    }

    public function testStudentImportPageIsFriendlyWithNewExample(): void
    {
        $page = $this->asAdmin()->get('admin/siswa/impor');
        $page->assertStatus(200);

        $page->assertSee('Sasuke Uchiha');
        $page->assertDontSee('Ahmad Fauzan');
        $page->assertSee('Siswa yang NISN-nya sudah ada cukup diperbarui datanya, jadi tidak dobel.');
        $page->assertSee('Kolom NISN dan kodeunik sudah diatur supaya angka 0 di depan tidak hilang, jadi tinggal ketik saja.');
        $page->assertSee('Yang dicek sebelum data disimpan');
        $page->assertSee('Jenis kelamin cukup ditulis L (laki-laki) atau P (perempuan).');

        foreach (['DDMMYYYY', 'berformat Teks', 'memvalidasi', 'Header sesuai template'] as $technical) {
            $page->assertDontSee($technical);
        }
    }

    public function testTeacherImportPageAvoidsTechnicalWordsAndUsesNewExample(): void
    {
        $page = $this->asAdmin()->get('admin/guru/impor');
        $page->assertStatus(200);

        foreach (['199305012020121004', 'Fajar Afif Dewantoro, S.Pd.', '01012000'] as $example) {
            $page->assertSee($example);
        }
        $page->assertDontSee('198501012010011001');
        $page->assertDontSee('Siti Nur Aini');
        $page->assertSee('Kolom NIP dan kodeunik sudah disiapkan agar angkanya tersimpan utuh, cukup ketik seperti biasa.');
        $page->assertSee('misalnya 01012000 untuk 1 Januari 2000');

        foreach (['DDMMYYYY', 'berformat Teks', 'terbaca sebagai angka', 'dibulatkan Excel', 'NISN'] as $technical) {
            $page->assertDontSee($technical);
        }

        // Petunjuk di file template ikut contoh baru.
        $this->assertContains('4. kodeunik = tanggal lahir DDMMYYYY sebagai teks, contoh 01012000 untuk 1 Januari 2000.', (new ReflectionMethod(\App\Services\Import\TeacherImporter::class, 'instructions'))->invoke(new \App\Services\Import\TeacherImporter(db_connect())));
    }

    public function testDownloadTemplateButtonIsFullWidthOnPhones(): void
    {
        $this->asAdmin()->get('admin/siswa/impor')->assertSee('class="btn import-download"');

        $mobile = $this->mediaBlocks($this->asset('assets/css/admin.css'), '(max-width: 719px)');
        $this->assertMatchesRegularExpression('/\.import-download \{\s*display: flex;\s*width: 100%;\s*text-align: center;\s*\}/', $mobile);
    }

    public function testPreviewFilterTabsLoadInPlace(): void
    {
        $token = $this->previewToken();
        $url   = 'admin/siswa/impor/cek/' . $token;

        $issues = (string) $this->asAdmin()->get($url)->response()->getBody();
        $this->assertStringContainsString('<nav class="tabs" aria-label="Saring baris pratinjau" data-live-nav="saring">', $issues);
        $this->assertStringContainsString('<div class="preview-rows" data-live-region="pratinjau" data-live-nav="halaman">', $issues);
        $this->assertStringContainsString('<p class="visually-hidden" data-live-announce>Perlu diperiksa: 1 baris.</p>', $issues);

        // Tab yang sama diambil admin.js lewat fetch: region & tab aktif ada di balasan.
        $all = (string) $this->asAdmin()->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get($url . '?show=all')->response()->getBody();
        $this->assertStringContainsString('data-live-announce>Semua baris: 2 baris.</p>', $all);
        $this->assertMatchesRegularExpression('#\?show=all" aria-current="page">\s*Semua baris#', $all);
        $this->assertStringContainsString('Rizky Amelia', $all);
    }

    public function testPreviewTabsScrollOnPhonesAndStayCentered(): void
    {
        $css  = $this->asset('assets/css/admin.css');
        $tabs = $this->rule($css, '.tabs');

        $this->assertStringContainsString('overflow-x: auto;', $tabs);
        $this->assertStringNotContainsString('flex-wrap: wrap;', $tabs);
        $this->assertStringContainsString('white-space: nowrap;', $this->rule($css, '.tabs__link'));
        // Rata tengah yang tetap bisa digulir sampai tab pertama.
        $this->assertStringContainsString('.tabs__link:first-child { margin-left: auto; }', $css);
        $this->assertStringContainsString('.tabs__link:last-child { margin-right: auto; }', $css);
        $this->assertStringContainsString('scrollbar-width: none;', $this->mediaBlocks($css, '(max-width: 719px)'));
    }

    public function testLiveNavigationScriptContract(): void
    {
        $js = $this->asset('assets/js/admin.js');

        $this->assertStringContainsString("event.target.closest('[data-live-nav] a[href]')", $js);
        $this->assertStringContainsString("window.history.pushState({ liveNav: true }, '', url.href);", $js);
        $this->assertStringContainsString("window.addEventListener('popstate', function () {", $js);
        $this->assertStringContainsString("load(new URL(window.location.href), { history: 'none' });", $js);
        $this->assertStringContainsString("[data-live-region] [data-live-announce]", $js);
        // Klik dengan Ctrl/Shift/klik tengah tetap membuka tab baru.
        $this->assertStringContainsString('event.metaKey || event.ctrlKey || event.shiftKey || event.altKey', $js);
        // Kontrak live search Stage 12 tetap.
        $this->assertStringContainsString("window.history.replaceState(window.history.state, '', url.href);", $js);
    }
}
