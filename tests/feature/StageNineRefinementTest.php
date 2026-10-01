<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AssetSource;

/**
 * Stage 9 (STAGE9-NOTES.md): rapikan bilik suara, login pemilih dua tahap,
 * dan panel admin.
 *
 * - panel terminal tanpa teks bilah judul/baris perintah, angka satu baris
 *   di HP; pembuka + "Sekilas paslon" satu layar di HP; petunjuk sekilas
 *   satu baris di desktop;
 * - login siswa/guru dua tahap (NISN/NIP -> kode unik) dengan indikator
 *   langkah & gembok terbuka; server menjawab JSON untuk permintaan AJAX;
 * - admin: brand "SMP 1 DAWE / Panel Admin", menu tanpa nomor & judul
 *   kelompok, "Beranda", "Home", footer hak cipta saja, breadcrumb stepper
 *   pengganti eyebrow, keterangan di balik ikon, strip jadwal mulai/berakhir,
 *   bar "diperbarui" menempel di bawah layar HP, catatan panel jadi tooltip.
 *
 * Gerak & tata letak diuji di browser (STAGE9-NOTES.md); di sini markup,
 * hook, respon JSON, dan aturan CSS/JS yang menjadi kontraknya.
 *
 * @internal
 */
final class StageNineRefinementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private const AJAX = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

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

    private function student(int $id = 1): array
    {
        return ['user_type' => 'student', 'student_id' => $id, 'isLoggedIn' => true];
    }

    private function admin(): array
    {
        return ['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true];
    }

    private function scheduleAt(string $now, string $end = '2026-10-01 12:00:00'): void
    {
        $this->db->table('elections')->where('id', 1)
            ->update(['start_at' => '2026-10-01 07:00:00', 'end_at' => $end]);
        Time::setTestNow($now);
    }

    private function asset(string $path): string
    {
        return AssetSource::read($path);
    }

    /**
     * @return array<string, mixed>
     */
    private function ajaxLogin(string $path, array $data): array
    {
        $result = $this->withHeaders(self::AJAX)->post($path, [csrf_token() => csrf_hash()] + $data);

        return ['status' => $result->response()->getStatusCode(), 'json' => json_decode((string) $result->getJSON(), true)];
    }

    // ------------------------------------------------------------------
    // bilik suara
    // ------------------------------------------------------------------

    public function testBoothTerminalHasNoTitleBarTextOrCommandLine(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00', '2026-10-05 12:00:00');

        // Stage 22: panel terminal di kepala surat suara (halaman bilik-suara).
        $page = $this->withSession($this->student())->get('siswa/bilik-suara');
        $html = (string) $page->response()->getBody();

        $page->assertDontSee('jam-server &middot; pilketos');
        $page->assertDontSee('pilketos:~$');
        $page->assertDontSee('sisa-waktu');
        $page->assertDontSee('term__bar');
        $page->assertSee('<span class="term__dots" aria-hidden="true">');
        $page->assertSee('Ditutup dalam');
        // Sisa >= 1 hari: "04 hari 03:00:00" tetap satu baris di HP.
        $page->assertSee('data-unit="days">04<');

        $css = $this->asset('assets/css/voting.css');
        $this->assertStringNotContainsString('.term__cmd', $css);
        $this->assertMatchesRegularExpression('/\.term \.countdown__units \{[^}]*flex-wrap: nowrap;[^}]*white-space: nowrap;/', $css);
        $this->assertMatchesRegularExpression('/\.term \.countdown--terminal \{\s*--term-size: clamp\(1\.5rem, 8vw, 2\.25rem\);/', $css);

        $this->assertLessThan(strpos($html, 'class="ballot__sheet"'), strpos($html, 'class="term ballot__term"'));
    }

    /**
     * Stage 9: pembuka + Sekilas paslon berbagi layar pertama HP (.booth-open).
     * Stage 22: ketiganya halaman sendiri: pembuka (coblos), Sekilas paslon
     * (sekilas-paslon, tanpa petunjuk), surat suara (bilik-suara).
     */
    public function testBoothIntroLineupAndBallotAreSeparatePages(): void
    {
        $intro = (string) $this->withSession($this->student())->get('siswa/coblos')->response()->getBody();
        $this->assertStringContainsString('<section class="steps"', $intro);
        $this->assertStringNotContainsString('class="booth-open"', $intro);
        $this->assertStringNotContainsString('<section class="lineup"', $intro);
        $this->assertStringNotContainsString('<section class="ballot', $intro);

        $lineup = (string) $this->withSession($this->student())->get('siswa/sekilas-paslon')->response()->getBody();
        $this->assertStringContainsString('<section class="lineup" id="sekilas"', $lineup);
        $this->assertStringNotContainsString('<section class="ballot', $lineup);
        $this->assertStringNotContainsString('lineup__hint', $lineup);

        $ballot = (string) $this->withSession($this->student())->get('siswa/bilik-suara')->response()->getBody();
        $this->assertStringContainsString('<section class="ballot', $ballot);
        $this->assertStringNotContainsString('<section class="lineup"', $ballot);

        $css = $this->asset('assets/css/voting.css');
        $this->assertStringNotContainsString('.lineup__hint', $css);
        $this->assertStringNotContainsString('.booth-open > .lineup', $css);
    }

    // ------------------------------------------------------------------
    // login pemilih dua tahap
    // ------------------------------------------------------------------

    public function testVoterLoginIsTwoStepWithoutEyebrowOrDateHint(): void
    {
        foreach (['siswa/masuk' => ['Siswa', 'NISN', 'nisn'], 'guru/masuk' => ['Guru', 'NIP', 'nip']] as $path => [$role, $label, $field]) {
            $page = $this->get($path);
            $html = (string) $page->response()->getBody();

            $page->assertDontSee('<p class="eyebrow">Masuk ' . $role . '</p>');
            $page->assertDontSee('DDMMYYYY');
            $page->assertDontSee('kodeunik-hint');
            $page->assertDontSee('tanggal lahir');

            $page->assertSee('class="card card--auth auth" data-auth');
            $page->assertSee('<ol class="auth-steps" aria-label="Langkah masuk" data-auth-steps>');
            $this->assertSame(3, substr_count($html, 'data-auth-step>'), $path);
            $page->assertSee('<span class="auth-steps__label">' . $label . '</span>');
            $page->assertSee('<span class="auth-steps__label">Kode unik</span>');
            $page->assertSee('<span class="auth-steps__label">Terbuka</span>');

            // Tahap 1 (identitas) sebelum tahap 2 (kode unik), satu form.
            $this->assertLessThan(strpos($html, 'data-auth-panel="2"'), strpos($html, 'data-auth-panel="1"'), $path);
            $this->assertLessThan(strpos($html, 'id="kodeunik"'), strpos($html, 'id="' . $field . '"'), $path);
            $page->assertSee('data-auth-next>Lanjut');
            $page->assertSee('class="padlock"');
            $page->assertSee('assets/js/auth.js');
            $page->assertSee('assets/css/auth.css');
        }

        $css = $this->asset('assets/css/auth.css');
        // Tanpa .is-stepped (tanpa JS) kedua isian tampil; langkah tersembunyi.
        $this->assertStringContainsString('.auth.is-stepped .auth__panel:not(.is-active) { display: none; }', $css);
        $this->assertStringContainsString(".auth-steps { display: none; }", $css);
        $this->assertMatchesRegularExpression('/\.auth\.is-unlocked \.padlock__shackle \{\s*animation: shackle-open/', $css);

        $js = $this->asset('assets/js/auth.js');
        $this->assertStringContainsString("root.classList.add('is-stepped');", $js);
        $this->assertStringContainsString("'X-Requested-With': 'XMLHttpRequest'", $js);
        $this->assertStringContainsString("root.classList.add('is-unlocked');", $js);
        $this->assertStringContainsString('window.location.assign(target);', $js);
        $this->assertDoesNotMatchRegularExpression('/(local|session)Storage\.(get|set|remove)Item/', $js);
    }

    public function testAjaxLoginAnswersJsonForTheUnlockAnimation(): void
    {
        $ok = $this->ajaxLogin('siswa/masuk', ['nisn' => '0000000001', 'kodeunik' => '05062013']);
        $this->assertSame(200, $ok['status']);
        $this->assertSame(['ok' => true, 'redirect' => site_url('siswa')], $ok['json']);
        $this->assertSame('student', session('user_type'));
        $this->assertSame(1, session('student_id'));

        $teacher = $this->ajaxLogin('guru/masuk', ['nip' => '000000000000000004', 'kodeunik' => '01061992']);
        $this->assertTrue($teacher['json']['ok']);
        $this->assertSame(site_url('guru'), $teacher['json']['redirect']);
        $this->assertSame('teacher', session('user_type'));
    }

    public function testAjaxLoginFailuresPointToTheStepToFix(): void
    {
        // Kode salah: tahap 2, pesan sama dengan pesan biasa (tanpa bocoran NISN terdaftar).
        $wrong = $this->ajaxLogin('siswa/masuk', ['nisn' => '0000000001', 'kodeunik' => '01012000']);
        $this->assertSame(422, $wrong['status']);
        $this->assertFalse($wrong['json']['ok']);
        $this->assertSame(2, $wrong['json']['step']);
        $this->assertSame(['NISN atau kode unik belum cocok. Coba periksa lagi, ya.'], $wrong['json']['messages']);
        $this->assertNull(session('user_type'));

        $unknown = $this->ajaxLogin('siswa/masuk', ['nisn' => '9999999999', 'kodeunik' => '01012000']);
        $this->assertSame($wrong['json'], $unknown['json']);

        // NISN tidak valid: kembali ke tahap 1.
        $bad = $this->ajaxLogin('siswa/masuk', ['nisn' => 'abc', 'kodeunik' => '05062013']);
        $this->assertSame(1, $bad['json']['step']);
        $this->assertContains('NISN hanya berisi angka.', $bad['json']['messages']);

        // Throttle tetap berlaku: 429.
        for ($i = 0; $i < 5; $i++) {
            $this->ajaxLogin('siswa/masuk', ['nisn' => '0000000002', 'kodeunik' => '0101200' . $i]);
        }
        $blocked = $this->ajaxLogin('siswa/masuk', ['nisn' => '0000000002', 'kodeunik' => '17092013']);
        $this->assertSame(429, $blocked['status']);
        $this->assertStringContainsString('Terlalu banyak percobaan', $blocked['json']['messages'][0]);
        $this->assertNull(session('user_type'));
    }

    public function testPlainFormLoginStillRedirects(): void
    {
        $this->post('siswa/masuk', [csrf_token() => csrf_hash(), 'nisn' => '0000000001', 'kodeunik' => '05062013'])
            ->assertRedirectTo(site_url('siswa'));

        // Admin tetap login satu tahap tanpa eyebrow (Stage 13: layar terbelah,
        // judul "Panel Admin").
        session()->destroy();
        $admin = $this->get('admin/masuk');
        $admin->assertDontSee('<p class="eyebrow">Masuk Admin</p>');
        $admin->assertSee('<h1 class="split-login__title">Panel Admin</h1>');
        $admin->assertDontSee('data-auth');
    }

    // ------------------------------------------------------------------
    // panel admin
    // ------------------------------------------------------------------

    public function testAdminShellBrandMenuAndFooter(): void
    {
        $page = $this->withSession($this->admin())->get('admin');
        $html = (string) $page->response()->getBody();

        $page->assertSee('class="admin-side__mark">SMP 1 DAWE</a>');
        $page->assertSee('<p class="admin-side__school">Panel Admin</p>');
        foreach (['admin-side__word', 'admin-side__year', 'admin-side__no', 'admin-side__group', 'Dasbor &amp; live count', 'Situs pemilih', 'waktu server'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }

        $page->assertSee('<span>Beranda</span>');
        $page->assertSee('<title>Beranda — Admin');
        // Stage 13: "Home" menjadi menu "Halaman Utama" di kelompok terakhir.
        $this->assertMatchesRegularExpression('#<ul class="admin-side__list admin-side__list--end">.*<span>Halaman Utama<#s', $html);
        $this->assertSame(3, substr_count($html, '<ul class="admin-side__list">'));
        $this->assertMatchesRegularExpression('/<footer class="admin-foot">\s*<p>&copy; ' . date('Y') . ' SMP 1 DAWE<\/p>\s*<\/footer>/', $html);

        $css = $this->asset('assets/css/admin.css');
        $this->assertStringNotContainsString('.admin-side__no', $css);
        $this->assertStringNotContainsString('.eyebrow-x', $css);
        $this->assertMatchesRegularExpression('/\.admin-side__link \{[^}]*grid-template-columns: 1\.25rem minmax\(0, 1fr\);/', $css);
    }

    public function testAdminPagesUseBreadcrumbStepperAndHintedLede(): void
    {
        $pages = [
            'admin'                => ['Beranda'],
            'admin/analitik'       => ['Beranda', 'Analitik'],
            // Stage 11: detail suara = bagian (pill) halaman Analitik.
            'admin/analitik/suara' => ['Beranda', 'Analitik'],
            'admin/hasil'          => ['Beranda', 'Hasil akhir'],
            'admin/paslon'         => ['Beranda', 'Pasangan calon'],
            'admin/siswa'          => ['Beranda', 'Siswa'],
            'admin/guru'           => ['Beranda', 'Guru'],
            'admin/siswa/1'        => ['Beranda', 'Siswa', 'Detail'],
            'admin/siswa/impor'    => ['Beranda', 'Siswa', 'Impor Excel'],
            'admin/jadwal'         => ['Beranda', 'Jadwal pemilihan'],
            'admin/buka-kunci'     => ['Beranda', 'Unlock hak suara'],
            'admin/riwayat'        => ['Beranda', 'Audit log'],
        ];

        foreach ($pages as $path => $trail) {
            $page = $this->withSession($this->admin())->get($path);
            $html = (string) $page->response()->getBody();

            $this->assertStringNotContainsString('eyebrow-x', $html, $path);
            // Stage 13: Beranda saja = crumbs--root (salinan HP disembunyikan).
            $page->assertSee(count($trail) === 1 ? '<nav class="crumbs crumbs--root" aria-label="Breadcrumb">' : '<nav class="crumbs" aria-label="Breadcrumb">');
            // Stage 12: satu salinan di topbar (desktop) + satu di atas judul (HP).
            $this->assertSame(2 * count($trail), substr_count($html, 'class="crumbs__item'), $path);
            $current = end($trail);
            $this->assertMatchesRegularExpression('#aria-current="page"><svg class="icon[^"]*crumbs__icon"[^>]*>.*?</svg><span class="crumbs__label">' . preg_quote($current, '#') . '</span></span>#', $html, $path);
            if (count($trail) > 1) {
                $this->assertMatchesRegularExpression('#<a class="crumbs__step" href="' . preg_quote(site_url('admin'), '#') . '"><svg class="icon&\#x20;icon--grid[^"]*"[^>]*>.*?</svg><span class="crumbs__label">Beranda</span></a>#', $html, $path);
            }

            // Keterangan halaman di balik ikon "i" di samping judul.
            $page->assertSee('<div class="admin-head__heading">');
            $page->assertSee('aria-controls="admin-head-lede" data-lede-toggle');
            $page->assertSee('<p class="admin-head__lede" id="admin-head-lede" data-lede>');
            $this->assertLessThan(strpos($html, 'data-lede-toggle'), strpos($html, 'class="admin-head__title"'), $path);
        }

        // Halaman tanpa keterangan: tanpa ikon.
        $form = $this->withSession($this->admin())->get('admin/paslon/tambah');
        $form->assertSee('<nav class="crumbs" aria-label="Breadcrumb">');
        $form->assertDontSee('data-lede-toggle');

        $css = $this->asset('assets/css/admin.css');
        $this->assertStringContainsString('.js .admin-head__lede[data-lede]:not(.is-open) { display: none; }', $css);
        $this->assertStringContainsString('.js .admin-head__hint { display: inline-grid; }', $css);

        $js = $this->asset('assets/js/admin.js');
        $this->assertStringContainsString("lede.classList.toggle('is-open', open);", $js);
        $this->assertStringContainsString("button.setAttribute('aria-expanded', open ? 'true' : 'false');", $js);
    }

    public function testDashboardScheduleStripLiveBarAndNoteTooltip(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $page = $this->withSession($this->admin())->get('admin');
        $html = (string) $page->response()->getBody();

        $strip = substr($html, strpos($html, '<section class="schedule-strip"'));
        $strip = substr($strip, 0, strpos($strip, '</section>'));
        $this->assertStringNotContainsString('<dt>Status</dt>', $strip);
        $this->assertStringContainsString('<dt>Mulai</dt>', $strip);
        $this->assertStringContainsString('<dt>Berakhir</dt>', $strip);
        $this->assertStringContainsString('countdown--compact', $strip);
        $this->assertLessThan(strpos($strip, 'countdown--compact'), strpos($strip, '<dt>Berakhir</dt>'));

        // Waktu diperbarui + tombol Perbarui dalam satu bar (Stage 11: ikon &
        // status ikut di bar, waktu di bawah status).
        $this->assertMatchesRegularExpression('/<div class="live__bar">\s*<span class="live__signal">.*<\/span>\s*<span class="live__text">\s*<span class="live__state" data-live-state>Live<\/span>\s*<span class="live__time">diperbarui <time data-live-updated>.*<\/time><\/span>\s*<\/span>\s*<a class="btn btn--sm btn--outline live__refresh"/', $html);

        // Catatan panel = ikon + tooltip.
        $page->assertDontSee('<p class="panel__note">');
        $page->assertSee('<span class="panel__note note-tip" data-note-tip>');
        $page->assertSee('aria-describedby="grade-note"');
        $page->assertSee('<span class="note-tip__text" role="tooltip" id="grade-note">Siswa aktif; kelas 7, 8, 9 dibaca dari awal nama rombel.</span>');

        $css = $this->asset('assets/css/admin.css');
        $this->assertMatchesRegularExpression('/\.live__bar,\s*\.live__text \{ display: contents; \}/', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{[^@]*\.live \{ display: contents; \}\s*\.live__bar \{\s*position: fixed;[^}]*bottom: 0;/', $css);
        $this->assertMatchesRegularExpression('/\.schedule-strip__list \{[^}]*grid-template-columns: repeat\(2, minmax\(0, max-content\)\);/', $css);
        $this->assertMatchesRegularExpression('/\.note-tip:hover \.note-tip__text,\s*\.note-tip:focus-within \.note-tip__text \{[^}]*visibility: visible;/', $css);
    }
}
