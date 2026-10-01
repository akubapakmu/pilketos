<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\CandidateTheme;
use App\Services\VisitorTracker;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Stage 22: bilik suara dipecah menjadi halaman berurutan, yaitu pembuka
 * multi-langkah bergaya mengetik (coblos), Sekilas paslon (sekilas-paslon,
 * tombol "Profil Lengkap Paslon 0X" bermotif unik, tanpa geser otomatis),
 * profil paslon (bar pemilih paslon menempel, nama penutup maks. dua baris,
 * tombol 3D tangan menekan "Pilih Paslon 0X"), dan bilik suara (bilik-suara,
 * surat suara + paku + countdown). Gerak, WebGL, dan tata letak diuji di
 * browser (STAGE22-NOTES.md); di sini route, markup, dan aturan CSS/JS.
 *
 * @internal
 */
final class StageTwentyTwoRefinementTest extends CIUnitTestCase
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
        service('renderer')->resetData();
    }

    protected function tearDown(): void
    {
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

    private function body($result): string
    {
        return (string) preg_replace('/<!-- DEBUG-VIEW[^>]*-->\s*/', '', (string) $result->response()->getBody());
    }

    /** URL seperti ditulis view: site_url() di-escape untuk atribut. */
    private function url(string $path): string
    {
        return esc(site_url($path), 'attr');
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

    private function vote(int $studentId, int $candidateId): void
    {
        $this->db->table('student_votes')->insert([
            'election_id' => 1, 'student_id' => $studentId, 'candidate_id' => $candidateId, 'status' => 'LOCKED', 'voted_at' => '2026-10-01 08:00:00',
        ]);
    }

    // ------------------------------------------------------------------
    // route & alur halaman
    // ------------------------------------------------------------------

    public function testBoothFlowIsThreePagesForEachVoterRole(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        foreach ([['siswa', self::STUDENT], ['guru', self::TEACHER]] as [$role, $session]) {
            $intro = $this->body($this->withSession($session)->get($role . '/coblos'));
            $this->assertStringContainsString('<section class="steps"', $intro);
            $this->assertStringContainsString('href="' . $this->url($role . '/sekilas-paslon') . '" data-steps-next>Selanjutnya', $intro);

            $lineup = $this->body($this->withSession($session)->get($role . '/sekilas-paslon'));
            $this->assertStringContainsString('<h1 class="lineup__title" id="lineup-title">Sekilas paslon</h1>', $lineup);
            $this->assertStringContainsString('href="' . $this->url($role . '/paslon/01') . '"', $lineup);

            $booth = $this->body($this->withSession($session)->get($role . '/bilik-suara'));
            $this->assertStringContainsString('<h1 class="ballot__title" id="ballot-title">Surat Suara</h1>', $booth);
            $this->assertStringContainsString('data-submit-url="' . $this->url($role . '/coblos') . '"', $booth);
            $this->assertStringContainsString('<a class="ballot__back" href="' . $this->url($role . '/sekilas-paslon') . '">', $booth);
        }
    }

    public function testNewPagesNeedTheMatchingVoterLogin(): void
    {
        foreach (['siswa/sekilas-paslon', 'siswa/bilik-suara'] as $path) {
            $this->get($path)->assertRedirectTo(site_url('siswa/masuk'));
            $this->withSession(self::TEACHER)->get($path)->assertStatus(302);
            $this->withSession(self::ADMIN)->get($path)->assertStatus(302);
        }
        foreach (['guru/sekilas-paslon', 'guru/bilik-suara'] as $path) {
            $this->get($path)->assertRedirectTo(site_url('guru/masuk'));
            $this->withSession(self::STUDENT)->get($path)->assertStatus(302);
        }
    }

    public function testVoterWhoAlreadyVotedIsSentToOwnChoiceFromEveryBoothPage(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');
        $this->vote(1, 2);

        foreach (['siswa/coblos', 'siswa/sekilas-paslon', 'siswa/bilik-suara'] as $path) {
            $this->withSession(self::STUDENT)->get($path)->assertRedirectTo(site_url('siswa/pilihanku'));
        }
    }

    public function testVoteErrorsReturnToTheBallotPage(): void
    {
        $this->scheduleAt('2026-10-02 00:00:00');

        $this->withSession(self::STUDENT)->get('siswa/coblos/yakin/1')
            ->assertRedirectTo(site_url('siswa/bilik-suara'));

        $confirm = file_get_contents(APPPATH . 'Views/voting/confirm.php');
        $this->assertStringContainsString("base_url(\$type->path('bilik-suara')) ?>#surat-suara\">Batal, pilih ulang", $confirm);
    }

    public function testVisitorLabelsNameTheNewPages(): void
    {
        $this->assertSame('Langkah memilih (siswa)', VisitorTracker::pageLabel('siswa/coblos'));
        $this->assertSame('Sekilas paslon (guru)', VisitorTracker::pageLabel('guru/sekilas-paslon'));
        $this->assertSame('Bilik suara siswa', VisitorTracker::pageLabel('siswa/bilik-suara'));
        $this->assertSame('Bilik suara guru', VisitorTracker::pageLabel('guru/bilik-suara'));
    }

    // ------------------------------------------------------------------
    // pembuka multi-langkah
    // ------------------------------------------------------------------

    public function testIntroTypesThreeStepsThenShowsTheNextButton(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/coblos'));

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<h1 class="visually-hidden" id="steps-title">Langkah memilih</h1>', $html);
        $this->assertSame(3, substr_count($html, 'data-step>'));

        $steps = [
            ['Kenali Calonmu', 'Yuk, kenalan lebih dekat dengan setiap pasangan calon Ketua &amp; Wakil Ketua OSIS. Lihat profil, visi, misi, dan gagasan mereka sebelum menentukan pilihanmu!'],
            ['Pilih Pasanganmu', 'Tekan Coblos, atau seret paku ke kotak pasangan yang kamu pilih.'],
            ['Cek Lagi Pilihanmu', 'Sudah yakin? Periksa kembali pilihanmu sebelum dikunci. Setelah dikunci, pilihan <strong>tidak dapat diubah</strong>.'],
        ];
        $last = 0;
        foreach ($steps as [$title, $text]) {
            $at = strpos($html, '<h2 class="steps__title" data-type="title">' . $title . '</h2>');
            $this->assertNotFalse($at, $title);
            $this->assertGreaterThan($last, $at);
            $this->assertStringContainsString('<p class="steps__desc" data-type="desc">' . $text . '</p>', $html);
            $last = $at;
        }

        // Tombol setelah langkah terakhir; kontrol animasi tersembunyi tanpa JS.
        $this->assertLessThan(strpos($html, 'data-steps-next'), $last);
        $this->assertStringContainsString('<div class="steps__bar" data-steps-bar hidden>', $html);
        $this->assertStringContainsString('<button type="button" class="steps__skip" data-steps-skip>Lewati', $html);
        $this->assertStringContainsString('assets/js/booth-steps.js', $html);
        // Pencoblosan dibuka: tanpa pemberitahuan status.
        $this->assertStringNotContainsString('steps__notice', $html);
        foreach (['journey', 'class="term', 'pair-card', 'ballot-cell'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }
    }

    public function testIntroShowsTheScheduleNoticeWhenVotingIsNotOpen(): void
    {
        foreach (['2026-09-30 08:00:00' => 'Pencoblosan belum dibuka.', '2026-10-02 00:00:00' => 'Pemilihan telah ditutup.'] as $now => $text) {
            $this->scheduleAt($now);

            $html = $this->body($this->withSession(self::STUDENT)->get('siswa/coblos'));
            $this->assertStringContainsString('class="notice steps__notice" role="status"', $html);
            $this->assertStringContainsString($text, $html);
            $this->assertStringContainsString('data-steps-next>Selanjutnya', $html);
        }
    }

    public function testStepsScriptTypesEachTextNodeAndRemembersTheSession(): void
    {
        $js = $this->asset('assets/js/booth-steps.js');

        $this->assertStringContainsString("var SEEN_KEY = 'osis2026.stepsSeen';", $js);
        $this->assertStringContainsString('if (!root || reduced || !document.createTreeWalker) {', $js);
        $this->assertStringContainsString("stage.setAttribute('aria-hidden', 'true');", $js);
        $this->assertStringContainsString("list.classList.add('visually-hidden');", $js);
        $this->assertStringContainsString('NodeFilter.SHOW_TEXT', $js);
        $this->assertStringContainsString("root.classList.add('is-done');", $js);
        $this->assertStringContainsString('next.focus();', $js);
        $this->assertStringNotContainsString('innerHTML', $js);

        $css = $this->asset('assets/css/voting.css');
        $this->assertStringContainsString('.steps__rest { color: transparent; }', $css);
        $this->assertMatchesRegularExpression('/\.steps--live \.steps__end \{[^}]*visibility: hidden;/', $css);
        $this->assertMatchesRegularExpression('/\.steps--live\.is-done \.steps__end \{[^}]*visibility: visible;/', $css);
        $this->assertMatchesRegularExpression('/\.steps--live \.steps__stage \.steps__item \{[^}]*grid-area: 1 \/ 1;/', $css);
    }

    // ------------------------------------------------------------------
    // Sekilas paslon
    // ------------------------------------------------------------------

    public function testLineupCardsHaveOneFullWidthProfileButtonWithAUniqueMotif(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/sekilas-paslon'));

        foreach (['01' => 'diagonal', '02' => 'dots', '03' => 'waves'] as $label => $motif) {
            $this->assertStringContainsString(
                '<a class="pair-card__profile pair-card__profile--' . $motif . '" href="' . $this->url('siswa/paslon/' . $label) . '">' . "\n"
                . '              <span class="pair-card__profile-text">Profil Lengkap Paslon ' . $label . '</span>',
                $html,
                $label,
            );
        }
        foreach (['lineup__hint', 'pair-card__pick', 'pair-card__read', 'pair-card__actions', 'data-pick', 'data-lineup', 'lineup__next', 'Profil lengkap<'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }
        $this->assertStringNotContainsString('pattern', $html);
    }

    public function testEveryPairUpToTheAdminLimitGetsItsOwnMotif(): void
    {
        $motifs = array_map(CandidateTheme::motif(...), range(1, 10));

        $this->assertSame(CandidateTheme::MOTIFS, $motifs);
        $this->assertCount(10, array_unique($motifs));

        $css = $this->asset('assets/css/voting.css');
        foreach (CandidateTheme::MOTIFS as $motif) {
            $this->assertFileExists(FCPATH . 'assets/img/patterns/px-' . $motif . '.svg');
            $this->assertStringContainsString("mask-image: url('../img/patterns/px-" . $motif . ".svg');", $this->rule($css, '.pair-card__profile--' . $motif . '::before'));
        }
        $this->assertStringContainsString('background: var(--accent);', $this->rule($css, '.pair-card__profile'));
        $this->assertStringContainsString('background: var(--accent-ink);', $this->rule($css, '.pair-card__profile::before'));
    }

    public function testLineupStacksOnPhonesAndShowsAtMostThreePerRowOnDesktop(): void
    {
        $css = $this->asset('assets/css/voting.css');

        $list = $this->rule($css, '.lineup .lineup__list');
        $this->assertStringContainsString('flex-wrap: wrap;', $list);
        $this->assertStringContainsString('justify-content: center;', $list);
        $this->assertStringContainsString('--row: 1;', $list);
        $this->assertStringContainsString('flex: 0 1 calc((100% - (var(--row) - 1) * var(--gap)) / var(--row));', $this->rule($css, '.pair-card'));
        $this->assertMatchesRegularExpression('/@media \(min-width: 720px\) \{[^@]*--row: min\(2, var\(--cols-md, 2\)\);/', $css);
        $this->assertMatchesRegularExpression('/@media \(min-width: 1024px\) \{\s*\.lineup \.lineup__list \{ --row: var\(--cols-md, 3\); \}/', $css);

        // --cols-md = maks. 3 kolom seimbang (4 -> 2+2, 5 -> 3+2, 7 -> 3+3+1)
        $this->assertSame('--cols: 4; --cols-md: 2;', CandidateTheme::gridStyle(4));
        $this->assertSame('--cols: 3; --cols-md: 3;', CandidateTheme::gridStyle(5));
    }

    // ------------------------------------------------------------------
    // bilik suara
    // ------------------------------------------------------------------

    public function testBallotPageKeepsTheNailConfirmationAndCountdown(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/bilik-suara'));

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<div class="term ballot__term">', $html);
        $this->assertStringContainsString('id="coblos-02"', $html);
        $this->assertStringContainsString('id="vote-confirm"', $html);
        $this->assertStringContainsString('data-nail3d', $html);
        $this->assertStringContainsString('assets/js/countdown.js', $html);
        $this->assertStringContainsString('assets/js/ballot.js', $html);
        $this->assertStringNotContainsString('pair-card', $html);

        $js = $this->asset('assets/js/ballot.js');
        $this->assertStringContainsString("window.addEventListener('hashchange', pickFromHash);", $js);
        $this->assertStringNotContainsString('osis:journey', substr($js, (int) strpos($js, '(function')));
        $this->assertStringNotContainsString("querySelectorAll('[data-pick]')", $js);
    }

    // ------------------------------------------------------------------
    // profil paslon
    // ------------------------------------------------------------------

    public function testProfileLinksBackToTheLineupAndPicksOnTheBallotPage(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/02'));

        $this->assertStringContainsString('<a class="px-back" href="' . $this->url('siswa/sekilas-paslon') . '">', $html);
        $this->assertStringContainsString('<a class="px-btn" href="' . $this->url('siswa/sekilas-paslon') . '">' . "\n" . '                  Kembali ke daftar paslon', $html);
        $this->assertStringContainsString('<a class="px-pick" href="' . esc(site_url('siswa/bilik-suara') . '#coblos-02', 'attr') . '" data-px-pick>', $html);
        $this->assertStringContainsString('<canvas class="px-pick__canvas" data-px-pick-canvas></canvas>', $html);
        $this->assertStringContainsString('<span class="px-pick__label">Pilih Paslon 02</span>', $html);
        $this->assertMatchesRegularExpression('#<script type="module" src="[^"]*assets/js/pick3d\.js\?v=\d+"></script>#', $html);
        $this->assertStringNotContainsString('px-btn--primary', $html);
    }

    public function testThreeDimensionalButtonUsesTheLocalThreeJsBuild(): void
    {
        $js = $this->asset('assets/js/pick3d.js');

        $this->assertStringContainsString("import * as THREE from '../vendor/three-0.186.1/three.module.js';", $js);
        foreach (['three.module.js', 'three.core.js', 'LICENSE'] as $file) {
            $this->assertFileExists(FCPATH . 'assets/vendor/three-0.186.1/' . $file);
        }
        $this->assertStringContainsString("from\"./three.core.js\"", $this->asset('assets/vendor/three-0.186.1/three.module.js'));
        $this->assertStringContainsString('MIT', $this->asset('assets/vendor/three-0.186.1/LICENSE'));

        // tanpa WebGL/JS tombol 2D; GPU dilepas saat halaman ditinggal
        $this->assertStringContainsString("link.classList.add('is-3d');", $js);
        $this->assertStringContainsString('return null; // tanpa WebGL: tombol 2D tetap dipakai', $js);
        $this->assertStringContainsString("window.addEventListener('pagehide'", $js);
        $this->assertStringContainsString('renderer.forceContextLoss();', $js);
        $this->assertStringContainsString("(prefers-reduced-motion: reduce)", $js);
        $this->assertStringContainsString('event.metaKey || event.ctrlKey || event.shiftKey', $js);

        $css = $this->asset('assets/css/paslon.css');
        $this->assertStringContainsString('.px-pick__stage { display: none; }', $css);
        $this->assertMatchesRegularExpression('/\.px-pick\.is-3d \.px-pick__stage \{[^}]*display: block;/', $css);
        $this->assertStringNotContainsString('--accent', $this->rule($css, '.px-pick__face'));
    }

    public function testSwitchBarReplacesTheNumberRowInTheClosing(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/01'));

        $this->assertStringNotContainsString('px-others', $html);
        $this->assertStringContainsString('<nav class="px-switch" aria-label="Profil paslon lain">', $html);
        $this->assertStringContainsString('<p class="px-switch__label" aria-hidden="true">Profil Paslon</p>', $html);
        $this->assertSame(3, substr_count($html, '<li class="px-switch__item">'));
        $this->assertStringContainsString('<a class="px-switch__link" href="' . $this->url('siswa/paslon/01') . '" aria-current="page">', $html);
        $this->assertStringContainsString('<span class="px-switch__names" aria-hidden="true">Arka Wibisana &amp; Naya Kirana</span>', $html);
        // bar di awal artikel, sebelum isi profil
        $this->assertLessThan(strpos($html, '<div class="px-shell'), strpos($html, '<nav class="px-switch"'));

        $css = $this->asset('assets/css/paslon.css');
        $this->assertStringNotContainsString('.px-others', $css);
        $bar = $this->rule($css, '.px-switch');
        $this->assertStringContainsString('position: fixed;', $bar);
        $this->assertStringContainsString('bottom: 0;', $bar);
        $this->assertStringContainsString('background: var(--ink);', $bar);
        $this->assertStringContainsString('flex: 1 1 0;', $this->rule($css, '.px-switch__item'));
        $this->assertStringContainsString('border-radius: 0;', $this->rule($css, '.px-switch__link'));
        $this->assertMatchesRegularExpression('/@media \(min-width: 1024px\) \{\s*\.px-switch \{\s*position: sticky;\s*top: 0;/', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 1023px\) \{\s*body:has\(\.px-switch\) \{ padding-bottom:/', $css);
    }

    public function testOnlyOnePairMeansNoSwitchBar(): void
    {
        $this->db->table('candidates')->whereIn('nomor_urut', [2, 3])->update(['status_aktif' => 0]);

        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/01'));

        $this->assertStringNotContainsString('px-switch', $html);
    }

    public function testClosingNamesBreakIntoAtMostTwoBalancedLines(): void
    {
        $this->assertSame(['Arka', 'Wibisana'], CandidateTheme::nameLines('Arka Wibisana'));
        $this->assertSame(['Muhammad', 'Rizky Pratama'], CandidateTheme::nameLines('Muhammad  Rizky Pratama'));
        $this->assertSame(['Siti Nur Aini', 'Rahmawati'], CandidateTheme::nameLines('Siti Nur Aini Rahmawati'));
        $this->assertSame(['Budi'], CandidateTheme::nameLines(' Budi '));
        $this->assertSame([], CandidateTheme::nameLines(''));

        $this->db->table('candidates')->where('nomor_urut', 1)->update(['nama_wakil' => 'Naya Kirana Putri']);
        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/01'));

        $this->assertStringContainsString('<strong style="--px-chars: 8;"><span class="px-finale__line">Arka</span> <span class="px-finale__line">Wibisana</span></strong>', $html);
        $this->assertStringContainsString('<strong style="--px-chars: 11;"><span class="px-finale__line">Naya Kirana</span> <span class="px-finale__line">Putri</span></strong>', $html);

        $css = $this->asset('assets/css/paslon.css');
        $this->assertMatchesRegularExpression('/\.px-finale__person \{ container-type: inline-size; \}/', $css);
        $this->assertMatchesRegularExpression('/\.px-finale__person strong \{[^}]*font-size: min\(4\.5rem, 100cqi \/ \(var\(--px-chars, 12\) \* 0\.64\)\);[^}]*white-space: nowrap;/', $css);
        $this->assertStringContainsString('.px-finale__line { display: block; white-space: nowrap; }', $css);
    }

    public function testSectionHeadsProgramsAndTagsFollowTheNewLayout(): void
    {
        $css = $this->asset('assets/css/paslon.css');

        // judul kepala bagian: HP 1,5x (1.05rem), desktop 3x (1.3rem); subjudul miring
        $this->assertStringContainsString('font-size: 1.575rem;', $this->rule($css, '.px-head__title'));
        $this->assertMatchesRegularExpression('/@media \(min-width: 1024px\) \{\s*\.px-head__title \{ font-size: 3\.9rem;/', $css);
        $this->assertStringContainsString('font-style: italic;', $this->rule($css, '.px-head__sub'));
        $this->assertFileExists(FCPATH . 'assets/fonts/plus-jakarta-sans-latin-wght-italic.woff2');
        $this->assertMatchesRegularExpression("/font-style: italic;[^}]*plus-jakarta-sans-latin-wght-italic\\.woff2/", $this->asset('assets/css/app.css'));

        // pola kepala Misi disamarkan lapisan putih buram
        $misi = $this->rule($css, '.px-head--misi::after');
        $this->assertStringContainsString('background: rgba(255, 255, 255, 0.6);', $misi);
        $this->assertStringContainsString('backdrop-filter: blur(3px);', $misi);
        $this->assertStringContainsString('z-index: -1;', $misi);

        // nomor program di kiri, sebaris dengan judul
        $item = $this->rule($css, '.px-programs__item');
        $this->assertStringContainsString('grid-template-columns: auto minmax(0, 1fr);', $item);
        $this->assertStringContainsString('align-items: baseline;', $item);
        $this->assertStringContainsString('grid-column: 2;', $this->rule($css, '.px-programs__title'));
        $this->assertStringContainsString('grid-column: 2;', $this->rule($css, '.px-programs__text'));

        // kata karakter: anak kartu, di HP selebar kartu di bawah
        $this->assertStringContainsString('.px-meet__card > .px-tags { grid-column: 1 / -1; }', $css);
        $html = $this->body($this->withSession(self::STUDENT)->get('siswa/paslon/01'));
        $this->assertMatchesRegularExpression('#<p class="px-meet__bio">[^<]*</p>\s*</div>\s*<ul class="px-tags"#', $html);
    }

    public function testAdminPreviewKeepsThePlainEditButtonAndTheSwitchBar(): void
    {
        $html = $this->body($this->withSession(self::ADMIN)->get('admin/paslon/2/intip'));

        $this->assertStringContainsString('<nav class="px-switch" aria-label="Profil paslon lain">', $html);
        $this->assertStringContainsString('href="' . $this->url('admin/paslon/2/intip') . '" aria-current="page"', $html);
        $this->assertStringContainsString('px-btn px-btn--primary', $html);
        $this->assertStringNotContainsString('data-px-pick', $html);
        $this->assertStringNotContainsString('pick3d.js', $html);
    }
}
