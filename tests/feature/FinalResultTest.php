<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Stage 4: logika hasil akhir & confetti (04-FINAL... bagian 4 dan 5).
 *
 * - hasil akhir hanya aktif bila now >= end_at (jam server);
 * - voting ditolak, dasbor menampilkan keadaan final;
 * - angka final = suara LOCKED pemilih aktif (sama dengan dasbor/live count);
 * - confetti hanya di halaman hasil akhir, hanya bila ada satu pemenang.
 *
 * @internal
 */
final class FinalResultTest extends CIUnitTestCase
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

    private function asAdmin()
    {
        return $this->withSession(['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true]);
    }

    private function vote(string $type, int $voterId, int $candidateId, string $status = 'LOCKED'): void
    {
        $this->db->table($type . '_votes')->insert([
            'election_id'  => 1,
            $type . '_id'  => $voterId,
            'candidate_id' => $candidateId,
            'status'       => $status,
            'voted_at'     => '2026-10-01 08:00:00',
            'unlocked_at'  => $status === 'UNLOCKED' ? '2026-10-01 08:30:00' : null,
        ]);
    }

    /**
     * Pasangan 02 menang: 02 = 3 suara (2 siswa + 1 guru), 01 = 2, 03 = 1.
     */
    private function seedWinnerTwo(): void
    {
        $this->vote('student', 1, 2);
        $this->vote('student', 2, 2);
        $this->vote('teacher', 1, 2);
        $this->vote('student', 3, 1);
        $this->vote('teacher', 2, 1);
        $this->vote('student', 5, 3);
    }

    private function scheduleAt(string $now, string $start = '2026-10-01 07:00:00', string $end = '2026-10-01 12:00:00'): void
    {
        $this->db->table('elections')->where('id', 1)->update(['start_at' => $start, 'end_at' => $end]);
        Time::setTestNow($now);
    }

    private function json($result): array
    {
        return json_decode($result->getJSON(), true);
    }

    // ------------------------------------------------------------------
    // akses
    // ------------------------------------------------------------------

    public function testResultsPageIsAdminOnly(): void
    {
        $this->scheduleAt('2026-10-01 13:00:00');

        foreach ([[], ['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true], ['user_type' => 'teacher', 'teacher_id' => 1, 'isLoggedIn' => true]] as $session) {
            $this->withSession($session)->get('admin/hasil')->assertRedirectTo(site_url('admin/masuk'));
        }

        $this->withSession(['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true])
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get('admin/hasil')
            ->assertStatus(401);
    }

    // ------------------------------------------------------------------
    // hanya aktif bila benar-benar selesai
    // ------------------------------------------------------------------

    public function testResultsStayLockedWhileOngoingEvenOneSecondBeforeEnd(): void
    {
        $this->seedWinnerTwo();
        $this->scheduleAt('2026-10-01 11:59:59');

        $result = $this->asAdmin()->get('admin/hasil');

        $result->assertStatus(200);
        $result->assertSee('Hasil akhir belum tersedia');
        $result->assertSee('Sedang Berlangsung');
        $result->assertSee('data-countdown');
        $result->assertDontSee('Pasangan terpilih');
        $result->assertDontSee('class="standings"');
        $result->assertDontSee('data-confetti');
        $result->assertDontSee('assets/js/confetti.js');
    }

    public function testResultsStayLockedBeforeStartAndWithoutElection(): void
    {
        $this->scheduleAt('2026-10-01 06:00:00');
        $upcoming = $this->asAdmin()->get('admin/hasil');
        $upcoming->assertSee('Belum Dibuka');
        $upcoming->assertDontSee('data-confetti');

        $this->db->disableForeignKeyChecks();
        $this->db->table('elections')->truncate();
        $this->db->enableForeignKeyChecks();
        $none = $this->asAdmin()->get('admin/hasil');
        $none->assertSee('Pemilihan belum dijadwalkan');
        $none->assertDontSee('data-confetti');
    }

    public function testFinalResultAtEndTimeShowsWinnerRankingTotalsAndConfetti(): void
    {
        $this->seedWinnerTwo();
        $this->scheduleAt('2026-10-01 12:00:00'); // tepat end_at = FINISHED

        $result = $this->asAdmin()->get('admin/hasil');
        $body   = (string) $result->response()->getBody();

        $result->assertStatus(200);
        $result->assertSee('Pasangan terpilih');
        $result->assertSee('Bagas Prayoga');
        $result->assertSee('Citra Maheswari');
        $result->assertSee('unggul 1 suara dari peringkat 2');
        $result->assertSee('data-confetti');
        $result->assertSee('assets/js/confetti.js');
        $result->assertDontSee('Hasil akhir belum tersedia');

        // Peringkat: 02 (3) -> 01 (2) -> 03 (1).
        $positions = array_map(
            static fn (string $no): int|false => strpos($body, 'standing__no" aria-hidden="true">' . $no . '</span>'),
            ['02', '01', '03'],
        );
        $this->assertNotContains(false, $positions);
        $this->assertLessThan($positions[1], $positions[0]);
        $this->assertLessThan($positions[2], $positions[1]);

        // Total & persentase sama dengan definisi AnalyticsService (6 suara sah).
        $this->assertStringContainsString('<dt>Suara sah</dt><dd>6</dd>', $body);
        $this->assertStringContainsString('50,0%', $body); // 3 dari 6
        $this->assertStringContainsString('33,3%', $body); // 2 dari 6

        // Kunci confetti terikat pada election & waktu selesai, warna = aksen pasangan (pemenang pertama).
        $this->assertMatchesRegularExpression('/data-confetti-key="1-\d+"/', $body);
        $this->assertStringContainsString('data-confetti-colors="&#x23;2F5D50', $body);
    }

    public function testWinnerStageUsesTheCandidateSpecificTheme(): void
    {
        $this->seedWinnerTwo();
        $this->db->table('candidates')->where('id', 2)->update([
            'theme_accent' => '#7A1F5C',
            'theme_asset'  => json_encode(['hero' => 'c02-hero-0123456789abcdef.webp']),
        ]);
        $this->scheduleAt('2026-10-01 13:00:00');

        $result = $this->asAdmin()->get('admin/hasil');

        // Stage 16: satu susunan untuk pemenang mana pun, tanpa pola.
        $result->assertSee('<section class="victor" style="--accent: #7A1F5C;');
        $result->assertDontSee('victor--');
        $result->assertDontSee('victor__pattern');
        $result->assertSee(base_url('uploads/candidates/c02-hero-0123456789abcdef.webp'));
        $result->assertSee('data-confetti-colors="#7A1F5C');
    }

    public function testTieHasNoWinnerAndNoConfetti(): void
    {
        $this->vote('student', 1, 1);
        $this->vote('student', 2, 2);
        $this->vote('teacher', 1, 3);
        $this->scheduleAt('2026-10-01 13:00:00');

        $result = $this->asAdmin()->get('admin/hasil');

        $result->assertSee('Perolehan suara tertinggi sama');
        $result->assertSee('Pasangan 01 dan Pasangan 02 dan Pasangan 03');
        $result->assertDontSee('Pasangan terpilih');
        $result->assertDontSee('data-confetti');
        $result->assertDontSee('assets/js/confetti.js');
    }

    public function testNoValidVotesHasNoWinnerAndNoConfetti(): void
    {
        $this->vote('student', 1, 2, 'UNLOCKED'); // riwayat tidak dihitung
        $this->scheduleAt('2026-10-01 13:00:00');

        $result = $this->asAdmin()->get('admin/hasil');

        $result->assertSee('Tidak ada suara sah');
        $result->assertDontSee('Pasangan terpilih');
        $result->assertDontSee('data-confetti');
    }

    public function testOnlyLockedVotesOfActiveVotersAreCounted(): void
    {
        // 01: 2 suara sah; 02: 1 sah + 1 riwayat UNLOCKED + 1 suara pemilih nonaktif (12).
        $this->vote('student', 1, 1);
        $this->vote('teacher', 1, 1);
        $this->vote('student', 2, 2);
        $this->vote('student', 3, 2, 'UNLOCKED');
        $this->vote('student', 12, 2); // siswa 12 nonaktif (seeder)
        $this->scheduleAt('2026-10-01 13:00:00');

        $result = $this->asAdmin()->get('admin/hasil');
        $body   = (string) $result->response()->getBody();

        $result->assertSee('Arka Wibisana'); // 01 menang 2-1
        $this->assertStringContainsString('<dt>Suara sah</dt><dd>3</dd>', $body);
        $this->assertStringContainsString('unggul 1 suara dari peringkat 2', $body);

        $snapshot = service('analytics')->snapshot(model(\App\Models\ElectionModel::class)->getCurrentElection());
        $this->assertSame(3, $snapshot['summary']['all']['voted']);
    }

    public function testInactiveCandidateWithVotesStaysInFinalResult(): void
    {
        $this->seedWinnerTwo();
        $this->db->table('candidates')->where('id', 3)->update(['status_aktif' => 0]);
        $this->scheduleAt('2026-10-01 13:00:00');

        $result = $this->asAdmin()->get('admin/hasil');

        $result->assertSee('Dewi Anggraini');
        $result->assertSee('nonaktif');
    }

    // ------------------------------------------------------------------
    // dasbor & endpoint lain saat selesai
    // ------------------------------------------------------------------

    public function testDashboardShowsFinalStateWithoutConfetti(): void
    {
        $this->seedWinnerTwo();
        $this->scheduleAt('2026-10-01 12:30:00');

        $dashboard = $this->asAdmin()->get('admin');

        $dashboard->assertSee('Pemilihan selesai');
        $dashboard->assertSee('Pasangan 02 terpilih');
        $dashboard->assertSee('href="' . site_url('admin/hasil') . '"');
        $dashboard->assertDontSee('data-confetti');
        $dashboard->assertDontSee('assets/js/confetti.js');

        foreach (['admin/analitik', 'admin/analitik/suara'] as $path) {
            $page = $this->asAdmin()->get($path);
            $page->assertDontSee('data-confetti');
            $page->assertDontSee('assets/js/confetti.js');
        }
    }

    public function testDashboardHasNoFinalStateWhileOngoing(): void
    {
        $this->seedWinnerTwo();
        $this->scheduleAt('2026-10-01 10:00:00');

        $dashboard = $this->asAdmin()->get('admin');

        $dashboard->assertSee('Hasil sementara');
        $dashboard->assertDontSee('final-banner');
        $dashboard->assertDontSee('data-confetti');
    }

    public function testVotingEndpointRejectsAfterEndAndResultIsUnchanged(): void
    {
        $this->seedWinnerTwo();
        $this->scheduleAt('2026-10-01 12:00:00');

        $vote = $this->withSession(['user_type' => 'student', 'student_id' => 4, 'isLoggedIn' => true])
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->withBodyFormat('json')
            ->post('siswa/coblos', ['candidate_id' => 1]);

        $vote->assertStatus(403);
        $this->assertSame('voting_closed', $this->json($vote)['status']);
        $this->assertSame(0, $this->db->table('student_votes')->where('student_id', 4)->countAllResults());
    }

    public function testFinalNumbersEqualLiveCountAndPollingStops(): void
    {
        $this->seedWinnerTwo();
        $this->scheduleAt('2026-10-01 12:00:00');

        $live = $this->json($this->asAdmin()->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])->get('admin/hitung-suara'));

        $this->assertSame('FINISHED', $live['election']['status']);
        $this->assertSame(0, $live['poll']['interval']);
        $this->assertSame(6, $live['summary']['all']['voted']);

        $votes = array_column($live['candidates'], 'votes', 'number');
        $this->assertSame([1 => 2, 2 => 3, 3 => 1], $votes);
    }

    public function testResultsNavigationIsListedForAdmin(): void
    {
        $this->asAdmin()->get('admin')->assertSee('href="' . site_url('admin/hasil') . '"');
    }
}
