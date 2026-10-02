<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\CandidateTheme;
use CodeIgniter\Config\Services;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Homepage;
use Config\Visitors;
use Tests\Support\AssetSource;

/**
 * Stage 21: angka online beranda & papan angka admin diperbarui tiap detik
 * tanpa muat ulang (GET kunjungan/online, GET admin/pengunjung/ringkas),
 * beranda HP (partisipasi 0.7rem, dock tanpa jadwal, ikon online biru muda),
 * foto pasangan ukuran penuh dengan tautan profil per peran, dan profil
 * paslon: poster berikon unduh & layar penuh, kepala bagian tanpa ikon
 * berpola khas, kutipan miring 400 dengan avatar sebaris nama, video tanpa
 * bilah, navigasi gelap tanpa titik di tepi kanan, halaman selebar layar.
 *
 * @internal
 */
final class StageTwentyOneRefinementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private const CHROME  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';
    private const TOKEN   = 'fedcba9876543210fedcba9876543210';
    private const STUDENT = ['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true];
    private const TEACHER = ['user_type' => 'teacher', 'teacher_id' => 1, 'isLoggedIn' => true];
    private const ADMIN   = ['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true];

    protected function setUp(): void
    {
        parent::setUp();

        cache()->clean();
        service('renderer')->resetData();
        config(Visitors::class)->countCacheSeconds = 0;
        Services::resetSingle('visitorTracker');
        Services::resetSingle('onlineVisitors');
        Services::resetSingle('response');
        service('superglobals')->unsetCookie('osis_visitor');
    }

    protected function tearDown(): void
    {
        service('superglobals')->unsetCookie('osis_visitor');
        Time::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // helper
    // ------------------------------------------------------------------

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

    /** Isi blok @media (sampai komentar blok berikutnya di level atas). */
    private function media(string $css, string $query, string $until): string
    {
        $start = strpos($css, '@media ' . $query . ' {');
        $this->assertNotFalse($start, $query);
        $end = strpos($css, $until, $start);
        $this->assertNotFalse($end, $until);

        return substr($css, $start, $end - $start);
    }

    private function insertVisitor(array $data): void
    {
        $now = Time::now()->toDateTimeString();

        $this->db->table('visitors')->insert($data + [
            'visitor_hash'  => hash('sha256', bin2hex(random_bytes(16))),
            'user_agent'    => self::CHROME,
            'device_type'   => 'Komputer',
            'current_path'  => '',
            'landing_path'  => '',
            'page_views'    => 1,
            'first_seen_at' => $now,
            'last_seen_at'  => $now,
        ]);
    }

    private function poster(int $number, string $file): void
    {
        $this->db->table('candidates')->where('nomor_urut', $number)->update(['theme_asset' => json_encode(['poster' => $file])]);
    }

    // ------------------------------------------------------------------
    // angka online tiap detik
    // ------------------------------------------------------------------

    public function testOnlineEndpointReturnsOnlyCountsAndWritesNothing(): void
    {
        $this->insertVisitor([]);
        $this->insertVisitor([]);
        $this->insertVisitor(['user_type' => 'teacher', 'user_id' => 2]);
        $this->insertVisitor(['last_seen_at' => Time::now()->subMinutes(10)->toDateTimeString()]);
        service('superglobals')->setCookie('osis_visitor', self::TOKEN);

        $result = $this->withHeaders(['User-Agent' => self::CHROME, 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get('kunjungan/online');

        $result->assertOK();
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
        $data = json_decode($result->getJSON(), true);
        $this->assertSame(['online', 'interval'], array_keys($data), 'tanpa nama, IP, atau perangkat');
        $this->assertSame(['total' => 3, 'guests' => 2, 'users' => 1], $data['online']);
        $this->assertSame(1, $data['interval']);

        // Hanya membaca: browser ini tidak tercatat sebagai kunjungan.
        $this->assertSame(4, $this->db->table('visitors')->countAllResults());
        $this->assertNull($this->db->table('visitors')->where('visitor_hash', hash('sha256', self::TOKEN))->get()->getRowArray());
    }

    public function testOnlineEndpointIs404WhenThePublicCounterIsOff(): void
    {
        config(Visitors::class)->publicCounter = false;
        $this->get('kunjungan/online')->assertStatus(404);

        config(Visitors::class)->publicCounter = true;
        config(Visitors::class)->enabled       = false;
        $this->get('kunjungan/online')->assertStatus(404);
    }

    public function testPresenceScriptReadsTheCountEverySecondOnlyWhenTheCounterIsPublic(): void
    {
        $this->assertSame(1, (new Visitors())->counterRefreshSeconds);
        $this->assertSame(1, (new Visitors())->countCacheSeconds);

        $html = $this->body($this->get('/'));
        $this->assertStringContainsString('data-online-url="' . esc(site_url('kunjungan/online'), 'attr') . '" data-online-refresh="1"', $html);

        $js = $this->asset('assets/js/presence.js');
        foreach (["getAttribute('data-online-url')", "getAttribute('data-online-refresh')", "document.querySelector('[data-online-count]')", "method: 'GET'", 'countStopped = true', 'delay = 5000', 'node.textContent !== text'] as $needle) {
            $this->assertStringContainsString($needle, $js, $needle);
        }

        config(Visitors::class)->publicCounter = false;
        service('renderer')->resetData();
        $hidden = $this->body($this->get('/'));
        $this->assertStringContainsString('assets/js/presence.js', $hidden);
        $this->assertStringNotContainsString('data-online-url', $hidden);
    }

    public function testNewVisitorsAndLoginsDropTheCountCacheImmediately(): void
    {
        config(Visitors::class)->countCacheSeconds = 60;
        Services::resetSingle('onlineVisitors');
        Services::resetSingle('visitorTracker');

        $this->assertSame(['total' => 0, 'guests' => 0, 'users' => 0], service('onlineVisitors')->publicCounts());

        // Pengunjung baru: beranda membaca angka (cache) lalu tercatat.
        service('superglobals')->setCookie('osis_visitor', self::TOKEN);
        $this->withHeaders(['User-Agent' => self::CHROME])->get('/')->assertOK();
        $this->assertSame(['total' => 1, 'guests' => 1, 'users' => 0], service('onlineVisitors')->publicCounts());

        // Browser yang sama login: pindah dari tamu ke pengguna.
        $this->withSession(self::STUDENT + ['auth_seen_at' => Time::now()->getTimestamp()])
            ->withHeaders(['User-Agent' => self::CHROME])->get('siswa')->assertOK();
        $this->assertSame(['total' => 1, 'guests' => 0, 'users' => 1], service('onlineVisitors')->publicCounts());
    }

    public function testAdminBoardFragmentIsAdminOnlyAndReadEverySecond(): void
    {
        $this->get('admin/pengunjung/ringkas')->assertRedirectTo(site_url('admin/masuk'));
        $this->withSession(self::STUDENT)->get('admin/pengunjung/ringkas')->assertRedirect();

        $this->insertVisitor([]);
        $this->insertVisitor(['user_type' => 'student', 'user_id' => 3]);

        $result = $this->withSession(self::ADMIN)->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('admin/pengunjung/ringkas');
        $result->assertOK();
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
        $html = trim($this->body($result));
        $this->assertStringStartsWith('<div data-live-region="ringkas">', $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringContainsString('<dt>Online sekarang</dt>', $html);
        $this->assertMatchesRegularExpression('#<dt>Online sekarang</dt>\s*<dd class="scoreboard__value">2</dd>#', $html);
        $this->assertStringContainsString('dari 16 pengguna sistem', $html);
        $this->assertStringContainsString('<p class="visitor-updated">Diperbarui <time', $html);

        $page = $this->body($this->withSession(self::ADMIN)->get('admin/pengunjung'));
        $this->assertStringContainsString('data-visitors-tick="1" data-visitors-summary="' . esc(site_url('admin/pengunjung/ringkas'), 'attr') . '"', $page);
        $this->assertStringContainsString('data-visitors-refresh="20"', $page);
        $this->assertMatchesRegularExpression('/Angka ringkas diperbarui otomatis\s+setiap detik, daftar pengunjung setiap 20 detik\./', $page);
        // Papan angka halaman = fragmen yang dibaca tiap detik (partial yang sama).
        $this->assertSame(1, substr_count($page, 'data-live-region="ringkas"'));

        $js = $this->asset('assets/js/admin-visitors.js');
        foreach (["getAttribute('data-visitors-summary')", "getAttribute('data-visitors-tick')", 'board.innerHTML !== freshBoard.innerHTML', "'X-Requested-With': 'XMLHttpRequest'"] as $needle) {
            $this->assertStringContainsString($needle, $js, $needle);
        }
    }

    // ------------------------------------------------------------------
    // beranda HP
    // ------------------------------------------------------------------

    public function testPhoneHomeShrinksTurnoutHidesScheduleAndUsesALightBlueOnlineIcon(): void
    {
        $css    = $this->asset('assets/css/home.css');
        $mobile = $this->media($css, '(max-width: 767px)', '/* -- layar pendek');

        $this->assertStringContainsString('.turnout__count { font-size: 0.7rem; }', $mobile);
        $this->assertStringContainsString('.dock__meta { display: none; }', $mobile);
        $this->assertMatchesRegularExpression("/\\.dock__row \\{[^}]*grid-template-areas: 'status line';/", $mobile);
        $this->assertStringNotContainsString("'meta meta'", $css);
        $this->assertStringContainsString('--dock-h: calc(40px + env(safe-area-inset-bottom, 0px));', $mobile);
        $this->assertMatchesRegularExpression('/\.site-footer__online-icon \{[^}]*display: block;[^}]*color: #7DD3FC;/', $mobile);
        $this->assertMatchesRegularExpression('/\.site-footer__online-text \{[^}]*position: absolute;[^}]*clip: rect\(0, 0, 0, 0\);/', $mobile);
        // Desktop: kata "Online" tetap, ikon disembunyikan.
        $this->assertStringContainsString('.site-footer__online-icon { display: none; }', $css);

        $html = $this->body($this->get('/'));
        $this->assertMatchesRegularExpression('#<span class="site-footer__online-label"><svg class="icon[^"]*icon--live[^"]*site-footer__online-icon"[^>]*aria-hidden="true"[^>]*>.*?</svg><span class="site-footer__online-text">Online</span></span>#', $html);
    }

    // ------------------------------------------------------------------
    // foto pasangan ukuran penuh
    // ------------------------------------------------------------------

    public function testPairPhotosOpenAFullViewOutsideTheScenes(): void
    {
        $html = $this->body($this->get('/'));

        foreach ([1, 2, 3] as $id) {
            $this->assertStringContainsString('<button type="button" class="pair__zoom" data-pair-zoom="pair-view-' . $id . '" aria-haspopup="dialog" aria-controls="pair-view-' . $id . '">', $html);
            $this->assertStringContainsString('<dialog class="pair-view" id="pair-view-' . $id . '"', $html);
        }
        $this->assertStringContainsString('<span class="visually-hidden">Lihat foto pasangan 01 ukuran penuh</span>', $html);
        $this->assertStringContainsString('aria-labelledby="pair-view-1-title" data-pair-view>', $html);
        $this->assertStringContainsString('<button type="button" class="pair-view__close" data-pair-close>', $html);
        // Tanpa foto: monogram + nama & peran di bawahnya.
        $this->assertStringContainsString('<span class="pair-view__mono" aria-hidden="true">AW</span>', $html);
        $this->assertStringContainsString('<figcaption class="pair-view__caption"><strong>Naya Kirana</strong> <span>Calon Wakil</span></figcaption>', $html);

        // Dialog di luar scene (fokus di dalamnya tidak memindahkan scene).
        $this->assertGreaterThan(strpos($html, 'class="scene-top"'), strpos($html, '<dialog class="pair-view"'));
        $this->assertLessThan(strpos($html, '<nav class="pager"'), strrpos($html, '<dialog class="pair-view"'));

        // Tamu: tanpa tautan profil.
        $this->assertStringNotContainsString('pair-view__profile', $html);
        $this->assertStringNotContainsString('Lihat profil pasangan', $html);

        $js = $this->asset('assets/js/home.js');
        $this->assertStringContainsString('function initPairViews()', $js);
        $this->assertStringContainsString('dialog.showModal();', $js);
        $this->assertStringContainsString("if (event.touches.length !== 1 || self.locked || document.querySelector('dialog[open]')) {", $js);

        $css = $this->asset('assets/css/home.css');
        $this->assertStringContainsString('.no-js .pair__zoom { display: none; }', $css);
        $this->assertMatchesRegularExpression('/\.pair-view__photo--hero \.pair-view__img \{ max-height:/', $css);
        $this->assertStringContainsString('object-fit: contain;', $this->rule($css, '.pair-view__img'));
    }

    public function testFullViewHeroPhotoWhenThePairHasOne(): void
    {
        $this->db->table('candidates')->where('id', 2)->update(['theme_asset' => json_encode(['hero' => 'c02-hero-a.webp'])]);

        $html = $this->body($this->get('/'));
        $url  = esc(base_url('uploads/candidates/c02-hero-a.webp'), 'attr');

        $this->assertMatchesRegularExpression('#<div class="pair-view__photo pair-view__photo--hero">\s*<img class="pair-view__img" src="' . preg_quote($url, '#') . '"\s+alt="Foto pasangan 02: Bagas&\#x20;Prayoga dan Citra&\#x20;Maheswari"#', $html);
    }

    public function testProfileLinkFollowsTheSignedInRole(): void
    {
        $student = $this->body($this->withSession(self::STUDENT)->get('/'));
        $this->assertStringContainsString('<a class="pair-view__profile" href="' . esc(site_url('siswa/paslon/01'), 'attr') . '">Lihat profil pasangan <svg', $student);
        $this->assertSame(3, substr_count($student, 'class="pair-view__profile"'));

        service('renderer')->resetData();
        $teacher = $this->body($this->withSession(self::TEACHER)->get('/'));
        $this->assertStringContainsString('href="' . esc(site_url('guru/paslon/02'), 'attr') . '">Lihat profil pasangan', $teacher);

        service('renderer')->resetData();
        $admin = $this->body($this->withSession(self::ADMIN)->get('/'));
        $this->assertStringContainsString('href="' . esc(site_url('admin/paslon/3/intip'), 'attr') . '">Lihat profil pasangan', $admin);
    }

    public function testInactivePairStillCountedHasNoVoterProfileLink(): void
    {
        $this->db->table('elections')->where('id', 1)->update(['start_at' => '2026-10-01 07:00:00', 'end_at' => '2026-10-01 12:00:00']);
        Time::setTestNow('2026-10-01 09:00:00');
        $this->db->table('student_votes')->insert(['election_id' => 1, 'student_id' => 2, 'candidate_id' => 3, 'status' => 'LOCKED', 'voted_at' => '2026-10-01 08:00:00']);
        $this->db->table('candidates')->where('id', 3)->update(['status_aktif' => 0]);

        $student = $this->body($this->withSession(self::STUDENT)->get('/'));
        $this->assertStringContainsString('<dialog class="pair-view" id="pair-view-3"', $student);
        $this->assertStringNotContainsString(esc(site_url('siswa/paslon/03'), 'attr'), $student);
        $this->assertSame(2, substr_count($student, 'class="pair-view__profile"'));

        // Admin tetap bisa membuka pratinjau paslon nonaktif.
        service('renderer')->resetData();
        $admin = $this->body($this->withSession(self::ADMIN)->get('/'));
        $this->assertStringContainsString('href="' . esc(site_url('admin/paslon/3/intip'), 'attr') . '"', $admin);
    }

    public function testTeaserPairsAreClickableToo(): void
    {
        config(Homepage::class)->publicLiveCount = false;

        $html = $this->body($this->withSession(self::STUDENT)->get('/'));

        $this->assertStringContainsString('data-pair-zoom="pair-view-1"', $html);
        $this->assertStringContainsString('href="' . esc(site_url('siswa/paslon/03'), 'attr') . '">Lihat profil pasangan', $html);
    }

    // ------------------------------------------------------------------
    // profil paslon
    // ------------------------------------------------------------------

    public function testPosterHasDownloadAndFullscreenIconsWithoutTheOldButton(): void
    {
        $this->poster(2, 'c02-poster-abc.webp');

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/02'));
        $url  = esc(base_url('uploads/candidates/c02-poster-abc.webp'), 'attr');

        $this->assertMatchesRegularExpression('#<a class="px-poster__tool px-poster__tool--download" href="' . preg_quote($url, '#') . '" download="paslon-osis-no-02-spensada-2026\.webp" title="Unduh poster"><svg class="icon[^"]*icon--download"#', $html);
        $this->assertMatchesRegularExpression('#<a class="px-poster__tool px-poster__tool--full" href="' . preg_quote($url, '#') . '" target="_blank" rel="noopener" data-px-zoom title="Layar penuh"><svg class="icon[^"]*icon--expand"#', $html);
        $this->assertStringContainsString('<span class="visually-hidden">Unduh poster kampanye pasangan 02</span>', $html);
        $this->assertStringContainsString('data-px-lightbox', $html);

        // Ikon tanpa teks terlihat; tombol lama "Lihat fullscreen" hilang.
        preg_match_all('#<a class="px-poster__tool.*?</a>#s', $html, $tools);
        $this->assertCount(2, $tools[0]);
        foreach ($tools[0] as $tool) {
            $visible = trim(strip_tags((string) preg_replace('#<span class="visually-hidden">.*?</span>#', '', $tool)));
            $this->assertSame('', $visible);
        }
        foreach (['Lihat fullscreen', 'px-poster__bar', 'px-btn--dark'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }

        $css = $this->asset('assets/css/paslon.css');
        $this->assertStringContainsString('.px-poster__tool--download { left: var(--space-3); }', $css);
        $this->assertStringContainsString('.px-poster__tool--full { right: var(--space-3); }', $css);
        $this->assertStringContainsString('position: absolute;', $this->rule($css, '.px-poster__tool'));
        $this->assertStringNotContainsString('.px-btn--dark', $css);
    }

    public function testPosterDownloadNameUsesTheTwoDigitNumberAndFileExtension(): void
    {
        $this->assertSame('paslon-osis-no-02-spensada-2026.webp', CandidateTheme::posterDownload('02', 'c02-poster-1a2b.webp'));
        $this->assertSame('paslon-osis-no-11-spensada-2026.jpg', CandidateTheme::posterDownload('11', 'C11-POSTER.JPG'));
        $this->assertSame('paslon-osis-no-03-spensada-2026.png', CandidateTheme::posterDownload('03', '../x/c03-poster.png'));
        $this->assertSame('paslon-osis-no-01-spensada-2026.jpg', CandidateTheme::posterDownload('01', 'poster-tanpa-ekstensi'));

        $row = ['id' => 4, 'nomor_urut' => 4, 'nama_ketua' => 'A', 'nama_wakil' => 'B', 'theme_asset' => ['poster' => 'c04-poster-x.png']];
        $this->assertSame('paslon-osis-no-04-spensada-2026.png', CandidateTheme::present($row)['poster_download']);
        $this->assertNull(CandidateTheme::present(['nomor_urut' => 4])['poster_download']);
    }

    public function testSectionHeadsAreCentredTextOnUniqueFullWidthPatterns(): void
    {
        $this->poster(1, 'c01-poster-a.webp');
        $this->db->table('candidates')->where('nomor_urut', 1)->update(['video_youtube' => 'dQw4w9WgXcQ']);

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/01'));
        $keys = ['profil', 'visi', 'misi', 'program', 'poster', 'pesan', 'video'];

        preg_match_all('#<div class="px-head px-head--([a-z]+)">(.*?)</div>#s', $html, $heads);
        $this->assertSame($keys, $heads[1]);
        foreach ($heads[2] as $inner) {
            $this->assertStringNotContainsString('<svg', $inner);
        }
        $this->assertStringNotContainsString('px-head__icon', $html);
        $this->assertStringNotContainsString('px-head__text', $html);
        // Kepala Visi kini di luar panel kutipan.
        $this->assertLessThan(strpos($html, '<div class="px-visi__panel"'), strpos($html, '<div class="px-head px-head--visi">'));

        $css = $this->asset('assets/css/paslon.css');
        $head = $this->rule($css, '.px-head');
        $this->assertStringContainsString('text-align: center;', $head);
        $this->assertStringContainsString('justify-items: center;', $head);
        $before = $this->rule($css, '.px-head::before');
        $this->assertStringContainsString('left: calc(-1 * var(--px-pad-l, var(--px-gutter)));', $before);
        $this->assertStringContainsString('right: calc(-1 * var(--px-pad-r, var(--px-gutter)));', $before);
        $this->assertStringContainsString('background: var(--accent);', $before);
        $this->assertMatchesRegularExpression('/opacity: 0\.1\d;/', $before);

        // Stage 24: pola berulang diganti latar SVG abstrak (img/abstract/).
        $files = [];
        foreach ($keys as $key) {
            $rule = $this->rule($css, '.px-head--' . $key . '::before');
            $this->assertMatchesRegularExpression("#mask-image: url\\('\\.\\./img/abstract/([a-z]+\\.svg)'\\);#", $rule, $key);
            preg_match("#mask-image: url\\('\\.\\./img/abstract/([a-z]+\\.svg)'\\);#", $rule, $m);
            $files[$key] = $m[1];
            $this->assertFileExists(FCPATH . 'assets/img/abstract/' . $m[1]);
            $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $this->asset('assets/img/abstract/' . $m[1]));
        }
        $this->assertCount(7, array_unique($files), 'latar berbeda tiap bagian');

        // Ikon kepala bagian Stage 16 tidak dipakai lagi.
        foreach (['target', 'bulb', 'message', 'video'] as $icon) {
            $this->assertSame('', icon($icon), $icon);
        }
    }

    public function testQuotesAreItalicWithTheAvatarBesideTheName(): void
    {
        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/01'));

        $this->assertMatchesRegularExpression('#<figure class="px-quote" data-px-reveal>\s*<blockquote class="px-quote__text">.*?</blockquote>\s*<figcaption class="px-quote__who">\s*<span class="px-avatar" aria-hidden="true">.*?</span>\s*</span>\s*<span class="px-quote__id"><strong>Arka Wibisana</strong><span>Calon Ketua OSIS</span></span>\s*</figcaption>#s', $html);

        $css = $this->asset('assets/css/paslon.css');
        $text = $this->rule($css, '.px-quote__text p');
        $this->assertStringContainsString('font-style: italic;', $text);
        $this->assertStringContainsString('font-weight: 400;', $text);
        $this->assertStringNotContainsString('font-weight: 600;', $text);
        $who = $this->rule($css, '.px-quote__who');
        $this->assertStringContainsString('display: flex;', $who);
        $this->assertStringContainsString('align-items: center;', $who);
    }

    public function testVideoIsJustThePlayer(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 1)->update(['video_youtube' => 'dQw4w9WgXcQ']);

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/01'));

        $this->assertStringContainsString('data-px-video-frame', $html);
        $this->assertStringContainsString('<a class="px-video__facade" href="' . esc('https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'attr') . '"', $html);
        foreach (['px-video__bar', 'Buka di YouTube', 'px-btn--quiet'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }
        $this->assertStringNotContainsString('.px-video__bar', $this->asset('assets/css/paslon.css'));
    }

    public function testDarkNavigationWithoutDotsOnTheRightAndAFullWidthPage(): void
    {
        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/01'));

        $this->assertStringNotContainsString('px-nav__dot', $html);
        $this->assertMatchesRegularExpression('#<nav class="px-nav"[^>]*data-px-nav>\s*<div class="px-nav__rail">\s*<div class="px-nav__inner">#', $html);
        $this->assertStringContainsString('<a class="px-nav__link" href="#visi" data-px-nav-link>' . "\n" . '                    <span class="px-nav__label">Visi</span>', $html);
        $this->assertStringContainsString('<span class="px-nav__progress" aria-hidden="true" data-px-progress hidden><span class="px-nav__progress-fill"></span></span>', $html);

        $css = $this->asset('assets/css/paslon.css');
        $this->assertStringNotContainsString('.px-nav__dot', $css);
        $inner = $this->rule($css, '.px-nav__inner');
        $this->assertStringContainsString('background: var(--ink);', $inner);
        $this->assertStringContainsString('color: #FFFFFF;', $inner);
        $this->assertStringContainsString('color: rgba(255, 255, 255, 0.7);', $this->rule($css, '.px-nav__link'));

        $desktop = substr($css, strpos($css, '.px-nav__progress-fill {'), strpos($css, '3. Bagian isi') - strpos($css, '.px-nav__progress-fill {'));
        $this->assertMatchesRegularExpression('/\.px-nav \{[^}]*right: var\(--px-nav-edge\);/', $desktop);
        // Stage 22: rail di bawah bar pemilih paslon (--px-switch-h).
        $this->assertMatchesRegularExpression('/\.px-nav__rail \{[^}]*position: sticky;[^}]*align-items: center;[^}]*height: calc\(100vh - var\(--px-switch-h\)\);/', $desktop);
        $this->assertMatchesRegularExpression('/\.px-nav__inner \{[^}]*border-radius: 22px;[^}]*background: rgba\(21, 20, 26, 0\.95\);/', $desktop);

        // Selebar layar: tanpa batas 1240 px; ruang kanan untuk panel navigasi.
        $shell = $this->rule($css, '.px-shell');
        $this->assertStringNotContainsString('max-width', $shell);
        $this->assertStringContainsString('.px-shell--nav { --px-pad-r: calc(var(--px-nav-edge) + var(--px-nav-w) + var(--px-nav-gap)); }', $css);

        $js = $this->asset('assets/js/paslon.js');
        $this->assertStringContainsString("progress.style.setProperty('--px-progress', value.toFixed(4));", $js);
        $this->assertStringContainsString('progress.hidden = false;', $js);
    }
}
