<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\AvatarIdentity;
use App\Services\VisitorTracker;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Homepage;
use Config\Visitors;
use Tests\Support\AssetSource;

/**
 * Stage 26: Living Election Wall (GET partisipasi, GET partisipasi/status):
 * hanya nama, avatar, sudah/belum berpartisipasi per rombel & guru; hitungan
 * sama dengan AnalyticsService; tidak pernah NISN/NIP, jenis kelamin, waktu,
 * atau paslon; urutan rombel; filter di URL; sakelar electionWall; tautan di
 * footer scene perolehan suara; perolehan publik (hitung-suara) tidak berubah.
 * Gerak & tampilan diuji di browser (STAGE26-NOTES.md).
 *
 * @internal
 */
final class ElectionWallTest extends CIUnitTestCase
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

    protected function setUp(): void
    {
        parent::setUp();

        AvatarIdentity::useSecret('kunci-uji-stage-26');
        config(Homepage::class)->wallCacheSeconds = 0;
        cache()->clean();
        service('renderer')->resetData();
        $this->scheduleAt('2026-10-01 09:00:00');
    }

    protected function tearDown(): void
    {
        $config                   = config(Homepage::class);
        $config->electionWall     = true;
        $config->wallCacheSeconds = 5;
        $config->wallPollSeconds  = 20;
        AvatarIdentity::useSecret(null);
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

    private function vote(string $type, int $voterId, int $candidateId, string $at = '2026-10-01 08:05:09'): void
    {
        $this->db->table($type . '_votes')->insert([
            'election_id'  => 1,
            $type . '_id'  => $voterId,
            'candidate_id' => $candidateId,
            'status'       => 'LOCKED',
            'voted_at'     => $at,
            'device_info'  => 'HP / Android 14 / Samsung Galaxy A15',
            'browser_info' => 'Chrome 140',
        ]);
    }

    private function body($result): string
    {
        return (string) preg_replace('/<!-- DEBUG-VIEW[^>]*-->\s*/', '', (string) $result->response()->getBody());
    }

    private function page(string $query = '', array $session = []): string
    {
        service('renderer')->resetData();
        $result = $this->withSession($session)->get('partisipasi' . $query);
        $result->assertStatus(200);

        return $this->body($result);
    }

    private function wallStatus(): array
    {
        $result = $this->withSession([])->get('partisipasi/status');
        $result->assertStatus(200);

        return json_decode($result->getJSON(), true);
    }

    /** Isi satu <details> bagian wall. */
    private function group(string $html, string $key): string
    {
        $this->assertMatchesRegularExpression('/<details class="wall-group"[^>]*data-group="' . preg_quote($key, '/') . '"/', $html, $key);
        preg_match('/<details class="wall-group"[^>]*data-group="' . preg_quote($key, '/') . '".*?<\/details>/s', $html, $match);

        return $match[0];
    }

    // ------------------------------------------------------------------
    // halaman & privasi
    // ------------------------------------------------------------------

    public function testWallShowsNamesAndParticipationWithoutSensitiveData(): void
    {
        $this->vote('student', 1, 2);
        $this->vote('teacher', 2, 3);

        $html = $this->page();

        $this->assertStringContainsString('<title>Living Election Wall — Pemilihan OSIS SMP 1 DAWE</title>', $html);
        $this->assertStringContainsString('class="page-wall"', $html);
        $this->assertStringContainsString('Partisipasi pemilihan', $html);
        $this->assertStringContainsString('Halaman ini hanya menandai partisipasi. Pilihan setiap pemilih tetap rahasia.', $html);
        $this->assertStringContainsString('Ahmad Fauzan', $html);
        $this->assertStringContainsString('Rina Kusumawati, S.Pd.', $html);
        $this->assertMatchesRegularExpression('/<li class="wall-card is-voted" data-key="s1"/', $html);
        $this->assertMatchesRegularExpression('/<li class="wall-card" data-key="s2"/', $html);
        $this->assertMatchesRegularExpression('/<li class="wall-card is-voted" data-key="t2"/', $html);
        $this->assertStringContainsString('<span class="visually-hidden" data-card-state>Sudah berpartisipasi</span>', $html);
        $this->assertStringContainsString('<span class="visually-hidden" data-card-state>Belum berpartisipasi</span>', $html);

        // Siswa & guru nonaktif tidak tampil.
        $this->assertStringNotContainsString('Larasati Putri', $html);
        $this->assertStringNotContainsString('Yusuf Kurniawan', $html);

        // Tidak pernah: NISN/NIP, jenis kelamin, nomor absen, waktu, perangkat, paslon.
        foreach (['0000000001', '000000000000000002', 'Laki-laki', 'Perempuan', 'jenis_kelamin', '08.05', '2026-10-01 08:05', 'Samsung', 'Chrome 140', 'Arka Wibisana', 'Bagas Prayoga', 'Dewi Anggraini', 'Pasangan 0', 'Paslon', 'memilih pasangan', 'Belum memilih', 'yang belum memilih'] as $secret) {
            $this->assertStringNotContainsString($secret, $html, $secret);
        }

        // Tidak ada warna aksen paslon di halaman.
        foreach ($this->db->table('candidates')->select('theme_accent')->get()->getResultArray() as $candidate) {
            $this->assertStringNotContainsStringIgnoringCase((string) $candidate['theme_accent'], $html);
        }
    }

    public function testStatusJsonIsMinimalSortedByKeyAndNotCached(): void
    {
        // Urutan memilih: s5, t2, s2 -> JSON tetap urut kunci, bukan waktu.
        $this->vote('student', 5, 1, '2026-10-01 08:00:00');
        $this->vote('teacher', 2, 2, '2026-10-01 08:10:00');
        $this->vote('student', 2, 3, '2026-10-01 08:20:00');

        $result = $this->withSession([])->get('partisipasi/status');
        $result->assertStatus(200);
        $result->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));

        $data = json_decode($result->getJSON(), true);
        $this->assertSame(['status', 'label', 'notice', 'totals', 'voted', 'poll'], array_keys($data));
        $this->assertSame(['s2', 's5', 't2'], $data['voted']);
        $this->assertSame('ONGOING', $data['status']);
        $this->assertSame(20, $data['poll']['interval']);

        $json = $result->getJSON();
        foreach (['0000000002', '000000000000000002', 'voted_at', 'candidate', '08:00', '08:20', 'Andriana', 'nisn', 'nip'] as $secret) {
            $this->assertStringNotContainsString($secret, $json, $secret);
        }
    }

    public function testCountsMatchTheAnalyticsDefinition(): void
    {
        $this->vote('student', 1, 1);
        $this->vote('student', 3, 2);
        $this->vote('student', 12, 2); // siswa nonaktif: tidak dihitung
        $this->vote('teacher', 1, 3);
        $this->db->table('student_votes')->insert(['election_id' => 1, 'student_id' => 4, 'candidate_id' => 1, 'status' => 'UNLOCKED', 'voted_at' => '2026-10-01 08:00:00', 'unlocked_at' => '2026-10-01 08:30:00']);

        $election = model(\App\Models\ElectionModel::class)->getCurrentElection();
        $summary  = service('analytics')->snapshot($election)['summary'];
        $totals   = $this->wallStatus()['totals'];

        foreach (['students' => 'students', 'teachers' => 'teachers', 'all' => 'all'] as $key => $analytics) {
            $this->assertSame($summary[$analytics]['total'], $totals[$key]['total'], $key);
            $this->assertSame($summary[$analytics]['voted'], $totals[$key]['voted'], $key);
            $this->assertSame($totals[$key]['total'] - $totals[$key]['voted'], $totals[$key]['not_voted'], $key);
        }

        $this->assertSame(['s1', 's3', 't1'], $this->wallStatus()['voted']);

        $html = $this->page();
        $this->assertMatchesRegularExpression('/data-scope-voted>3</', $html);
        $this->assertMatchesRegularExpression('/dari <span data-scope-total>15<\/span> <span data-scope-noun>pemilih<\/span>/', $html);
        $this->assertStringContainsString('<span class="wall-split__num" data-scope-no>12</span> belum berpartisipasi', $html);
        $this->assertStringContainsString('<span class="wall-meter__pct" data-scope-percent>20,0%</span>', $html);
    }

    public function testRombelsAreOrderedByGradeWithOthersBeforeTeachers(): void
    {
        $this->db->table('students')->insertBatch([
            ['nisn' => '1111111111', 'name' => 'Zaki Lintas', 'jenis_kelamin' => 'L', 'kelas' => 'Ekskul', 'kodeunik' => '01012012', 'status_aktif' => 1],
            ['nisn' => '1111111112', 'name' => 'Yoga Romawi', 'jenis_kelamin' => 'L', 'kelas' => 'VIII-C', 'kodeunik' => '01012012', 'status_aktif' => 1],
            ['nisn' => '1111111113', 'name' => 'Abel Sepuluh', 'jenis_kelamin' => 'P', 'kelas' => '7-10', 'kodeunik' => '01012012', 'status_aktif' => 1],
            ['nisn' => '1111111114', 'name' => 'Aldo Dua', 'jenis_kelamin' => 'L', 'kelas' => '7-2', 'kodeunik' => '01012012', 'status_aktif' => 1],
        ]);

        $html = $this->page();
        preg_match_all('/<details class="wall-group"[^>]*data-group="([^"]+)"/', $html, $groups);
        $this->assertSame(['7-2', '7-10', '7A', '7B', '8A', '8B', 'VIII-C', '9A', '9B', 'EKSKUL', 'guru'], $groups[1]);

        preg_match_all('/<section class="wall-grade" data-grade="([^"]+)"/', $html, $grades);
        $this->assertSame(['7', '8', '9', 'lainnya', 'guru'], $grades[1]);
        $this->assertStringContainsString('<span class="wall-group__kicker">Kelas 7</span>', $html);
        $this->assertStringContainsString('<span class="wall-group__kicker">Lainnya</span>', $html);

        // Anggota urut nama di dalam bagian.
        $sevenA = $this->group($html, '7A');
        $this->assertLessThan(strpos($sevenA, 'Bunga Larasati'), strpos($sevenA, 'Ahmad Fauzan'));
        $this->assertStringContainsString('<span data-group-voted>0</span>/<span data-group-total>2</span>', $sevenA);
    }

    public function testFiltersFromTheQueryStringWorkWithoutJavascript(): void
    {
        $this->vote('student', 2, 1);

        $html = $this->page('?kelas=7a&q=bunga&urut=sudah');
        $this->assertStringContainsString('<option value="7A" selected>7A</option>', $html);
        $this->assertStringContainsString('value="bunga"', $html);
        $this->assertStringContainsString('<option value="sudah" selected>', $html);
        $this->assertStringContainsString('data-scope-noun>siswa 7A</span>', $html);
        $this->assertStringContainsString('1 nama', $html);
        $this->assertMatchesRegularExpression('/data-key="s1"[^>]*hidden>/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-key="s2"[^>]*hidden>/', $html);
        $this->assertMatchesRegularExpression('/data-group="7B"[^>]*hidden>/', $html);
        $this->assertMatchesRegularExpression('/<section class="wall-grade" data-grade="guru"[^>]*hidden>/', $html);

        // Urutan "sudah dulu": Bunga (sudah) sebelum Ahmad (belum) di 7A.
        $sevenA = $this->group($html, '7A');
        $this->assertLessThan(strpos($sevenA, 'Ahmad Fauzan'), strpos($sevenA, 'Bunga Larasati'));

        $teachers = $this->page('?kelas=guru');
        $this->assertStringContainsString('data-scope-noun>guru</span>', $teachers);
        $this->assertStringContainsString('dari <span data-scope-total>4</span>', $teachers);

        $nothing = $this->page('?q=tidak-ada-nama');
        $this->assertStringContainsString('Tidak ada nama yang cocok', $nothing);
        $this->assertMatchesRegularExpression('/<p class="wall-empty" data-wall-empty>/', $nothing);

        // Nilai asing diabaikan.
        $this->assertStringContainsString('<option value="" selected>Semua</option>', $this->page('?kelas=<script>&urut=acak'));
    }

    public function testSearchIgnoresCaseAndDiacritics(): void
    {
        $this->db->table('students')->where('id', 1)->update(['name' => 'Ahmad Fauzán']);

        $html = $this->page('?q=FAUZAN');
        $this->assertStringContainsString('data-name="ahmad fauzan"', $html);
        $this->assertDoesNotMatchRegularExpression('/data-key="s1"[^>]*hidden>/', $html);
    }

    public function testElectionStatesAndPolling(): void
    {
        $this->scheduleAt('2026-09-30 08:00:00');
        $upcoming = $this->wallStatus();
        $this->assertSame('UPCOMING', $upcoming['status']);
        $this->assertSame('Pemilihan dibuka 1 Okt 2026, 07.00 WIB', $upcoming['notice']);
        $this->assertSame(20, $upcoming['poll']['interval']);
        $this->assertStringContainsString('Pemilihan dibuka 1 Okt 2026, 07.00 WIB', $this->page());

        $this->scheduleAt('2026-10-02 08:00:00');
        $finished = $this->wallStatus();
        $this->assertSame('FINISHED', $finished['status']);
        $this->assertSame(0, $finished['poll']['interval']);
        $this->assertStringContainsString('data-poll="0"', $this->page());

        $this->scheduleAt('2026-10-01 09:00:00');
        config(Homepage::class)->wallPollSeconds = 3;
        $this->assertSame(10, $this->wallStatus()['poll']['interval'], 'batas bawah 10 detik');
    }

    public function testUnlockedVotesDisappearFromTheWall(): void
    {
        $this->vote('student', 1, 2);
        $this->assertSame(['s1'], $this->wallStatus()['voted']);

        $this->db->table('student_votes')->where('student_id', 1)->update(['status' => 'UNLOCKED', 'unlocked_at' => '2026-10-01 09:30:00']);
        $this->assertSame([], $this->wallStatus()['voted']);
    }

    public function testWallUsesPhotosOrDiceBearAvatarsWithInitialsFallback(): void
    {
        $this->db->table('students')->where('id', 2)->update(['avatar_style' => 'avataaars', 'avatar_seed' => '0f0e0d0c0b0a0908']);

        $html    = $this->page();
        $default = AvatarIdentity::default(\App\Services\VoterType::Student, 1);

        $this->assertStringContainsString('data-avatar-style="' . $default['style'] . '" data-avatar-seed="' . $default['seed'] . '"', $html);
        $this->assertStringContainsString('data-avatar-style="avataaars" data-avatar-seed="0f0e0d0c0b0a0908"', $html);
        $this->assertStringContainsString('<span class="avatar__initials" aria-hidden="true">AF</span>', $html);
        $this->assertStringContainsString('data-art-module="' . esc(asset_url('assets/js/avatar-art.js'), 'attr') . '"', $html);
        $this->assertStringContainsString('assets/js/wall.js', $html);
        $this->assertStringContainsString('Ilustrasi avatar: DiceBear', $html);
    }

    // ------------------------------------------------------------------
    // menu, sakelar, tautan, rute
    // ------------------------------------------------------------------

    public function testHeaderLinksBackToResultsAndToTheRightDashboard(): void
    {
        $guest = $this->page();
        $this->assertStringContainsString('<header class="wall-nav">', $guest);
        $this->assertStringContainsString('href="' . esc(site_url('/') . '#perolehan', 'attr') . '"', $guest);
        $this->assertStringContainsString('href="' . esc(site_url('/') . '#masuk', 'attr') . '"', $guest);
        $this->assertStringContainsString('<span>Masuk</span>', $guest);
        $this->assertStringNotContainsString('site-nav__brand', $guest);

        $this->assertStringContainsString('href="' . esc(site_url('siswa'), 'attr') . '" title="Dasbor Siswa"', $this->page('', self::STUDENT));
        $this->assertStringContainsString('href="' . esc(site_url('guru'), 'attr') . '" title="Dasbor Guru"', $this->page('', self::TEACHER));
        $this->assertStringContainsString('href="' . esc(site_url('admin'), 'attr') . '" title="Dasbor Admin"', $this->page('', self::ADMIN));
    }

    public function testWallSwitchTurnsOffPagesJsonAndLinks(): void
    {
        $home = $this->body($this->get('/'));
        $this->assertMatchesRegularExpression('#<div class="site-footer__row">\s*<p class="site-footer__online">.*?</p>\s*<a class="site-footer__wall" href="' . preg_quote(site_url('partisipasi'), '#') . '">Living Election Wall<svg class="icon[^"]*icon--arrow-up-right[^"]*site-footer__wall-icon"#s', $home);

        config(Homepage::class)->electionWall = false;
        service('renderer')->resetData();

        foreach (['partisipasi', 'partisipasi/status'] as $path) {
            try {
                $this->get($path);
                $this->fail($path . ' harus 404 saat electionWall = false');
            } catch (PageNotFoundException) {
                // 404
            }
        }

        service('renderer')->resetData();
        $off = $this->body($this->get('/'));
        $this->assertStringNotContainsString('site-footer__wall', $off);
        $this->assertStringContainsString('site-footer__online', $off);

        // Angka online dimatikan tetapi wall aktif: baris berisi tautan saja.
        config(Homepage::class)->electionWall = true;
        config(Visitors::class)->publicCounter = false;
        service('renderer')->resetData();
        $onlyWall = $this->body($this->get('/'));
        config(Visitors::class)->publicCounter = true;
        $this->assertMatchesRegularExpression('#<div class="site-footer__row">\s*<a class="site-footer__wall"#', $onlyWall);
    }

    public function testFooterLinkSitsBesideTheOnlineCountOnDesktopAndBelowOnPhones(): void
    {
        $css    = AssetSource::read('assets/css/home.css');
        $mobile = substr($css, (int) strpos($css, '@media (max-width: 767px)'));

        $this->assertMatchesRegularExpression('/\.site-footer__row \{[^}]*display: flex;[^}]*justify-content: center;/', $css);
        $this->assertMatchesRegularExpression('/\.site-footer__wall \{[^}]*font-family: var\(--font-mono\);[^}]*font-size: 0\.6875rem;/', $css);
        $this->assertMatchesRegularExpression('/\.site-footer__online \+ \.site-footer__wall \{[^}]*border-left: 1px solid var\(--night-line-2\);/', $css);
        $this->assertMatchesRegularExpression('/\.site-footer__row \{\s*flex-direction: column;/', $mobile);
    }

    public function testPublicLiveCountStillShowsPercentagesPerPair(): void
    {
        $this->vote('student', 1, 2);

        $live = json_decode($this->get('hitung-suara')->getJSON(), true);

        $this->assertSame('ONGOING', $live['status']);
        $this->assertCount(3, $live['candidates']);
        $this->assertSame(100.0, (float) $live['candidates'][1]['percent']);
    }

    public function testVisitorLabelAndStylesheetRules(): void
    {
        $this->assertSame('Living Election Wall', VisitorTracker::pageLabel('partisipasi'));

        // Larangan diperiksa pada kode, bukan komentar ("tanpa gradient").
        $strip = static fn (string $code): string => (string) preg_replace('~/\*.*?\*/~s', '', $code);
        $css   = AssetSource::read('assets/css/wall.css');
        $js    = AssetSource::read('assets/js/wall.js');

        $this->assertStringContainsString('--night:        #101312;', $css);
        $this->assertMatchesRegularExpression('/\.wall-nav \{[^}]*position: sticky;/', $css);
        $this->assertMatchesRegularExpression('/\.wall-tools \{[^}]*position: sticky;[^}]*top: var\(--wall-nav-h\);/', $css);
        $this->assertMatchesRegularExpression('/\.wall-card:not\(\.is-voted\) \.wall-card__avatar \{[^}]*filter: saturate\(0\.6\) brightness\(0\.85\);/', $css);
        $this->assertMatchesRegularExpression('/\.wall-group \{[^}]*content-visibility: auto;/', $css);
        $this->assertStringContainsString('font-variant-numeric: tabular-nums', $css);
        $this->assertMatchesRegularExpression('/@media \(prefers-reduced-motion: reduce\) \{/', $css);
        foreach (['gradient', 'backdrop-filter', 'bounce'] as $banned) {
            $this->assertStringNotContainsString($banned, $strip($css), $banned);
        }

        $this->assertStringContainsString('const FLIP_MS = 260;', $js);
        $this->assertStringContainsString('const STAGGER = 70;', $js);
        $this->assertStringContainsString('IntersectionObserver', $js);
        $this->assertStringContainsString('history.replaceState', $js);
        $this->assertStringContainsString('sessionStorage', $js);
        $this->assertStringNotContainsString('localStorage', $strip($js));
        $this->assertStringContainsString('document.hidden', $js);
    }
}
