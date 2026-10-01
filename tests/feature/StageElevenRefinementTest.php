<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\DeviceInfo;
use App\Models\AuditLogModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AssetSource;

/**
 * Stage 11: analitik pill section header + bagian dimuat lewat fetch, detail
 * suara siswa/guru terpisah, indikator "Live", countdown dasbor gaya terminal
 * di HP, rekap kelas/rombel, kolom status & waktu memilih, CRUD siswa/guru,
 * Accept-CH untuk deteksi perangkat.
 *
 * @internal
 */
final class StageElevenRefinementTest extends CIUnitTestCase
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

    private function body($result): string
    {
        return (string) $result->response()->getBody();
    }

    private function asset(string $path): string
    {
        return AssetSource::read($path);
    }

    private function scheduleAt(string $now, string $start = '2026-10-01 07:00:00', string $end = '2026-10-01 12:00:00'): void
    {
        $this->db->table('elections')->where('id', 1)->update(['start_at' => $start, 'end_at' => $end]);
        Time::setTestNow($now);
    }

    private function vote(string $type, int $voterId, int $candidateId, string $votedAt = '2026-10-01 08:05:09'): void
    {
        $this->db->table($type . '_votes')->insert([
            'election_id'  => 1,
            $type . '_id'  => $voterId,
            'candidate_id' => $candidateId,
            'status'       => 'LOCKED',
            'voted_at'     => $votedAt,
            'device_info'  => 'HP / Android 14 / Samsung Galaxy A54 5G',
            'browser_info' => 'Chrome 140',
        ]);
    }

    private function lastAudit(): ?array
    {
        return $this->db->table('audit_logs')->orderBy('id', 'DESC')->get()->getRowArray();
    }

    // ------------------------------------------------------------------
    // analitik: pill section header + fetch
    // ------------------------------------------------------------------

    public function testAnalyticsUsesPillSectionHeaderWithoutChapterNumbers(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $html = $this->body($this->asAdmin()->get('admin/analitik/rombel'));

        $this->assertStringContainsString('<nav class="pills" aria-label="Bagian analitik" data-pane-nav>', $html);
        $this->assertStringContainsString('<span class="pills__glider" aria-hidden="true" data-pane-glider></span>', $html);
        foreach (['keseluruhan' => 'admin/analitik', 'jenis-pemilih' => 'admin/analitik/jenis-pemilih', 'jenis-kelamin' => 'admin/analitik/jenis-kelamin', 'kelas' => 'admin/analitik/kelas', 'rombel' => 'admin/analitik/rombel', 'suara' => 'admin/analitik/suara'] as $slug => $path) {
            $this->assertStringContainsString('href="' . site_url($path) . '" data-pane-link="' . $slug . '"', $html, $slug);
        }
        $this->assertStringContainsString('data-pane-link="rombel" aria-current="page">Rombel</a>', $html);
        $this->assertSame(1, substr_count($html, 'aria-current="page">Rombel'));
        $this->assertStringContainsString('<div class="pane" data-pane="rombel"', $html);
        $this->assertStringContainsString('<h2 class="chapter-x__title" id="pane-title" tabindex="-1">Rekap Rombel</h2>', $html);
        $this->assertStringContainsString('data-live-label="Rombel"', $html);

        // Tanpa penomoran bab & tanpa TOC lama; hanya satu bagian dirender.
        $this->assertStringNotContainsString('chapter-x__no"', $html);
        $this->assertStringNotContainsString('class="toc"', $html);
        $this->assertStringNotContainsString('data-live-table="grade"', $html);
        $this->assertStringContainsString('assets/js/admin-analytics.js', $html);

        $this->expectException(PageNotFoundException::class);
        $this->asAdmin()->get('admin/analitik/tidak-ada');
    }

    public function testPaneRequestReturnsOnlyTheSectionFragment(): void
    {
        $this->vote('student', 1, 1);

        $result = $this->asAdmin()->withHeaders(['X-Analytics-Pane' => '1', 'X-Requested-With' => 'XMLHttpRequest'])->get('admin/analitik/kelas');
        $html   = $this->body($result);

        $result->assertStatus(200);
        $this->assertSame('X-Analytics-Pane', $result->response()->getHeaderLine('Vary'));
        $this->assertStringStartsWith('<div class="pane" data-pane="kelas"', ltrim((string) preg_replace('/<!-- DEBUG-VIEW[^>]*-->/', '', $html)));
        $this->assertStringContainsString('data-pane-title="Kelas · Analitik — Admin Pemilihan OSIS SMP 1 DAWE"', html_entity_decode($html, ENT_QUOTES | ENT_HTML5));
        $this->assertStringContainsString('data-live-table="grade"', $html);
        $this->assertStringContainsString('data-live-label="Kelas"', $html);
        $this->assertStringNotContainsString('<html', $html);
        $this->assertStringNotContainsString('admin-side', $html);
        $this->assertStringNotContainsString('data-pane-nav', $html);

        // Detail suara lewat jalur yang sama (filter & pagination di dalam bagian).
        $votes = $this->body($this->asAdmin()->withHeaders(['X-Analytics-Pane' => '1'])->get('admin/analitik/suara?type=student'));
        $this->assertStringContainsString('<div class="pane" data-pane="suara"', $votes);
        $this->assertStringContainsString('data-pane-form', $votes);
        $this->assertStringContainsString('Ahmad Fauzan', $votes);
        $this->assertStringNotContainsString('<html', $votes);
    }

    public function testDetailVotesIsAnAnalyticsPaneWithSeparateStudentAndTeacherTables(): void
    {
        $this->vote('student', 1, 1);
        $this->vote('student', 2, 2);
        $this->vote('teacher', 1, 3);

        $result = $this->asAdmin()->get('admin/analitik/suara');
        $html   = $this->body($result);

        $this->assertStringContainsString('data-pane-link="suara" aria-current="page">Detail Suara</a>', $html);
        $this->assertMatchesRegularExpression('/<a href="[^"]*admin\/analitik\/suara" class="admin-side__link" aria-current="page">/', $html);

        $student = substr($html, strpos($html, 'data-table--votes-siswa'));
        $student = substr($student, 0, strpos($student, '</table>'));
        $teacher = substr($html, strpos($html, 'data-table--votes-guru'));
        $teacher = substr($teacher, 0, strpos($teacher, '</table>'));

        foreach (['Siswa &middot; NISN', '>Rombel<', '>Absen<', '>JK<', 'Ahmad Fauzan', 'Bunga Larasati'] as $text) {
            $this->assertStringContainsString($text, $student, $text);
        }
        $this->assertStringNotContainsString('Sudarmanto', $student);

        $this->assertStringContainsString('Guru &middot; NIP', $teacher);
        $this->assertStringContainsString('Sudarmanto, S.Pd.', $teacher);
        foreach (['>Rombel<', '>Absen<', '>JK<', 'Ahmad Fauzan'] as $text) {
            $this->assertStringNotContainsString($text, $teacher, $text);
        }

        // Perangkat: model di baris pertama, kategori · OS · browser di bawahnya.
        $this->assertStringContainsString('<span class="device-cell__main">Samsung Galaxy A54 5G</span><span class="cell-sub">HP · Android 14 · Chrome 140</span>', $student);
        $this->assertStringContainsString('icon--phone', $student);

        // Filter rombel: blok guru menjelaskan kenapa kosong.
        $filtered = $this->body($this->asAdmin()->get('admin/analitik/suara?rombel=7A'));
        $this->assertStringContainsString('Filter rombel/jenis kelamin hanya berlaku untuk siswa, sehingga guru tidak ditampilkan.', $filtered);
        $this->assertStringNotContainsString('data-table--votes-guru', $filtered);

        // Jenis pemilih = guru: tabel siswa tidak dirender, total hanya guru.
        $teachers = $this->body($this->asAdmin()->get('admin/analitik/suara?type=teacher'));
        $this->assertStringContainsString('<strong>1</strong> baris suara sesuai filter.', $teachers);
        $this->assertStringNotContainsString('data-table--votes-siswa', $teachers);
    }

    public function testAnalyticsScriptLoadsPanesInPlaceWithHistory(): void
    {
        $js = $this->asset('assets/js/admin-analytics.js');

        $this->assertStringContainsString("var HEADER = 'X-Analytics-Pane';", $js);
        $this->assertStringContainsString("window.history.pushState({ pane: slug }, '', url);", $js);
        $this->assertStringContainsString("window.addEventListener('popstate'", $js);
        $this->assertStringContainsString("template.content.querySelector('[data-pane]')", $js);
        $this->assertStringContainsString('hardNavigate(', $js);
        $this->assertStringContainsString("form[data-pane-form]", $js);
        $this->assertStringNotContainsString('eval(', $js);

        // Filter dropdown tetap langsung diterapkan untuk form yang dimuat ulang.
        $admin = $this->asset('assets/js/admin.js');
        $this->assertStringContainsString("document.addEventListener('change', function (event) {", $admin);
        $this->assertStringContainsString("select.closest('form[data-autosubmit]')", $admin);

        $css = $this->asset('assets/css/admin.css');
        $this->assertMatchesRegularExpression('/\.pills \{\s*position: sticky;/', $css);
        $this->assertStringContainsString('.pills__item[aria-current="page"],', $css);
        $this->assertStringNotContainsString('.chapter-x__no {', $css);
    }

    // ------------------------------------------------------------------
    // indikator live, dasbor
    // ------------------------------------------------------------------

    public function testLiveIndicatorSaysLiveWithStreamingIcon(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $html = $this->body($this->asAdmin()->get('admin'));

        $this->assertStringContainsString('<div class="live live--ongoing" data-live-indicator>', $html);
        $this->assertStringContainsString('<span class="live__state" data-live-state>Live</span>', $html);
        $this->assertStringNotContainsString('data-live-state>Langsung', $html);
        $this->assertStringNotContainsString('live__dot', $html);
        $this->assertMatchesRegularExpression('/<span class="live__signal"><svg class="icon&#x20;icon--live"[^>]*>.*icon__wave icon__wave--in.*icon__wave icon__wave--out/', $html);

        $this->assertStringContainsString("ONGOING: 'Live',", $this->asset('assets/js/admin-live.js'));

        $css = $this->asset('assets/css/admin.css');
        $this->assertStringContainsString('.live--ongoing:not(.is-paused):not(.is-error) .icon__wave--in { animation: live-wave', $css);
        // HP: seluruh indikator (ikon + status + waktu + tombol) di bar bawah.
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{[^@]*\.live \{ display: contents; \}/', $css);
    }

    public function testDashboardRenamesRecapsAndUsesTerminalCountdownOnPhones(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $html = $this->body($this->asAdmin()->get('admin'));

        $this->assertStringContainsString('<h2 class="panel__title" id="grade-title">Rekap kelas</h2>', $html);
        $this->assertStringContainsString('<h2 class="panel__title" id="class-title">Rekap rombel</h2>', $html);
        $this->assertStringNotContainsString('Rekap jenjang', $html);
        $this->assertStringContainsString('data-live-table="grade" data-live-label="Kelas"', $html);
        $this->assertStringContainsString('data-live-table="class" data-live-label="Rombel"', $html);

        // Countdown: sisa < 1 hari -> tanpa "hari", dengan garis progres.
        $clock = substr($html, strpos($html, '<div class="admin-clock admin-clock--ongoing">'));
        $clock = substr($clock, 0, strpos($clock, '</section>'));
        $this->assertStringContainsString('<span class="admin-clock__dots" aria-hidden="true">', $clock);
        $this->assertStringContainsString('countdown--compact', $clock);
        $this->assertStringNotContainsString('countdown__unit--days', $clock);
        $this->assertStringContainsString('data-unit="hours">03</span>', $clock);
        $this->assertStringContainsString('countdown__progress-fill', $clock);

        $css = $this->asset('assets/css/admin.css');
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{\s*\.admin-clock \{[^}]*margin-inline: calc\(var\(--admin-pad\) \* -1\);[^}]*background: var\(--ink\);[^}]*font-family: var\(--font-mono\);[^}]*text-align: center;/', $css);
        $this->assertMatchesRegularExpression('/\.admin-clock \.countdown--compact \{[^}]*border-radius: 0;/', $css);

        // Halaman lain yang memakai compact tetap menampilkan satuan hari.
        service('renderer')->resetData();
        $results = $this->body($this->asAdmin()->get('admin/hasil'));
        if (str_contains($results, 'countdown--compact')) {
            $this->assertStringContainsString('countdown__unit--days', $results);
        }
    }

    // ------------------------------------------------------------------
    // daftar pemilih: status & waktu memilih
    // ------------------------------------------------------------------

    public function testVoterListShowsFullStatusTextAndSeparateTimeColumn(): void
    {
        $this->vote('student', 1, 2, '2026-10-01 08:05:09');

        $html = $this->body($this->asAdmin()->get('admin/siswa'));

        $this->assertMatchesRegularExpression('/<th scope="col">Status memilih<\/th>\s*<th scope="col">Waktu memilih<\/th>/', $html);
        $this->assertStringContainsString('<span class="pill pill--ink">', $html);
        $this->assertStringContainsString(' Sudah memilih</span>', $html);
        $this->assertStringContainsString('<span class="pill pill--outline">Belum memilih</span>', $html);
        $this->assertMatchesRegularExpression('/<td class="nowrap">\s*<time datetime="2026-10-01&#x20;08&#x3A;05&#x3A;09">1 Okt 2026<span class="cell-sub">08\.05\.09 WIB<\/span><\/time>/', $html);
        $this->assertStringContainsString('<span class="cell-empty" aria-hidden="true">&mdash;</span><span class="visually-hidden">Belum memilih</span>', $html);
        $this->assertStringNotContainsString('> Sudah</span>', $html);
        $this->assertStringNotContainsString('>Belum</span>', $html);

        // CRUD: tombol tambah & tautan ubah per baris.
        $this->assertStringContainsString('href="' . site_url('admin/siswa/tambah') . '"', $html);
        $this->assertStringContainsString('href="' . site_url('admin/siswa/1/ubah') . '"', $html);
    }

    // ------------------------------------------------------------------
    // CRUD siswa & guru
    // ------------------------------------------------------------------

    public function testAdminCreatesStudentWithImportRules(): void
    {
        $page = $this->asAdmin()->get('admin/siswa/tambah');
        $page->assertStatus(200);
        $page->assertSee('Tambah siswa');

        $result = $this->postAdmin('admin/siswa', [
            'nisn'          => '0099 887 766',
            'name'          => '  Nadia   Putri ',
            'jenis_kelamin' => 'P',
            'kelas'         => ' 7a ',
            'nomor_absen'   => '9',
            'kodeunik'      => '01-03-2013',
            'status_aktif'  => '1',
            'id'            => '999', // diabaikan
        ]);

        $row = $this->db->table('students')->where('nisn', '0099887766')->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('Nadia Putri', $row['name']);
        $this->assertSame('7A', $row['kelas']);
        $this->assertSame('01032013', $row['kodeunik']);
        $this->assertSame('9', (string) $row['nomor_absen']);
        $this->assertSame('1', (string) $row['status_aktif']);
        $this->assertNotSame('999', (string) $row['id']);
        $result->assertRedirectTo(site_url('admin/siswa/' . $row['id']));

        $audit = $this->lastAudit();
        $this->assertSame(AuditLogModel::STUDENT_CREATE, $audit['action']);
        $this->assertStringContainsString('Siswa Nadia Putri (NISN 0099887766) ditambahkan lewat form, rombel 7A.', $audit['description']);
        $this->assertStringNotContainsString('01032013', $audit['description']);
        $this->assertSame('Tambah data siswa', AuditLogModel::label(AuditLogModel::STUDENT_CREATE));
    }

    public function testInvalidOrDuplicateStudentIsRejectedWithFieldErrors(): void
    {
        $before = $this->db->table('students')->countAllResults();

        $result = $this->postAdmin('admin/siswa', [
            'nisn' => '12345', 'name' => '', 'jenis_kelamin' => '', 'kelas' => '10A', 'nomor_absen' => 'x', 'kodeunik' => '31022013',
        ]);

        $result->assertRedirectTo(site_url('admin/siswa/tambah'));
        $errors = session()->getFlashdata('errors');
        $this->assertSame([
            'nisn'          => 'NISN harus 10 digit (terbaca 5 digit).',
            'name'          => 'Nama wajib diisi.',
            'jenis_kelamin' => 'Jenis kelamin wajib diisi (L atau P).',
            'kelas'         => 'Rombel harus diawali kelas 7, 8, atau 9 (contoh 7A, 8B, IX-C); terbaca "10A".',
            'nomor_absen'   => 'Nomor absen harus angka bulat 1-999 atau dikosongkan.',
            'kodeunik'      => 'Kode unik harus tanggal lahir DDMMYYYY yang valid, contoh 01032013.',
        ], $errors);
        $this->assertSame($before, $this->db->table('students')->countAllResults());

        // Form menampilkan error per kolom & isian lama.
        $form = $this->body($this->withSession($this->admin() + [
            'errors'        => $errors,
            '_ci_old_input' => ['get' => [], 'post' => ['name' => 'Isian Lama']],
            '__ci_vars'     => ['errors' => 'new', '_ci_old_input' => 'new'],
        ])->get('admin/siswa/tambah'));
        $this->assertStringContainsString('<p class="field-error" id="err-nisn">NISN harus 10 digit (terbaca 5 digit).</p>', $form);
        $this->assertStringContainsString('aria-invalid="true"', $form);
        $this->assertStringContainsString('value="Isian&#x20;Lama"', $form);

        $dup = $this->postAdmin('admin/siswa', [
            'nisn' => '0000000001', 'name' => 'Kembar', 'jenis_kelamin' => 'L', 'kelas' => '7A', 'nomor_absen' => '', 'kodeunik' => '01012013',
        ]);
        $dup->assertRedirectTo(site_url('admin/siswa/tambah'));
        $this->assertSame(['nisn' => 'NISN 0000000001 sudah dipakai Ahmad Fauzan (rombel 7A). Satu NISN hanya untuk satu pemilih.'], session()->getFlashdata('errors'));
        $this->assertSame($before, $this->db->table('students')->countAllResults());
    }

    public function testAdminEditsStudentAndAuditListsChangesWithoutSecret(): void
    {
        $this->vote('student', 1, 1);

        $page = $this->asAdmin()->get('admin/siswa/1/ubah');
        $page->assertStatus(200);
        $page->assertSee('Simpan perubahan');

        $result = $this->postAdmin('admin/siswa/1', [
            'nisn' => '0000000001', 'name' => 'Ahmad Fauzan Akbar', 'jenis_kelamin' => 'L', 'kelas' => '7B', 'nomor_absen' => '1', 'kodeunik' => '06062013',
            'status_aktif' => '0', // status tidak diubah lewat form ubah
        ]);

        $result->assertRedirectTo(site_url('admin/siswa/1'));
        $row = $this->db->table('students')->where('id', 1)->get()->getRowArray();
        $this->assertSame('Ahmad Fauzan Akbar', $row['name']);
        $this->assertSame('7B', $row['kelas']);
        $this->assertSame('06062013', $row['kodeunik']);
        $this->assertSame('1', (string) $row['status_aktif']);
        // Suara tetap milik pemilih yang sama.
        $this->assertSame(1, $this->db->table('student_votes')->where('student_id', 1)->countAllResults());

        $audit = $this->lastAudit();
        $this->assertSame(AuditLogModel::STUDENT_UPDATE, $audit['action']);
        $this->assertSame('Data siswa Ahmad Fauzan (NISN 0000000001) diubah: nama Ahmad Fauzan -> Ahmad Fauzan Akbar; rombel 7A -> 7B; kode unik.', $audit['description']);
        $this->assertStringContainsString('Nomor absen 1 di rombel 7B juga dipakai Candra Setiawan', (string) session()->getFlashdata('warning'));

        // Tanpa perubahan: tidak ada audit baru.
        $count = $this->db->table('audit_logs')->countAllResults();
        $this->postAdmin('admin/siswa/1', [
            'nisn' => '0000000001', 'name' => 'Ahmad Fauzan Akbar', 'jenis_kelamin' => 'L', 'kelas' => '7B', 'nomor_absen' => '1', 'kodeunik' => '06062013',
        ])->assertRedirectTo(site_url('admin/siswa/1'));
        $this->assertSame('Tidak ada perubahan pada data Ahmad Fauzan Akbar.', session()->getFlashdata('success'));
        $this->assertSame($count, $this->db->table('audit_logs')->countAllResults());
    }

    public function testAdminCreatesAndEditsTeacher(): void
    {
        $page = $this->asAdmin()->get('admin/guru/tambah');
        $page->assertStatus(200);
        $page->assertSee('Tambah guru');
        $page->assertDontSee('name="kelas"');

        $this->postAdmin('admin/guru', [
            'nip' => '1234567890123456', 'name' => 'Rahmat Hidayat, S.Pd.', 'kodeunik' => '12121990', 'status_aktif' => '0',
        ]);

        $row = $this->db->table('teachers')->where('nip', '1234567890123456')->get()->getRowArray();
        $this->assertNotNull($row);
        $this->assertSame('0', (string) $row['status_aktif']);
        $this->assertStringContainsString('NIP terdiri dari 16 digit (NIP PNS 18 digit).', (string) session()->getFlashdata('warning'));
        $this->assertSame(AuditLogModel::TEACHER_CREATE, $this->lastAudit()['action']);

        $this->postAdmin('admin/guru/' . $row['id'], ['nip' => '1234567890123456', 'name' => 'Rahmat Hidayat, M.Pd.', 'kodeunik' => '12121990'])
            ->assertRedirectTo(site_url('admin/guru/' . $row['id']));
        $this->seeInDatabase('teachers', ['id' => $row['id'], 'name' => 'Rahmat Hidayat, M.Pd.']);
        $this->assertSame(AuditLogModel::TEACHER_UPDATE, $this->lastAudit()['action']);

        $this->asAdmin()->get('admin/guru/' . $row['id'])->assertSee(site_url('admin/guru/' . $row['id'] . '/ubah'));
    }

    public function testCreateAndEditAreLockedAfterElectionFinished(): void
    {
        $this->scheduleAt('2026-10-01 12:00:00');
        $before = $this->db->table('students')->countAllResults();

        $this->asAdmin()->get('admin/siswa/tambah')->assertRedirectTo(site_url('admin/siswa'));
        $this->asAdmin()->get('admin/siswa/1/ubah')->assertRedirectTo(site_url('admin/siswa/1'));

        $this->postAdmin('admin/siswa', [
            'nisn' => '0099887766', 'name' => 'Terlambat', 'jenis_kelamin' => 'L', 'kelas' => '7A', 'kodeunik' => '01012013',
        ])->assertRedirectTo(site_url('admin/siswa'));
        $this->postAdmin('admin/siswa/1', [
            'nisn' => '0000000001', 'name' => 'Diubah', 'jenis_kelamin' => 'L', 'kelas' => '9A', 'kodeunik' => '05062013',
        ])->assertRedirectTo(site_url('admin/siswa/1'));

        $this->assertSame($before, $this->db->table('students')->countAllResults());
        $this->seeInDatabase('students', ['id' => 1, 'name' => 'Ahmad Fauzan', 'kelas' => '7A']);

        $list = $this->body($this->asAdmin()->get('admin/siswa'));
        $this->assertStringNotContainsString(site_url('admin/siswa/tambah'), $list);
        $this->assertStringNotContainsString('/ubah"', $list);
    }

    // ------------------------------------------------------------------
    // rombel (7A, 8B) seragam di semua bagian; "kelas" = 7/8/9
    // ------------------------------------------------------------------

    public function testRombelLabelIsUsedForClassSectionsEverywhere(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');
        $this->vote('student', 1, 1);

        $pages = [
            'admin/siswa'                          => ['<label for="f-rombel">Rombel</label>', 'name="rombel"', '<th scope="col">Rombel</th>'],
            'admin/siswa/1'                        => ['<dt>Rombel</dt>'],
            'admin/siswa/tambah'                   => ['<label for="kelas">Rombel</label>', 'Diawali kelas 7, 8, atau 9'],
            'admin/analitik/suara'                 => ['<label for="f-rombel">Rombel</label>', '<th scope="col">Rombel</th>'],
            'admin/buka-kunci?q=Ahmad'             => ['<th scope="col">Rombel</th>'],
            'admin/siswa/impor'                    => ['Rombel diawali kelas 7, 8, atau 9'],
        ];

        foreach ($pages as $path => $needles) {
            service('renderer')->resetData();
            $html = $this->body($this->asAdmin()->get($path));

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $html, $path);
            }
            // "Kelas" hanya untuk 7/8/9 (pil analitik, rekap), bukan label rombel.
            $this->assertDoesNotMatchRegularExpression('/>Kelas<\/(th|label|dt)>|Kelas \(rombel\)/', $html, $path);
        }

        // Tautan rekap rombel & filter memakai ?rombel=; ?kelas= lama tetap diterima.
        service('renderer')->resetData();
        $this->asAdmin()->get('admin')->assertSee('href="' . site_url('admin/siswa') . '?status=aktif&amp;rombel=7A"');
        foreach (['admin/siswa?rombel=7a', 'admin/siswa?kelas=7A'] as $path) {
            $html = $this->body($this->asAdmin()->get($path));
            $this->assertStringContainsString('<option value="7A" selected>7A</option>', $html, $path);
            $this->assertStringNotContainsString('Candra Setiawan', $html, $path); // 7B
        }

        // Halaman siswa (dasbor & pilihan saya).
        $student = ['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true];
        $this->assertStringContainsString('<dt class="visually-hidden">Rombel</dt>', $this->body($this->withSession($student)->get('siswa')));
        $this->assertStringContainsString('<dt>Rombel</dt>', $this->body($this->withSession($student)->get('siswa/pilihanku')));
    }

    public function testStudentImportTemplateUsesRombelColumnAndAcceptsLegacyKelas(): void
    {
        $importer = service('voterEditor')->importer(\App\Services\VoterType::Student);
        $this->assertArrayHasKey('rombel', $importer->columns());
        $this->assertArrayNotHasKey('kelas', $importer->columns());
        $this->assertSame('kelas', $importer->columns()['rombel']['field']);

        foreach (['rombel', 'kelas'] as $header) {
            $path   = \Tests\Support\SpreadsheetFactory::write([
                ['no', 'NISN', 'nama', 'jenis_kelamin', $header, 'nomor_absen', 'kodeunik'],
                [1, '0098765432', 'Siswa Rombel', 'L', '8b', 3, '02022012'],
                [2, '0098765433', 'Siswa Salah', 'P', '10A', 4, '02022012'],
            ]);
            $result = $importer->parse($path);
            @unlink($path);

            $this->assertSame([], $result['errors'], $header);
            $this->assertSame('8B', $result['rows'][0]['values']['kelas'], $header);
            $this->assertSame(['Rombel harus diawali kelas 7, 8, atau 9 (contoh 7A, 8B, IX-C); terbaca "10A".'], $result['rows'][1]['errors'], $header);
        }
    }

    // ------------------------------------------------------------------
    // perangkat & browser
    // ------------------------------------------------------------------

    public function testResponsesRequestClientHintsForDeviceDetection(): void
    {
        foreach (['/', 'siswa/masuk', 'admin/masuk'] as $path) {
            $this->assertSame(DeviceInfo::ACCEPT_CH, $this->get($path)->response()->getHeaderLine('Accept-CH'), $path);
        }

        $this->assertStringContainsString('Sec-CH-UA-Model', DeviceInfo::ACCEPT_CH);
        $this->assertStringContainsString('Sec-CH-UA-Platform-Version', DeviceInfo::ACCEPT_CH);
    }
}
