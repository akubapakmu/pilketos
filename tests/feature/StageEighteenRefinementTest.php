<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Stage 18: scene pertama beranda hanya gambar hero (desktop & HP) + tombol
 * "Masuk untuk memilih" dan "Lihat perolehan suara" (tanpa kicker, judul
 * terlihat, baris pendukung, kontur SVG, dan perforasi); tautan "Kembali ke
 * awal" di scene perolehan suara menjadi panah saja.
 *
 * @internal
 */
final class StageEighteenRefinementTest extends CIUnitTestCase
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
        service('renderer')->resetData();
    }

    /**
     * Isi beranda tanpa komentar DEBUG-VIEW (lingkungan development), entitas
     * didekode (atribut di-escape esc(..., 'attr')).
     */
    private function home(): string
    {
        $html = (string) preg_replace('/<!-- DEBUG-VIEW[^>]*-->\s*/', '', (string) $this->get('/')->response()->getBody());

        return html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
    }

    /** Scene hero utuh: dari <section> hero sampai <section> pintu masuk. */
    private function hero(string $html): string
    {
        $start = strpos($html, '<section class="scene scene--hero');
        $end   = strpos($html, '<section class="scene scene--entry', (int) $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    /** Teks yang terlihat: tanpa elemen .visually-hidden, tag, dan spasi berlebih. */
    private function visibleText(string $html): string
    {
        $html = (string) preg_replace('#<(\w+) class="visually-hidden"[^>]*>.*?</\1>#s', '', $html);

        return trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    /** Isi satu aturan CSS (selektor persis, sampai kurung kurawal tutup pertama). */
    private function rule(string $css, string $selector): string
    {
        $this->assertMatchesRegularExpression('/(^|\n)\s*' . preg_quote($selector, '/') . ' \{/', $css, $selector);
        preg_match('/(?:^|\n)\s*' . preg_quote($selector, '/') . ' \{([^}]*)\}/', $css, $match);

        return $match[1];
    }

    public function testHeroShowsOnlyTheImageAndBothButtons(): void
    {
        $hero = $this->hero($this->home());

        // Gambar hero lanskap & potret tetap.
        $this->assertStringContainsString('class="hero-bg__img" src="' . base_url('assets/img/home/hero-desktop.svg'), $hero);
        $this->assertStringContainsString('media="(orientation: portrait)" srcset="' . base_url('assets/img/home/hero-mobile.svg'), $hero);

        // Satu-satunya teks terlihat = dua tombol.
        $this->assertSame('Masuk untuk memilih Lihat perolehan suara', $this->visibleText($hero));
        $this->assertStringContainsString('href="#masuk" data-scene-link>Masuk untuk memilih', $hero);
        $this->assertStringContainsString('href="#perolehan" data-scene-link>Lihat perolehan suara</a>', $hero);

        // Judul tetap untuk pembaca layar (nama scene, satu <h1>).
        $this->assertStringContainsString('aria-labelledby="hero-title"', $hero);
        $this->assertStringContainsString('<h1 class="visually-hidden" id="hero-title">Pemilihan Ketua & Wakil Ketua OSIS SMP 1 DAWE 2026</h1>', $hero);

        foreach (['hero__kicker', 'hero__reg', 'hero__title', 'hero__word', 'hero__support', 'hero__perf', 'hero-bg__contour', 'PILKETOS 2026', 'Satu pemilih'] as $gone) {
            $this->assertStringNotContainsString($gone, $hero, $gone);
        }
    }

    public function testContourPerforationAndRegistrationAssetsAreRemoved(): void
    {
        foreach (['hero-contour.svg', 'hero-contour-mobile.svg', 'hero-perforation.svg', 'hero-registration.svg'] as $file) {
            $this->assertFileDoesNotExist(FCPATH . 'assets/img/home/' . $file, $file);
        }

        $css = (string) file_get_contents(FCPATH . 'assets/css/home.css');

        foreach (['hero-contour', 'hero-perforation', 'hero-registration', '.hero-bg__contour', '.hero__perf', '.hero__kicker', '.hero__title', '.hero__support'] as $gone) {
            $this->assertStringNotContainsString($gone, $css, $gone);
        }

        // CTA tetap muncul lebih dulu (slot yang dikosongkan kontur & teks).
        $this->assertStringContainsString('.js.is-ready .scene--hero.is-shown .hero__actions { transition-delay: 240ms; }', $css);
    }

    public function testBackToTopOnTheResultsSceneIsAnArrowOnly(): void
    {
        $html = $this->home();

        $this->assertMatchesRegularExpression('#<a class="scene-top" href="\#beranda" data-scene-link aria-label="Kembali ke awal"><svg class="icon icon--arrow-up"[^>]*aria-hidden="true"[^>]*>.*?</svg></a>#s', $html);
        preg_match('#<a class="scene-top".*?</a>#s', $html, $link);
        $this->assertSame('', $this->visibleText($link[0]));
        // Hanya sebagai nama tautan, tidak lagi sebagai teks.
        $this->assertSame(1, substr_count($html, 'Kembali ke awal'));

        $css  = (string) file_get_contents(FCPATH . 'assets/css/home.css');
        $rule = $this->rule($css, '.scene-top');
        $this->assertStringContainsString('justify-content: center;', $rule);
        $this->assertStringContainsString('min-width: 40px;', $rule);
        $this->assertStringNotContainsString('text-transform', $rule);
        $this->assertStringNotContainsString('letter-spacing', $rule);
        $this->assertStringContainsString('.scene-top .icon { width: 18px; height: 18px; }', $css);
    }
}
