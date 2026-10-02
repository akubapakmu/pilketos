<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\CandidateTheme;
use App\Libraries\HeroText;
use App\Models\SettingModel;
use App\Services\VisitorTracker;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AssetSource;

/**
 * Stage 24: Dasbor & Keluar beranda tanpa garis tepi/latar; kalimat hero
 * dari admin (Teks Halaman Utama, tabel settings) diketik bergantian di
 * tengah hero; Sekilas paslon tanpa jumlah poin misi; pola SVG berulang
 * diganti latar SVG abstrak; profil paslon tanpa px-back, peran di atas nama
 * yang membungkus otomatis, tombol 3D "klik ala Subscribe" (kursor, tulisan
 * di tengah, ringkas); bilik suara dengan menu melayang (Kembali + Dasbor)
 * pengganti navigasi situs, tanpa countdown, kepala rata tengah. Gerak &
 * WebGL diuji di browser (STAGE24-NOTES.md); di sini route, data, markup,
 * dan aturan CSS/JS.
 *
 * @internal
 */
final class StageTwentyFourRefinementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private const STUDENT = ['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true];
    private const TEACHER = ['user_type' => 'teacher', 'teacher_id' => 1, 'isLoggedIn' => true];
    private const ADMIN   = ['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true];

    private const NBSP = "\u{00A0}";

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

    // ------------------------------------------------------------------
    // helper
    // ------------------------------------------------------------------

    private function scheduleAt(string $now, string $start = '2026-10-01 07:00:00', string $end = '2026-10-01 12:00:00'): void
    {
        $this->db->table('elections')->where('id', 1)->update(['start_at' => $start, 'end_at' => $end]);
        Time::setTestNow($now);
    }

    private function body($result): string
    {
        return (string) preg_replace('/<!-- DEBUG-VIEW[^>]*-->\s*/', '', (string) $result->response()->getBody());
    }

    /** URL seperti ditulis view: site_url() di-escape untuk atribut. */
    private function url(string $path): string
    {
        return esc(site_url($path), 'attr');
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

    /** Scene hero beranda: dari <section> hero sampai <section> pintu masuk. */
    private function hero(string $html): string
    {
        $start = strpos($html, '<section class="scene scene--hero');
        $end   = strpos($html, '<section class="scene scene--entry', (int) $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
    }

    private function heroSetting(): ?string
    {
        return model(SettingModel::class)->value(HeroText::SETTING);
    }

    private function postAdmin(string $path, array $fields)
    {
        return $this->withSession(self::ADMIN)->call('POST', $path, [csrf_token() => csrf_hash()] + $fields);
    }

    // ------------------------------------------------------------------
    // beranda: Dasbor & Keluar
    // ------------------------------------------------------------------

    public function testHomeNavActionsHaveNoBorderOrBackground(): void
    {
        $css = $this->asset('assets/css/home.css');

        $action = $this->rule($css, '.site-nav--immersive .site-nav__action');
        $this->assertStringContainsString('border: 0;', $action);
        $this->assertStringContainsString('background: none;', $action);
        $this->assertStringContainsString('padding-inline: 0;', $action);
        $hover = $this->rule($css, '.site-nav--immersive .site-nav__action:hover');
        $this->assertStringContainsString('background: none;', $hover);
        $this->assertStringNotContainsString('border-color', $hover);
        $this->assertStringContainsString('.site-nav--immersive .site-nav__start .site-nav__action { justify-content: flex-start; }', $css);
        $this->assertStringContainsString('.site-nav--immersive .site-nav__end .site-nav__action { justify-content: flex-end; }', $css);

        // Pengguna login tetap mendapat Dasbor & Keluar di beranda.
        $html = $this->body($this->withSession(self::STUDENT)->get('/'));
        $this->assertStringContainsString('site-nav site-nav--immersive', $html);
        $this->assertSame(2, substr_count($html, 'class="site-nav__action"'));
    }

    // ------------------------------------------------------------------
    // teks hero: data & beranda
    // ------------------------------------------------------------------

    public function testMigrationSeedsTheTwelveHeroSentences(): void
    {
        $value = (string) $this->heroSetting();
        $lines = explode("\n", $value);

        $this->assertCount(12, $lines);
        $this->assertSame('PILKETOS SMP 1 DAWE dimulai di sini.', $lines[0]);
        $this->assertSame('Satu suara, satu pilihan, untuk masa depan sekolah.', $lines[3]);
        $this->assertSame('Bersama menentukan pemimpin OSIS SMP 1 DAWE.', $lines[10]);
        $this->assertSame('PILKETOS: Jujur, demokratis, dan bertanggung jawab.', $lines[11]);
        // Nama sekolah selalu kapital (07-TYPOGRAPHY §3).
        $this->assertStringNotContainsString('SMP 1 Dawe', $value);
        $this->assertSame($lines, HeroText::lines());
    }

    public function testHomepageTypesTheHeroSentencesFromTheAdminSetting(): void
    {
        $html = $this->body($this->get('/'));
        $hero = $this->hero($html);

        $this->assertStringContainsString('<div class="hero-type" data-hero-type>', $hero);
        // Kalimat pertama tampil diam tanpa JavaScript; "SMP 1 DAWE" tidak dipenggal.
        $first = 'PILKETOS SMP' . self::NBSP . '1' . self::NBSP . 'DAWE dimulai di sini.';
        $this->assertStringContainsString('<p class="hero-type__text" aria-hidden="true" data-reveal data-hero-type-text>' . $first . '</p>', $hero);
        // Daftar utuh untuk pembaca layar = sumber home.js.
        $this->assertStringContainsString('<ul class="visually-hidden" data-hero-type-lines>', $hero);
        $this->assertSame(12, substr_count($hero, '<li>'));
        $this->assertStringContainsString('<li>PILKETOS: Jujur, demokratis, dan bertanggung jawab.</li>', $hero);
        // Di tengah, sebelum baris tombol.
        $this->assertLessThan(strpos($hero, '<div class="hero-bar">'), strpos($hero, 'data-hero-type'));
        $this->assertStringContainsString('assets/fonts/newsreader-latin-wght-normal.woff2', $html);

        $css = $this->asset('assets/css/home.css');
        $text = $this->rule($css, '.hero-type__text');
        $this->assertStringContainsString('font-family: var(--font-display);', $text);
        $this->assertStringContainsString('text-align: center;', $text);
        $this->assertStringContainsString('text-wrap: balance;', $text);
        $this->assertStringContainsString('white-space: nowrap;', $this->rule($css, '.hero-type__line'));
        $this->assertStringContainsString('.hero-type__rest { color: transparent; }', $css);
        $this->assertStringContainsString('flex: 1 1 auto;', $this->rule($css, '.hero-type'));
        $this->assertStringContainsString('position: absolute;', $this->rule($css, '.hero-type__caret::after'));
    }

    public function testHeroTextIsEscapedAndHiddenWhenEmpty(): void
    {
        model(SettingModel::class)->put(HeroText::SETTING, "<b>Pilih</b> & \"jujur\"\nKalimat kedua");

        $hero = $this->hero($this->body($this->get('/')));
        $this->assertStringContainsString('&lt;b&gt;Pilih&lt;/b&gt; &amp; &quot;jujur&quot;', $hero);
        $this->assertStringNotContainsString('<b>Pilih</b>', $hero);
        $this->assertSame(2, substr_count($hero, '<li>'));

        model(SettingModel::class)->put(HeroText::SETTING, '');
        service('renderer')->resetData();

        $html = $this->body($this->get('/'));
        $this->assertStringNotContainsString('hero-type', $html);
        $this->assertStringNotContainsString('newsreader-latin-wght-normal.woff2', $html);
    }

    public function testHomepageStillRendersWithoutTheSettingsTable(): void
    {
        $this->db->query('DROP TABLE ' . $this->db->escapeIdentifiers($this->db->prefixTable('settings')));

        $this->assertSame([], HeroText::lines());
        $result = $this->get('/');
        $result->assertStatus(200);
        $this->assertStringNotContainsString('hero-type', $this->body($result));
    }

    public function testHeroTextParsingAndLimits(): void
    {
        $this->assertSame(
            ['Satu', 'Dua kalimat', 'SMP 1 DAWE juara'],
            HeroText::parse("  Satu  \r\n\n\tDua\u{200B}   kalimat \n smp 1 dawe juara\n"),
        );
        $this->assertSame([], HeroText::parse(null));
        $this->assertSame([], HeroText::parse(['x']));
        $this->assertSame([], HeroText::parse(" \n \n"));

        $this->assertSame([], HeroText::errors([str_repeat('a', HeroText::MAX_LENGTH)]));
        $this->assertSame(['Kalimat ke-2 terlalu panjang: 81 karakter (maks. 80).'], HeroText::errors(['ok', str_repeat('a', 81)]));
        $this->assertSame(['Maksimal 20 kalimat (terisi 21).'], HeroText::errors(array_fill(0, 21, 'a')));

        $this->assertSame('Bersama SMP' . self::NBSP . '1' . self::NBSP . 'DAWE', HeroText::display('Bersama SMP 1 DAWE'));
        $this->assertSame("a\nb", HeroText::text(['a', 'b']));
    }

    public function testHomeScriptTypesEachSentenceAndPausesThreeSecondsAfterTheLast(): void
    {
        $js = $this->asset('assets/js/home.js');

        $this->assertStringContainsString('function HeroType(el) {', $js);
        $this->assertStringContainsString("el.querySelectorAll('[data-hero-type-lines] li')", $js);
        $this->assertStringContainsString('HeroType.PAUSE = 3000;', $js);
        $this->assertStringContainsString('return last ? HeroType.PAUSE : HeroType.GAP;', $js);
        // Berjalan hanya saat scene hero aktif; gerak dikurangi tanpa efek ketik.
        $this->assertStringContainsString('heroType.setActive(index === heroIndex);', $js);
        $this->assertStringContainsString('var delay = reduced ? this.stepStill() : this.stepTyping();', $js);

        $section = substr($js, (int) strpos($js, 'function HeroType(el)'), 9000);
        $this->assertStringNotContainsString('innerHTML', $section);
        $this->assertStringContainsString('textContent', $section);
    }

    // ------------------------------------------------------------------
    // teks hero: panel admin
    // ------------------------------------------------------------------

    public function testAdminPageListsTheSentencesInTheThirdMenuGroup(): void
    {
        $result = $this->withSession(self::ADMIN)->get('admin/halaman-utama');
        $result->assertStatus(200);
        $html = $this->body($result);

        $this->assertMatchesRegularExpression('#<a href="' . preg_quote(site_url('admin/halaman-utama'), '#') . '" class="admin-side__link" aria-current="page">\s*<svg[^>]*icon--type.*?<span>Teks Halaman Utama</span>#s', $html);
        // Tetap tiga kelompok menu; menu baru tepat setelah Jadwal Pemilihan.
        $this->assertSame(3, substr_count($html, '<ul class="admin-side__list">'));
        $this->assertLessThan(strpos($html, '<span>Teks Halaman Utama</span>'), strpos($html, '<span>Jadwal Pemilihan</span>'));
        $this->assertLessThan(strpos($html, '<span>Unlock Hak Suara</span>'), strpos($html, '<span>Teks Halaman Utama</span>'));

        $this->assertStringContainsString('<h1 class="admin-head__title">Teks halaman utama</h1>', $html);
        $this->assertStringContainsString('action="' . site_url('admin/halaman-utama') . '" method="post"', $html);
        $this->assertMatchesRegularExpression('#<textarea class="hero-lines-input" id="hero_lines" name="hero_lines"[^>]*>PILKETOS SMP 1 DAWE dimulai di sini\.\nKenali calon ketua#', $html);
        $this->assertSame(12, substr_count($html, '<li class="hero-lines__item">'));
        $this->assertStringContainsString('crumbs__label">Teks halaman utama</span>', $html);
        $this->assertStringContainsString('icon--type', $html);

        $this->assertSame('Admin: Teks halaman utama', VisitorTracker::pageLabel('admin/halaman-utama'));
        $this->assertNotSame('', icon('type'));
    }

    public function testOnlyAdminsReachTheHeroTextPage(): void
    {
        $this->get('admin/halaman-utama')->assertRedirectTo(site_url('admin/masuk'));
        $this->withSession(self::STUDENT)->get('admin/halaman-utama')->assertRedirectTo(site_url('admin/masuk'));
        $this->withSession(self::STUDENT)->call('POST', 'admin/halaman-utama', [csrf_token() => csrf_hash(), 'hero_lines' => 'Diretas'])
            ->assertRedirectTo(site_url('admin/masuk'));

        $this->assertCount(12, HeroText::lines());
    }

    public function testAdminSavesOneSentencePerLineAndItIsAudited(): void
    {
        $this->postAdmin('admin/halaman-utama', ['hero_lines' => "  Kalimat   satu.  \r\n\r\n Kalimat dua di smp 1 dawe.\n"])
            ->assertRedirectTo(site_url('admin/halaman-utama'));

        $this->assertSame("Kalimat satu.\nKalimat dua di SMP 1 DAWE.", $this->heroSetting());
        $this->assertSame('Teks halaman utama disimpan: 2 kalimat diketik bergantian.', session('success'));

        $audit = $this->db->table('audit_logs')->where('action', 'HOMEPAGE_TEXT')->get()->getResultArray();
        $this->assertCount(1, $audit);
        $this->assertSame('Teks halaman utama: 2 kalimat (sebelumnya 12). Kalimat satu. | Kalimat dua di SMP 1 DAWE.', $audit[0]['description']);
        $this->withSession(self::ADMIN)->get('admin/riwayat')->assertSee('Ubah teks halaman utama');

        service('renderer')->resetData();
        $hero = $this->hero($this->body($this->get('/')));
        $this->assertStringContainsString('data-hero-type-text>Kalimat satu.</p>', $hero);
        $this->assertSame(2, substr_count($hero, '<li>'));

        // Isi sama: tidak disimpan ulang, tidak tercatat lagi.
        $this->postAdmin('admin/halaman-utama', ['hero_lines' => "Kalimat satu.\nKalimat dua di SMP 1 DAWE."]);
        $this->assertSame('Tidak ada perubahan teks.', session('success'));
        $this->assertSame(1, $this->db->table('audit_logs')->where('action', 'HOMEPAGE_TEXT')->countAllResults());

        // Dikosongkan: hero tanpa teks.
        $this->postAdmin('admin/halaman-utama', ['hero_lines' => " \n "]);
        $this->assertSame('', $this->heroSetting());
        $this->assertSame([], HeroText::lines());
        $this->assertStringContainsString('Hero tampil tanpa teks.', (string) $this->db->table('audit_logs')->where('action', 'HOMEPAGE_TEXT')->orderBy('id', 'DESC')->get()->getRowArray()['description']);
        $this->withSession(self::ADMIN)->get('admin/halaman-utama')->assertSee('Belum ada kalimat.');
    }

    public function testAdminRejectsTooLongTooManyOrMalformedInput(): void
    {
        $this->postAdmin('admin/halaman-utama', ['hero_lines' => "Pendek.\n" . str_repeat('a', 81)])
            ->assertRedirectTo(site_url('admin/halaman-utama'));
        $this->assertSame('Kalimat ke-2 terlalu panjang: 81 karakter (maks. 80).', session('errors')['hero_lines'] ?? null);

        $this->postAdmin('admin/halaman-utama', ['hero_lines' => implode("\n", array_fill(0, 21, 'Kalimat.'))]);
        $this->assertSame('Maksimal 20 kalimat (terisi 21).', session('errors')['hero_lines'] ?? null);

        // Isian berbentuk array tidak pernah dianggap "kosongkan teks".
        $this->postAdmin('admin/halaman-utama', ['hero_lines' => ['x']]);
        $this->assertStringContainsString('Isi teks tidak valid', session('errors')['hero_lines'] ?? '');

        $this->assertCount(12, HeroText::lines());
        $this->assertSame(0, $this->db->table('audit_logs')->where('action', 'HOMEPAGE_TEXT')->countAllResults());
    }

    // ------------------------------------------------------------------
    // Sekilas paslon & latar SVG abstrak
    // ------------------------------------------------------------------

    public function testLineupCardsHaveNoMissionCount(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/sekilas-paslon'));

        $this->assertStringNotContainsString('poin misi', $html);
        $this->assertStringNotContainsString('pair-card__meta', $html);
        $this->assertStringNotContainsString('.pair-card__meta', $this->asset('assets/css/voting.css'));
        $this->assertSame(3, substr_count($html, '<p class="pair-card__theme">'));
    }

    public function testRepeatingPatternsAreReplacedByAbstractBackgrounds(): void
    {
        $this->assertSame(['orbit', 'tide', 'arc', 'petal', 'shard', 'ripple', 'stream', 'cluster', 'ribbon', 'hills'], CandidateTheme::MOTIFS);

        foreach (CandidateTheme::MOTIFS as $motif) {
            $svg = (string) file_get_contents(FCPATH . 'assets/img/abstract/' . $motif . '.svg');
            $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="240" viewBox="0 0 1200 240">', $svg, $motif);
            foreach (['Gradient', '<filter', 'blur(', '<text', '<pattern'] as $banned) {
                $this->assertStringNotContainsString($banned, $svg, $motif . ' ' . $banned);
            }
            $this->assertLessThanOrEqual(4 * 1024, strlen($svg), $motif);
        }

        // Pola berulang lama dihapus; meter partisipasi beranda (ticks) tetap.
        $this->assertSame([], glob(FCPATH . 'assets/img/patterns/px-*.svg'));
        $this->assertFileExists(FCPATH . 'assets/img/patterns/ticks.svg');

        $voting = $this->asset('assets/css/voting.css');
        $paslon = $this->asset('assets/css/paslon.css');
        $this->assertStringNotContainsString('img/patterns/', $voting . $paslon);

        foreach ([[$voting, '.pair-card__profile::before'], [$paslon, '.px-head::before']] as [$css, $selector]) {
            $rule = $this->rule($css, $selector);
            $this->assertStringContainsString('mask-size: cover;', $rule, $selector);
            $this->assertStringContainsString('mask-repeat: no-repeat;', $rule, $selector);
            $this->assertStringContainsString('mask-position: center;', $rule, $selector);
        }

        $this->assertStringContainsString("mask-image: url('../img/abstract/orbit.svg');", $this->rule($paslon, '.px-head--profil::before'));
        $this->assertStringContainsString("mask-image: url('../img/abstract/ripple.svg');", $this->rule($paslon, '.px-head--pesan::before'));
    }

    // ------------------------------------------------------------------
    // profil paslon
    // ------------------------------------------------------------------

    public function testProfileHasNoBackLinkOnTopInEveryState(): void
    {
        foreach (['2026-10-01 09:00:00', '2026-09-30 08:00:00', '2026-10-02 00:00:00'] as $now) {
            $this->scheduleAt($now);
            $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/01'));
            $this->assertStringNotContainsString('px-back', $html, $now);
        }

        $this->scheduleAt('2026-10-01 09:00:00');
        $this->db->table('student_votes')->insert(['election_id' => 1, 'student_id' => 1, 'candidate_id' => 2, 'status' => 'LOCKED', 'voted_at' => '2026-10-01 08:00:00']);
        $this->assertStringNotContainsString('px-back', $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/01')));
        $this->assertStringNotContainsString('px-back', $this->body($this->withSession(self::ADMIN)->get('admin/paslon/1/intip')));
        $this->assertDoesNotMatchRegularExpression('/^\s*\.px-back\b/m', $this->asset('assets/css/paslon.css'));
    }

    public function testClosingShowsTheRoleAboveANaturallyWrappingName(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');
        $this->db->table('candidates')->where('nomor_urut', 1)->update(['nama_wakil' => 'Naya Kirana Putri Maharani']);

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/01'));

        $this->assertMatchesRegularExpression('#<span class="px-finale__person">\s*<span class="px-finale__role">Calon Ketua OSIS<span class="visually-hidden">: </span></span>\s*<strong>Arka Wibisana</strong>\s*</span>#', $html);
        $this->assertMatchesRegularExpression('#<span class="px-finale__role">Calon Wakil Ketua OSIS<span class="visually-hidden">: </span></span>\s*<strong>Naya Kirana Putri Maharani</strong>#', $html);
        foreach (['px-finale__line', '--px-chars'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }
        $this->assertFalse(method_exists(CandidateTheme::class, 'nameLines'));

        $css = $this->asset('assets/css/paslon.css');
        $this->assertStringContainsString('.px-finale__person { display: contents; }', $css);
        $this->assertStringContainsString(".px-finale__names {\n  display: grid;", $css);
        $this->assertStringContainsString("'role-a . role-b'", $this->rule($css, '.px-finale__names'));
        $name = $this->rule($css, '.px-finale__person strong');
        $this->assertStringContainsString('text-wrap: balance;', $name);
        $this->assertStringNotContainsString('nowrap', $name);
        foreach (['.px-finale__line', '--px-chars', 'container-type: inline-size'] as $gone) {
            $this->assertStringNotContainsString($gone, $css, $gone);
        }
    }

    public function testPickButtonIsACompactCursorClickWithCentredLabel(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/02'));
        $this->assertStringContainsString('<span class="px-pick__label">Pilih Paslon 02</span>', $html);
        $this->assertStringContainsString('<svg class="px-pick__cursor"', $html);
        $this->assertStringNotContainsString('px-pick__hand', $html);

        $js = $this->asset('assets/js/pick3d.js');
        $this->assertStringContainsString('function buildCursor() {', $js);
        $this->assertStringNotContainsString('buildHand', $js);
        $this->assertStringContainsString('const PERIOD = 3.2;', $js);
        // tulisan tepat di tengah keycap; kursor di lapisan kedua di atas tombol
        $this->assertStringContainsString('text.position.set(0, capTop + 0.004, 0);', $js);
        $this->assertStringContainsString("ctx.textAlign = 'center';", $js);
        $this->assertStringContainsString('renderer.clearDepth();', $js);
        $this->assertStringContainsString('renderer.render(overlay, camera);', $js);

        $css = $this->asset('assets/css/paslon.css');
        $this->assertStringContainsString('width: min(100%, 340px);', $this->rule($css, '.px-pick'));
        $this->assertMatchesRegularExpression('/@media \(min-width: 720px\) \{\s*\.px-pick \{ width: min\(100%, 420px\); \}\s*\.px-pick\.is-3d \.px-pick__stage \{ aspect-ratio: 2\.5 \/ 1; \}/', $css);
        $this->assertStringContainsString('aspect-ratio: 2.3 / 1;', $this->rule($css, '.px-pick.is-3d .px-pick__stage'));
        $this->assertStringNotContainsString('px-pick__hand', $css);
        $this->assertStringNotContainsString('--accent', $this->rule($css, '.px-pick__face'));
    }

    // ------------------------------------------------------------------
    // bilik suara
    // ------------------------------------------------------------------

    public function testBallotPageUsesTheFloatingMenuInsteadOfTheSiteNav(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        foreach ([['siswa', self::STUDENT, 'Siswa'], ['guru', self::TEACHER, 'Guru']] as [$role, $session, $label]) {
            $html = $this->body($this->withSession($session)->get($role . '/bilik-suara'));

            $this->assertStringContainsString('<header class="booth-nav">', $html, $role);
            $this->assertStringContainsString('<nav class="container booth-nav__row" aria-label="Navigasi bilik suara">', $html);
            $this->assertMatchesRegularExpression('#<a class="booth-nav__back" href="' . preg_quote($this->url($role . '/sekilas-paslon'), '#') . '"><svg[^>]*icon--arrow-left.*?</svg><span>Kembali</span></a>#s', $html);
            $this->assertMatchesRegularExpression('#<a class="booth-nav__home" href="' . preg_quote($this->url($role), '#') . '" title="' . preg_quote(esc('Dasbor ' . $label, 'attr'), '#') . '"><svg[^>]*icon--grid.*?</svg><span class="visually-hidden">Dasbor ' . $label . '</span></a>#s', $html);

            // Navigasi situs (logo, Dasbor, Keluar) tidak dirender di bilik suara.
            foreach (['class="site-nav', 'site-nav__logo', 'action="' . site_url($role . '/keluar') . '"', 'ballot__back'] as $gone) {
                $this->assertStringNotContainsString($gone, $html, $role . ' ' . $gone);
            }
        }
    }

    public function testFloatingMenuDoesNotLeakIntoTheNextPage(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $this->withSession(self::STUDENT)->get('siswa/bilik-suara')->assertSee('booth-nav');

        foreach (['siswa', 'siswa/sekilas-paslon', 'siswa/paslon/01'] as $path) {
            $html = $this->body($this->withSession(self::STUDENT)->get($path));
            $this->assertStringContainsString('<header class="site-nav"', $html, $path);
            $this->assertStringNotContainsString('booth-nav', $html, $path);
        }
    }

    public function testBallotHeadIsCentredWithoutCountdownOrRoleOnPhones(): void
    {
        foreach (['2026-10-01 09:00:00' => 'ongoing', '2026-09-30 08:00:00' => 'upcoming'] as $now => $state) {
            $this->scheduleAt($now);
            $html = $this->body($this->withSession(self::STUDENT)->get('siswa/bilik-suara'));

            $this->assertStringContainsString('<p class="ballot__kicker">Bilik suara digital<span class="ballot__kicker-role"> &middot; Siswa</span></p>', $html, $state);
            foreach (['data-countdown', 'class="term', 'ballot__term', 'ballot__top', 'assets/js/countdown.js'] as $gone) {
                $this->assertStringNotContainsString($gone, $html, $state . ' ' . $gone);
            }
        }

        $css = $this->asset('assets/css/voting.css');
        $head = $this->rule($css, '.ballot__head');
        $this->assertStringContainsString('text-align: center;', $head);
        $this->assertStringContainsString('margin-inline: auto;', $head);
        $this->assertStringContainsString('font-style: italic;', $this->rule($css, '.ballot__hint'));
        $sheet = $this->rule($css, '.ballot__sheet-title');
        $this->assertStringContainsString('justify-content: center;', $sheet);
        $this->assertStringContainsString('text-align: center;', $sheet);
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{\s*\.ballot__kicker-role \{ display: none; \}/', $css);
        foreach (['.ballot__back', '.ballot__term', '.ballot__top'] as $gone) {
            $this->assertStringNotContainsString($gone, $css, $gone);
        }

        // halaman bilik suara berlatar tinta (pesan flash menyatu), footer tetap kertas
        $this->assertStringContainsString('body:has(> .booth-nav) { background: var(--ink); }', $css);
        $this->assertStringContainsString('body:has(> .booth-nav) .site-footer { background: var(--paper); }', $css);

        $nav = $this->rule($css, '.booth-nav');
        foreach (['--container-w: 1360px;', 'position: sticky;', 'top: 0;', 'background: var(--ink);'] as $decl) {
            $this->assertStringContainsString($decl, $nav, $decl);
        }
        $this->assertMatchesRegularExpression('/\n\.booth-nav__home \{\s*justify-content: flex-end;/', $css);
        $link = $this->rule($css, '.booth-nav__back,
.booth-nav__home');
        $this->assertStringNotContainsString('border', $link);
        $this->assertStringNotContainsString('background', $link);
    }
}
