<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Models\AdminModel;
use App\Services\FinalResult;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AssetSource;

/**
 * Stage 13: hasil akhir (tampilan, cetak browser, PDF dompdf), sidebar
 * (Title Case, Halaman Utama & Keluar di kelompok terakhir, Akun Admin),
 * kepala halaman HP, banner final rata tengah, istilah analitik, login admin
 * layar terbelah + lihat kata sandi, ganti nama pengguna & kata sandi admin.
 *
 * @internal
 */
final class StageThirteenRefinementTest extends CIUnitTestCase
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

        // throttler memegang instance cache: buat ulang agar kuota bersih
        \Config\Services::resetSingle('throttler');
        cache()->clean();
        service('renderer')->resetData();
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    private function adminSession(array $extra = []): array
    {
        return ['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true] + $extra;
    }

    private function asAdmin(array $extra = [])
    {
        return $this->withSession($this->adminSession($extra));
    }

    private function postAdmin(string $path, array $data = [])
    {
        return $this->asAdmin()->post($path, [csrf_token() => csrf_hash()] + $data);
    }

    /** Isi respons tanpa komentar DEBUG-VIEW (lingkungan development). */
    private function body($result): string
    {
        return (string) preg_replace('/<!-- DEBUG-VIEW[^>]*-->\s*/', '', (string) $result->response()->getBody());
    }

    private function asset(string $path): string
    {
        return AssetSource::read($path);
    }

    /** Isi satu aturan CSS (selektor persis, sampai kurung kurawal tutup pertama). */
    private function rule(string $css, string $selector): string
    {
        $this->assertMatchesRegularExpression('/(^|\n)\s*' . preg_quote($selector, '/') . ' \{/', $css, $selector);
        preg_match('/(?:^|\n)\s*' . preg_quote($selector, '/') . ' \{([^}]*)\}/', $css, $match);

        return $match[1];
    }

    /** Blok @media print terakhir sebuah stylesheet. */
    private function printBlock(string $css): string
    {
        $start = strrpos($css, '@media print {');
        $this->assertNotFalse($start);

        return substr($css, (int) $start);
    }

    private function vote(string $type, int $voterId, int $candidateId): void
    {
        $this->db->table($type . '_votes')->insert([
            'election_id'  => 1,
            $type . '_id'  => $voterId,
            'candidate_id' => $candidateId,
            'status'       => 'LOCKED',
            'voted_at'     => '2026-10-01 08:00:00',
        ]);
    }

    private function finishedWithWinner(): void
    {
        $this->vote('student', 1, 2);
        $this->vote('student', 2, 2);
        $this->vote('teacher', 1, 2);
        $this->vote('student', 3, 1);
        $this->db->table('elections')->where('id', 1)->update(['start_at' => '2026-10-01 07:00:00', 'end_at' => '2026-10-01 12:00:00']);
        Time::setTestNow('2026-10-01 13:00:00');
    }

    private function passwordHash(): string
    {
        return (string) $this->db->table('admins')->where('id', 1)->get()->getRow()->password_hash;
    }

    // ------------------------------------------------------------------
    // hasil akhir: tampilan layar
    // ------------------------------------------------------------------

    public function testFinalResultMastheadIsCenteredUprightAndWithoutMark(): void
    {
        $this->finishedWithWinner();

        $html = $this->body($this->asAdmin()->get('admin/hasil'));

        $this->assertStringNotContainsString('final__mark', $html);
        $this->assertStringContainsString('<span class="final__school">SMP 1 DAWE 2026</span>', $html);
        // logo sekolah pengganti judul saat dicetak
        $this->assertStringContainsString('<img class="admin-head__logo" src="' . base_url('assets/img/brand/logo-smp1dawe.png') . '"', $html);
        $this->assertFileExists(FCPATH . 'assets/img/brand/logo-smp1dawe.png');

        $css = $this->asset('assets/css/results.css');
        $this->assertStringNotContainsString('.final__mark', $css);
        $this->assertStringNotContainsString('font-style: italic', $this->rule($css, '.final__school'));
        $this->assertStringContainsString('text-align: center;', $this->rule($css, '.final__kicker'));
        $this->assertStringContainsString('text-align: center;', $this->rule($css, '.final__title'));
        $this->assertStringContainsString('display: none;', $this->rule($css, '.admin-head__logo'));

        // catatan kaki: selebar lembar (Stage 19: rata kiri & miring)
        $foot = $this->rule($css, '.final__foot');
        $this->assertStringContainsString('text-align: left;', $foot);
        $this->assertStringContainsString('width: 100%;', $foot);
        $this->assertStringNotContainsString('max-width: 90ch', $foot);
        $this->assertStringContainsString('max-width: none;', $this->rule($css, '.final__foot p'));
    }

    public function testFootnoteUsesPlainLanguage(): void
    {
        $this->finishedWithWinner();

        $html = $this->body($this->asAdmin()->get('admin/hasil'));
        // Stage 19: diawali tanda bintang dekoratif (aria-hidden)
        preg_match('#<footer class="final__foot">\s*<p><span class="final__star" aria-hidden="true">\*</span>(.*?)</p>#s', $html, $match);
        $foot = html_entity_decode($match[1] ?? '', ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString('Dihitung pada 1 Oktober 2026 pukul 13.00 WIB.', $foot);
        $this->assertStringContainsString('Hanya suara sah dari pemilih terdaftar yang dihitung.', $foot);
        $this->assertStringContainsString('hasil ini bersifat tetap', $foot);
        foreach (['LOCKED', 'terkunci', 'admin', 'riwayat', 'dikunci'] as $technical) {
            $this->assertStringNotContainsString($technical, $foot, $technical);
        }
        $this->assertSame(FinalResult::footnote('2026-10-01 13:00:00'), $foot);
    }

    public function testFinalActionsBecomeIconButtonsOnMobile(): void
    {
        $this->finishedWithWinner();

        $html = $this->body($this->asAdmin()->get('admin/hasil'));

        $this->assertStringContainsString('<span class="btn__label" data-fullscreen-label>Layar penuh</span>', $html);
        $this->assertStringContainsString('<a class="btn btn--outline" href="' . site_url('admin/hasil/cetak') . '" target="_blank" rel="noopener" data-print-pdf>', $html);
        $this->assertStringContainsString('<span class="btn__label">Cetak PDF</span>', $html);
        $this->assertStringContainsString('<span class="btn__label">Analitik lengkap</span>', $html);

        $css = $this->asset('assets/css/results.css');
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{[^@]*\.final-actions \.btn__label \{[^}]*clip-path: inset\(50%\);/s', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{[^@]*\.final-actions \.btn \{[^}]*border-radius: 50%;/s', $css);
    }

    // ------------------------------------------------------------------
    // hasil akhir: cetak lewat browser
    // ------------------------------------------------------------------

    public function testBrowserPrintRules(): void
    {
        $this->finishedWithWinner();

        $html  = $this->body($this->asAdmin()->get('admin/hasil'));
        $print = $this->printBlock($this->asset('assets/css/results.css'));

        // catatan seri & elemen catatan kaki tidak dicetak; judul diganti logo
        $this->assertMatchesRegularExpression('/\.final-note--tie,\s*\.final__foot \{ display: none !important; \}/', $print);
        $this->assertStringContainsString('.final-page .admin-head__title { display: none; }', $print);
        $this->assertStringContainsString('display: block;', $this->rule($print, '.admin-head__logo'));
        // rekap mulai halaman kedua
        $this->assertStringContainsString('.final__recap { break-before: page; }', $print);

        // catatan kaki + nomor halaman = kotak margin bawah setiap halaman
        $this->assertMatchesRegularExpression('#<style nonce="[^"]+">\s*@page \{\s*@bottom-center \{\s*content: "[^"]+\\\\A Halaman " counter\(page\) " dari " counter\(pages\);#', $html);
        $this->assertStringContainsString('font-style: italic;', $html);

        // footer panel admin tidak ikut dicetak
        $this->assertMatchesRegularExpression('/\.admin-foot,\s*\.skip-link \{ display: none !important; \}/', $this->printBlock($this->asset('assets/css/admin.css')));
    }

    // ------------------------------------------------------------------
    // hasil akhir: PDF (dompdf)
    // ------------------------------------------------------------------

    public function testPdfIsGeneratedWithDompdfWhenFinished(): void
    {
        $this->finishedWithWinner();

        $result = $this->asAdmin()->get('admin/hasil/cetak');

        $result->assertStatus(200);
        $response = $result->response();
        $this->assertSame('application/pdf', $response->getHeaderLine('Content-Type'));
        $this->assertSame('inline; filename="hasil-akhir-pilketos-2026.pdf"', $response->getHeaderLine('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));

        $pdf = (string) $response->getBody();
        $this->assertStringStartsWith('%PDF-', $pdf);
        // font brand tertanam (subset)
        $this->assertMatchesRegularExpression('/\/FontName \/[A-Z]{6}\+Newsreader-Regular/', $pdf);
        $this->assertMatchesRegularExpression('/\/FontName \/[A-Z]{6}\+PlusJakartaSans-Italic/', $pdf);
        // dua halaman: hasil + rekap
        $this->assertSame(2, preg_match_all('#/Type /Page\b#', $pdf));
    }

    public function testPdfIsUnavailableBeforeElectionFinishes(): void
    {
        $this->db->table('elections')->where('id', 1)->update(['start_at' => '2026-10-01 07:00:00', 'end_at' => '2026-10-01 12:00:00']);
        Time::setTestNow('2026-10-01 11:00:00');

        $result = $this->asAdmin()->get('admin/hasil/cetak');
        $result->assertRedirectTo(site_url('admin/hasil'));
        $result->assertSessionHas('error', 'PDF hasil akhir tersedia setelah pemilihan selesai.');

        $this->withSession([])->get('admin/hasil/cetak')->assertRedirectTo(site_url('admin/masuk'));
    }

    public function testPdfTemplateLayout(): void
    {
        $this->finishedWithWinner();
        $election = model(\App\Models\ElectionModel::class)->getCurrentElection();
        $snapshot = service('analytics')->snapshot($election);

        $render = static fn (array $result): string => view('admin/results/pdf', [
            'election' => $election,
            'snapshot' => $snapshot,
            'result'   => $result,
            'logo'     => 'data:image/png;base64,AAAA',
            'photos'   => [],
        ], ['debug' => false]);

        $html = $render(FinalResult::build($election, $snapshot));

        $this->assertStringContainsString('<img class="logo" src="data:image/png;base64,AAAA" alt="Logo SMP 1 DAWE">', $html);
        $this->assertStringContainsString('<p class="kicker">Rekapitulasi resmi penghitungan suara</p>', $html);
        $this->assertStringContainsString('.masthead { text-align: center; }', $html);
        $this->assertStringContainsString('<div class="recap-page">', $html);
        $this->assertStringContainsString('.recap-page { page-break-before: always; }', $html);
        $this->assertStringContainsString('<div class="foot">' . esc(FinalResult::footnote($snapshot['generated_at'])) . '</div>', $html);
        $this->assertMatchesRegularExpression('/\.foot \{[^}]*position: fixed;[^}]*font-style: italic;[^}]*text-align: center;/s', $html);
        $this->assertStringContainsString('Pasangan terpilih', $html);
        foreach (['<th>Pemilih</th>', '<th>Kelas</th>', '<th>Jenis Kelamin Siswa</th>'] as $head) {
            $this->assertStringContainsString($head, $html);
        }

        // seri: catatan seri tidak dicetak
        $tie = FinalResult::build($election, $snapshot);
        $tie['state'] = FinalResult::TIE;
        $tie['winner'] = null;
        $html = $render($tie);
        $this->assertStringNotContainsString('tertinggi sama', $html);
        $this->assertStringNotContainsString('Pasangan terpilih', $html);
    }

    // ------------------------------------------------------------------
    // sidebar, kepala halaman, banner final
    // ------------------------------------------------------------------

    public function testSidebarUsesTitleCaseAndBottomGroup(): void
    {
        $html = $this->body($this->asAdmin()->get('admin'));

        foreach (['Beranda', 'Analitik', 'Detail Suara', 'Hasil Akhir', 'Pasangan Calon', 'Siswa', 'Guru', 'Jadwal Pemilihan', 'Unlock Hak Suara', 'Audit Log'] as $label) {
            $this->assertMatchesRegularExpression('#class="admin-side__link"[^>]*>\s*<svg[^>]*>.*?</svg>\s*<span>' . preg_quote($label, '#') . '</span>#s', $html, $label);
        }

        // kelompok terakhir: Akun Admin, Halaman Utama (tab baru), Keluar (POST)
        $end = substr($html, (int) strpos($html, '<ul class="admin-side__list admin-side__list--end">'));
        $end = substr($end, 0, (int) strpos($end, '</ul>'));
        $this->assertStringContainsString('href="' . site_url('admin/akun') . '" class="admin-side__link"', $end);
        $this->assertStringContainsString('<span>Akun Admin</span>', $end);
        $this->assertMatchesRegularExpression('#<a href="' . preg_quote(base_url('/'), '#') . '" class="admin-side__link" target="_blank" rel="noopener">\s*<svg class="icon&\#x20;icon--home#', $end);
        $this->assertStringContainsString('<span>Halaman Utama<span class="visually-hidden"> (tab baru)</span></span>', $end);
        $this->assertMatchesRegularExpression('#<form action="' . preg_quote(site_url('admin/keluar'), '#') . '" method="post" class="admin-side__form">.*<button type="submit" class="admin-side__link admin-side__link--button">.*<span>Keluar</span>#s', $end);
        $this->assertLessThan(strpos($html, 'admin-side__foot'), strpos($html, 'admin-side__list--end'));

        // tombol lama di kaki sidebar sudah tidak ada
        foreach (['admin-side__site', 'admin-side__logout', 'admin-side__actions', '>Home <'] as $gone) {
            $this->assertStringNotContainsString($gone, $html, $gone);
        }

        $css = $this->asset('assets/css/admin.css');
        $this->assertStringContainsString('flex-direction: column;', $this->rule($css, '.admin-side__nav'));
        $this->assertStringContainsString('.admin-side__list + .admin-side__list--end { margin-top: auto; }', $css);
        $this->assertStringNotContainsString('.admin-side__site', $css);
    }

    public function testMobileHeadHidesRootCrumbAndHintButtons(): void
    {
        $dashboard = $this->body($this->asAdmin()->get('admin'));
        $this->assertStringContainsString('<div class="admin-crumbs"><nav class="crumbs crumbs--root" aria-label="Breadcrumb">', $dashboard);

        $students = $this->body($this->asAdmin()->get('admin/siswa'));
        $this->assertStringContainsString('<div class="admin-crumbs"><nav class="crumbs" aria-label="Breadcrumb">', $students);

        $css = $this->asset('assets/css/admin.css');
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{[^}]*\}\s*\.admin-crumbs \{ display: flex; justify-content: center; \}\s*\.admin-crumbs \.crumbs \{ margin-bottom: var\(--space-4\); \}\s*\/\*[^*]*\*\/\s*\.admin-crumbs \.crumbs--root \{ display: none; \}/', $css);
        $this->assertMatchesRegularExpression('/@media \(max-width: 719px\) \{\s*\.js \.admin-head__hint \{ display: none; \}/', $css);
    }

    public function testFinalBannerIsCentered(): void
    {
        $css    = $this->asset('assets/css/admin.css');
        $banner = $this->rule($css, '.final-banner');

        $this->assertStringContainsString('justify-items: center;', $banner);
        $this->assertStringContainsString('text-align: center;', $banner);
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $banner);
        $this->assertStringNotContainsString('.final-banner__cta { grid-column: auto; justify-self: end; }', $css);
        $this->assertStringContainsString('justify-content: center;', $this->rule($css, '.final-banner__eyebrow'));
    }

    // ------------------------------------------------------------------
    // analitik
    // ------------------------------------------------------------------

    public function testAnalyticsTermsAndPlainNotes(): void
    {
        $html = $this->body($this->asAdmin()->get('admin/analitik'));

        foreach (['keseluruhan' => 'Total', 'jenis-pemilih' => 'Pemilih', 'jenis-kelamin' => 'Jenis Kelamin', 'suara' => 'Detail Suara'] as $slug => $label) {
            $this->assertMatchesRegularExpression('#data-pane-link="' . $slug . '"( aria-current="page")?>' . $label . '</a>#', $html, $slug);
        }
        $this->assertStringNotContainsString('>Keseluruhan<', $html);
        $this->assertStringContainsString('<h2 class="chapter-x__title" id="pane-title" tabindex="-1">Total</h2>', $html);
        $this->assertStringContainsString('<p class="chapter-x__note">Gabungan suara siswa dan guru.</p>', $html);

        $pemilih = $this->body($this->asAdmin()->get('admin/analitik/jenis-pemilih'));
        $this->assertStringContainsString('data-live-label="Pemilih"', $pemilih);
        $this->assertStringContainsString('<th scope="col">Pemilih</th>', $pemilih);

        $gender = $this->body($this->asAdmin()->get('admin/analitik/jenis-kelamin'));
        $this->assertStringContainsString('data-live-label="Jenis&#x20;Kelamin"', $gender);
        $this->assertStringContainsString('<p class="chapter-x__note">Suara siswa menurut jenis kelamin. Guru tidak termasuk.</p>', $gender);

        $votes = $this->body($this->asAdmin()->get('admin/analitik/suara'));
        $this->assertStringContainsString('<label for="f-type">Pemilih</label>', $votes);
        $this->assertStringContainsString('<label for="f-gender">Jenis Kelamin</label>', $votes);

        // catatan tanpa istilah teknis
        foreach ([$html, $pemilih, $gender, $votes] as $page) {
            preg_match('#<p class="chapter-x__note">(.*?)</p>#s', $page, $note);
            foreach (['jenis_kelamin', 'User-Agent', 'Client Hints', 'tabel', 'LOCKED', 'terkunci', 'Bawaan'] as $technical) {
                $this->assertStringNotContainsString($technical, $note[1], $technical);
            }
        }
    }

    // ------------------------------------------------------------------
    // login admin
    // ------------------------------------------------------------------

    public function testAdminLoginIsSplitScreenWithPasswordToggle(): void
    {
        $result = $this->get('admin/masuk');
        $html   = $this->body($result);

        $result->assertStatus(200);
        $this->assertStringContainsString('<title>Panel Admin — Pemilihan OSIS SMP 1 DAWE</title>', $html);
        $this->assertStringContainsString('<div class="split-login">', $html);
        $this->assertStringContainsString('<h1 class="split-login__title">Panel Admin</h1>', $html);
        $this->assertStringNotContainsString('Masuk ke panel admin', $html);
        // foto surat suara (bukan logo) di panel gambar
        $this->assertStringContainsString('assets/img/auth/admin-login-1600.webp', $html);
        $this->assertStringContainsString('src="' . base_url('assets/img/auth/admin-login-1600.jpg') . '"', $html);
        foreach (['960.jpg', '960.webp', '1600.jpg', '1600.webp'] as $file) {
            $this->assertFileExists(FCPATH . 'assets/img/auth/admin-login-' . $file);
        }
        // tanpa navigasi situs & footer
        $this->assertStringNotContainsString('data-site-nav', $html);
        $this->assertStringContainsString('assets/css/auth.css', $html);

        // lihat kata sandi
        $this->assertStringContainsString('<span class="password-field">', $html);
        $this->assertMatchesRegularExpression('#<input type="password" id="password" name="password" autocomplete="current-password"#', $html);
        $this->assertStringContainsString('<button type="button" class="password-field__toggle" data-password-toggle aria-controls="password" aria-pressed="false" hidden>', $html);
        $this->assertStringContainsString('icon--eye-off', $html);

        $js = $this->asset('assets/js/app.js');
        $this->assertStringContainsString('function initPasswordToggles()', $js);
        $this->assertStringContainsString("input.type = visible ? 'text' : 'password';", $js);
        $this->assertStringContainsString('initPasswordToggles();', $js);

        $css = $this->asset('assets/css/auth.css');
        $this->assertStringContainsString('grid-template-columns: minmax(0, 1.25fr) minmax(420px, 1fr);', $css);
    }

    public function testAdminLoginShowsErrorsAndSetsSessionStamp(): void
    {
        $failed = $this->post('admin/masuk', [csrf_token() => csrf_hash(), 'username' => 'admin', 'password' => 'salah']);
        $failed->assertRedirect();
        $failed->assertSessionHas('error', 'Nama pengguna atau kata sandi tidak sesuai.');

        // pesan flash tampil di panel formulir
        $page = $this->body($this->withSession(['error' => 'Nama pengguna atau kata sandi tidak sesuai.', '__ci_vars' => ['error' => 'new']])->get('admin/masuk'));
        $this->assertStringContainsString('<div class="split-login__alert" role="alert">', $page);
        $this->assertStringContainsString('Nama pengguna atau kata sandi tidak sesuai.', $page);

        $this->post('admin/masuk', [csrf_token() => csrf_hash(), 'username' => 'admin', 'password' => 'admin123'])
            ->assertRedirectTo(site_url('admin'));
        $this->assertSame(AdminModel::stampFor($this->passwordHash()), $_SESSION[AdminModel::SESSION_STAMP_KEY] ?? null);
    }

    // ------------------------------------------------------------------
    // akun admin
    // ------------------------------------------------------------------

    public function testAccountPageListsBothForms(): void
    {
        $result = $this->asAdmin()->get('admin/akun');
        $html   = $this->body($result);

        $result->assertStatus(200);
        $this->assertStringContainsString('<title>Akun Admin — Admin', $html);
        $this->assertStringContainsString('<h1 class="admin-head__title">Akun Admin</h1>', $html);
        $this->assertMatchesRegularExpression('#href="' . preg_quote(site_url('admin/akun'), '#') . '" class="admin-side__link" aria-current="page">#', $html);
        $this->assertStringContainsString('action="' . site_url('admin/akun/nama-pengguna') . '" method="post"', $html);
        $this->assertStringContainsString('action="' . site_url('admin/akun/kata-sandi') . '" method="post"', $html);
        $this->assertStringContainsString('value="admin"', $html);
        $this->assertSame(4, substr_count($html, 'data-password-toggle aria-controls'));
        // hint hanya pada isian kata sandi baru (data view tidak bocor ke isian lain)
        $this->assertSame(1, substr_count($html, 'Minimal 8 karakter.'));
        $this->assertStringContainsString('<span class="crumbs__label">Akun Admin</span>', $html);
        $this->assertStringContainsString('icon--key', $html);
    }

    public function testChangeUsername(): void
    {
        $result = $this->postAdmin('admin/akun/nama-pengguna', ['username' => ' Panitia.OSIS ', 'current_password' => 'admin123']);

        $result->assertRedirectTo(site_url('admin/akun'));
        $result->assertSessionHas('success', 'Nama pengguna diganti menjadi panitia.osis. Gunakan nama ini saat masuk berikutnya.');
        $this->seeInDatabase('admins', ['id' => 1, 'username' => 'panitia.osis']);
        $this->seeInDatabase('audit_logs', ['admin_id' => 1, 'action' => 'ADMIN_USERNAME', 'description' => 'Nama pengguna admin diganti dari @admin menjadi @panitia.osis.']);

        // nama baru dipakai untuk masuk
        $this->assertNotNull(model(AdminModel::class)->verifyCredentials('panitia.osis', 'admin123'));
        $this->assertNull(model(AdminModel::class)->verifyCredentials('admin', 'admin123'));
    }

    public function testChangeUsernameValidation(): void
    {
        $this->db->table('admins')->insert([
            'name' => 'Admin Kedua', 'username' => 'kedua', 'password_hash' => password_hash('rahasia123', PASSWORD_DEFAULT),
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]);

        $cases = [
            [['username' => 'x', 'current_password' => 'admin123'], 'username', 'Nama pengguna 3-50 karakter'],
            [['username' => 'admin', 'current_password' => 'admin123'], 'username', 'sama dengan yang sekarang'],
            [['username' => 'kedua', 'current_password' => 'admin123'], 'username', 'sudah dipakai admin lain'],
            [['username' => 'baru.sekali', 'current_password' => 'salah'], 'current_password', 'Kata sandi saat ini tidak sesuai.'],
            [['username' => 'baru.sekali', 'current_password' => ''], 'current_password', 'Kata sandi saat ini wajib diisi.'],
        ];

        foreach ($cases as [$input, $field, $message]) {
            $result = $this->postAdmin('admin/akun/nama-pengguna', $input);
            $result->assertRedirectTo(site_url('admin/akun'));
            $result->assertSessionHas('account_form', 'username');
            $this->assertStringContainsString($message, $_SESSION['errors'][$field] ?? '', $message);
            // kata sandi tidak pernah disimpan ke sesi
            $this->assertStringNotContainsString('admin123', serialize($_SESSION));
        }

        $this->seeInDatabase('admins', ['id' => 1, 'username' => 'admin']);
        $this->dontSeeInDatabase('audit_logs', ['action' => 'ADMIN_USERNAME']);
    }

    public function testChangePasswordKeepsThisSessionAndSignsOutOthers(): void
    {
        $oldStamp = AdminModel::stampFor($this->passwordHash());

        $result = $this->asAdmin([AdminModel::SESSION_STAMP_KEY => $oldStamp])->post('admin/akun/kata-sandi', [
            csrf_token()           => csrf_hash(),
            'current_password'     => 'admin123',
            'new_password'         => 'Sandi-Baru#2026',
            'new_password_confirm' => 'Sandi-Baru#2026',
        ]);

        $result->assertRedirectTo(site_url('admin/akun'));
        $result->assertSessionHas('success');
        $hash = $this->passwordHash();
        $this->assertTrue(password_verify('Sandi-Baru#2026', $hash));
        $this->assertFalse(password_verify('admin123', $hash));
        $this->seeInDatabase('audit_logs', ['admin_id' => 1, 'action' => 'ADMIN_PASSWORD', 'description' => 'Kata sandi admin diganti.']);
        $this->assertStringNotContainsString('Sandi-Baru', serialize($_SESSION));

        // sesi ini memegang cap baru dan tetap masuk
        $newStamp = AdminModel::stampFor($hash);
        $this->assertNotSame($oldStamp, $newStamp);
        $this->assertSame($newStamp, $_SESSION[AdminModel::SESSION_STAMP_KEY] ?? null);
        $this->asAdmin([AdminModel::SESSION_STAMP_KEY => $newStamp])->get('admin')->assertStatus(200);

        // sesi lain (cap lama) otomatis keluar
        $this->asAdmin([AdminModel::SESSION_STAMP_KEY => $oldStamp])->get('admin')->assertRedirectTo(site_url('admin/masuk'));

        // sesi lama tanpa cap (sebelum Stage 13) tetap berlaku
        $this->asAdmin()->get('admin')->assertStatus(200);
    }

    public function testChangePasswordValidation(): void
    {
        $cases = [
            [['current_password' => 'admin123', 'new_password' => 'pendek', 'new_password_confirm' => 'pendek'], 'new_password', 'minimal 8 karakter'],
            [['current_password' => 'admin123', 'new_password' => str_repeat('a', 73), 'new_password_confirm' => str_repeat('a', 73)], 'new_password', 'terlalu panjang'],
            [['current_password' => 'admin123', 'new_password' => 'Sandi-Baru#2026', 'new_password_confirm' => 'Sandi-Lain#2026'], 'new_password_confirm', 'isi yang sama'],
            [['current_password' => 'admin123', 'new_password' => 'admin123', 'new_password_confirm' => 'admin123'], 'new_password', 'berbeda dari kata sandi saat ini'],
            [['current_password' => 'admin123', 'new_password' => 'ADMIN', 'new_password_confirm' => 'ADMIN'], 'new_password', 'minimal 8 karakter'],
            [['current_password' => 'salah-sekali', 'new_password' => 'Sandi-Baru#2026', 'new_password_confirm' => 'Sandi-Baru#2026'], 'current_password', 'tidak sesuai'],
        ];

        foreach ($cases as [$input, $field, $message]) {
            $result = $this->postAdmin('admin/akun/kata-sandi', $input);
            $result->assertRedirectTo(site_url('admin/akun'));
            $result->assertSessionHas('account_form', 'password');
            $this->assertStringContainsString($message, $_SESSION['errors'][$field] ?? '', $message);
        }

        $this->assertTrue(password_verify('admin123', $this->passwordHash()));
        $this->dontSeeInDatabase('audit_logs', ['action' => 'ADMIN_PASSWORD']);

        // galat tampil di isian yang tepat
        $html = $this->body($this->withSession($this->adminSession([
            'account_form' => 'password',
            'errors'       => ['new_password' => 'Kata sandi baru minimal 8 karakter.'],
            '__ci_vars'    => ['account_form' => 'new', 'errors' => 'new'],
        ]))->get('admin/akun'));
        $this->assertStringContainsString('<p class="field-error" id="err-acc-password-new">Kata sandi baru minimal 8 karakter.</p>', $html);
    }

    public function testWrongCurrentPasswordIsThrottled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postAdmin('admin/akun/kata-sandi', ['current_password' => 'salah', 'new_password' => 'Sandi-Baru#2026', 'new_password_confirm' => 'Sandi-Baru#2026']);
            $this->assertSame('Kata sandi saat ini tidak sesuai.', $_SESSION['errors']['current_password'] ?? null);
        }

        // kuota habis: kata sandi yang benar pun ditolak sementara
        $this->postAdmin('admin/akun/kata-sandi', ['current_password' => 'admin123', 'new_password' => 'Sandi-Baru#2026', 'new_password_confirm' => 'Sandi-Baru#2026']);
        $this->assertStringContainsString('Terlalu banyak percobaan', $_SESSION['errors']['current_password'] ?? '');
        $this->assertTrue(password_verify('admin123', $this->passwordHash()));
    }

    public function testAccountRoutesRequireAdmin(): void
    {
        $this->withSession([])->get('admin/akun')->assertRedirectTo(site_url('admin/masuk'));
        $this->withSession([])->post('admin/akun/kata-sandi', [csrf_token() => csrf_hash(), 'current_password' => 'admin123', 'new_password' => 'Sandi-Baru#2026', 'new_password_confirm' => 'Sandi-Baru#2026'])
            ->assertRedirectTo(site_url('admin/masuk'));
        $this->assertTrue(password_verify('admin123', $this->passwordHash()));
    }
}
