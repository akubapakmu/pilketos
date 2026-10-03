<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Commands\Utilities\Routes\FilterCollector;
use CodeIgniter\Commands\Utilities\Routes\SampleURIGenerator;
use CodeIgniter\I18n\Time;
use CodeIgniter\Router\DefinedRouteCollector;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Stage 4: route matrix & role permission matrix (04-FINAL... bagian 2, 17, 18).
 *
 * 1. Setiap route yang terdaftar dicek filternya: di luar daftar publik yang
 *    eksplisit, route admin/siswa/guru WAJIB memakai filter role-nya,
 *    sehingga route baru tidak mungkin lolos tanpa proteksi.
 * 2. Tabel kapabilitas (Student / Teacher / Admin) diuji lewat HTTP.
 *
 * @internal
 */
final class RoleMatrixTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    /**
     * Satu-satunya route tanpa filter autentikasi (route matrix "public").
     * hitung-suara (redesign beranda): hanya persentase per pasangan + partisipasi.
     * kunjungan/detak (Stage 19): detak pengunjung online, balasan hanya angka
     * ringkas tamu & pengguna login. kunjungan/online (Stage 21): angka yang
     * sama, dibaca beranda tiap detik (GET, tanpa tulis). partisipasi &
     * partisipasi/status (Stage 26): Living Election Wall, hanya nama, avatar,
     * dan sudah/belum berpartisipasi (tanpa NISN/NIP, waktu, pilihan).
     */
    private const PUBLIC_ROUTES = [
        'GET'  => ['/', 'jam-server', 'hitung-suara', 'kunjungan/online', 'partisipasi', 'partisipasi/status', 'siswa/masuk', 'guru/masuk', 'admin/masuk'],
        'POST' => ['kunjungan/detak', 'siswa/masuk', 'guru/masuk', 'admin/masuk'],
    ];

    /**
     * Segmen pertama URL (Stage 7: bahasa Indonesia) -> filter wajib.
     */
    private const ROLE_FILTERS = [
        'admin' => 'adminauth',
        'siswa' => 'studentauth',
        'guru'  => 'teacherauth',
    ];

    private const STUDENT = ['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true];
    private const TEACHER = ['user_type' => 'teacher', 'teacher_id' => 1, 'isLoggedIn' => true];
    private const ADMIN   = ['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true];

    protected function setUp(): void
    {
        parent::setUp();

        Services::resetSingle('throttler');
        cache()->clean();
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    private function json($result): array
    {
        return json_decode($result->getJSON(), true);
    }

    private function ajax(array $session)
    {
        return $this->withSession($session)
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->withBodyFormat('json');
    }

    private function page(array $session, string $path)
    {
        return $this->withSession($session)->withHeaders([])->withBodyFormat('')->get($path);
    }

    private function postAs(array $session, string $path, array $data = [])
    {
        return $this->withSession($session)->withHeaders([])->withBodyFormat('')->post($path, [csrf_token() => csrf_hash()] + $data);
    }

    // ------------------------------------------------------------------
    // route matrix
    // ------------------------------------------------------------------

    public function testEveryRouteIsPublicByDesignOrProtectedByItsRoleFilter(): void
    {
        $collection = service('routes')->loadRoutes();
        $samples    = new SampleURIGenerator();
        $finder     = new FilterCollector();
        $seen       = ['GET' => [], 'POST' => []];

        foreach ((new DefinedRouteCollector($collection))->collect() as $route) {
            $verb = strtoupper($route['method']);
            $path = (string) $route['route'];

            if (! isset($seen[$verb])) {
                continue;
            }

            $seen[$verb][] = $path;

            // Stage 7: URL Inggris lama tidak dialihkan; tidak ada route redirect.
            $this->assertFalse($collection->isRedirect($path), "{$verb} {$path} tidak boleh berupa redirect");

            $before = $finder->get($verb, $samples->get($path))['before'];
            $auth   = array_values(array_intersect($before, ['adminauth', 'studentauth', 'teacherauth']));

            // CSRF & filter global selalu aktif untuk semua route.
            $this->assertContains('csrf', $before, "{$verb} {$path} tanpa CSRF");

            if (in_array($path, self::PUBLIC_ROUTES[$verb], true)) {
                $this->assertSame([], $auth, "{$verb} {$path} publik");

                continue;
            }

            $prefix = strtok($path, '/');
            $this->assertArrayHasKey($prefix, self::ROLE_FILTERS, "{$verb} {$path} tidak terdaftar di route matrix");
            $this->assertSame([self::ROLE_FILTERS[$prefix]], $auth, "{$verb} {$path} wajib memakai filter " . self::ROLE_FILTERS[$prefix]);
        }

        foreach (self::PUBLIC_ROUTES as $verb => $list) {
            foreach ($list as $path) {
                $this->assertContains($path, $seen[$verb], "{$verb} {$path} terdokumentasi tetapi tidak ada");
            }
        }

        $this->assertGreaterThan(60, count($seen['GET']) + count($seen['POST']));
    }

    public function testNoAdminRouteWritesVotes(): void
    {
        foreach (service('routes')->getRoutes('POST', false) as $route => $handler) {
            if (str_starts_with((string) $route, 'admin/')) {
                $this->assertStringNotContainsString('VoteController', (string) $handler, (string) $route);
            }
        }
    }

    // ------------------------------------------------------------------
    // role permission matrix
    // ------------------------------------------------------------------

    public function testLoginIsSeparatedPerRole(): void
    {
        $studentCred = ['nisn' => '0000000001', 'kodeunik' => '05062013'];
        $teacherCred = ['nip' => '000000000000000004', 'kodeunik' => '01061992'];

        // Login Student: Yes / No / No
        $this->postAs([], 'siswa/masuk', $studentCred)->assertRedirectTo(site_url('siswa'));
        $this->postAs([], 'siswa/masuk', ['nisn' => '000000000000000004', 'kodeunik' => '01061992']);
        $this->assertNotSame('teacher', session('user_type'));
        $this->postAs([], 'siswa/masuk', ['nisn' => 'admin', 'kodeunik' => 'admin123']);
        $this->assertNotSame('admin', session('user_type'));

        // Login Teacher: No / Yes / No
        $this->postAs([], 'guru/masuk', $teacherCred)->assertRedirectTo(site_url('guru'));
        $this->postAs([], 'guru/masuk', ['nip' => '0000000001', 'kodeunik' => '05062013']);
        $this->assertNotSame('student', session('user_type'));

        // Admin Login: No / No / Yes
        $this->postAs([], 'admin/masuk', ['username' => 'admin', 'password' => 'admin123'])->assertRedirectTo(site_url('admin'));
        $this->postAs([], 'admin/masuk', ['username' => '0000000001', 'password' => '05062013']);
        $this->assertNotSame('student', session('user_type'));
    }

    public function testStudentCannotEnterTeacherOrAdminAreas(): void
    {
        foreach (['guru', 'guru/coblos', 'guru/pilihanku'] as $path) {
            $this->page(self::STUDENT, $path)->assertRedirectTo(site_url('guru/masuk'));
        }

        foreach (['admin', 'admin/analitik', 'admin/analitik/suara', 'admin/hasil', 'admin/siswa', 'admin/riwayat'] as $path) {
            $this->page(self::STUDENT, $path)->assertRedirectTo(site_url('admin/masuk'));
        }

        $this->ajax(self::STUDENT)->get('admin/hitung-suara')->assertStatus(401);
    }

    public function testTeacherCannotEnterStudentOrAdminAreas(): void
    {
        foreach (['siswa', 'siswa/coblos', 'siswa/pilihanku'] as $path) {
            $this->page(self::TEACHER, $path)->assertRedirectTo(site_url('siswa/masuk'));
        }

        foreach (['admin', 'admin/analitik', 'admin/hasil', 'admin/guru', 'admin/buka-kunci'] as $path) {
            $this->page(self::TEACHER, $path)->assertRedirectTo(site_url('admin/masuk'));
        }

        $this->ajax(self::TEACHER)->get('admin/hitung-suara')->assertStatus(401);
    }

    public function testVotersCannotUseAdminActions(): void
    {
        $actions = [
            ['admin/paslon', ['nomor_urut' => '4', 'nama_ketua' => 'X', 'nama_wakil' => 'Y']],
            ['admin/paslon/1/hapus', []],
            ['admin/paslon/batas', ['batas_pasangan' => '9']],
            ['admin/siswa/1/status', ['status_aktif' => '0']],
            ['admin/guru/1/hapus', []],
            ['admin/siswa/impor/simpan', ['token' => str_repeat('a', 32)]],
            ['admin/jadwal/tutup', []],
            ['admin/jadwal', ['nama' => 'X', 'tahun' => '2026', 'start_at' => '2026-10-01T07:00', 'end_at' => '2026-10-01T12:00']],
            ['admin/buka-kunci/siswa/1', ['vote_id' => '1', 'reason' => 'Percobaan tanpa hak akses.', 'confirm' => '1']],
        ];

        foreach ([self::STUDENT, self::TEACHER] as $session) {
            foreach ($actions as [$path, $data]) {
                $this->postAs($session, $path, $data)->assertRedirectTo(site_url('admin/masuk'));
            }
        }

        $this->seeInDatabase('candidates', ['id' => 1, 'status_aktif' => 1]);
        $this->seeInDatabase('students', ['id' => 1, 'status_aktif' => 1]);
        $this->assertSame(0, $this->db->table('audit_logs')->countAllResults());
        $this->assertSame(0, $this->db->table('candidates')->where('nomor_urut', 4)->countAllResults());
        $this->seeInDatabase('elections', ['id' => 1, 'batas_pasangan' => 3]);
    }

    public function testVoteCapabilitiesPerRole(): void
    {
        // Vote: Yes / Yes / No
        $this->ajax(self::STUDENT)->post('siswa/coblos', ['candidate_id' => 2])->assertStatus(200);
        $this->ajax(self::TEACHER)->post('guru/coblos', ['candidate_id' => 3])->assertStatus(200);
        $this->ajax(self::ADMIN)->post('siswa/coblos', ['candidate_id' => 1, 'student_id' => 2])->assertStatus(401);
        $this->ajax(self::ADMIN)->post('guru/coblos', ['candidate_id' => 1, 'teacher_id' => 2])->assertStatus(401);

        // Re-vote while locked: No / No / No
        $again = $this->ajax(self::STUDENT)->post('siswa/coblos', ['candidate_id' => 1]);
        $again->assertStatus(409);
        $this->assertSame('already_voted', $this->json($again)['status']);
        $this->ajax(self::TEACHER)->post('guru/coblos', ['candidate_id' => 1])->assertStatus(409);

        $this->assertSame(1, $this->db->table('student_votes')->countAllResults());
        $this->assertSame(1, $this->db->table('teacher_votes')->countAllResults());
        $this->seeInDatabase('student_votes', ['student_id' => 1, 'candidate_id' => 2, 'status' => 'LOCKED']);
        $this->seeInDatabase('teacher_votes', ['teacher_id' => 1, 'candidate_id' => 3, 'status' => 'LOCKED']);
    }

    public function testOwnVoteOnlyAndNoResultsForVoters(): void
    {
        // Pemilih lain (siswa 2) memilih 03; siswa 1 memilih 02.
        $this->ajax(['user_type' => 'student', 'student_id' => 2, 'isLoggedIn' => true])->post('siswa/coblos', ['candidate_id' => 3])->assertStatus(200);
        $this->ajax(self::STUDENT)->post('siswa/coblos', ['candidate_id' => 2])->assertStatus(200);

        // View Own Vote: Yes / Yes / N/A
        $mine = $this->page(self::STUDENT, 'siswa/pilihanku');
        $mine->assertStatus(200);
        $mine->assertSee('Bagas Prayoga');
        $mine->assertDontSee('Dewi Anggraini'); // pilihan pemilih lain
        $mine->assertDontSee('Bunga Larasati'); // identitas pemilih lain
        $mine->assertDontSee('suara sah');

        $this->page(self::ADMIN, 'siswa/pilihanku')->assertRedirectTo(site_url('siswa/masuk'));

        // View Analytics / hasil: No / No / Yes
        $this->page(self::ADMIN, 'admin/analitik')->assertStatus(200);
        $this->page(self::STUDENT, 'admin/analitik')->assertRedirectTo(site_url('admin/masuk'));

        // Dasbor pemilih tidak memuat jumlah suara pasangan mana pun.
        $dashboard = $this->page(self::STUDENT, 'siswa');
        $dashboard->assertDontSee('data-live');
        $dashboard->assertDontSee('suara sah');
    }

    public function testViewCandidatesForAllRoles(): void
    {
        // Stage 22: daftar pasangan pemilih di Sekilas paslon & bilik suara.
        foreach ([[self::STUDENT, 'siswa/sekilas-paslon'], [self::TEACHER, 'guru/sekilas-paslon'], [self::STUDENT, 'siswa/bilik-suara'], [self::TEACHER, 'guru/bilik-suara'], [self::ADMIN, 'admin/paslon']] as [$session, $path]) {
            $page = $this->page($session, $path);
            $page->assertStatus(200);
            $page->assertSee('Arka Wibisana');
            $page->assertSee('Dewi Anggraini');
        }
    }
}
