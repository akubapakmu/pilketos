<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Stage 12: breadcrumb ikon (topbar di desktop, ikon saja di HP), kepala
 * halaman rata tengah tanpa garis bawah, kerangka "memuat" analitik
 * (pengganti garis progres), "Selengkapnya" & kolom "Grafik", kartu paslon
 * tanpa eyebrow + timeline asset, timeline tahapan unlock, list-bar, live
 * search di semua pencarian admin.
 *
 * @internal
 */
final class StageTwelveRefinementTest extends CIUnitTestCase
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
        return (string) file_get_contents(FCPATH . $path);
    }

    /** Isi satu aturan CSS (selektor persis, sampai kurung kurawal tutup pertama). */
    private function rule(string $css, string $selector): string
    {
        $this->assertMatchesRegularExpression('/(^|\n)' . preg_quote($selector, '/') . ' \{/', $css, $selector);
        preg_match('/(?:^|\n)' . preg_quote($selector, '/') . ' \{([^}]*)\}/', $css, $match);

        return $match[1];
    }

    private function vote(int $studentId, int $candidateId): void
    {
        $this->db->table('student_votes')->insert([
            'election_id'  => 1,
            'student_id'   => $studentId,
            'candidate_id' => $candidateId,
            'status'       => 'LOCKED',
            'voted_at'     => '2026-10-01 08:05:09',
            'device_info'  => 'HP / Android 14',
            'browser_info' => 'Chrome 140',
        ]);
    }

    // ------------------------------------------------------------------
    // breadcrumb & kepala halaman
    // ------------------------------------------------------------------

    public function testBreadcrumbMovesToTopbarAndShowsIcons(): void
    {
        $html = $this->body($this->asAdmin()->get('admin/siswa/1'));

        // Topbar: breadcrumb menggantikan judul teks.
        $this->assertStringNotContainsString('<p class="admin-top__title">', $html);
        $this->assertStringContainsString('<div class="admin-top__title"><nav class="crumbs" aria-label="Breadcrumb">', $html);
        // HP: salinan yang sama di awal konten.
        $this->assertStringContainsString('<div class="admin-crumbs"><nav class="crumbs" aria-label="Breadcrumb">', $html);
        $this->assertSame(2, substr_count($html, '<nav class="crumbs" aria-label="Breadcrumb">'));

        // Tidak lagi di dalam kepala halaman.
        $head = substr($html, (int) strpos($html, '<header class="admin-head">'));
        $head = substr($head, 0, (int) strpos($head, '</header>'));
        $this->assertStringNotContainsString('crumbs', $head);

        // Ikon sama dengan menu samping; label tetap ada untuk pembaca layar.
        foreach (['grid' => 'Beranda', 'users' => 'Siswa', 'eye' => 'Detail'] as $icon => $label) {
            $this->assertMatchesRegularExpression('#<svg class="icon&\#x20;icon--' . $icon . '&\#x20;crumbs__icon"[^>]*>.*?</svg><span class="crumbs__label">' . $label . '</span>#', $html);
        }
        $this->assertStringNotContainsString('crumbs__dot', $html);

        // Halaman tanpa jejak tambahan: hanya Beranda.
        $dashboard = $this->body($this->asAdmin()->get('admin'));
        $this->assertSame(2, substr_count($dashboard, 'class="crumbs__item is-current"'));
        $this->assertStringContainsString('<span class="crumbs__label">Beranda</span></span>', $dashboard);

        $css = $this->asset('assets/css/admin.css');
        $this->assertStringContainsString('.admin-crumbs { display: none; }', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{\s*\.admin-top__title \{ display: none; \}\s*\.admin-crumbs \{ display: flex;/', $css);
        $this->assertStringContainsString('clip-path: inset(50%);', $this->rule($css, '.admin-crumbs .crumbs__label'));
    }

    public function testAdminHeadIsCenteredWithoutBottomBorder(): void
    {
        $css  = $this->asset('assets/css/admin.css');
        $head = $this->rule($css, '.admin-head');

        $this->assertStringNotContainsString('border-bottom', $head);
        $this->assertStringContainsString('align-items: center;', $head);
        $this->assertStringContainsString('text-align: center;', $head);
        $this->assertStringContainsString('justify-content: center;', $this->rule($css, '.admin-head__actions'));
        $this->assertStringNotContainsString('border-left', $this->rule($css, '.js .admin-head__lede[data-lede]'));

        $results = $this->asset('assets/css/results.css');
        $this->assertStringContainsString('.final-page > .admin-head { margin-bottom: var(--space-5); }', $results);
    }

    // ------------------------------------------------------------------
    // analitik: kerangka "memuat"
    // ------------------------------------------------------------------

    public function testAnalyticsShowsSkeletonLoaderInsteadOfProgressLine(): void
    {
        $html = $this->body($this->asAdmin()->get('admin/analitik'));

        $this->assertStringContainsString('<div class="pane-stage">', $html);
        $this->assertStringContainsString('<div class="pane-loader" data-pane-loader hidden aria-hidden="true">', $html);
        $this->assertStringContainsString('<span data-pane-loader-label>Memuat bagian</span>', $html);
        $this->assertSame(4, substr_count($html, 'class="skel skel--row"'));

        $css = $this->asset('assets/css/admin.css');
        $this->assertStringNotContainsString('.pills.is-loading', $css);
        $this->assertStringNotContainsString('pane-progress', $css);
        $this->assertStringContainsString('.pane-host.is-hidden { opacity: 0; }', $css);
        $this->assertStringContainsString('position: sticky;', $this->rule($css, '.pane-loader__inner'));

        $js = $this->asset('assets/js/admin-analytics.js');
        $this->assertStringNotContainsString("nav.classList.add('is-loading')", $js);
        $this->assertStringNotContainsString('topTitle', $js);
        $this->assertStringContainsString("document.querySelector('[data-pane-loader]')", $js);
        $this->assertStringContainsString('var LOADER_DELAY = 120;', $js);
        $this->assertStringContainsString('window.setTimeout(resolve, loaderRemaining());', $js);
    }

    // ------------------------------------------------------------------
    // teks dasbor & unlock
    // ------------------------------------------------------------------

    public function testPanelLinksSaySelengkapnyaAndRecapColumnSaysGrafik(): void
    {
        $dashboard = $this->body($this->asAdmin()->get('admin'));

        $this->assertStringContainsString('Selengkapnya<span class="visually-hidden">: analitik</span>', $dashboard);
        $this->assertStringContainsString('Selengkapnya<span class="visually-hidden">: detail suara</span>', $dashboard);
        $this->assertStringNotContainsString('>Analitik lengkap <', $dashboard);
        $this->assertStringContainsString('<th scope="col" class="dist">Grafik</th>', $dashboard);
        $this->assertStringNotContainsString('Komposisi', $dashboard);

        $analytics = $this->body($this->asAdmin()->get('admin/analitik/rombel'));
        $this->assertStringContainsString('<th scope="col" class="dist">Grafik</th>', $analytics);

        $unlock = $this->body($this->asAdmin()->get('admin/buka-kunci'));
        $this->assertStringContainsString('Selengkapnya<span class="visually-hidden">: audit log unlock</span>', $unlock);

        $live = $this->asset('assets/js/admin-live.js');
        $this->assertStringContainsString("headCell('Grafik', 'dist')", $live);
        $this->assertStringNotContainsString('Komposisi', $live);
    }

    // ------------------------------------------------------------------
    // paslon
    // ------------------------------------------------------------------

    public function testCandidateCardsDropEyebrowAndShowAssetTimeline(): void
    {
        $this->db->table('candidates')->where('id', 3)->update(['status_aktif' => 0]);

        $html = $this->body($this->asAdmin()->get('admin/paslon'));

        $this->assertStringNotContainsString('cand-card__eyebrow', $html);
        $this->assertStringContainsString('<span class="visually-hidden">Pasangan 01: </span>', $html);
        $this->assertStringContainsString('<div><dt>Tema</dt><dd>Terracotta</dd></div>', $html);
        $this->assertSame(1, substr_count($html, '<div><dt>Status</dt><dd><span class="pill pill--muted">Nonaktif</span></dd></div>'));

        $this->assertSame(3, substr_count($html, '<ol class="asset-dots" aria-label="Kelengkapan asset">'));
        // Stage 16: 6 slot per pasangan (gambar pembuka & latar penutup menggantikan latar panggung, artwork, tekstur).
        $this->assertSame(3 * count(\App\Libraries\CandidateAssets::SLOTS), substr_count($html, '<span class="asset-dots__node" aria-hidden="true"></span>'));
        $this->assertSame(18, 3 * count(\App\Libraries\CandidateAssets::SLOTS));
        $this->assertStringContainsString('<span class="asset-dots__label">Foto calon ketua<span class="visually-hidden">: belum ada</span></span>', $html);

        $css = $this->asset('assets/css/admin.css');
        $this->assertStringNotContainsString('.cand-list { border-top', $css);
        $this->assertStringNotContainsString('border-bottom', $this->rule($css, '.cand-card'));
        $this->assertStringNotContainsString('.cand-card__eyebrow', $css);
        $this->assertStringContainsString('container-type: inline-size;', $this->rule($css, '.cand-card__body'));
        $this->assertStringContainsString('.asset-dots__item.is-filled + .asset-dots__item.is-filled::before { background: var(--ink); }', $css);
        $this->assertMatchesRegularExpression('/@container \(min-width: 560px\) \{\s*ol\.asset-dots \{ grid-auto-columns: minmax\(0, 1fr\); grid-auto-flow: column; \}/', $css);
    }

    // ------------------------------------------------------------------
    // unlock: timeline tahapan
    // ------------------------------------------------------------------

    public function testUnlockStepsAreATimeline(): void
    {
        $index = $this->body($this->asAdmin()->get('admin/buka-kunci'));

        $this->assertStringContainsString('<li class="steps__item is-current" aria-current="step"><span class="steps__node" aria-hidden="true">01</span><span class="steps__label">Cari pemilih</span></li>', $index);
        $this->assertStringContainsString('<li class="steps__item"><span class="steps__node" aria-hidden="true">04</span><span class="steps__label">Konfirmasi</span></li>', $index);
        $this->assertSame(1, substr_count($index, 'aria-current="step"'));

        $form = $this->body($this->asAdmin()->get('admin/buka-kunci/siswa/1'));

        $this->assertMatchesRegularExpression('#<li class="steps__item is-done"><span class="steps__node" aria-hidden="true"><svg class="icon&\#x20;icon--check"[^>]*>.*?</svg></span><span class="steps__label">Cari pemilih<span class="visually-hidden"> \(selesai\)</span></span></li>#', $form);
        $this->assertSame(3, substr_count($form, 'class="steps__item is-current"'));
        $this->assertSame(1, substr_count($form, 'aria-current="step"'));

        $css = $this->asset('assets/css/admin.css');
        $this->assertStringContainsString('grid-auto-flow: column;', $this->rule($css, 'ol.steps'));
        $this->assertStringContainsString('border-radius: 50%;', $this->rule($css, '.steps__node'));
    }

    // ------------------------------------------------------------------
    // list-bar & live search
    // ------------------------------------------------------------------

    public function testListBarHasRoomAndCountChip(): void
    {
        $css = $this->asset('assets/css/admin.css');

        $this->assertStringContainsString('margin: var(--space-5) 0 var(--space-5);', $this->rule($css, '.list-bar'));
        $this->assertStringContainsString('.list-bar .result-count { margin: 0; }', $css);
        $this->assertStringContainsString('border-radius: 999px;', $this->rule($css, '.result-count strong'));
    }

    public function testEverySearchFormIsLiveWithReplaceableRegions(): void
    {
        $this->vote(1, 1);

        $pages = [
            'admin/siswa'          => true,
            'admin/guru'           => true,
            'admin/riwayat'        => true,
            'admin/analitik/suara' => true,
            'admin/buka-kunci'     => false,
        ];

        foreach ($pages as $path => $hasActions) {
            $html = $this->body($this->asAdmin()->get($path));

            $this->assertMatchesRegularExpression('/<form class="[^"]*" method="get" action="[^"]*" role="search"[^>]* data-live-search>/', $html, $path);
            $this->assertMatchesRegularExpression('#<span class="search-field"><svg class="icon&\#x20;icon--search&\#x20;search-field__icon"[^>]*>.*?</svg><input type="search"#', $html, $path);
            $this->assertSame(1, substr_count($html, 'data-live-region="results"'), $path);
            $this->assertSame($hasActions ? 1 : 0, substr_count($html, 'data-live-region="actions"'), $path);
        }

        // Hitungan & tabel berada di dalam wilayah hasil.
        $students = $this->body($this->asAdmin()->get('admin/siswa?q=Ahmad'));
        $region   = substr($students, (int) strpos($students, 'data-live-region="results"'));
        $this->assertLessThan(strpos($region, 'id="voter-table"'), strpos($region, 'class="result-count"'));
        $this->assertStringContainsString('data-secret-toggle', $region);
        $this->assertStringContainsString('<a class="btn btn--sm btn--outline" href="' . site_url('admin/siswa') . '">Reset</a>', $students);

        // Unlock: wilayah selalu ada; tanpa kueri kosong, dengan kueri berisi hasil.
        $unlock = $this->body($this->asAdmin()->get('admin/buka-kunci'));
        $this->assertStringNotContainsString('pemilih ditemukan', $unlock);
        $found = $this->body($this->asAdmin()->get('admin/buka-kunci?q=Ahmad'));
        $this->assertStringContainsString('<strong>1</strong> pemilih ditemukan untuk "Ahmad".', $found);

        // Detail suara: fragmen bagian juga memuat wilayah untuk live search.
        $pane = $this->body($this->asAdmin()->withHeaders(['X-Analytics-Pane' => '1'])->get('admin/analitik/suara?q=Ahmad'));
        $this->assertStringNotContainsString('<html', $pane);
        $this->assertStringContainsString('data-live-region="results"', $pane);
        $this->assertStringContainsString('data-live-region="actions"', $pane);
        $this->assertStringContainsString('<strong>1</strong> baris suara sesuai filter', $pane);

        $css = $this->asset('assets/css/admin.css');
        $this->assertStringContainsString('.is-searching .search-field::after { opacity: 1; animation: spin 0.8s linear infinite; }', $css);
        $this->assertStringContainsString('[data-live-region].is-stale { opacity: 0.55; }', $css);
    }

    public function testLiveSearchScriptContract(): void
    {
        $js = $this->asset('assets/js/admin.js');

        $this->assertStringContainsString('function initLiveSearch() {', $js);
        $this->assertStringContainsString('initLiveSearch();', $js);
        $this->assertStringContainsString("input.closest('form[data-live-search]')", $js);
        $this->assertStringContainsString('var DELAY = 300;', $js);
        $this->assertStringContainsString("document.querySelectorAll('[data-live-region]')", $js);
        $this->assertStringContainsString("new DOMParser().parseFromString(html, 'text/html')", $js);
        $this->assertStringContainsString("window.history.replaceState(window.history.state, '', url.href);", $js);
        $this->assertStringContainsString("headers['X-Analytics-Pane'] = '1';", $js);
        // Form audit punya kolom bernama "action": URL diambil dari atributnya.
        $this->assertStringContainsString("form.getAttribute('action')", $js);
        $this->assertStringNotContainsString('new URL(form.action', $js);
        // Submit diambil alih sebelum penjaga submit ganda app.js.
        $this->assertMatchesRegularExpression("/form\\.hasAttribute\\('data-pane-form'\\) \\|\\| !enhanced\\(form\\)\\) \\{\\s*return;\\s*\\}\\s*event\\.preventDefault\\(\\);\\s*event\\.stopImmediatePropagation\\(\\);\\s*search\\(form\\);\\s*\\}, true\\);/", $js);
        // Tombol kode unik di hasil baru ikut dipasang.
        $this->assertStringContainsString('initSecrets(node);', $js);
        $this->assertStringContainsString("button.dataset.secretReady = '1';", $js);
        $this->assertStringNotContainsString('eval(', $js);
    }
}
