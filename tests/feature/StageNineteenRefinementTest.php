<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\DeviceInfo;
use App\Services\OnlineVisitors;
use App\Services\VisitorTracker;
use CodeIgniter\Config\Services;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Homepage;
use Config\Visitors;

/**
 * Stage 19: banner final dasbor dua baris kapital tanpa kalimat "Pencoblosan
 * ditolak server", panggung pemenang (victor) rata tengah & warna teks
 * mengikuti aksen, catatan kaki miring berbintang, logo header admin HP
 * dari Config\Homepage, serta pengunjung online (pencatatan, detak, halaman
 * admin, angka di beranda).
 *
 * @internal
 */
final class StageNineteenRefinementTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private const CHROME  = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';
    private const ANDROID = 'Mozilla/5.0 (Linux; Android 14; SM-A546E) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36';
    private const TOKEN   = '0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        cache()->clean();
        service('renderer')->resetData();
        config(Visitors::class)->countCacheSeconds = 0;
        Services::resetSingle('visitorTracker');
        Services::resetSingle('onlineVisitors');
        // Objek respons dipakai bersama antar-test: Content-Type JSON dari
        // request test sebelumnya tidak boleh terbawa ke halaman HTML.
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

    private function asAdmin()
    {
        return $this->withSession(['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true]);
    }

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
        $this->assertMatchesRegularExpression('/(^|\n)\s*' . preg_quote($selector, '/') . ' \{/', $css, $selector);
        preg_match('/(?:^|\n)\s*' . preg_quote($selector, '/') . ' \{([^}]*)\}/', $css, $match);

        return $match[1];
    }

    private function finishedWithWinner(): void
    {
        foreach ([[1, 2], [2, 2], [3, 1], [5, 3]] as [$student, $candidate]) {
            $this->db->table('student_votes')->insert([
                'election_id'  => 1,
                'student_id'   => $student,
                'candidate_id' => $candidate,
                'status'       => 'LOCKED',
                'voted_at'     => '2026-10-01 08:00:00',
            ]);
        }

        $this->db->table('elections')->where('id', 1)->update(['start_at' => '2026-10-01 07:00:00', 'end_at' => '2026-10-01 12:00:00']);
        Time::setTestNow('2026-10-01 13:00:00');
    }

    private function visitor(?string $token = self::TOKEN): void
    {
        if ($token === null) {
            service('superglobals')->unsetCookie('osis_visitor');
        } else {
            service('superglobals')->setCookie('osis_visitor', $token);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(string $token = self::TOKEN): ?array
    {
        return $this->db->table('visitors')->where('visitor_hash', hash('sha256', $token))->get()->getRowArray();
    }

    private function insertVisitor(array $data): void
    {
        $now = Time::now()->toDateTimeString();

        $this->db->table('visitors')->insert($data + [
            'visitor_hash'  => hash('sha256', bin2hex(random_bytes(16))),
            'user_agent'    => self::CHROME,
            'device_type'   => 'Komputer',
            'os_name'       => 'Windows',
            'browser_name'  => 'Chrome',
            'current_path'  => '',
            'landing_path'  => '',
            'page_views'    => 1,
            'first_seen_at' => $now,
            'last_seen_at'  => $now,
        ]);
    }

    // ------------------------------------------------------------------
    // banner final dasbor
    // ------------------------------------------------------------------

    public function testFinalBannerTitleIsTwoUppercaseLinesWithoutServerSentence(): void
    {
        $this->finishedWithWinner();

        $html = $this->body($this->asAdmin()->get('admin'));

        $this->assertMatchesRegularExpression(
            '#<h2 class="final-banner__title" id="final-banner-title">\s*<span class="final-banner__lead">Pasangan 02 terpilih<span class="visually-hidden">:</span></span>\s*<span class="final-banner__names">Bagas Prayoga &amp; Citra Maheswari</span>\s*</h2>#',
            $html,
        );
        $this->assertStringContainsString('<p class="final-banner__text">2 suara (50,0% dari 4 suara sah).</p>', $html);
        $this->assertStringNotContainsString('Pencoblosan ditolak server', $html);
        $this->assertStringNotContainsString('angka di dasbor ini adalah hasil akhir', $html);

        $css   = $this->asset('assets/css/admin.css');
        $title = $this->rule($css, '.final-banner__title');
        $this->assertStringContainsString('display: grid;', $title);
        $this->assertStringContainsString('text-transform: uppercase;', $title);
        $this->assertStringContainsString(".final-banner__lead,\n.final-banner__names { display: block; }", $css);
    }

    // ------------------------------------------------------------------
    // panggung pemenang & catatan kaki
    // ------------------------------------------------------------------

    public function testVictorTallyHasTwoLinesAndFootnoteIsStarredItalicLeft(): void
    {
        $this->finishedWithWinner();

        $html = $this->body($this->asAdmin()->get('admin/hasil'));

        $this->assertMatchesRegularExpression(
            '#<p class="victor__tally">\s*<span class="victor__tally-line"><strong>2</strong> suara &middot; <strong>50,0%</strong> dari suara sah</span>\s*<span class="victor__tally-line">unggul 1 suara dari peringkat 2</span>\s*</p>#',
            $html,
        );
        $this->assertMatchesRegularExpression('#<footer class="final__foot">\s*<p><span class="final__star" aria-hidden="true">\*</span>Dihitung pada#', $html);

        $css  = $this->asset('assets/css/results.css');
        $foot = $this->rule($css, '.final__foot');
        $this->assertStringContainsString('font-style: italic;', $foot);
        $this->assertStringContainsString('text-align: left;', $foot);
    }

    public function testVictorFollowsAccentInkIsCenteredAndUppercase(): void
    {
        $css = $this->asset('assets/css/results.css');

        $victor = $this->rule($css, '.victor');
        $this->assertStringContainsString('color: var(--accent-ink);', $victor);
        $this->assertStringContainsString('text-align: center;', $victor);
        $this->assertStringContainsString('justify-items: center;', $victor);

        // h3 di app.css berwarna tinta: nama & peran wajib mewarisi --accent-ink
        $names = $this->rule($css, '.victor__names');
        $this->assertStringContainsString('color: inherit;', $names);
        $this->assertStringContainsString('text-transform: uppercase;', $names);
        $this->assertStringContainsString('color: inherit;', $this->rule($css, '.victor__person-name'));
        $this->assertStringContainsString('color: inherit;', $this->rule($css, '.victor__role'));
        $this->assertStringContainsString('.victor__tally-line { display: block; }', $css);

        // desktop: label di tengah, nomor & foto sebaris, lalu nama dan tally
        preg_match('/@media \(min-width: 900px\) \{(.*?)\n\}/s', $css, $desktop);
        $this->assertNotEmpty($desktop);
        $this->assertMatchesRegularExpression('/"label label"\s*"no people"\s*"names names"\s*"tally tally";/', $desktop[1]);
        $this->assertStringContainsString('.victor__label { justify-self: center; }', $desktop[1]);
    }

    // ------------------------------------------------------------------
    // logo header admin HP
    // ------------------------------------------------------------------

    public function testMobileAdminLogoIsSetInHomepageConfig(): void
    {
        $config                        = config(Homepage::class);
        $config->adminLogoMobile       = 'assets/img/brand/logo-dark.svg';
        $config->adminLogoMobileHeight = 80;
        $config->adminLogoMobileAlt    = 'Logo uji';

        $html = html_entity_decode($this->body($this->asAdmin()->get('admin')), ENT_QUOTES | ENT_HTML5);
        $size = asset_size('assets/img/brand/logo-dark.svg');

        $this->assertNotNull($size);
        // tinggi dibatasi 24-48 px; ukuran asli dibaca dari file
        $this->assertMatchesRegularExpression(
            '#<a class="admin-top__logo" href="[^"]+/admin" style="--admin-logo-h: 48px;">\s*<img src="[^"]+assets/img/brand/logo-dark\.svg\?v=\d+" alt="Logo uji"\s+width="' . $size[0] . '" height="' . $size[1] . '"#',
            $html,
        );
        $this->assertStringContainsString('height: var(--admin-logo-h, 34px);', $this->rule($this->asset('assets/css/admin.css'), '.admin-top__logo img'));
        $this->assertStringContainsString("homepage.adminLogoMobile", (string) file_get_contents(ROOTPATH . '.env.example'));
    }

    // ------------------------------------------------------------------
    // pencatatan kunjungan
    // ------------------------------------------------------------------

    public function testPageViewCreatesOneRowPerBrowserWithDeviceDetails(): void
    {
        $result = $this->withHeaders(['User-Agent' => self::ANDROID, 'Accept-Language' => 'id-ID,id;q=0.9,en;q=0.8', 'Referer' => 'https://www.google.com/search?q=pilketos'])->get('/');
        $result->assertOK();

        // browser baru: cookie penanda acak, HttpOnly
        $cookie = $result->response()->getCookie('osis_visitor');
        $this->assertNotNull($cookie);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $cookie->getValue());
        $this->assertTrue($cookie->isHTTPOnly());

        $row = $this->row($cookie->getValue());
        $this->assertNotNull($row);
        $this->assertNull($row['user_type']);
        $this->assertSame('HP', $row['device_type']);
        $this->assertSame('Samsung', $row['device_brand']);
        $this->assertSame('Galaxy A54 5G', $row['device_model']);
        $this->assertSame('Android', $row['os_name']);
        $this->assertSame('14', $row['os_version']);
        $this->assertSame('Chrome', $row['browser_name']);
        $this->assertSame('Blink', $row['engine']);
        $this->assertSame('id-ID, id, en', $row['language']);
        $this->assertSame('https://www.google.com/search', $row['referrer'], 'tanpa query string');
        $this->assertSame('', $row['current_path']);
        $this->assertSame('1', (string) $row['page_views']);
        $this->assertSame(self::ANDROID, $row['user_agent']);

        // browser yang sama: baris yang sama, jumlah halaman bertambah
        $this->visitor($cookie->getValue());
        $this->withHeaders(['User-Agent' => self::ANDROID])->get('siswa/masuk')->assertOK();

        $this->assertSame(1, $this->db->table('visitors')->countAllResults());
        $row = $this->row($cookie->getValue());
        $this->assertSame('siswa/masuk', $row['current_path']);
        $this->assertSame('', $row['landing_path']);
        $this->assertSame('2', (string) $row['page_views']);
    }

    public function testBotsLibrariesAjaxAndJsonAreNotCounted(): void
    {
        $this->visitor();

        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'])->get('/')->assertOK();
        $this->withHeaders(['User-Agent' => 'curl/8.5.0'])->get('/')->assertOK();
        $this->withHeaders([])->get('/')->assertOK();
        $this->withHeaders(['User-Agent' => self::CHROME, 'X-Requested-With' => 'XMLHttpRequest'])->get('/')->assertOK();
        $this->withHeaders(['User-Agent' => self::CHROME])->get('jam-server')->assertOK();
        $this->withHeaders(['User-Agent' => self::CHROME])->get('hitung-suara')->assertOK();

        $this->assertSame(0, $this->db->table('visitors')->countAllResults());
    }

    public function testIdentityComesFromSessionAndVoterPagesHideCandidateNumbers(): void
    {
        $this->visitor();

        $this->withSession(['user_type' => 'student', 'student_id' => 3, 'isLoggedIn' => true, 'auth_seen_at' => Time::now()->getTimestamp()])
            ->withHeaders(['User-Agent' => self::CHROME])
            ->get('siswa/paslon/02')
            ->assertOK();

        $row = $this->row();
        $this->assertSame('student', $row['user_type']);
        $this->assertSame('3', (string) $row['user_id']);
        $this->assertSame('siswa/paslon', $row['current_path']);

        // keluar: browser yang sama kembali menjadi tamu
        $this->withSession([])->withHeaders(['User-Agent' => self::CHROME])->get('/')->assertOK();
        $this->assertNull($this->row()['user_type']);

        $this->assertSame('siswa/coblos/yakin', VisitorTracker::normalizePath('/siswa/coblos/yakin/2?x=1'));
        $this->assertSame('guru/paslon', VisitorTracker::normalizePath('guru/paslon/03'));
        $this->assertSame('admin/siswa/12/ubah', VisitorTracker::normalizePath('admin/siswa/12/ubah'));
        $this->assertSame('', VisitorTracker::normalizePath('/'));
        $this->assertSame('Profil paslon (siswa)', VisitorTracker::pageLabel('siswa/paslon'));
        $this->assertSame('Admin: Siswa', VisitorTracker::pageLabel('admin/siswa/12/ubah'));
        $this->assertSame('Beranda', VisitorTracker::pageLabel(''));
        $this->assertSame('/lain', VisitorTracker::pageLabel('lain'));
    }

    public function testIdleVoterSessionCountsAsGuest(): void
    {
        $this->visitor();

        $this->withSession(['user_type' => 'teacher', 'teacher_id' => 1, 'isLoggedIn' => true, 'auth_seen_at' => Time::now()->getTimestamp() - 3600])
            ->withHeaders(['User-Agent' => self::CHROME])
            ->get('/')
            ->assertOK();

        $this->assertNull($this->row()['user_type']);
    }

    // ------------------------------------------------------------------
    // detak
    // ------------------------------------------------------------------

    public function testHeartbeatStoresValidatedClientInfoAndReturnsOnlyCounts(): void
    {
        $this->visitor();
        $this->withHeaders(['User-Agent' => self::CHROME])->get('/')->assertOK();

        $result = $this->withHeaders([
            'User-Agent'       => self::CHROME,
            'X-CSRF-TOKEN'     => csrf_hash(),
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json',
        ])->withBodyFormat('json')->post('kunjungan/detak', [
            'screen'   => '1920x1080',
            'viewport' => '1440x900',
            'dpr'      => 1.25,
            'tz'       => 'Asia/Jakarta',
            'touch'    => 0,
            'net'      => '4g',
            'scheme'   => 'dark',
        ]);

        $result->assertOK();
        $data = json_decode($result->getJSON(), true);
        $this->assertTrue($data['ok']);
        $this->assertTrue($data['tracked']);
        $this->assertSame(60, $data['interval']);
        $this->assertSame(['total' => 1, 'guests' => 1, 'users' => 0], $data['online']);
        $this->assertSame(['ok', 'tracked', 'interval', 'online'], array_keys($data), 'tanpa nama, IP, atau perangkat');

        $row = $this->row();
        $this->assertSame('1920x1080', $row['screen']);
        $this->assertSame('1440x900', $row['viewport']);
        $this->assertSame('1.25', (string) $row['pixel_ratio']);
        $this->assertSame('Asia/Jakarta', $row['timezone']);
        $this->assertSame('0', (string) $row['touch_points']);
        $this->assertSame('4g', $row['connection']);
        $this->assertSame('dark', $row['color_scheme']);
        $this->assertSame('1', (string) $row['page_views'], 'detak bukan kunjungan halaman');
    }

    public function testClientInfoRejectsUnexpectedValues(): void
    {
        $info = VisitorTracker::clientInfo([
            'screen'   => '<script>',
            'viewport' => '99999x1',
            'dpr'      => 40,
            'tz'       => 'Asia/Jakarta"; DROP',
            'touch'    => -1,
            'net'      => '5g',
            'scheme'   => 'blue',
        ]);

        $this->assertSame([null], array_values(array_unique($info, SORT_REGULAR)));
    }

    public function testHeartbeatWithoutVisitorCookieIsNotTrackedAndDisabledFeatureIs404(): void
    {
        $headers = ['User-Agent' => self::CHROME, 'X-CSRF-TOKEN' => csrf_hash(), 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];

        $result = $this->withHeaders($headers)->withBodyFormat('json')->post('kunjungan/detak', ['screen' => '800x600']);
        $result->assertOK();
        $this->assertFalse(json_decode($result->getJSON(), true)['tracked']);
        $this->assertSame(0, $this->db->table('visitors')->countAllResults());

        config(Visitors::class)->enabled = false;
        Services::resetSingle('visitorTracker');

        $this->withHeaders($headers)->withBodyFormat('json')->post('kunjungan/detak', [])->assertStatus(404);
        $this->assertStringNotContainsString('presence.js', $this->body($this->get('/')));
    }

    // ------------------------------------------------------------------
    // angka online
    // ------------------------------------------------------------------

    public function testCountsUseDistinctAccountsAndPercentOfAllUsers(): void
    {
        $stale = Time::now()->subMinutes(10)->toDateTimeString();

        $this->insertVisitor([]);                                                  // tamu
        $this->insertVisitor([]);                                                  // tamu
        $this->insertVisitor(['user_type' => 'student', 'user_id' => 1]);          // siswa 1, HP
        $this->insertVisitor(['user_type' => 'student', 'user_id' => 1]);          // siswa 1, komputer lab
        $this->insertVisitor(['user_type' => 'teacher', 'user_id' => 2]);
        $this->insertVisitor(['user_type' => 'student', 'user_id' => 4, 'last_seen_at' => $stale]); // tidak online
        $this->insertVisitor(['last_seen_at' => $stale]);

        $online  = new OnlineVisitors();
        $summary = $online->summary();

        $this->assertSame(['total' => 4, 'guests' => 2, 'users' => 2], $online->publicCounts());
        $this->assertSame(5, $summary['devices']);
        // seeder: 11 siswa aktif + 4 guru aktif + 1 admin
        $this->assertSame(16, $summary['accounts']);
        $this->assertSame(12.5, $summary['percent']);
        $this->assertSame(['label' => 'Siswa', 'online' => 1, 'accounts' => 11, 'percent' => 9.1], $summary['roles']['student']);
        $this->assertSame(['label' => 'Guru', 'online' => 1, 'accounts' => 4, 'percent' => 25.0], $summary['roles']['teacher']);
        $this->assertSame(0, $summary['roles']['admin']['online']);

        $list = $online->list(OnlineVisitors::SCOPE_TODAY, 'siswa', '', 1, 50);
        $this->assertSame(3, $list['total']);
        $this->assertSame('Ahmad Fauzan', $list['rows'][0]['name']);
        $this->assertSame(2, $online->list(OnlineVisitors::SCOPE_ONLINE, 'siswa', '', 1, 50)['total']);
        $this->assertSame(2, $online->list(OnlineVisitors::SCOPE_ONLINE, 'tamu', '', 1, 50)['total']);
        $this->assertSame(1, $online->list(OnlineVisitors::SCOPE_ONLINE, '', 'Rina', 1, 50)['total']);

        $devices = $online->breakdown(OnlineVisitors::SCOPE_ONLINE)['device'];
        $this->assertSame([['label' => 'Komputer', 'count' => 5, 'percent' => 100.0]], $devices);
    }

    // ------------------------------------------------------------------
    // halaman admin
    // ------------------------------------------------------------------

    public function testVisitorPageIsAdminOnly(): void
    {
        $this->get('admin/pengunjung')->assertRedirectTo(site_url('admin/masuk'));
        $this->withSession(['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true])->get('admin/pengunjung')->assertRedirect();
    }

    public function testVisitorPageListsVisitorsWithTrackersAndMarksOwnBrowser(): void
    {
        $this->insertVisitor([
            'user_type'      => 'student',
            'user_id'        => 3,
            'ip_address'     => '192.168.1.40',
            'device_type'    => 'HP',
            'device_brand'   => 'Samsung',
            'device_model'   => 'Galaxy A54 5G',
            'os_name'        => 'Android',
            'os_version'     => '14',
            'browser_name'   => 'Chrome',
            'browser_version' => '140.0',
            'engine'         => 'Blink',
            'language'       => 'id-ID, id',
            'screen'         => '412x915',
            'timezone'       => 'Asia/Jakarta',
            'touch_points'   => 5,
            'current_path'   => 'siswa/coblos',
            'client_hints'   => json_encode(['sec-ch-ua-model' => '"SM-A546E"']),
        ]);
        $this->visitor();

        $html = $this->body($this->asAdmin()->withHeaders(['User-Agent' => self::CHROME])->get('admin/pengunjung'));

        $this->assertStringContainsString('<h1 class="admin-head__title">Pengunjung online</h1>', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('<span>Pengunjung Online</span>', $html);
        $this->assertStringContainsString('<dt>Pengguna login</dt>', $html);
        $this->assertStringContainsString('dari 16 pengguna sistem', $html);
        $this->assertStringContainsString('<strong>Candra Setiawan</strong>', $html);
        $this->assertStringContainsString('Siswa &middot; NISN 0000000003 · Kelas 7B', $html);
        $this->assertStringContainsString('Bilik suara siswa', $html);
        $this->assertStringContainsString('HP &middot; Samsung Galaxy A54 5G', $html);
        $this->assertStringContainsString('Android 14', $html);
        $this->assertStringContainsString('Chrome 140.0', $html);
        $this->assertStringContainsString('192.168.1.40', $html);
        $this->assertStringContainsString('Layar 412×915', $html);
        $this->assertStringContainsString('Layar sentuh', $html);
        $this->assertStringContainsString('sec-ch-ua-model: &quot;SM-A546E&quot;', $html);
        $this->assertStringContainsString('data-visitors-refresh="20"', $html);
        $this->assertStringContainsString('assets/js/admin-visitors.js', $html);

        // baris browser admin ini tercatat sebelum daftar dibuat: sudah admin & halaman ini
        $this->assertStringContainsString('<strong>Administrator OSIS (Dev)</strong>', $html);
        $this->assertStringContainsString('<span class="pill pill--muted">Anda</span>', $html);
        $this->assertSame('admin/pengunjung', $this->row()['current_path']);
        $this->assertSame('1', (string) $this->row()['page_views'], 'tidak dicatat dua kali oleh filter');

        $filtered = $this->body($this->asAdmin()->get('admin/pengunjung?siapa=tamu'));
        $this->assertStringNotContainsString('Candra Setiawan', $filtered);
    }

    // ------------------------------------------------------------------
    // beranda
    // ------------------------------------------------------------------

    public function testHomeShowsMinimalOnlineCounters(): void
    {
        $this->insertVisitor([]);
        $this->insertVisitor(['user_type' => 'teacher', 'user_id' => 1]);

        $html = $this->body($this->get('/'));

        // pengunjung ini (belum punya cookie) ikut terhitung sebagai tamu
        $this->assertMatchesRegularExpression('#<p class="hero-online" data-reveal>\s*<span class="hero-online__item"><span class="hero-online__num" data-online-count="total">3</span> online</span>\s*<span class="hero-online__item"><span class="hero-online__num" data-online-count="users">1</span> <span class="visually-hidden">pengguna </span>login</span>\s*</p>#', $html);
        $this->assertMatchesRegularExpression('#<p class="site-footer__online">\s*<span class="site-footer__online-label">Online</span>\s*<span><span class="site-footer__num" data-online-count="guests">2</span> belum login</span>\s*<span><span class="site-footer__num" data-online-count="users">1</span> sudah login</span>\s*</p>\s*<p class="site-footer__text">#', $html);
        $this->assertStringContainsString('assets/js/presence.js', $html);
        $this->assertStringContainsString('data-presence-url="' . esc(site_url('kunjungan/detak'), 'attr') . '"', $html);

        $css = $this->asset('assets/css/home.css');
        $this->assertStringContainsString('min-height: 52px;', $this->rule($css, '.hero-online'));
        $this->assertMatchesRegularExpression('/@media \(max-width: 767px\) \{.*?\.hero-online \{ display: none; \}/s', $css);
        foreach (['animation', 'box-shadow: 0 0', 'gradient'] as $flashy) {
            $this->assertStringNotContainsString($flashy, $this->rule($css, '.hero-online'), $flashy);
        }

        config(Visitors::class)->publicCounter = false;
        service('renderer')->resetData();
        $hidden = $this->body($this->get('/'));
        $this->assertStringNotContainsString('hero-online', $hidden);
        $this->assertStringNotContainsString('site-footer__online', $hidden);
    }

    public function testDeviceProfileFlagsBotsAndLibraries(): void
    {
        $this->assertSame('Googlebot', DeviceInfo::profile('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')['bot']);
        $this->assertSame('library', DeviceInfo::profile('curl/8.5.0')['client_type']);

        $chrome = DeviceInfo::profile(self::CHROME);
        $this->assertSame('Komputer', $chrome['device_type']);
        $this->assertSame('Windows', $chrome['os_name']);
        $this->assertSame('10/11', $chrome['os_version']);
        $this->assertSame('x64', $chrome['os_platform']);
        $this->assertSame('Chrome 140', $chrome['browser']);

        $this->assertSame('45 detik', durasi(45));
        $this->assertSame('2 jam 5 menit', durasi(7500));
        $this->assertSame('2 hari 4 jam', durasi(187200));
    }
}
