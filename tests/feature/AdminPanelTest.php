<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Stage 3: panel admin lewat HTTP — proteksi route, dasbor, live count,
 * analitik, detail suara, jadwal, data pemilih, unlock, dan audit.
 *
 * Checklist 03-ADMIN-IMPORT-ANALYTICS.md "TEST STAGE 3" dipetakan di
 * STAGE3-NOTES.md bagian TESTING.
 *
 * @internal
 */
final class AdminPanelTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private const REASON = 'Siswa salah menekan pasangan, dikonfirmasi wali kelas 7A.';

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

    // ------------------------------------------------------------------
    // helper
    // ------------------------------------------------------------------

    private function admin(): array
    {
        return ['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true];
    }

    private function asAdmin()
    {
        return $this->withSession($this->admin());
    }

    private function postAdmin(string $path, array $data = [])
    {
        return $this->withSession($this->admin())->post($path, [csrf_token() => csrf_hash()] + $data);
    }

    private function vote(string $type, int $voterId, int $candidateId, string $status = 'LOCKED', ?string $votedAt = null): int
    {
        $this->db->table($type . '_votes')->insert([
            'election_id'  => 1,
            $type . '_id'  => $voterId,
            'candidate_id' => $candidateId,
            'status'       => $status,
            'voted_at'     => $votedAt ?? Time::now()->toDateTimeString(),
            'unlocked_at'  => $status === 'UNLOCKED' ? Time::now()->toDateTimeString() : null,
            'device_info'  => 'HP / Android / Samsung',
            'browser_info' => 'Samsung Internet 25',
        ]);

        return (int) $this->db->insertID();
    }

    private function seedVotes(): void
    {
        $this->vote('student', 1, 1);
        $this->vote('student', 2, 2);
        $this->vote('student', 3, 1);
        $this->vote('student', 5, 3);
        $this->vote('teacher', 1, 1);
    }

    private function json($result): array
    {
        return json_decode($result->getJSON(), true);
    }

    private function scheduleAt(string $now, string $start = '2026-10-01 07:00:00', string $end = '2026-10-01 12:00:00'): void
    {
        $this->db->table('elections')->where('id', 1)->update(['start_at' => $start, 'end_at' => $end]);
        Time::setTestNow($now);
    }

    // ------------------------------------------------------------------
    // proteksi route
    // ------------------------------------------------------------------

    public function testAdminRoutesRequireAdminSession(): void
    {
        $paths = [
            'admin', 'admin/hitung-suara', 'admin/analitik', 'admin/analitik/suara',
            'admin/paslon', 'admin/paslon/tambah', 'admin/paslon/1/ubah', 'admin/paslon/1/intip',
            'admin/siswa', 'admin/siswa/1', 'admin/siswa/impor', 'admin/siswa/impor/templat',
            'admin/guru', 'admin/guru/1', 'admin/guru/impor',
            'admin/jadwal', 'admin/buka-kunci', 'admin/buka-kunci/siswa/1', 'admin/riwayat',
        ];

        $student = ['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true];
        $teacher = ['user_type' => 'teacher', 'teacher_id' => 1, 'isLoggedIn' => true];

        foreach ($paths as $path) {
            $this->withSession([])->get($path)->assertRedirectTo(site_url('admin/masuk'));
            $this->withSession($student)->get($path)->assertRedirectTo(site_url('admin/masuk'));
            $this->withSession($teacher)->get($path)->assertRedirectTo(site_url('admin/masuk'));
        }
    }

    public function testLiveCountRejectsNonAdminAjaxWith401Json(): void
    {
        $result = $this->withSession(['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get('admin/hitung-suara');

        $result->assertStatus(401);
        $this->assertSame('unauthenticated', $this->json($result)['status']);
        $this->assertSame(site_url('admin/masuk'), $this->json($result)['redirect']);
    }

    public function testAdminPostRequiresCsrfToken(): void
    {
        $this->expectException(SecurityException::class);

        $this->asAdmin()->post('admin/jadwal/tutup', []);
    }

    public function testAdminCannotCastVoteForVoters(): void
    {
        $asAdmin = $this->asAdmin()
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->withBodyFormat('json');

        $asAdmin->post('siswa/coblos', ['candidate_id' => 1, 'student_id' => 1])->assertStatus(401);
        $asAdmin->post('guru/coblos', ['candidate_id' => 1, 'teacher_id' => 1])->assertStatus(401);

        $this->assertSame(0, $this->db->table('student_votes')->countAllResults());
        $this->assertSame(0, $this->db->table('teacher_votes')->countAllResults());
    }

    public function testOversizedPostGetsClearMessageInsteadOfCsrfError(): void
    {
        service('superglobals')->setServer('CONTENT_LENGTH', (string) (64 * 1024 * 1024 * 1024));

        $result = $this->asAdmin()->post('admin/paslon', []);

        $result->assertRedirect();
        $this->assertStringContainsString('melebihi batas server', (string) session('error'));
        service('superglobals')->setServer('CONTENT_LENGTH', '0');
    }

    // ------------------------------------------------------------------
    // dasbor & live count
    // ------------------------------------------------------------------

    public function testDashboardShowsSummaryCandidatesAndRecaps(): void
    {
        $this->seedVotes();

        $result = $this->asAdmin()->get('admin');

        $result->assertStatus(200);
        $result->assertSee('Pemilihan Ketua dan Wakil Ketua OSIS SMP 1 DAWE');
        // Stage 17: badge status topbar dihapus; status terbaca dari countdown.
        $result->assertDontSee('Sedang Berlangsung');
        $result->assertSee('Ditutup dalam');
        $result->assertSee('Hasil sementara');
        $result->assertSee('data-live-url="' . site_url('admin/hitung-suara') . '"');
        $result->assertSee('assets/js/admin-live.js');
        $result->assertSee('noindex, nofollow');

        // 11 siswa + 4 guru aktif; 5 sudah memilih (4 siswa + 1 guru).
        $result->assertSee('data-live-value="summary.all.total">15<');
        $result->assertSee('data-live-value="summary.all.voted">5<');
        $result->assertSee('data-live-value="summary.all.not_voted">10<');
        $result->assertSee('data-live-value="summary.all.participation" data-live-format="percent">33,3%<');

        // Suara per pasangan dengan warna aksen & persentase.
        $result->assertSee('data-live-candidate="1"');
        $result->assertSee('--accent: #C4432B;');
        $result->assertSee('data-live-field="votes">3<');
        $result->assertSee('data-live-field="percent">60,0%<');
        $result->assertSee('data-live-donut');

        // Rekap jenjang & kelas, kelas bertaut ke daftar siswa.
        $result->assertSee('data-live-table="grade"');
        $result->assertSee('data-live-table="class"');
        $result->assertSee('Kelas 7');
        $result->assertSee('href="' . site_url('admin/siswa') . '?status=aktif&amp;rombel=7A"');
        $result->assertSee('Rekap kelas');
    }

    public function testDashboardWithoutElectionPromptsForSchedule(): void
    {
        $this->db->disableForeignKeyChecks();
        $this->db->table('elections')->truncate();
        $this->db->enableForeignKeyChecks();
        $this->db->enableForeignKeyChecks();

        $result = $this->asAdmin()->get('admin');

        $result->assertStatus(200);
        $result->assertSee('Belum ada jadwal pemilihan');
        $result->assertSee('href="' . site_url('admin/jadwal') . '"');
    }

    public function testLiveCountReturnsConsistentJson(): void
    {
        $this->seedVotes();
        $this->vote('student', 4, 3, 'UNLOCKED');

        $result = $this->asAdmin()
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get('admin/hitung-suara');

        $result->assertStatus(200);
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));

        $data = $this->json($result);
        $this->assertSame('ONGOING', $data['election']['status']);
        $this->assertSame('Sedang Berlangsung', $data['election']['label']);
        $this->assertSame(10, $data['poll']['interval']);
        $this->assertSame(['total' => 11, 'voted' => 4, 'not_voted' => 7], array_intersect_key($data['summary']['students'], array_flip(['total', 'voted', 'not_voted'])));
        $this->assertSame(1, $data['summary']['teachers']['voted']);
        $this->assertSame(5, $data['summary']['all']['voted']);
        $this->assertSame(['1' => 3, '2' => 1, '3' => 1], $data['summary']['all']['votes']);
        $this->assertSame([3, 1, 1], array_column($data['candidates'], 'votes'));
        $this->assertSame([60, 20, 20], array_map('intval', array_column($data['candidates'], 'percent')));
        $this->assertSame(['7', '8', '9'], array_column($data['groups']['grade'], 'key'));
        $this->assertSame(['7A', '7B', '8A', '8B', '9A', '9B'], array_column($data['groups']['class'], 'key'));
        $this->assertSame(['student', 'teacher'], array_column($data['groups']['type'], 'key'));
        $this->assertSame(['L', 'P'], array_column($data['groups']['gender'], 'key'));
        $this->assertMatchesRegularExpression('/^\d{2}\.\d{2}\.\d{2} WIB$/', $data['generated_label']);

        // Tidak ada data identitas pemilih di live count.
        $this->assertStringNotContainsString('Ahmad Fauzan', $result->getJSON());
        $this->assertStringNotContainsString('0000000001', $result->getJSON());
    }

    public function testLiveCountPollingFollowsElectionStatus(): void
    {
        $this->scheduleAt('2026-10-01 06:00:00');
        $this->assertSame(60, $this->json($this->asAdmin()->get('admin/hitung-suara'))['poll']['interval']);

        Time::setTestNow('2026-10-01 12:00:00');
        $finished = $this->json($this->asAdmin()->get('admin/hitung-suara'));
        $this->assertSame('FINISHED', $finished['election']['status']);
        $this->assertSame(0, $finished['poll']['interval']);
    }

    // ------------------------------------------------------------------
    // analitik & detail suara
    // ------------------------------------------------------------------

    public function testAnalyticsPageShowsAllBreakdowns(): void
    {
        $this->seedVotes();

        // Stage 11: satu bagian per halaman (pill section header); setiap
        // bagian punya URL sendiri, tabel rekap tetap diperbarui live count.
        // Stage 13: label Total / Pemilih / Jenis Kelamin, catatan sederhana.
        $panes = [
            'admin/analitik'               => [null, ['>Total</h2>', 'Gabungan suara siswa dan guru.']],
            'admin/analitik/jenis-pemilih' => ['type', ['>Pemilih</h2>', 'Siswa', 'Guru']],
            'admin/analitik/jenis-kelamin' => ['gender', ['Jenis Kelamin Siswa', 'Laki-laki', 'Perempuan']],
            'admin/analitik/kelas'         => ['grade', ['Rekap Kelas', 'Kelas 7']],
            'admin/analitik/rombel'        => ['class', ['Rekap Rombel', '7A']],
        ];

        foreach ($panes as $path => [$key, $texts]) {
            $result = $this->asAdmin()->get($path);

            $result->assertStatus(200);
            foreach ($texts as $text) {
                $result->assertSee($text);
            }
            if ($key !== null) {
                $result->assertSee('data-live-table="' . $key . '"');
                $result->assertSee('class="stackbar__seg"');
            }
        }

        $this->asAdmin()->get('admin/analitik')->assertSee('data-live-results');
    }

    public function testDetailVotesListsCountedVotesWithDeviceAndBrowser(): void
    {
        $this->seedVotes();
        $this->vote('student', 4, 2, 'UNLOCKED');

        $result = $this->asAdmin()->get('admin/analitik/suara');

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();
        $this->assertStringContainsString('<strong>5</strong> baris suara: 4 siswa &middot; 1 guru.', $body);
        $result->assertSee('Ahmad Fauzan');
        $result->assertSee('Sudarmanto, S.Pd.');
        // device_info "HP / Android / Samsung": model di baris pertama, sisanya + browser di bawahnya.
        $this->assertStringContainsString('<span class="device-cell__main">Samsung</span><span class="cell-sub">HP · Android · Samsung Internet 25</span>', $body);
        $result->assertSee('0000000001');
        $result->assertDontSee('Dinda Permatasari'); // hanya riwayat UNLOCKED

        $history = $this->asAdmin()->get('admin/analitik/suara?status=UNLOCKED');
        $history->assertSee('Dinda Permatasari');
        $history->assertSee('<strong>1</strong> baris suara');

        $teachers = $this->asAdmin()->get('admin/analitik/suara?type=teacher');
        $teachers->assertSee('<strong>1</strong> baris suara');
        $teachers->assertDontSee('Ahmad Fauzan');

        $filtered = $this->asAdmin()->get('admin/analitik/suara?rombel=7A&gender=P&candidate=2');
        $filtered->assertSee('Bunga Larasati');
        $filtered->assertSee('<strong>1</strong> baris suara');
    }

    public function testDetailVotesPaginates(): void
    {
        for ($id = 1; $id <= 11; $id++) {
            $this->vote('student', $id, ($id % 3) + 1, 'LOCKED', sprintf('2026-09-01 08:%02d:00', $id));
        }
        for ($id = 1; $id <= 4; $id++) {
            $this->vote('teacher', $id, 1, 'LOCKED', sprintf('2026-09-01 09:%02d:00', $id));
        }
        for ($id = 1; $id <= 16; $id++) {
            $this->db->table('students')->insert([
                'nisn' => sprintf('11%08d', $id), 'name' => 'Siswa Ekstra ' . $id, 'jenis_kelamin' => 'L',
                'kelas' => '9B', 'kodeunik' => '01012011', 'status_aktif' => 1,
            ]);
            $this->vote('student', (int) $this->db->insertID(), 2, 'LOCKED', sprintf('2026-09-01 10:%02d:00', $id));
        }

        // Stage 11: siswa (27 baris) & guru (4 baris) dipaginasi terpisah.
        $page1 = $this->asAdmin()->get('admin/analitik/suara');
        $this->assertStringContainsString('<strong>31</strong> baris suara: 27 siswa &middot; 4 guru.', (string) $page1->response()->getBody());
        $page1->assertSee('Halaman 1 dari 2');
        $page1->assertSee('Siswa Ekstra 16');   // terbaru lebih dulu
        $page1->assertSee('page_siswa=2');
        $page1->assertDontSee('page_guru=');

        $page2 = $this->asAdmin()->get('admin/analitik/suara?page_siswa=2');
        $page2->assertSee('Halaman 2 dari 2');
        $page2->assertSee('Ahmad Fauzan');
        $page2->assertSee('Sudarmanto, S.Pd.'); // tabel guru tetap di halaman 1-nya
    }

    public function testVoterNamesAreEscapedInAdminPages(): void
    {
        $this->db->table('students')->where('id', 1)->update(['name' => '<script>alert(1)</script>']);
        $this->vote('student', 1, 1);

        foreach (['admin/analitik/suara', 'admin/siswa', 'admin/siswa/1', 'admin/buka-kunci?q=script'] as $path) {
            $body = $this->asAdmin()->get($path)->getBody();
            $this->assertStringNotContainsString('<script>alert(1)', $body, $path);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body, $path);
        }
    }

    // ------------------------------------------------------------------
    // jadwal pemilihan
    // ------------------------------------------------------------------

    public function testScheduleUpdateUsesServerTimeZoneAndIsAudited(): void
    {
        $this->scheduleAt('2026-09-25 10:00:00', '2026-09-24 07:00:00', '2026-09-26 12:00:00');

        $result = $this->postAdmin('admin/jadwal', [
            'nama'     => 'Pemilihan OSIS 2026',
            'tahun'    => '2026',
            'start_at' => '2026-10-01T07:00',
            'end_at'   => '2026-10-01T12:30',
        ]);

        $result->assertRedirectTo(site_url('admin/jadwal'));
        $this->seeInDatabase('elections', [
            'id'       => 1,
            'nama'     => 'Pemilihan OSIS 2026',
            'start_at' => '2026-10-01 07:00:00',
            'end_at'   => '2026-10-01 12:30:00',
            'status'   => 'UPCOMING',
        ]);

        $audit = $this->db->table('audit_logs')->where('action', 'SCHEDULE_UPDATE')->get()->getRowArray();
        $this->assertSame('1', (string) $audit['election_id']);
        $this->assertStringContainsString('selesai', $audit['description']);
        $this->assertStringContainsString('2026-10-01 12:30:00', $audit['description']);
        $this->assertStringContainsString('Sedang Berlangsung -> Belum Dibuka', $audit['description']);

        // Pemilih langsung mengikuti jadwal baru (server yang memutuskan).
        $this->withSession(['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true])
            ->get('siswa/coblos')
            ->assertSee('Pencoblosan belum dibuka');
    }

    public function testExtendingOngoingElectionKeepsStatusAndSaysSoInAudit(): void
    {
        $this->scheduleAt('2026-10-01 11:45:00');

        $this->postAdmin('admin/jadwal', [
            'nama'     => 'Pemilihan Ketua dan Wakil Ketua OSIS SMP 1 DAWE',
            'tahun'    => '2026',
            'start_at' => '2026-10-01T07:00',
            'end_at'   => '2026-10-01T13:00',
        ])->assertRedirectTo(site_url('admin/jadwal'));

        $this->seeInDatabase('elections', ['id' => 1, 'end_at' => '2026-10-01 13:00:00', 'status' => 'ONGOING']);
        $audit = $this->db->table('audit_logs')->where('action', 'SCHEDULE_UPDATE')->get()->getRowArray();
        $this->assertSame('Selesai 2026-10-01 12:00:00 -> 2026-10-01 13:00:00. Status tetap: Sedang Berlangsung.', $audit['description']);
    }

    public function testScheduleValidationRejectsEndBeforeStart(): void
    {
        $before = $this->db->table('elections')->where('id', 1)->get()->getRowArray();

        $this->postAdmin('admin/jadwal', [
            'nama' => 'X', 'tahun' => '2026', 'start_at' => '2026-10-01T12:00', 'end_at' => '2026-10-01T07:00',
        ])->assertRedirectTo(site_url('admin/jadwal'));
        $this->assertSame('Waktu selesai harus setelah waktu mulai.', session('errors')['end_at']);

        $this->postAdmin('admin/jadwal', [
            'nama' => '', 'tahun' => 'abcd', 'start_at' => 'kemarin', 'end_at' => '',
        ]);
        $this->assertArrayHasKey('nama', session('errors'));
        $this->assertArrayHasKey('tahun', session('errors'));
        $this->assertArrayHasKey('start_at', session('errors'));

        $this->seeInDatabase('elections', ['id' => 1, 'start_at' => $before['start_at'], 'end_at' => $before['end_at']]);
        $this->assertSame(0, $this->db->table('audit_logs')->countAllResults());
    }

    public function testCloseNowEndsVotingAtServerTime(): void
    {
        $this->scheduleAt('2026-10-01 09:30:15');

        $this->postAdmin('admin/jadwal/tutup')->assertRedirectTo(site_url('admin/jadwal'));

        $this->seeInDatabase('elections', ['id' => 1, 'end_at' => '2026-10-01 09:30:15', 'status' => 'FINISHED']);
        $this->seeInDatabase('audit_logs', ['action' => 'SCHEDULE_UPDATE', 'election_id' => 1]);

        // Voting langsung ditolak server.
        $vote = $this->withSession(['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true])
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->withBodyFormat('json')
            ->post('siswa/coblos', ['candidate_id' => 1]);
        $vote->assertStatus(403);

        // Menutup pemilihan yang sudah selesai ditolak.
        $this->withHeaders([])->withBodyFormat('');
        $this->postAdmin('admin/jadwal/tutup');
        $this->assertStringContainsString('hanya dapat ditutup', (string) session('error'));
    }

    public function testOpenNowStartsUpcomingElection(): void
    {
        $this->scheduleAt('2026-09-30 08:00:00');

        $this->postAdmin('admin/jadwal/buka')->assertRedirectTo(site_url('admin/jadwal'));

        $this->seeInDatabase('elections', ['id' => 1, 'start_at' => '2026-09-30 08:00:00', 'status' => 'ONGOING']);
    }

    public function testElectionCanBeCreatedWhenNoneExists(): void
    {
        $this->db->disableForeignKeyChecks();
        $this->db->table('elections')->truncate();
        $this->db->enableForeignKeyChecks();
        $this->db->enableForeignKeyChecks();
        Time::setTestNow('2026-09-25 10:00:00');

        $this->postAdmin('admin/jadwal', [
            'nama' => 'Pemilihan OSIS 2026', 'tahun' => '2026', 'start_at' => '2026-09-25T09:00', 'end_at' => '2026-09-25T15:00',
        ])->assertRedirectTo(site_url('admin/jadwal'));

        $this->seeInDatabase('elections', ['nama' => 'Pemilihan OSIS 2026', 'status' => 'ONGOING']);
        $this->seeInDatabase('audit_logs', ['action' => 'ELECTION_CREATE']);
        $this->asAdmin()->get('admin/jadwal')->assertSee('Tutup pemilihan sekarang');
    }

    // ------------------------------------------------------------------
    // data siswa & guru
    // ------------------------------------------------------------------

    public function testStudentListFiltersMatchDashboardNumbers(): void
    {
        $this->seedVotes();

        $notVoted = $this->asAdmin()->get('admin/siswa?vote=belum');
        $notVoted->assertStatus(200);
        $notVoted->assertSee('<strong>7</strong> siswa sesuai filter');
        $notVoted->assertDontSee('Ahmad Fauzan');
        $notVoted->assertDontSee('Larasati Putri'); // nonaktif tidak dihitung

        $class = $this->asAdmin()->get('admin/siswa?rombel=7a&jk=P');
        $class->assertSee('Bunga Larasati');
        $class->assertSee('<strong>1</strong> siswa sesuai filter');

        $search = $this->asAdmin()->get('admin/siswa?q=0000000003');
        $search->assertSee('Candra Setiawan');
        $search->assertSee('Sudah');

        $inactive = $this->asAdmin()->get('admin/siswa?status=nonaktif');
        $inactive->assertSee('Larasati Putri');
        $inactive->assertSee('Nonaktif');

        // Kode unik ditampilkan (disamarkan lewat CSS) untuk membantu pemilih yang lupa.
        $this->asAdmin()->get('admin/siswa')->assertSee('class="secret">05062013<');
    }

    public function testTeacherListIsSeparateFromStudents(): void
    {
        $this->seedVotes();

        $result = $this->asAdmin()->get('admin/guru');

        $result->assertStatus(200);
        $result->assertSee('Sudarmanto, S.Pd.');
        $result->assertSee('000000000000000001');
        $result->assertDontSee('Ahmad Fauzan');
        $result->assertDontSee('Rombel</th>');
    }

    public function testVoterDetailShowsVoteHistoryAndUnlockAction(): void
    {
        $old = $this->vote('student', 3, 2, 'UNLOCKED', '2026-09-22 08:00:00');
        $this->vote('student', 3, 1, 'LOCKED', '2026-09-22 09:00:00');

        $result = $this->asAdmin()->get('admin/siswa/3');

        $result->assertStatus(200);
        $result->assertSee('Candra Setiawan');
        $result->assertSee('Sudah memilih');
        $result->assertSee('Arka Wibisana');
        $result->assertSee('Samsung Internet 25');
        $result->assertSee('href="' . site_url('admin/buka-kunci/siswa/3') . '"');
        $result->assertSee('Dibuka');           // baris riwayat UNLOCKED
        $result->assertSee('Bagas Prayoga');    // pilihan lama tetap terlihat di riwayat
        $this->assertGreaterThan(0, $old);

        try {
            $this->asAdmin()->get('admin/guru/99');
            $this->fail('Guru yang tidak ada harus 404');
        } catch (PageNotFoundException $e) {
            $this->assertSame('Guru tidak ditemukan.', $e->getMessage());
        }
    }

    public function testDeactivatingVoterIsAuditedAndBlocksLogin(): void
    {
        $this->postAdmin('admin/siswa/2/status', ['status_aktif' => '0'])
            ->assertRedirectTo(site_url('admin/siswa/2'));

        $this->seeInDatabase('students', ['id' => 2, 'status_aktif' => 0]);
        $this->seeInDatabase('audit_logs', ['action' => 'STUDENT_STATUS']);

        $this->withSession([])->post('siswa/masuk', [csrf_token() => csrf_hash(), 'nisn' => '0000000002', 'kodeunik' => '17092013']);
        $this->assertNull(session('user_type'));

        $this->postAdmin('admin/siswa/2/status', ['status_aktif' => '1']);
        $this->seeInDatabase('students', ['id' => 2, 'status_aktif' => 1]);
    }

    public function testVoterWithHistoryCannotBeDeletedButCleanRecordCan(): void
    {
        $this->vote('teacher', 2, 1);

        $this->postAdmin('admin/guru/2/hapus')->assertRedirectTo(site_url('admin/guru/2'));
        $this->seeInDatabase('teachers', ['id' => 2]);
        $this->assertStringContainsString('riwayat suara', (string) session('error'));

        $this->postAdmin('admin/guru/3/hapus')->assertRedirectTo(site_url('admin/guru'));
        $this->dontSeeInDatabase('teachers', ['id' => 3]);
        $this->seeInDatabase('audit_logs', ['action' => 'TEACHER_DELETE']);
    }

    // ------------------------------------------------------------------
    // unlock & audit
    // ------------------------------------------------------------------

    public function testUnlockSearchFindsStudentsAndTeachersWithVoteStatus(): void
    {
        $this->seedVotes();

        $result = $this->asAdmin()->get('admin/buka-kunci?q=0000000001');

        $result->assertStatus(200);
        $result->assertSee('Ahmad Fauzan');          // NISN 0000000001
        $result->assertSee('Sudarmanto, S.Pd.');     // NIP 000000000000000001
        $result->assertSee('href="' . site_url('admin/buka-kunci/siswa/1') . '"');
        $result->assertSee('href="' . site_url('admin/buka-kunci/guru/1') . '"');
    }

    public function testUnlockFlowRequiresReasonAndConfirmation(): void
    {
        $voteId = $this->vote('student', 1, 2);

        $form = $this->asAdmin()->get('admin/buka-kunci/siswa/1');
        $form->assertStatus(200);
        $form->assertSee('Bagas Prayoga');
        $form->assertSee('Terkunci (LOCKED)');
        $form->assertSee('name="vote_id" value="' . $voteId . '"');
        $form->assertSee('Unlock Hak Suara');

        $this->postAdmin('admin/buka-kunci/siswa/1', ['vote_id' => (string) $voteId, 'reason' => 'salah', 'confirm' => '1'])
            ->assertRedirectTo(site_url('admin/buka-kunci/siswa/1'));
        $this->assertArrayHasKey('reason', session('errors'));

        $this->postAdmin('admin/buka-kunci/siswa/1', ['vote_id' => (string) $voteId, 'reason' => self::REASON])
            ->assertRedirectTo(site_url('admin/buka-kunci/siswa/1'));
        $this->assertArrayHasKey('confirm', session('errors'));

        $this->seeInDatabase('student_votes', ['id' => $voteId, 'status' => 'LOCKED']);
        $this->assertSame(0, $this->db->table('vote_unlock_logs')->countAllResults());
    }

    public function testUnlockKeepsHistoryLogsAuditAndLetsVoterVoteAgain(): void
    {
        $voteId = $this->vote('student', 1, 2);

        $this->postAdmin('admin/buka-kunci/siswa/1', [
            'vote_id'      => (string) $voteId,
            'reason'       => self::REASON,
            'confirm'      => '1',
            'candidate_id' => '3', // diabaikan: admin tidak dapat memilih
        ])->assertRedirectTo(site_url('admin/siswa/1'));

        $this->assertStringContainsString('Hak suara Ahmad Fauzan berhasil dibuka', (string) session('success'));
        $this->seeInDatabase('student_votes', ['id' => $voteId, 'status' => 'UNLOCKED', 'candidate_id' => 2]);
        $this->assertSame(1, $this->db->table('student_votes')->countAllResults());
        $this->seeInDatabase('vote_unlock_logs', ['student_id' => 1, 'student_vote_id' => $voteId, 'admin_id' => 1, 'reason' => self::REASON]);

        // Pemilih kembali "belum memilih" dan memilih sendiri.
        $student = ['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true];
        $this->withSession($student)->get('siswa')->assertSee('Belum memilih');

        // Audit menampilkan waktu, admin, pemilih, jenis pemilih, election, alasan, tindakan.
        $audit = $this->asAdmin()->get('admin/riwayat?action=UNLOCK_VOTE');
        $audit->assertStatus(200);
        $audit->assertSee('Unlock hak suara');
        $audit->assertSee('Administrator OSIS (Dev)');
        $audit->assertSee('Ahmad Fauzan');
        $audit->assertSee('0000000001');
        $audit->assertSee('Siswa');
        $audit->assertSee('Pemilihan Ketua dan Wakil Ketua OSIS SMP 1 DAWE 2026');
        $audit->assertSee(self::REASON);
        $audit->assertSee('<strong>1</strong> catatan');
    }

    public function testUnlockOfStaleVoteIsRejected(): void
    {
        $voteId = $this->vote('teacher', 2, 1);
        // Admin lain baru saja membuka suara ini (Stage 4: UNLOCKED wajib punya unlocked_at).
        $this->db->table('teacher_votes')->where('id', $voteId)->update(['status' => 'UNLOCKED', 'unlocked_at' => Time::now()->toDateTimeString()]);

        $this->postAdmin('admin/buka-kunci/guru/2', ['vote_id' => (string) $voteId, 'reason' => self::REASON, 'confirm' => '1'])
            ->assertRedirectTo(site_url('admin/buka-kunci/guru/2'));

        $this->assertStringContainsString('sudah tidak aktif', (string) session('error'));
        $this->assertSame(0, $this->db->table('vote_unlock_logs')->countAllResults());
    }

    public function testUnlockIsRefusedAfterElectionFinished(): void
    {
        $voteId = $this->vote('student', 1, 1);
        $this->scheduleAt('2026-10-02 08:00:00');

        $this->asAdmin()->get('admin/buka-kunci/siswa/1')->assertSee('Unlock hanya dapat dilakukan saat pemilihan sedang berlangsung');

        $this->postAdmin('admin/buka-kunci/siswa/1', ['vote_id' => (string) $voteId, 'reason' => self::REASON, 'confirm' => '1']);

        $this->assertStringContainsString('hasil akhir', (string) session('error'));
        $this->seeInDatabase('student_votes', ['id' => $voteId, 'status' => 'LOCKED']);
    }

    public function testAuditLogListsAdministrativeActionsNewestFirst(): void
    {
        $this->postAdmin('admin/siswa/4/status', ['status_aktif' => '0']);
        Time::setTestNow(Time::now()->addMinutes(1)->toDateTimeString());
        $this->postAdmin('admin/guru/3/status', ['status_aktif' => '0']);

        $result = $this->asAdmin()->get('admin/riwayat');

        $result->assertStatus(200);
        $table = substr($result->getBody(), (int) strpos($result->getBody(), '<tbody>'));
        $this->assertLessThan(strpos($table, 'Ubah status akun siswa'), strpos($table, 'Ubah status akun guru'));
        $result->assertSee('Dinda Permatasari');
        $result->assertSee('Bambang Hartono');

        $filtered = $this->asAdmin()->get('admin/riwayat?action=TEACHER_STATUS');
        $filtered->assertSee('<strong>1</strong> catatan');
        $filtered->assertDontSee('Dinda Permatasari');

        $searched = $this->asAdmin()->get('admin/riwayat?q=Bambang');
        $searched->assertSee('<strong>1</strong> catatan');
    }
}
