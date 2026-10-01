<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AssetSource;

/**
 * Stage 17: panel admin tanpa badge status "Sedang Berlangsung", topbar HP
 * ikon saja + logo khusus, note tip kecil di pojok kanan atas judul, tanpa
 * garis atas/bawah (termasuk .pills::after), dua foto pasangan dalam satu
 * kotak persegi (nomor muncul saat hover), hasil akhir terkunci rata tengah,
 * countdown compact lebih lega tanpa garis, tabel bergaya lembar kerja.
 *
 * @internal
 */
final class StageSeventeenRefinementTest extends CIUnitTestCase
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

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    private function asAdmin()
    {
        return $this->withSession(['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true]);
    }

    /** Isi respons tanpa komentar DEBUG-VIEW (lingkungan development). */
    private function body($result): string
    {
        return (string) preg_replace('/<!-- DEBUG-VIEW[^>]*-->\s*/', '', (string) $result->response()->getBody());
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

    private function withPhotos(): void
    {
        $this->db->table('candidates')->where('id', 1)->update(['foto_ketua' => 'ketua-01.jpg', 'foto_wakil' => 'wakil-01.jpg']);
    }

    // ------------------------------------------------------------------
    // Topbar
    // ------------------------------------------------------------------

    public function testTopbarHasNoElectionStatusBadgeOnAnyPage(): void
    {
        foreach (['admin', 'admin/analitik', 'admin/paslon', 'admin/siswa'] as $path) {
            $html = $this->body($this->asAdmin()->get($path));
            $top  = substr($html, strpos($html, '<header class="admin-top">'));
            $top  = substr($top, 0, strpos($top, '</header>'));

            $this->assertStringNotContainsString('admin-top__status', $top, $path);
            $this->assertStringNotContainsString('data-live-badge', $html, $path);
            $this->assertStringNotContainsString('Sedang Berlangsung', $top, $path);
        }

        $this->assertStringNotContainsString('data-live-badge', $this->asset('assets/js/admin-live.js'));
        $this->assertStringNotContainsString('.admin-top__status', $this->asset('assets/css/admin.css'));
    }

    public function testMobileTopbarIsIconOnlyMenuWithDedicatedLogo(): void
    {
        $html = $this->body($this->asAdmin()->get('admin'));

        $this->assertMatchesRegularExpression('#<button type="button" class="admin-top__menu"[^>]*data-admin-menu>\s*<svg[^>]*>.*?</svg><span class="visually-hidden">Menu</span>\s*</button>#s', $html);
        // Stage 19: path, tinggi, dan alt diatur Config\Homepage; ukuran asli dibaca otomatis.
        $this->assertMatchesRegularExpression('#<a class="admin-top__logo" href="[^"]+/admin" style="--admin-logo-h: 34px;">\s*<img src="[^"]+assets/img/brand/logo-admin-mobile\.svg\?v=\d+" alt="PILKETOS Panel Admin: Beranda"\s+width="246" height="64"#', html_entity_decode($html, ENT_QUOTES | ENT_HTML5));

        $svg = $this->asset('assets/img/brand/logo-admin-mobile.svg');
        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
        $this->assertStringContainsString('<title>PILKETOS Panel Admin</title>', $svg);
        $this->assertStringNotContainsString('<text', $svg, 'huruf berupa path, tidak bergantung font perangkat');

        $css  = $this->asset('assets/css/admin.css');
        $menu = $this->rule($css, '.admin-top__menu');
        $this->assertStringContainsString('border: 0;', $menu);
        $this->assertStringContainsString('background: transparent;', $menu);
        $this->assertStringContainsString('width: 28px;', $this->rule($css, '.admin-top__menu .icon'));
        $this->assertStringContainsString('display: none;', $this->rule($css, '.admin-top__logo'));
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{[^@]*\.admin-top__logo \{\s*display: block;/', $css);
    }

    // ------------------------------------------------------------------
    // Note tip & garis
    // ------------------------------------------------------------------

    public function testNoteTipsAreSmallAtTopRightAfterText(): void
    {
        $css = $this->asset('assets/css/admin.css');

        $hint = $this->rule($css, '.admin-head__hint');
        $this->assertStringContainsString('width: 22px;', $hint);
        $this->assertStringContainsString('vertical-align: top;', $hint);
        $this->assertStringContainsString('border: 0;', $hint);

        $tip = $this->rule($css, '.note-tip');
        $this->assertStringContainsString('align-self: flex-start;', $tip);
        $this->assertStringContainsString('width: 20px;', $this->rule($css, '.note-tip__icon'));
        $this->assertStringContainsString('width: 15px;', $this->rule($css, '.note-tip__icon .icon'));
    }

    public function testAdminStylesHaveNoTopOrBottomBorders(): void
    {
        foreach (['assets/css/admin.css', 'assets/css/results.css'] as $file) {
            $css = $this->asset($file);

            // hanya spinner (border-top-color) dan penghapus garis (border-bottom: 0) yang tersisa
            preg_match_all('/border-(?:top|bottom)(?:-width|-style)?\s*:(?!\s*0;)[^;]+;/', $css, $matches);
            $this->assertSame([], $matches[0], $file);
        }

        $admin = $this->asset('assets/css/admin.css');
        $this->assertStringNotContainsString('.pills::after', $admin);
        $this->assertStringNotContainsString('border-top', $this->rule($admin, '.table-scroll'));
        $this->assertStringContainsString('border-bottom: 0;', $this->rule($admin, '.admin .kv-list__row'));

        $html = $this->body($this->asAdmin()->get('admin/hasil'));
        $this->assertStringNotContainsString('border-top: 0.3mm', $html);
    }

    // ------------------------------------------------------------------
    // Foto pasangan
    // ------------------------------------------------------------------

    public function testResultsListShowsPairPhotosInOneSquareWithHoverNumber(): void
    {
        $this->withPhotos();

        foreach (['admin', 'admin/analitik'] as $path) {
            $html = $this->body($this->asAdmin()->get($path));
            $list = substr($html, strpos($html, '<ol class="results__list">'));
            // URL gambar di atribut di-escape (esc 'attr'); dibaca sebagai teks
            $list = html_entity_decode(substr($list, 0, strpos($list, '</ol>')), ENT_QUOTES | ENT_HTML5);

            $this->assertStringNotContainsString('result__no', $list, $path);
            $this->assertMatchesRegularExpression('#<span class="pair-thumb result__thumb" aria-hidden="true">\s*<span class="pair-thumb__half pair-thumb__half--ketua">\s*<img src="[^"]+/uploads/candidates/ketua-01\.jpg" alt=""[^>]*>\s*</span>\s*<span class="pair-thumb__half pair-thumb__half--wakil">\s*<img src="[^"]+/uploads/candidates/wakil-01\.jpg" alt=""[^>]*>\s*</span>\s*<span class="pair-thumb__no">01</span>#', $list, $path);
            // tanpa foto: inisial di atas warna aksen
            $this->assertMatchesRegularExpression('#<span class="pair-thumb__half pair-thumb__half--ketua">\s*<span class="pair-thumb__mono">[A-Z?]{1,2}</span>#', $list, $path);
            $this->assertStringContainsString('<span class="pair-thumb__no">02</span>', $list, $path);
            // nomor tetap terbaca pembaca layar
            $this->assertStringContainsString('<span class="visually-hidden">Pasangan 01:</span>', $list, $path);
        }
    }

    public function testCandidateCardsShowPairPhotosInsteadOfNumberBox(): void
    {
        $this->withPhotos();

        $html = html_entity_decode($this->body($this->asAdmin()->get('admin/paslon')), ENT_QUOTES | ENT_HTML5);

        $this->assertStringNotContainsString('cand-card__no', $html);
        $this->assertSame(3, substr_count($html, '<span class="pair-thumb cand-card__thumb" aria-hidden="true">'));
        $this->assertStringContainsString('/uploads/candidates/ketua-01.jpg', $html);
        $this->assertStringContainsString('<span class="pair-thumb__no">03</span>', $html);

        $css = $this->asset('assets/css/admin.css');
        $this->assertStringContainsString('opacity: 0.4;', $this->rule($css, '.cand-card--inactive .cand-card__thumb'));
    }

    public function testLiveCountCarriesPairPhotosForRebuiltList(): void
    {
        $this->withPhotos();

        $result = $this->asAdmin()
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get('admin/hitung-suara');
        $data  = json_decode((string) $result->response()->getBody(), true);
        $first = $data['candidates'][0];

        $this->assertStringEndsWith('/uploads/candidates/ketua-01.jpg', $first['photo_ketua']);
        $this->assertStringEndsWith('/uploads/candidates/wakil-01.jpg', $first['photo_wakil']);
        $this->assertNull($data['candidates'][1]['photo_ketua']);
        $this->assertMatchesRegularExpression('/^[A-Z?]{1,2}$/', $data['candidates'][1]['ketua_initials']);
        $this->assertMatchesRegularExpression('/^[A-Z?]{1,2}$/', $data['candidates'][1]['wakil_initials']);

        $js = $this->asset('assets/js/admin-live.js');
        $this->assertStringContainsString("make('span', 'pair-thumb result__thumb')", $js);
        $this->assertStringContainsString("make('span', 'pair-thumb__no', c.label)", $js);
        $this->assertStringNotContainsString("'result__no'", $js);
    }

    public function testPairNumberAppearsSemiTransparentOnHover(): void
    {
        $css = $this->asset('assets/css/admin.css');

        $this->assertStringContainsString('aspect-ratio: 1;', $this->rule($css, '.pair-thumb'));
        $this->assertStringContainsString('grid-template-columns: 1fr 1fr;', $this->rule($css, '.pair-thumb'));
        $this->assertStringContainsString('opacity: 0;', $this->rule($css, '.pair-thumb__no'));

        $layer = $this->rule($css, '.pair-thumb__no::before');
        $this->assertStringContainsString('background: var(--accent);', $layer);
        $this->assertMatchesRegularExpression('/opacity: 0\.[5-8]\d*;/', $layer, 'foto tetap sedikit terlihat');

        $this->assertMatchesRegularExpression('/@media \(hover: hover\) \{\s*\.pair-thumb:hover \.pair-thumb__no \{ opacity: 1; \}/', $css);
        $this->assertMatchesRegularExpression('/@media \(hover: none\) \{\s*\.pair-thumb__no \{[^}]*opacity: 1;/', $css);
    }

    // ------------------------------------------------------------------
    // Hasil akhir & countdown
    // ------------------------------------------------------------------

    public function testLockedFinalResultIsCenteredWithoutStatusLabel(): void
    {
        $html = $this->body($this->asAdmin()->get('admin/hasil'));

        $this->assertStringContainsString('Hasil akhir belum tersedia', $html);
        $this->assertStringNotContainsString('Status sekarang', $html);
        $this->assertStringContainsString('countdown--compact', $html);

        $css    = $this->asset('assets/css/results.css');
        $locked = $this->rule($css, '.final-locked');
        $this->assertStringContainsString('justify-items: center;', $locked);
        $this->assertStringContainsString('text-align: center;', $locked);
        $this->assertStringContainsString('justify-content: center;', $this->rule($css, '.final-locked .countdown__units'));
    }

    public function testCompactCountdownIsRoomierWithoutBorder(): void
    {
        $css = $this->asset('assets/css/app.css');

        $box = $this->rule($css, '.countdown--compact');
        $this->assertStringNotContainsString('border:', $box);
        $this->assertStringContainsString('background: var(--paper-dim);', $box);

        $odo = $this->rule($css, '.countdown--compact .odo');
        $this->assertStringContainsString('--h: 1.55em;', $odo);
        $this->assertStringContainsString('width: 1.05em;', $odo);
        $this->assertStringContainsString('gap: 0.14em;', $this->rule($css, '.countdown--compact .countdown__digits'));

        // Beranda admin memakai countdown yang sama
        $html = $this->body($this->asAdmin()->get('admin'));
        $this->assertStringContainsString('countdown countdown--compact', $html);
    }

    // ------------------------------------------------------------------
    // Tabel
    // ------------------------------------------------------------------

    public function testTablesUseSpreadsheetGrid(): void
    {
        $css = $this->asset('assets/css/admin.css');

        $cells = $this->rule($css, ".data-table th,\n.data-table td");
        $this->assertStringContainsString('border: 1px solid var(--sheet-grid);', $cells);

        $head = $this->rule($css, '.data-table thead th');
        $this->assertStringContainsString('background: var(--sheet-head);', $head);
        $this->assertStringNotContainsString('text-transform: uppercase;', $head);

        $this->assertStringNotContainsString('.data-table th:first-child', $css);
        $this->assertStringContainsString('border: 1px solid var(--sheet-grid);', $this->rule($css, ".spec-table th,\n.spec-table td"));

        $html = $this->body($this->asAdmin()->get('admin'));
        $this->assertStringContainsString('<table class="data-table data-table--recap"', $html);
    }
}
