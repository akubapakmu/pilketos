<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Homepage;

/**
 * Stage 10 (STAGE10-NOTES.md): rapikan tampilan HP bilik suara, dasbor,
 * pilihan saya, dan scene perolehan suara beranda.
 *
 * - "Sekilas paslon" bergeser sendiri di HP + panah ke navigasi bab;
 * - dasbor HP: kepala lebih rapat, kartu hak suara rata tengah, jam tidak
 *   menempel tetapi selebar layar tepat di atas footer;
 * - modal sukses HP: waktu dicoblos di baris kedua tanpa titik;
 * - pilihan saya: "kamu" untuk siswa, pasangan disorot, waktu dua baris,
 *   catatan jadi peringatan di HP, hanya tombol "Selesai & keluar";
 * - beranda: tanpa label status, judul & pasangan rata tengah, "Suara masuk"
 *   jadi baris penutup (menggantikan catatan).
 *
 * Gerak & tata letak diuji di browser (STAGE10-NOTES.md); di sini markup,
 * hook, dan aturan CSS/JS yang menjadi kontraknya.
 *
 * @internal
 */
final class StageTenRefinementTest extends CIUnitTestCase
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
        config(Homepage::class)->liveCacheSeconds = 0;
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

    private function vote(string $type, int $voterId, int $candidateId): void
    {
        $this->db->table($type . '_votes')->insert([
            'election_id'  => 1,
            $type . '_id'  => $voterId,
            'candidate_id' => $candidateId,
            'status'       => 'LOCKED',
            'voted_at'     => '2026-10-01 08:05:09',
        ]);
    }

    private function asset(string $path): string
    {
        return (string) file_get_contents(FCPATH . $path);
    }

    // ------------------------------------------------------------------
    // bilik suara
    // ------------------------------------------------------------------

    /**
     * Stage 15: panah di bawah Sekilas paslon menuju surat suara (navigasi
     * bab sudah tidak ada; profil lengkap di halaman profil paslon).
     */
    public function testLineupAutoplaysOnPhonesAndArrowScrollsToChapterNav(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $page = $this->withSession($this->student())->get('siswa/coblos');
        $html = (string) $page->response()->getBody();

        $page->assertSee('<ol class="lineup__list" aria-label="Pasangan calon" style="--cols: 3; --cols-md: 3;" data-lineup>');
        $page->assertSee('<a class="lineup__next" href="#surat-suara" aria-label="Lanjut ke surat suara">');
        $page->assertDontSee('id="navigasi-paslon"');
        // panah di dalam .lineup (bagian bawahnya), surat suara sesudahnya
        $this->assertLessThan(strpos($html, 'class="lineup__next"'), strpos($html, '</ol>'));
        $this->assertLessThan(strpos($html, 'id="surat-suara"'), strpos($html, 'class="lineup__next"'));

        $js = $this->asset('assets/js/candidates.js');
        $this->assertStringContainsString("initLineupAutoplay(document.querySelector('[data-lineup]'))", $js);
        $this->assertStringContainsString("window.matchMedia('(max-width: 719px)')", $js);
        $this->assertStringContainsString('var DELAY = 2000;', $js);
        $this->assertStringContainsString("behavior: 'smooth'", $js);
        // setelah pasangan terakhir kembali ke pasangan pertama
        $this->assertStringContainsString('next = 0;', $js);
        // gerak mati dengan prefers-reduced-motion
        $this->assertMatchesRegularExpression('/function initLineupAutoplay\(list\) \{\s*if \(!list \|\| reduced/', $js);

        $css = $this->asset('assets/css/voting.css');
        $this->assertStringContainsString('.lineup__next { display: none; }', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{\s*\.lineup__next \{\s*display: flex;/', $css);
        $this->assertMatchesRegularExpression('/\.lineup__next \.icon \{[^}]*animation: lineup-nudge/', $css);
    }

    public function testSuccessMetaPutsVoteTimeOnItsOwnLineOnPhones(): void
    {
        $js = $this->asset('assets/js/ballot.js');
        $this->assertStringContainsString("metaPair.className = 'confirm__meta-pair';", $js);
        $this->assertStringContainsString("metaSep.className = 'confirm__meta-sep';", $js);
        $this->assertStringContainsString("metaTime.textContent = 'dicoblos ' + (vote.voted_at || '');", $js);
        $this->assertStringNotContainsString("' · dicoblos '", $js);

        $css = $this->asset('assets/css/voting.css');
        $this->assertMatchesRegularExpression('/\.confirm__meta-sep \{ display: none; \}\s*\.confirm__meta-time \{ display: block; \}/', $css);
    }

    // ------------------------------------------------------------------
    // dasbor pemilih
    // ------------------------------------------------------------------

    public function testDashboardIsCentredOnPhonesWithClockAboveFooter(): void
    {
        $css = $this->asset('assets/css/app.css');

        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{\s*\.dash-head \{ padding-block: var\(--space-5\) var\(--space-6\); \}/', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{\s*\.vote-card \{ text-align: center; \}/', $css);
        $this->assertMatchesRegularExpression('/\.vote-card--locked \{\s*padding-left: var\(--space-5\);[^}]*box-shadow: inset 0 6px 0 var\(--accent\);/', $css);
        // HP: bukan sticky, selebar layar tanpa sudut, didorong ke dasar <main>
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{\s*\.float-clock \{[^}]*position: relative;[^}]*width: 100%;[^}]*margin: auto 0 0;[^}]*border-radius: 0;/', $css);

        $this->scheduleAt('2026-10-01 09:00:00');
        $html = (string) $this->withSession($this->student())->get('siswa')->response()->getBody();
        $html = (string) preg_replace('/<!-- DEBUG-VIEW [^>]*-->/', '', $html);
        // jam adalah elemen terakhir <main>, tepat sebelum footer
        $this->assertMatchesRegularExpression('/<\/aside>\s*<\/main>\s*<footer class="site-footer">/', $html);
        $this->assertStringContainsString('class="float-clock float-clock--ongoing"', $html);
    }

    // ------------------------------------------------------------------
    // pilihan saya
    // ------------------------------------------------------------------

    public function testStudentReceiptSaysKamuAndHighlightsThePair(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');
        $this->vote('student', 1, 2);

        $page = $this->withSession($this->student())->get('siswa/pilihanku');
        $page->assertStatus(200);

        $page->assertSee('<p class="eyebrow receipt__eyebrow">Pilihan saya');
        $page->assertSee('<span class="receipt__lead">Kamu memilih</span>');
        $page->assertSee('<span class="receipt__pair">Pasangan 02</span>');
        $page->assertSee('<span class="receipt__date">1 Oktober 2026</span>');
        $page->assertSee('<span class="receipt__time">08.05.09 WIB</span>');
        $page->assertSee('<strong class="receipt__note-title">Pilihan tidak dapat diubah.</strong>');
        $page->assertSee('lalu kamu mencoblos sendiri.');
        $page->assertSee('Selesai &amp; keluar');
        $page->assertDontSee('Anda');
        $page->assertDontSee('Kembali ke dasbor');
    }

    public function testTeacherReceiptKeepsAnda(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');
        $this->vote('teacher', 1, 3);

        $page = $this->withSession($this->teacher())->get('guru/pilihanku');
        $page->assertStatus(200);

        $page->assertSee('<span class="receipt__lead">Anda memilih</span>');
        $page->assertSee('<span class="receipt__pair">Pasangan 03</span>');
        $page->assertSee('lalu Anda mencoblos sendiri.');
        $page->assertDontSee('Kembali ke dasbor');
    }

    public function testReceiptStylesForPhonesAndDesktop(): void
    {
        $css = $this->asset('assets/css/voting.css');

        $this->assertMatchesRegularExpression('/\.receipt__pair \{[^}]*background: var\(--accent\);[^}]*color: var\(--accent-ink\);/', $css);
        $this->assertStringContainsString('.receipt__portraits .portraits { max-width: 320px; margin-inline: auto; }', $css);
        $this->assertMatchesRegularExpression('/\.receipt__date,\s*\.receipt__time \{ display: block; \}/', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{\s*\.receipt__eyebrow \{ display: none; \}\s*\.receipt__head \{ text-align: center; \}/', $css);
        $this->assertMatchesRegularExpression('/\.receipt__note \{\s*display: grid;[^}]*background: var\(--paper-dim\);/', $css);
    }

    // ------------------------------------------------------------------
    // beranda: scene perolehan suara
    // ------------------------------------------------------------------

    public function testLiveSceneCentresPairsAndClosesWithTurnout(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');
        $this->vote('student', 1, 2);

        $page = $this->get('/');
        $html = (string) $page->response()->getBody();

        $page->assertDontSee('live__tag');
        $page->assertDontSee('data-live-tag');
        $page->assertDontSee('live__grid');
        $page->assertDontSee('live__note');

        // pasangan dulu, lalu baris penutup berisi "Suara masuk" + waktu pembaruan
        $pairs   = strpos($html, 'class="live__pairs"');
        $foot    = strpos($html, 'class="live__foot"');
        $turnout = strpos($html, '<section class="turnout"');
        $this->assertNotFalse($pairs);
        $this->assertLessThan($foot, strpos($html, '</ol>', $pairs));
        $this->assertLessThan($turnout, $foot);
        $block = substr($html, $turnout, strpos($html, '</section>', $turnout) - $turnout);
        $this->assertStringContainsString('<h3 class="turnout__title" id="turnout-title">Suara masuk</h3>', $block);
        $this->assertStringContainsString('data-live-turnout data-value="6.67">6,7<', $block);
        $this->assertStringContainsString('pemilih telah memberikan suara', $block);
        $this->assertStringContainsString('data-live-updated', $block);
        $this->assertStringContainsString('data-live-meter', $block);

        $css = $this->asset('assets/css/home.css');
        $this->assertStringNotContainsString('.live__tag', $css);
        $this->assertStringNotContainsString('.live__grid', $css);
        $this->assertMatchesRegularExpression('/\.live__head \{[^}]*justify-content: center;/', $css);
        $this->assertMatchesRegularExpression('/\.live__pairs \{[^}]*justify-content: center;/', $css);
        $this->assertMatchesRegularExpression("/\\.turnout \\{[^}]*grid-template-areas:\\s*'pct title'\\s*'pct count'\\s*'pct updated'\\s*'meter meter';/", $css);
    }

    public function testTeaserStillEndsWithNoteAndSignInButton(): void
    {
        config(Homepage::class)->publicLiveCount = false;

        $page = $this->get('/');
        $page->assertSee('<p class="live__note">Visi, misi, dan surat suara tersedia setelah masuk.</p>');
        $page->assertDontSee('class="turnout"');
        $page->assertDontSee('live__tag');
    }
}
