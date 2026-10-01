<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Services\VoterType;
use CodeIgniter\I18n\Time;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Stage 2: pengalaman voting siswa & guru lewat HTTP (route, filter, CSRF,
 * controller, view) sampai ke database.
 *
 * Checklist 02-STUDENT-TEACHER-VOTING.md "TEST STAGE 2" dipetakan di
 * STAGE2-NOTES.md bagian TESTING.
 *
 * @internal
 */
final class VotingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private const ANDROID_SAMSUNG = 'Mozilla/5.0 (Linux; Android 13; SM-A145F) AppleWebKit/537.36 '
        . '(KHTML, like Gecko) SamsungBrowser/25.0 Chrome/121.0.0.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        // Kuota throttle submit tersimpan di cache. Service throttler bersifat
        // shared antar-test dan bisa memegang MockCache test sebelumnya (dengan
        // jam uji Time::setTestNow()), jadi dibuat ulang setiap test.
        Services::resetSingle('throttler');
        cache()->clean();
        service('superglobals')->setServer('HTTP_USER_AGENT', self::ANDROID_SAMSUNG);
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // helper
    // ------------------------------------------------------------------

    private function student(int $id = 1): array
    {
        return ['user_type' => 'student', 'student_id' => $id, 'isLoggedIn' => true];
    }

    private function teacher(int $id = 1): array
    {
        return ['user_type' => 'teacher', 'teacher_id' => $id, 'isLoggedIn' => true];
    }

    /**
     * POST JSON seperti ballot.js (header App.jsonHeaders()).
     */
    private function voteJson(string $role, array $session, mixed $candidateId, array $extra = [])
    {
        return $this->withSession($session)
            ->withHeaders([
                'X-CSRF-TOKEN'     => csrf_hash(),
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept'           => 'application/json',
            ])
            ->withBodyFormat('json')
            ->post(VoterType::from($role)->path('coblos'), ['candidate_id' => $candidateId] + $extra);
    }

    /**
     * POST form biasa seperti halaman konfirmasi tanpa JavaScript.
     */
    private function voteForm(string $role, array $session, mixed $candidateId)
    {
        return $this->withSession($session)
            ->withHeaders([])
            ->withBodyFormat('')
            ->post(VoterType::from($role)->path('coblos'), [csrf_token() => csrf_hash(), 'candidate_id' => (string) $candidateId]);
    }

    private function lockedVotes(string $table, string $voterColumn, int $voterId): array
    {
        return $this->db->table($table)
            ->where($voterColumn, $voterId)
            ->where('status', 'LOCKED')
            ->get()
            ->getResultArray();
    }

    /**
     * Jadwal tetap + jam server palsu agar batas waktu dapat diuji tepat.
     */
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
    // 1-2. login siswa / guru -> dasbor pemilih
    // ------------------------------------------------------------------

    public function testStudentLoginLeadsToDashboardWithIdentityAndVoteCta(): void
    {
        $this->post('siswa/masuk', [csrf_token() => csrf_hash(), 'nisn' => '0000000003', 'kodeunik' => '01032013'])
            ->assertRedirectTo(site_url('siswa'));

        $result = $this->withSession()->get('siswa');

        $result->assertStatus(200);
        $result->assertSee('Candra Setiawan');
        $result->assertSee('7B');
        $result->assertSee('Nomor Absen');
        $result->assertSee('Belum memilih');
        $result->assertSee('href="' . site_url('siswa/coblos') . '"');
    }

    public function testTeacherLoginLeadsToTeacherDashboardWithVoteCta(): void
    {
        $this->post('guru/masuk', [csrf_token() => csrf_hash(), 'nip' => '000000000000000004', 'kodeunik' => '01061992'])
            ->assertRedirectTo(site_url('guru'));

        $result = $this->withSession()->get('guru');

        $result->assertStatus(200);
        $result->assertSee('Siti Nur Aini, S.Pd.');
        $result->assertSee('Belum memilih');
        $result->assertSee('href="' . site_url('guru/coblos') . '"');
        $result->assertDontSee('Nomor Absen');
    }

    // ------------------------------------------------------------------
    // 3-5. kandidat, tema per pasangan, markup visi-misi interaktif
    // Stage 15: visi-misi lengkap & tema per layout ada di halaman profil
    // paslon (siswa/paslon/0X). Stage 22: pembuka (coblos), Sekilas paslon
    // (sekilas-paslon), dan surat suara (bilik-suara) masing-masing halaman sendiri.
    // ------------------------------------------------------------------

    public function testBallotPageRendersAllActiveCandidatesWithVisiMisi(): void
    {
        $result = $this->withSession($this->student())->get('siswa/sekilas-paslon');

        $result->assertStatus(200);
        foreach (['Arka Wibisana', 'Naya Kirana', 'Bagas Prayoga', 'Citra Maheswari', 'Dewi Anggraini', 'Fajar Nugroho'] as $name) {
            $result->assertSee($name);
        }
        $result->assertSee('Mewujudkan OSIS yang aktif, terbuka, dan dekat dengan seluruh siswa.');

        $booth = $this->withSession($this->student())->get('siswa/bilik-suara');
        $booth->assertStatus(200);
        $booth->assertSee('Coblos Pasangan 01');
        $booth->assertSee('Coblos Pasangan 03');
        $booth->assertSee('href="' . site_url('siswa/coblos/yakin/2') . '"');

        // Misi lengkap di profil: penomoran manual "1." dibuang, diganti nomor editorial.
        $profile = $this->withSession($this->student())->get('siswa/paslon/01');
        $profile->assertStatus(200);
        $profile->assertSee('Menghidupkan kembali kegiatan ekstrakurikuler.');
        $profile->assertDontSee('1. Menghidupkan');
    }

    /**
     * Stage 7: "Sekilas paslon" membandingkan semua pasangan. Stage 15:
     * profil lengkap di halaman profil paslon. Stage 22: halaman sendiri
     * (sekilas-paslon) tanpa tombol "Pilih 0X": satu tombol "Profil Lengkap
     * Paslon 0X" per kartu; kotak coblos-0X ada di bilik suara.
     */
    public function testLineupComparesPairsAndLinksToEachProfile(): void
    {
        $result = $this->withSession($this->student())->get('siswa/sekilas-paslon');
        $html   = (string) $result->response()->getBody();

        $result->assertSee('Sekilas paslon');
        // Stage 8: tanpa sapaan "Halo, {nama} · Bilik suara siswa".
        $result->assertDontSee('Halo, Ahmad Fauzan');
        foreach (['01', '02', '03'] as $label) {
            $result->assertSee('href="' . site_url('siswa/paslon/' . $label) . '"');
            $result->assertSee('Profil Lengkap Paslon ' . $label);
        }
        $this->assertSame(3, substr_count($html, 'class="pair-card"'));
        $result->assertDontSee('data-pick');
        $result->assertDontSee('#coblos-');
        // Stage 15: tidak ada lagi bab pasangan & navigasi bab.
        $result->assertDontSee('id="pasangan-01"');
        $result->assertDontSee('data-chapter');

        $booth = $this->withSession($this->student())->get('siswa/bilik-suara');
        foreach (['01', '02', '03'] as $label) {
            $booth->assertSee('id="coblos-' . $label . '"');
        }
    }

    public function testLineupHasNoPickLinksWhenVotingIsClosed(): void
    {
        foreach (['2026-09-30 08:00:00', '2026-10-02 00:00:00'] as $now) {
            $this->scheduleAt($now);

            $result = $this->withSession($this->student())->get('siswa/sekilas-paslon');
            $result->assertSee('Sekilas paslon');
            $result->assertSee('href="' . site_url('siswa/paslon/01') . '"');
            $result->assertDontSee('data-pick');
            $result->assertDontSee('#coblos-');
        }
    }

    public function testEachCandidateHasItsOwnThemeWithOneLayout(): void
    {
        $result = $this->withSession($this->student())->get('siswa/sekilas-paslon');

        $result->assertSee('--accent: #C4432B;');
        $result->assertSee('--accent: #2F5D50;');
        $result->assertSee('--accent: #1B3A6B;');
        // Tanpa foto: monogram inisial yang tetap punya nama untuk pembaca layar.
        $result->assertSee('aria-label="Arka Wibisana, calon ketua pasangan 01 (foto belum tersedia)"');

        // Stage 16: monogram polos (tanpa pola) dan satu tampilan profil untuk semua.
        $result->assertDontSee('portrait__pattern');
        foreach (['01', '02', '03'] as $label) {
            $profile = $this->withSession($this->student())->get('siswa/paslon/' . $label);
            $profile->assertSee('<article class="paslon" style="--accent: ');
            $profile->assertDontSee('paslon--');
            $profile->assertDontSee('pattern');
        }
    }

    public function testUploadedThemeAssetsAreUsedByTheCandidatePage(): void
    {
        model(\App\Models\CandidateModel::class)->update(1, [
            'foto_ketua'   => 'ketua-01.webp',
            'foto_wakil'   => 'wakil-01.webp',
            'theme_asset'  => ['hero' => 'hero-01.webp', 'banner' => 'banner-01.webp', 'poster' => 'poster-01.webp', 'closing' => 'closing-01.webp'],
            'theme_accent' => '#f2d14b',
        ]);

        $result = $this->withSession($this->student())->get('siswa/paslon/01');

        foreach (['ketua-01.webp', 'wakil-01.webp', 'banner-01.webp', 'poster-01.webp', 'closing-01.webp'] as $file) {
            $result->assertSee(base_url('uploads/candidates/' . $file));
        }
        // Foto berdua (hero) khusus beranda & hasil akhir, tidak di profil.
        $result->assertDontSee('hero-01.webp');
        $result->assertSee('Foto Arka Wibisana, calon ketua pasangan 01');
        $result->assertSee('Poster kampanye pasangan 01');
        $result->assertSee('Gambar pembuka pasangan 01');
        // Aksen terang: teks di atas aksen memakai ink, teks aksen di atas kertas jatuh ke ink.
        $result->assertSee('--accent: #F2D14B; --accent-ink: #15141A; --accent-text: #15141A; --accent-soft: #FDF9E9;');

        // Sekilas paslon memakai potret yang sama.
        $booth = $this->withSession($this->student())->get('siswa/sekilas-paslon');
        $booth->assertSee(base_url('uploads/candidates/ketua-01.webp'));
        $booth->assertSee(base_url('uploads/candidates/wakil-01.webp'));
    }

    public function testCandidateTextIsEscaped(): void
    {
        $this->db->table('candidates')->where('id', 1)->update([
            'nama_ketua' => '<script>alert(1)</script>',
            'visi'       => '"><img src=x onerror=alert(1)>',
        ]);

        $result = $this->withSession($this->student())->get('siswa/sekilas-paslon');

        $body = $result->getBody();
        $this->assertStringNotContainsString('<script>alert(1)', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
        $this->assertStringContainsString('&gt;&lt;img src=x onerror=alert(1)&gt;', $body);
    }

    public function testVisiMisiInteractionHooksAreRendered(): void
    {
        $result = $this->withSession($this->student())->get('siswa/paslon/01');
        $body   = $result->getBody();

        // Stage 16: visi statis (tanpa kata menyala) dan tanpa parallax pembuka.
        $this->assertSame(0, substr_count($body, 'data-px-words'));
        $this->assertSame(3, substr_count($body, 'class="px-list__item"'));
        $result->assertDontSee('data-px-parallax');
        $result->assertSee('data-px-nav');
        $result->assertSee('data-px-reveal');
        $result->assertSee('assets/js/paslon.js');
        $result->assertSee('assets/css/paslon.css');
        $result->assertSee('assets/css/voting.css');

        // Stage 22: pembuka memuat skrip langkah memilih (efek mengetik).
        $this->withSession($this->student())->get('siswa/coblos')->assertSee('assets/js/booth-steps.js');
    }

    // ------------------------------------------------------------------
    // 6-7. voting interaktif: 3D dimuat malas, fallback & jalur tanpa JS
    // ------------------------------------------------------------------

    public function testBallotLoadsInteractiveScriptsWithLazyWebglAndFallbacks(): void
    {
        $result = $this->withSession($this->student())->get('siswa/bilik-suara');

        $result->assertSee('assets/js/ballot.js');
        // WebGL tidak dimuat langsung; ballot.js memuatnya malas bila mode 3D.
        $result->assertDontSee('<script src="' . base_url('assets/js/nail-webgl.js'));
        $result->assertSee('data-webgl-src="' . asset_url('assets/js/nail-webgl.js') . '"');
        // Fallback 2D (SVG + CSS) dan kanvas 3D sama-sama tersedia.
        $result->assertSee('data-nail2d');
        $result->assertSee('data-nail3d');
        $result->assertSee('data-nail-grip');
        // Stage 8: efek 3D selalu nyala, tombol pengalih "Efek 3D" dihapus.
        $result->assertDontSee('data-fx-toggle');
        $result->assertSee('id="vote-confirm"');
    }

    // ------------------------------------------------------------------
    // 8. konfirmasi
    // ------------------------------------------------------------------

    public function testConfirmPageShowsPairPhotoNumberNamesAndLockWarning(): void
    {
        $result = $this->withSession($this->student())->get('siswa/coblos/yakin/2');

        $result->assertStatus(200);
        $result->assertSee('Pasangan 02');
        $result->assertSee('Bagas Prayoga');
        $result->assertSee('Citra Maheswari');
        $result->assertSee('dikunci');
        $result->assertSee('KONFIRMASI PILIHAN');
        $result->assertSee('name="candidate_id" value="2"');
        $result->assertSee('portrait__mono');
    }

    public function testConfirmPageRejectsUnknownOrInactiveCandidate(): void
    {
        $this->db->table('candidates')->where('id', 3)->update(['status_aktif' => 0]);

        $this->withSession($this->student())->get('siswa/coblos/yakin/3')
            ->assertRedirectTo(site_url('siswa/bilik-suara'));
        $this->withSession($this->student())->get('siswa/coblos/yakin/99')
            ->assertRedirectTo(site_url('siswa/bilik-suara'));
    }

    // ------------------------------------------------------------------
    // 9. transaction: data yang disimpan
    // ------------------------------------------------------------------

    public function testJsonVoteIsStoredLockedWithServerSideMetadata(): void
    {
        $this->scheduleAt('2026-10-01 09:15:30');

        // student_id di body harus diabaikan: identitas hanya dari sesi.
        $result = $this->voteJson('student', $this->student(1), 2, ['student_id' => 5, 'status' => 'UNLOCKED']);

        $result->assertStatus(200);
        $data = $this->json($result);
        $this->assertSame('ok', $data['status']);
        $this->assertSame('SUARA BERHASIL DISIMPAN', $data['title']);
        $this->assertSame('Hak suara Anda telah dikunci.', $data['detail']);
        $this->assertSame('02', $data['vote']['number']);
        $this->assertSame(site_url('siswa/pilihanku'), $data['redirect']);

        $rows = $this->lockedVotes('student_votes', 'student_id', 1);
        $this->assertCount(1, $rows);
        $this->assertSame('1', (string) $rows[0]['election_id']);
        $this->assertSame('2', (string) $rows[0]['candidate_id']);
        $this->assertSame('2026-10-01 09:15:30', $rows[0]['voted_at']);
        $this->assertSame('1', (string) $rows[0]['active_lock']);
        $this->assertNull($rows[0]['unlocked_at']);
        $this->assertSame('HP / Android 13 / Samsung Galaxy A14', $rows[0]['device_info']);
        $this->assertSame('Samsung Internet 25', $rows[0]['browser_info']);

        $this->assertCount(0, $this->lockedVotes('student_votes', 'student_id', 5));
        $this->assertSame(0, $this->db->table('teacher_votes')->countAllResults());
    }

    public function testFormVoteWithoutJavascriptRedirectsToOwnChoice(): void
    {
        $result = $this->voteForm('student', $this->student(2), 3);

        $result->assertRedirectTo(site_url('siswa/pilihanku'));
        $result->assertSessionHas('success');
        $this->assertCount(1, $this->lockedVotes('student_votes', 'student_id', 2));
    }

    public function testTeacherVoteGoesToTeacherVotesOnly(): void
    {
        $this->voteJson('teacher', $this->teacher(1), 1)->assertStatus(200);

        $this->assertCount(1, $this->lockedVotes('teacher_votes', 'teacher_id', 1));
        $this->assertSame(0, $this->db->table('student_votes')->countAllResults());

        // Siswa dengan id numerik yang sama tetap punya hak suara sendiri.
        $this->voteJson('student', $this->student(1), 3)->assertStatus(200);
        $this->assertCount(1, $this->lockedVotes('student_votes', 'student_id', 1));
    }

    // ------------------------------------------------------------------
    // 10. duplicate prevention
    // ------------------------------------------------------------------

    public function testSecondVoteIsRejectedAndFirstChoiceKept(): void
    {
        $this->voteJson('student', $this->student(1), 2)->assertStatus(200);

        $second = $this->voteJson('student', $this->student(1), 3);

        $second->assertStatus(409);
        $data = $this->json($second);
        $this->assertSame('already_voted', $data['status']);
        $this->assertSame(site_url('siswa/pilihanku'), $data['redirect']);

        $rows = $this->lockedVotes('student_votes', 'student_id', 1);
        $this->assertCount(1, $rows);
        $this->assertSame('2', (string) $rows[0]['candidate_id']);
    }

    public function testDuplicateFormPostRedirectsToOwnChoice(): void
    {
        $this->voteForm('teacher', $this->teacher(2), 1);
        $result = $this->voteForm('teacher', $this->teacher(2), 1);

        $result->assertRedirectTo(site_url('guru/pilihanku'));
        $result->assertSessionHas('error');
        $this->assertCount(1, $this->lockedVotes('teacher_votes', 'teacher_id', 2));
    }

    public function testSubmitIsThrottledPerVoter(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->voteJson('student', $this->student(1), 1);
        }

        $result = $this->voteJson('student', $this->student(1), 1);

        $result->assertStatus(429);
        $this->assertSame('throttled', $this->json($result)['status']);
        $this->assertCount(1, $this->lockedVotes('student_votes', 'student_id', 1));

        // Pemilih lain tidak ikut terkena kuota.
        $this->voteJson('student', $this->student(2), 1)->assertStatus(200);
    }

    public function testVoteRequiresCsrfToken(): void
    {
        $this->expectException(SecurityException::class);

        $this->withSession($this->student())->post('siswa/coblos', ['candidate_id' => '1']);
    }

    // ------------------------------------------------------------------
    // 11-13. lock, login ulang, hanya pilihan sendiri
    // ------------------------------------------------------------------

    public function testAfterVotingBallotAndConfirmRedirectToOwnChoice(): void
    {
        $this->voteJson('student', $this->student(1), 2)->assertStatus(200);

        $this->withSession($this->student(1))->get('siswa/coblos')
            ->assertRedirectTo(site_url('siswa/pilihanku'));
        $this->withSession($this->student(1))->get('siswa/coblos/yakin/1')
            ->assertRedirectTo(site_url('siswa/pilihanku'));
    }

    public function testReloginShowsLockedOwnChoiceWithoutVotingCta(): void
    {
        $this->post('siswa/masuk', [csrf_token() => csrf_hash(), 'nisn' => '0000000003', 'kodeunik' => '01032013']);
        $this->withSession()->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'X-Requested-With' => 'XMLHttpRequest'])
            ->withBodyFormat('json')
            ->post('siswa/coblos', ['candidate_id' => 2])
            ->assertStatus(200);

        $this->withSession()->withHeaders([])->withBodyFormat('')
            ->post('siswa/keluar', [csrf_token() => csrf_hash()]);
        $this->assertNull(session('user_type'));

        $this->post('siswa/masuk', [csrf_token() => csrf_hash(), 'nisn' => '0000000003', 'kodeunik' => '01032013'])
            ->assertRedirectTo(site_url('siswa'));

        $dashboard = $this->withSession()->get('siswa');
        $dashboard->assertSee('Sudah memilih');
        $dashboard->assertSee('Terkunci');
        $dashboard->assertSee('Bagas Prayoga');
        $dashboard->assertSee('href="' . site_url('siswa/pilihanku') . '"');
        $dashboard->assertDontSee('href="' . site_url('siswa/coblos') . '"');
        $dashboard->assertDontSee('Lihat kandidat');

        $choice = $this->withSession()->get('siswa/pilihanku');
        $choice->assertStatus(200);
        $choice->assertSee('Kamu memilih');
        $choice->assertSee('Pasangan 02');
        $choice->assertSee('Candra Setiawan');
        $choice->assertSee('Waktu memilih');
        $choice->assertSee('Suara terkunci');
        $choice->assertDontSee('KONFIRMASI PILIHAN');
        $choice->assertDontSee('data-coblos');
    }

    public function testOwnChoicePageShowsNoOtherCandidatesOrOtherVoters(): void
    {
        $this->voteJson('student', $this->student(1), 2)->assertStatus(200);
        $this->voteJson('student', $this->student(2), 3)->assertStatus(200);
        $this->voteJson('teacher', $this->teacher(1), 1)->assertStatus(200);

        $result = $this->withSession($this->student(1))->get('siswa/pilihanku');

        $result->assertSee('Bagas Prayoga');
        $result->assertDontSee('Arka Wibisana');
        $result->assertDontSee('Dewi Anggraini');
        $result->assertDontSee('Bunga Larasati');
        $result->assertDontSee('suara masuk');
    }

    public function testOwnChoicePageWithoutVoteRedirectsToDashboard(): void
    {
        $this->withSession($this->student(4))->get('siswa/pilihanku')
            ->assertRedirectTo(site_url('siswa'));
    }

    public function testUnlockedHistoryAllowsVotingAgain(): void
    {
        $this->voteJson('student', $this->student(1), 1)->assertStatus(200);

        // Simulasi unlock admin (Stage 3): baris lama menjadi UNLOCKED, tidak dihapus.
        $this->db->table('student_votes')->where('student_id', 1)
            ->update(['status' => 'UNLOCKED', 'unlocked_at' => Time::now()->toDateTimeString()]);

        $this->withSession($this->student(1))->get('siswa')->assertSee('Belum memilih');

        $this->voteJson('student', $this->student(1), 3)->assertStatus(200);

        $this->assertSame(2, $this->db->table('student_votes')->where('student_id', 1)->countAllResults());
        $rows = $this->lockedVotes('student_votes', 'student_id', 1);
        $this->assertCount(1, $rows);
        $this->assertSame('3', (string) $rows[0]['candidate_id']);
    }

    // ------------------------------------------------------------------
    // 14. schedule enforcement (waktu server)
    // ------------------------------------------------------------------

    public function testVotingBeforeStartIsRejected(): void
    {
        $this->scheduleAt('2026-10-01 06:59:59');

        $result = $this->voteJson('student', $this->student(1), 1);

        $result->assertStatus(403);
        $data = $this->json($result);
        $this->assertSame('voting_closed', $data['status']);
        $this->assertSame('UPCOMING', $data['election_status']);
        $this->assertSame(0, $this->db->table('student_votes')->countAllResults());
    }

    public function testVotingAtEndTimeIsRejectedButOneSecondBeforeIsAccepted(): void
    {
        $this->scheduleAt('2026-10-01 11:59:59');
        $this->voteJson('student', $this->student(1), 1)->assertStatus(200);

        Time::setTestNow('2026-10-01 12:00:00');
        $result = $this->voteJson('teacher', $this->teacher(1), 1);

        $result->assertStatus(403);
        $this->assertSame('FINISHED', $this->json($result)['election_status']);
        $this->assertSame(0, $this->db->table('teacher_votes')->countAllResults());
    }

    public function testUpcomingBallotShowsCandidatesWithoutVotingControls(): void
    {
        $this->scheduleAt('2026-09-30 08:00:00');

        $this->withSession($this->student())->get('siswa/coblos')
            ->assertSee('Pencoblosan belum dibuka');

        $result = $this->withSession($this->student())->get('siswa/bilik-suara');

        $result->assertStatus(200);
        $result->assertSee('Arka Wibisana');
        $result->assertSee('Surat suara dapat dicoblos mulai');
        $result->assertDontSee('data-coblos');
        $result->assertDontSee('assets/js/ballot.js');
        $result->assertDontSee('id="vote-confirm"');

        $this->withSession($this->student())->get('siswa/coblos/yakin/1')
            ->assertRedirectTo(site_url('siswa/bilik-suara'));

        $this->withSession($this->student())->get('siswa')
            ->assertSee('Pencoblosan dibuka pada');
    }

    public function testFinishedElectionHidesVotingAndShowsNotVoted(): void
    {
        $this->scheduleAt('2026-10-02 00:00:00');

        $this->withSession($this->teacher(2))->get('guru')
            ->assertSee('Tidak memberikan suara');

        $ballot = $this->withSession($this->teacher(2))->get('guru/coblos');
        $ballot->assertSee('Pemilihan telah ditutup');
        $ballot->assertDontSee('data-coblos');
    }

    // ------------------------------------------------------------------
    // keamanan route & input
    // ------------------------------------------------------------------

    public function testStudentCannotUseTeacherVotingAndViceVersa(): void
    {
        $asStudent = $this->voteJson('teacher', $this->student(1), 1);
        $asStudent->assertStatus(401);
        $this->assertSame(site_url('guru/masuk'), $this->json($asStudent)['redirect']);

        $this->voteJson('student', $this->teacher(1), 1)->assertStatus(401);

        // Sesi siswa yang disisipi teacher_id tetap bukan guru.
        $forged = $this->student(1) + ['teacher_id' => 1];
        $this->voteJson('teacher', $forged, 1)->assertStatus(401);

        $this->assertSame(0, $this->db->table('teacher_votes')->countAllResults());
        $this->assertSame(0, $this->db->table('student_votes')->countAllResults());
    }

    public function testGuestAndDeactivatedVoterCannotVote(): void
    {
        $this->voteJson('student', [], 1)->assertStatus(401);

        $this->db->table('students')->where('id', 1)->update(['status_aktif' => 0]);
        $this->voteJson('student', $this->student(1), 1)->assertStatus(401);

        $this->assertSame(0, $this->db->table('student_votes')->countAllResults());
    }

    public function testInvalidCandidateInputIsRejected(): void
    {
        $this->db->table('candidates')->where('id', 3)->update(['status_aktif' => 0]);

        foreach (['abc', '', '-1', '1.5', 99, 3, ['1']] as $candidate) {
            $result = $this->voteJson('student', $this->student(1), $candidate);
            $result->assertStatus(422);
            $this->assertSame('invalid_candidate', $this->json($result)['status']);
        }

        $this->assertSame(0, $this->db->table('student_votes')->countAllResults());
    }

    // ------------------------------------------------------------------
    // beranda & jam server
    // ------------------------------------------------------------------

    public function testHomeShowsServerDrivenCountdownAndCandidateTeaser(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $result = $this->get('/');

        $result->assertStatus(200);
        $result->assertSee('data-status="ONGOING"');
        $result->assertSee('data-now="' . Time::parse('2026-10-01 09:00:00')->getTimestamp() * 1000 . '"');
        $result->assertSee('data-end="' . Time::parse('2026-10-01 12:00:00')->getTimestamp() * 1000 . '"');
        $result->assertSee('Ditutup dalam');
        // Sisa 3 jam: angka awal dirender server (tetap benar tanpa JavaScript).
        $result->assertSee('data-unit="hours">03<');
        $result->assertSee('Masuk sebagai Siswa');
        $result->assertSee('Masuk sebagai Guru');
        $result->assertSee('Arka Wibisana');
        $result->assertSee('assets/js/countdown.js');
    }

    public function testElectionClockEndpointReturnsServerTime(): void
    {
        $this->scheduleAt('2026-10-01 06:00:00');

        $result = $this->get('jam-server');

        $result->assertStatus(200);
        $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
        $data = $this->json($result);
        $this->assertSame('UPCOMING', $data['status']);
        $this->assertSame('Belum Dibuka', $data['label']);
        $this->assertSame(Time::parse('2026-10-01 06:00:00')->getTimestamp() * 1000, $data['now']);
        $this->assertSame(Time::parse('2026-10-01 07:00:00')->getTimestamp() * 1000, $data['start']);
        $this->assertSame(Time::parse('2026-10-01 12:00:00')->getTimestamp() * 1000, $data['end']);
    }
}
