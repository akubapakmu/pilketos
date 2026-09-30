<?php

use App\Controllers\Home;
use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Identitas visual SMP 1 DAWE (Stage 6, STAGE6-NOTES.md; dokumen 06-10):
 * sistem font aplikasi, penulisan nama sekolah, hero berlapis lereng Muria,
 * layar pembuka "selalu tampil", lockup acara & lambang resmi, netralitas
 * warna identitas terhadap pasangan calon, dan aturan aset SVG.
 *
 * Perilaku gerak (reveal, paralaks, kabut tersingkap, canvas) diuji di
 * browser; lihat STAGE6-NOTES.md bagian 9.
 *
 * @internal
 */
final class VisualIdentityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();

        cache()->clean();
        // Renderer view dipakai bersama antar-request di proses test
        // (Config\View::$saveData): judul halaman lain tidak boleh terbawa.
        service('renderer')->resetData();
    }

    /**
     * @return array<string, string> halaman => HTML
     */
    private function pages(): array
    {
        $admin = ['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true];

        return [
            'beranda'     => (string) $this->get('/')->response()->getBody(),
            'login siswa' => (string) $this->get('siswa/masuk')->response()->getBody(),
            'login admin' => (string) $this->get('admin/masuk')->response()->getBody(),
            'dasbor'      => (string) $this->withSession($admin)->get('admin')->response()->getBody(),
        ];
    }

    public function testUiFontIsPlusJakartaSansAcrossTheApp(): void
    {
        foreach ($this->pages() as $page => $html) {
            $this->assertStringContainsString('assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2', $html, $page);
            $this->assertStringNotContainsString('inter-latin', $html, $page);
        }

        $css = (string) file_get_contents(FCPATH . 'assets/css/app.css');
        $this->assertStringContainsString("--font-ui: 'Plus Jakarta Sans'", $css);
        $this->assertFileExists(FCPATH . 'assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2');
        $this->assertFileExists(FCPATH . 'assets/fonts/OFL-PlusJakartaSans.txt');
        // Tetap tiga family: file Inter dihapus.
        $this->assertFileDoesNotExist(FCPATH . 'assets/fonts/inter-latin-wght-normal.woff2');
    }

    public function testSchoolNameIsWrittenInCapitalsOnEveryPage(): void
    {
        $pages = $this->pages();

        foreach ($pages as $page => $html) {
            $this->assertStringContainsString('SMP 1 DAWE', $html, $page);
            $this->assertDoesNotMatchRegularExpression('/SMP 1 Dawe/', $html, $page);
        }

        // Ditulis literal di sumber, bukan lewat text-transform.
        $this->assertStringContainsString('<title>Pemilihan Ketua &amp; Wakil Ketua OSIS SMP 1 DAWE 2026</title>', $pages['beranda']);
        $this->assertSame('Pemilihan Ketua dan Wakil Ketua OSIS SMP 1 DAWE', $this->db->table('elections')->get()->getRowArray()['nama']);
    }

    /**
     * HTML beranda dengan entitas didekode (atribut di-escape esc(..., 'attr')).
     */
    private function home(): string
    {
        return html_entity_decode((string) $this->get('/')->response()->getBody(), ENT_QUOTES | ENT_HTML5);
    }

    /**
     * Potongan HTML satu scene beranda (dari id scene sampai scene berikutnya).
     */
    private function scene(string $html, string $id, ?string $nextId): string
    {
        $start = strpos($html, 'id="' . $id . '"');
        $this->assertNotFalse($start, $id);
        $end = $nextId === null ? strlen($html) : strpos($html, 'id="' . $nextId . '"', $start);

        return substr($html, $start, $end - $start);
    }

    private function splash(string $html): string
    {
        $start = strpos($html, '<div class="splash"');
        $this->assertNotFalse($start);

        return substr($html, $start, strpos($html, '<header', $start) - $start);
    }

    public function testHeroIsLayeredVisualWithCallsToActionOnly(): void
    {
        $html = $this->home();
        $hero = $this->scene($html, 'beranda', 'masuk');

        // Lapisan: latar lanskap/potret (dipilih <picture>, dimuat segera),
        // lapisan gelap; lapisan depan hanya bila dipasang. Stage 18: tanpa kontur.
        $this->assertStringContainsString('media="(orientation: portrait)" srcset="' . base_url('assets/img/home/hero-mobile.svg'), $hero);
        $this->assertMatchesRegularExpression('#<img class="hero-bg__img" src="' . preg_quote(base_url('assets/img/home/hero-desktop.svg'), '#') . '[^"]*" alt="" fetchpriority="high"#', $hero);
        $this->assertStringContainsString('hero-bg__shade', $hero);
        $this->assertStringNotContainsString('hero-bg__fore', $hero);
        $this->assertStringContainsString('data-field', $hero);

        // Stage 18: tanpa teks terlihat, hanya CTA; judul untuk pembaca layar.
        $this->assertStringContainsString('<h1 class="visually-hidden" id="hero-title">Pemilihan Ketua & Wakil Ketua OSIS SMP 1 DAWE 2026</h1>', $hero);
        $this->assertStringContainsString('Masuk untuk memilih', $hero);
        $this->assertStringContainsString('Lihat perolehan suara', $hero);

        // Dihapus dari Stage 5: pita nomor pasangan, lede, tahun bergaris tepi.
        // Dihapus di Stage 18: kontur, kicker, judul terlihat, baris pendukung, perforasi.
        foreach (['hero__bands', 'hero__band', 'hero__lede', 'hero__line--year', 'Kenali pasangan calon, lalu coblos', 'hero-bg__contour', 'hero__kicker', 'hero__title', 'hero__support', 'hero__perf', 'PILKETOS 2026', 'Satu pemilih'] as $gone) {
            $this->assertStringNotContainsString($gone, $hero, $gone);
        }

        // Hero netral: tanpa warna, nomor, nama, atau foto pasangan mana pun.
        foreach (['--accent', 'Arka Wibisana', 'Naya Kirana', 'pair__', 'uploads/candidates'] as $candidateTrace) {
            $this->assertStringNotContainsString($candidateTrace, $hero, $candidateTrace);
        }
    }

    public function testHeroImagesAreReplaceableThroughConfig(): void
    {
        $config                 = config(\Config\Homepage::class);
        $config->heroDesktop    = 'assets/img/home/hero-desktop.webp';
        $config->heroMobile     = 'assets/img/home/hero-mobile.webp';
        $config->heroForeground = 'assets/img/home/hero-foreground.webp';

        $hero = $this->scene($this->home(), 'beranda', 'masuk');

        $this->assertStringContainsString('srcset="' . base_url('assets/img/home/hero-mobile.webp') . '"', $hero);
        $this->assertStringContainsString('src="' . base_url('assets/img/home/hero-desktop.webp') . '"', $hero);
        $this->assertStringContainsString('<img class="hero-bg__fore-img" src="' . base_url('assets/img/home/hero-foreground.webp') . '" alt=""', $hero);
    }

    public function testSplashIsTheFoggyHeroAndAlwaysShows(): void
    {
        $html   = $this->home();
        $splash = $this->splash($html);

        // Latar = pemandangan hero berkabut, bingkai sama; dimuat segera.
        $this->assertStringContainsString('srcset="' . base_url('assets/img/home/intro-mobile.svg'), $splash);
        $this->assertMatchesRegularExpression('#<img class="splash__img" src="' . preg_quote(base_url('assets/img/home/intro-desktop.svg'), '#') . '[^"]*" alt="" fetchpriority="high"#', $splash);
        $this->assertStringNotContainsString('loading="lazy"', $splash);
        $this->assertSame(
            $this->viewBox('assets/img/home/hero-desktop.svg'),
            $this->viewBox('assets/img/home/intro-desktop.svg'),
        );
        $this->assertSame(
            $this->viewBox('assets/img/home/hero-mobile.svg'),
            $this->viewBox('assets/img/home/intro-mobile.svg'),
        );

        // Lockup di tengah, bilah muat + angka; tanpa teks lain & tanpa pasangan.
        $this->assertStringContainsString('data-splash-brand', $splash);
        $this->assertStringContainsString('assets/img/brand/logo-light.svg', $splash);
        $this->assertStringContainsString('data-splash-fill', $splash);
        $this->assertStringNotContainsString('--accent', $splash);
        $this->assertSame('', trim(preg_replace('/\s+/', ' ', strip_tags(str_replace('000', '', $splash)))));

        // Selalu tampil: tidak ada penanda sesi; hanya penanda sekali pakai
        // untuk muat ulang otomatis (dibaca lalu dihapus sebelum render).
        $this->assertStringNotContainsString("'osis2026.intro'", $html);
        $this->assertStringContainsString("sessionStorage.getItem('osis2026.skipIntro') === '1'", $html);
        $this->assertStringContainsString("sessionStorage.removeItem('osis2026.skipIntro')", $html);

        $js = (string) file_get_contents(FCPATH . 'assets/js/home.js');
        $this->assertStringNotContainsString("'osis2026.intro'", $js);
        $this->assertStringContainsString('Intro.MIN = 1500;', $js);
        $this->assertStringContainsString('Intro.MAX = 5200;', $js);

        // Aset scene lain tidak menahan layar pembuka: prioritas rendah.
        $this->assertStringNotContainsString('data-preload', $html);
        $this->assertMatchesRegularExpression('#class="portal__img"[^>]+fetchpriority="low"#', $html);
    }

    public function testIdentityAccentStaysNeutralAgainstCandidateColours(): void
    {
        // Seed: terracotta, hijau tua, biru tua -> parijoto boleh dipakai.
        $this->assertStringContainsString('style="--parijoto: #A8628F;"', $this->splash($this->home()));

        // Satu pasangan memakai ungu yang mirip parijoto -> beranda netral.
        $this->db->table('candidates')->where('id', 2)->update(['theme_accent' => '#9C5A86']);
        $this->assertStringNotContainsString('--parijoto', $this->home());

        $config = config(\Config\Homepage::class);
        $this->assertNull(Home::identityAccent($config, ['#C4432B', '#9C5A86']));
        $this->assertSame('#A8628F', Home::identityAccent($config, ['#C4432B', '#2F5D50', '#1B3A6B']));

        $config->identityAccent = null;
        $this->assertNull(Home::identityAccent($config, []));
        $config->identityAccent = 'purple; background: url(x)';
        $this->assertNull(Home::identityAccent($config, []));

        // Scene SUARA tidak memakai warna identitas sekolah.
        $css  = (string) file_get_contents(FCPATH . 'assets/css/home.css');
        $uses = substr_count($css, 'var(--parijoto');
        $this->assertSame(1, $uses);
        $this->assertStringContainsString('background: var(--parijoto, var(--on-night));', $css);
    }

    public function testLockupAndOptionalOfficialEmblemInTheNavigation(): void
    {
        $html = $this->home();

        $this->assertStringContainsString('<span class="brandmark" data-nav-brand>', $html);
        $this->assertStringContainsString('alt="Pemilihan Ketua OSIS SMP 1 DAWE 2026, ke beranda"', $html);
        $this->assertStringNotContainsString('brandmark__emblem', $html);

        // Lambang resmi dipasang apa adanya (file dari sekolah) di kiri lockup,
        // di navigasi & layar pembuka; ukuran intrinsik dibaca otomatis.
        config(\Config\Homepage::class)->schoolEmblem = 'assets/img/results/result-mark.svg';
        $html = $this->home();

        $this->assertSame(2, substr_count($html, 'class="brandmark__emblem" src="' . base_url('assets/img/results/result-mark.svg')));
        $this->assertSame(2, substr_count($html, 'width="240" height="240"'));
        $this->assertSame(2, substr_count($html, 'brandmark__rule'));
        $this->assertLessThan(strpos($html, 'brandmark__logo site-nav__logo'), strpos($html, 'brandmark__emblem', strpos($html, '<header')));

        $login = (string) $this->get('siswa/masuk')->response()->getBody();
        $this->assertStringContainsString('assets/img/brand/logo-dark.svg', $login);
        $this->assertStringContainsString('brandmark__emblem', $login);
    }

    public function testBrandAndCodeSvgsFollowTheAssetRules(): void
    {
        foreach (['logo-light.svg', 'logo-dark.svg'] as $file) {
            $svg = $this->svg('assets/img/brand/' . $file);
            // Wordmark di-outline (tanpa <text>), nama sekolah kapital.
            $this->assertStringNotContainsString('<text', $svg, $file);
            $this->assertStringContainsString('<title>PILKETOS 2026 SMP 1 DAWE</title>', $svg, $file);
            $this->assertLessThanOrEqual(60 * 1024, strlen($svg), $file);
        }

        $code = [
            'assets/img/results/result-mark.svg',
        ];

        foreach ($code as $file) {
            $svg = $this->svg($file);
            $this->assertStringContainsString('currentColor', $svg, $file);
            $this->assertStringContainsString('aria-hidden="true"', $svg, $file);
            $this->assertLessThanOrEqual(20 * 1024, strlen($svg), $file);
        }

        $placeholders = ['hero-desktop', 'hero-mobile', 'intro-desktop', 'intro-mobile', 'entry-student', 'entry-teacher'];

        foreach (array_merge($code, array_map(static fn ($f) => 'assets/img/home/' . $f . '.svg', $placeholders), ['assets/img/brand/logo-light.svg']) as $file) {
            $svg = $this->svg($file);
            // Tanpa gradient, tanpa filter/blur, tanpa teks terbaca.
            foreach (['Gradient', '<filter', 'blur(', '<text'] as $banned) {
                $this->assertStringNotContainsString($banned, $svg, $file . ' ' . $banned);
            }
        }
    }

    public function testHomepageStylesUsePagiMuriaAndCalmerType(): void
    {
        $css = (string) file_get_contents(FCPATH . 'assets/css/home.css');

        foreach (['--night:       #101312', '--on-night:    #F2F1EC', '--motion-scene:  800ms'] as $token) {
            $this->assertStringContainsString($token, $css, $token);
        }

        // Stage 18: hero tanpa teks, kontur, dan perforasi.
        foreach (['.hero__title', '.hero__kicker', '.hero__perf', 'hero-contour', 'hero-perforation', 'hero-registration'] as $gone) {
            $this->assertStringNotContainsString($gone, $css, $gone);
        }

        // Tanpa gradient, tanpa teks bergaris tepi, tanpa tracking ekstrem.
        $this->assertDoesNotMatchRegularExpression('/gradient\(/', $css);
        $this->assertStringNotContainsString('text-stroke', $css);
        preg_match_all('/letter-spacing:\s*([0-9.]+)em/', $css, $m);
        $this->assertNotEmpty($m[1]);
        $this->assertLessThanOrEqual(0.22, max(array_map('floatval', $m[1])));
    }

    private function svg(string $path): string
    {
        $this->assertFileExists(FCPATH . $path);

        return (string) file_get_contents(FCPATH . $path);
    }

    private function viewBox(string $path): string
    {
        preg_match('/viewBox="([^"]+)"/', $this->svg($path), $m);

        return $m[1] ?? '';
    }
}
