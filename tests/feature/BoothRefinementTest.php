<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Stage 8 (STAGE8-NOTES.md): rapikan dasbor pemilih & bilik suara.
 *
 * - navigasi HP ikon saja; kepala dasbor rata tengah, identitas "a / b / c"
 *   miring tanpa label terlihat; countdown pindah ke jam melayang di bawah;
 * - pembuka bilik suara tanpa sapaan, journey timeline, countdown terminal,
 *   tiap bagian satu layar di HP (Stage 22: pembuka multi-langkah, countdown
 *   di halaman bilik-suara);
 * - paku 3D selalu nyala (tanpa tombol), konfirmasi menunggu 3 detik;
 * - eyebrow bab tanpa teks kembar, modal konfirmasi rata tengah + latar buram.
 *
 * Gerak & tata letak diuji di browser (STAGE8-NOTES.md bagian 4); di sini
 * markup, hook, dan aturan CSS/JS yang menjadi kontraknya.
 *
 * @internal
 */
final class BoothRefinementTest extends CIUnitTestCase
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

    private function student(int $id = 1): array
    {
        return ['user_type' => 'student', 'student_id' => $id, 'isLoggedIn' => true];
    }

    private function teacher(int $id = 1): array
    {
        return ['user_type' => 'teacher', 'teacher_id' => $id, 'isLoggedIn' => true];
    }

    private function scheduleAt(string $now): void
    {
        $this->db->table('elections')->where('id', 1)
            ->update(['start_at' => '2026-10-01 07:00:00', 'end_at' => '2026-10-01 12:00:00']);
        Time::setTestNow($now);
    }

    private function asset(string $path): string
    {
        return (string) file_get_contents(FCPATH . $path);
    }

    // ------------------------------------------------------------------
    // navigasi & dasbor
    // ------------------------------------------------------------------

    public function testNavActionsAreIconOnlyOnPhonesButStillNamed(): void
    {
        $css = $this->asset('assets/css/app.css');
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 719px\) \{\s*\.site-nav__action \{[^}]*width: 44px;[^}]*\}\s*\.site-nav__label \{[^}]*clip: rect\(0, 0, 0, 0\);/',
            $css,
        );

        $page = $this->withSession($this->student())->get('siswa');
        $page->assertSee('<span class="site-nav__label">Dasbor');
        $page->assertSee('<span class="site-nav__label">Keluar</span>');
    }

    public function testDashboardHeadCentresNameAndSlashSeparatedIdentity(): void
    {
        $page = $this->withSession($this->student(3))->get('siswa');
        $html = (string) $page->response()->getBody();

        $page->assertDontSee('<p class="eyebrow">Dasbor Siswa</p>');
        $page->assertSee('<h1 class="dash-head__name">Candra Setiawan</h1>');
        // Nilai langsung; label NISN / Rombel / Nomor Absen hanya untuk pembaca layar.
        foreach (['NISN', 'Rombel', 'Nomor Absen'] as $label) {
            $page->assertSee('<dt class="visually-hidden">' . $label . '</dt>');
        }
        $this->assertLessThan(strpos($html, 'class="dash-meta"'), strpos($html, 'class="dash-head__name"'));

        $teacher = $this->withSession($this->teacher())->get('guru');
        $teacher->assertDontSee('<p class="eyebrow">Dasbor Guru</p>');
        $teacher->assertSee('<dt class="visually-hidden">NIP</dt>');

        $css = $this->asset('assets/css/app.css');
        $this->assertMatchesRegularExpression('/\.dash-head \{[^}]*text-align: center;/', $css);
        $this->assertMatchesRegularExpression('/\.dash-meta \{[^}]*font-style: italic;/', $css);
        $this->assertStringContainsString(".dash-meta > div + div::before {\n  content: '/';", $css);
    }

    public function testCountdownFloatsAtTheBottomOfVoterDashboards(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        foreach (['siswa' => $this->student(), 'guru' => $this->teacher()] as $path => $session) {
            $page = $this->withSession($session)->get($path);
            $html = (string) $page->response()->getBody();

            $page->assertSee('class="float-clock float-clock--ongoing" aria-label="Sisa waktu pemilihan"');
            $page->assertSee('countdown countdown--terminal countdown--ongoing');
            $page->assertSee('Ditutup dalam');
            $page->assertSee('data-unit="hours">03<');
            $page->assertDontSee('data-unit="days"');
            // Satu countdown saja: tidak lagi di kolom "Jadwal pemilihan".
            $this->assertSame(1, substr_count($html, 'role="timer"'), $path);
            $this->assertLessThan(strpos($html, 'class="float-clock'), strpos($html, 'id="schedule-title"'), $path);
        }

        $this->scheduleAt('2026-09-30 08:00:00');
        $upcoming = $this->withSession($this->student())->get('siswa');
        $upcoming->assertSee('float-clock--upcoming');
        $upcoming->assertSee('Dibuka dalam');

        $this->scheduleAt('2026-10-02 00:00:00');
        $this->withSession($this->student())->get('siswa')->assertDontSee('float-clock');

        $css = $this->asset('assets/css/app.css');
        $this->assertMatchesRegularExpression('/\.float-clock \{[^}]*position: sticky;[^}]*bottom: calc\(/', $css);
    }

    // ------------------------------------------------------------------
    // pembuka bilik suara
    // ------------------------------------------------------------------

    /**
     * Stage 22: journey timeline & panel terminal di pembuka diganti pembuka
     * multi-langkah (StageTwentyTwoRefinementTest); countdown terminal pindah
     * ke kepala surat suara di halaman bilik-suara.
     */
    public function testBoothIntroStepsAndTerminalCountdownOnTheBallotPage(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $page = $this->withSession($this->student())->get('siswa/coblos');
        $html = (string) $page->response()->getBody();

        $page->assertDontSee('Halo, ');
        $page->assertDontSee('data-journey');
        $page->assertDontSee('class="term');
        $page->assertSee('<section class="steps" aria-labelledby="steps-title" data-steps>');
        $this->assertSame(3, substr_count($html, 'data-step>'));

        $booth = $this->withSession($this->student())->get('siswa/bilik-suara');
        $booth->assertSee('<div class="term ballot__term">');
        // Stage 9: tanpa bilah judul & baris perintah (StageNineRefinementTest).
        $booth->assertDontSee('sisa-waktu --tutup');
        $booth->assertSee('countdown countdown--terminal countdown--ongoing');
        $booth->assertSee('Ditutup dalam');

        $css = $this->asset('assets/css/voting.css');
        $this->assertMatchesRegularExpression('/\.steps \{[^}]*text-align: center;/', $css);
        // Aturan pembuka lama dihapus (nama kelas boleh tersisa di catatan riwayat).
        $this->assertDoesNotMatchRegularExpression('/^\s*\.(?:booth-intro|booth-open|journey|chapter)\b[^{}]*\{/m', $css);
        // Surat suara tetap setinggi satu layar (dikurangi navigasi situs).
        $this->assertMatchesRegularExpression('/\.ballot \{[^}]*min-height: calc\(100svh - 65px\);/', $css);
        // Desktop: bagian lebih lebar dari kontainer bawaan (1080px).
        $this->assertStringContainsString(".steps,\n.lineup,\n.ballot { --container-w: 1360px; }", $css);
        $this->assertFileDoesNotExist(FCPATH . 'assets/js/candidates.js');
    }

    public function testClosedBoothShowsNoticeWithoutPickLinkOrCountdown(): void
    {
        $this->scheduleAt('2026-10-02 00:00:00');

        $page = $this->withSession($this->student())->get('siswa/coblos');
        $page->assertSee('Pemilihan telah ditutup');

        $booth = $this->withSession($this->student())->get('siswa/bilik-suara');
        $booth->assertDontSee('class="term');
        $booth->assertDontSee('data-coblos');
        $booth->assertSee('Pemilihan telah ditutup');
    }

    // ------------------------------------------------------------------
    // paku, bab, konfirmasi
    // ------------------------------------------------------------------

    public function testNailIsAlways3dAndConfirmationWaitsForThePunchedPaper(): void
    {
        $page = $this->withSession($this->student())->get('siswa/bilik-suara');
        $page->assertDontSee('data-fx-toggle');
        $page->assertDontSee('Efek 3D');
        $page->assertSee('data-nail3d');
        $page->assertSee('data-nail2d');

        $js = $this->asset('assets/js/ballot.js');
        $this->assertStringContainsString('var CONFIRM_DELAY = 3000;', $js);
        $this->assertStringContainsString('}, CONFIRM_DELAY);', $js);
        $this->assertStringContainsString('window.NailWebGL.create(canvas, true)', $js);
        foreach (['fxToggle', 'data-fx-toggle', "App.getPref('fx')", 'lowEndDevice'] as $gone) {
            $this->assertStringNotContainsString($gone, $js, $gone);
        }

        $css = $this->asset('assets/css/voting.css');
        $this->assertStringNotContainsString('.fx-toggle', $css);
        $this->assertMatchesRegularExpression('/\.hole \{\s*--hole: 72px;/', $css);
        $this->assertStringContainsString('.hole { --hole: 92px; }', $css);
        $this->assertStringContainsString('.hole-bit {', $css);
    }

    /**
     * Stage 15: kepala profil paslon (pengganti eyebrow bab) juga tidak
     * menulis "Pasangan 0X" dua kali bila nama tema kosong.
     * Stage 16: profil dibuka gambar khusus paslon (atau judul sederhana)
     * tanpa nama tema; nama tema isian admin tetap tampil di Sekilas paslon.
     */
    public function testThemeNameNeverRepeatsTheDefaultPairLabel(): void
    {
        $this->db->table('candidates')->where('id', 1)->update(['theme_name' => '']);

        $page = $this->withSession($this->student())->get('siswa/paslon/01');
        $html = (string) $page->response()->getBody();

        $this->assertSame(1, substr_count($html, '<p class="px-cover__no">Paslon <strong>01</strong></p>'));
        $this->assertStringNotContainsString('Pasangan 01</span>', $html);
        $profile = $this->withSession($this->student())->get('siswa/paslon/02');
        $profile->assertDontSee('Deep Forest');

        // Sekilas paslon: tema isian admin tampil, tema kosong tidak ditulis ulang.
        $booth = $this->withSession($this->student())->get('siswa/sekilas-paslon');
        $booth->assertSee('<span>Deep Forest</span>');
        $booth->assertDontSee('<span>Pasangan 01</span>');

        // Label peran berjarak dari nama.
        $css = $this->asset('assets/css/paslon.css');
        $this->assertMatchesRegularExpression('/\.px-cover__person \{[^}]*gap: var\(--space-1\);/', $css);
    }

    public function testConfirmDialogIsCentredOverABlurredBackdrop(): void
    {
        $page = $this->withSession($this->student())->get('siswa/bilik-suara');
        $page->assertSee('id="vote-confirm"');
        $page->assertSee('class="confirm__warning" id="confirm-warning"');

        $css = $this->asset('assets/css/voting.css');
        $this->assertMatchesRegularExpression('/\.confirm \{[^}]*text-align: center;/', $css);
        $this->assertMatchesRegularExpression('/\.confirm::backdrop \{[^}]*backdrop-filter: blur\(10px\);/', $css);
        $this->assertMatchesRegularExpression('/\.confirm__warning \{[^}]*flex-direction: column;[^}]*align-items: center;/', $css);
    }
}
