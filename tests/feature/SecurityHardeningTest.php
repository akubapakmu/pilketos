<?php

use App\Controllers\BaseController;
use App\Database\Seeds\DatabaseSeeder;
use App\Filters\AuthFilter;
use App\Services\Import\ImportStore;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\SpreadsheetFactory;
use Tests\Support\UploadFixture;

/**
 * Stage 4: hardening keamanan (04-FINAL... bagian 2).
 *
 * CSP + nonce, header keamanan & no-store, batas idle sesi pemilih, impor
 * yang tidak dapat diproses dua kali (replay POST), kebijakan .htaccess
 * (folder unggahan hanya gambar, folder proyek bukan document root), dan
 * halaman error tanpa kebocoran detail.
 *
 * @internal
 */
final class SecurityHardeningTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private string $storeDir;

    /**
     * @var list<string>
     */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        Services::resetSingle('throttler');
        cache()->clean();
        $this->storeDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'import-replay-' . bin2hex(random_bytes(4));
        Services::injectMock('importStore', new ImportStore($this->storeDir));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storeDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->storeDir);

        foreach ($this->files as $file) {
            @unlink($file);
        }

        UploadFixture::reset();
        Time::setTestNow();
        parent::tearDown();
    }

    private function student(array $extra = []): array
    {
        return ['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true] + $extra;
    }

    private function admin(): array
    {
        return ['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true];
    }

    /**
     * Respons test belum "dikirim"; CSP disusun saat Response::send(), jadi
     * dijalankan manual di sini seperti sebelum dikirim ke browser.
     */
    private function finalized($result)
    {
        $response = $result->response();
        $response->getCSP()->finalize($response);

        return $response;
    }

    // ------------------------------------------------------------------
    // CSP & header
    // ------------------------------------------------------------------

    public function testHtmlPagesCarryStrictCspWithMatchingNonce(): void
    {
        foreach ([[[], '/'], [$this->student(), 'siswa/coblos'], [$this->admin(), 'admin']] as [$session, $path]) {
            $response = $this->finalized($this->withSession($session)->get($path));
            $csp      = $response->getHeaderLine('Content-Security-Policy');
            $body     = (string) $response->getBody();

            $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)'/", $csp, $path);
            $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp, $path);
            $this->assertStringContainsString("script-src-attr 'none'", $csp);
            $this->assertStringContainsString("object-src 'none'", $csp);
            $this->assertStringContainsString("frame-ancestors 'self'", $csp);
            $this->assertStringContainsString("base-uri 'self'", $csp);
            $this->assertStringContainsString("form-action 'self'", $csp);

            // Satu-satunya skrip inline memakai nonce yang sama dengan header.
            preg_match("/'nonce-([A-Za-z0-9+\\/=]+)'/", $csp, $nonce);
            $this->assertStringContainsString('<script nonce="' . $nonce[1] . '">document.documentElement', $body, $path);
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bsrc=)(?![^>]*\bnonce=)[^>]*>/', $body, $path . ': skrip inline tanpa nonce');
            $this->assertDoesNotMatchRegularExpression('/\son(click|load|error|submit|change|input)=/i', $body, $path . ': handler inline');
        }
    }

    public function testSecurityHeadersAndNoStoreOnEveryArea(): void
    {
        foreach ([[[], 'siswa/masuk'], [$this->student(), 'siswa'], [$this->admin(), 'admin/hasil']] as [$session, $path]) {
            $response = $this->withSession($session)->get($path)->response();

            $this->assertSame('SAMEORIGIN', $response->getHeaderLine('X-Frame-Options'), $path);
            $this->assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'), $path);
            $this->assertSame('same-origin', $response->getHeaderLine('Referrer-Policy'), $path);
            $this->assertStringContainsString('camera=()', $response->getHeaderLine('Permissions-Policy'), $path);
            // Stage 15: layar penuh juga boleh untuk pemutar video kampanye (youtube-nocookie).
            $this->assertStringContainsString('fullscreen=(self "https://www.youtube-nocookie.com")', $response->getHeaderLine('Permissions-Policy'), $path);
            $this->assertSame('same-origin', $response->getHeaderLine('Cross-Origin-Opener-Policy'), $path);
            $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'), $path);
        }

        // Endpoint JSON publik juga tidak di-cache (jam server, live count beranda).
        foreach (['jam-server', 'hitung-suara'] as $path) {
            $json = $this->get($path)->response();
            $this->assertStringContainsString('no-store', $json->getHeaderLine('Cache-Control'), $path);
            $this->assertSame('nosniff', $json->getHeaderLine('X-Content-Type-Options'), $path);
        }
    }

    // ------------------------------------------------------------------
    // sesi
    // ------------------------------------------------------------------

    public function testLoginRecordsActivityAndLogoutClearsIdentity(): void
    {
        Time::setTestNow('2026-09-24 08:00:00');
        $this->post('siswa/masuk', [csrf_token() => csrf_hash(), 'nisn' => '0000000001', 'kodeunik' => '05062013']);

        $this->assertSame('student', session('user_type'));
        $this->assertSame(Time::now()->getTimestamp(), session(BaseController::AUTH_SEEN_KEY));

        $this->withSession()->post('siswa/keluar', [csrf_token() => csrf_hash()]);
        $this->assertNull(session('user_type'));
        $this->assertNull(session(BaseController::AUTH_SEEN_KEY));
    }

    public function testIdleVoterSessionExpires(): void
    {
        Time::setTestNow('2026-09-24 10:00:00');
        $stale = Time::now()->getTimestamp() - AuthFilter::VOTER_IDLE_SECONDS - 1;

        $this->withSession($this->student([BaseController::AUTH_SEEN_KEY => $stale]))
            ->get('siswa')
            ->assertRedirectTo(site_url('siswa/masuk'));
        $this->assertSame(AuthFilter::IDLE_MESSAGE, session('error'));
        $this->assertNull(session('student_id'));

        // Permintaan AJAX (mis. kirim suara) mendapat 401 JSON, suara tidak tersimpan.
        $result = $this->withSession($this->student([BaseController::AUTH_SEEN_KEY => $stale]))
            ->withHeaders(['X-CSRF-TOKEN' => csrf_hash(), 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->withBodyFormat('json')
            ->post('siswa/coblos', ['candidate_id' => 1]);
        $result->assertStatus(401);
        $this->assertSame(AuthFilter::IDLE_MESSAGE, json_decode($result->getJSON(), true)['message']);
        $this->assertSame(0, $this->db->table('student_votes')->countAllResults());

        // Guru sama.
        $this->withSession(['user_type' => 'teacher', 'teacher_id' => 1, 'isLoggedIn' => true, BaseController::AUTH_SEEN_KEY => $stale])
            ->withHeaders([])->withBodyFormat('')
            ->get('guru')
            ->assertRedirectTo(site_url('guru/masuk'));
    }

    public function testActiveVoterSessionIsExtendedAndAdminHasNoShortIdle(): void
    {
        Time::setTestNow('2026-09-24 10:00:00');
        $recent = Time::now()->getTimestamp() - AuthFilter::VOTER_IDLE_SECONDS + 60;

        $this->withSession($this->student([BaseController::AUTH_SEEN_KEY => $recent]))->get('siswa')->assertStatus(200);
        $this->assertSame(Time::now()->getTimestamp(), session(BaseController::AUTH_SEEN_KEY));

        // Admin memantau live count lama: tanpa batas idle 15 menit (batas sesi 2 jam tetap berlaku).
        $this->withSession($this->admin() + [BaseController::AUTH_SEEN_KEY => Time::now()->getTimestamp() - 3600])
            ->get('admin')
            ->assertStatus(200);
    }

    // ------------------------------------------------------------------
    // replay POST impor
    // ------------------------------------------------------------------

    public function testImportCommitCannotBeReplayed(): void
    {
        $path          = SpreadsheetFactory::write([
            ['no', 'NISN', 'nama', 'jenis_kelamin', 'kelas', 'nomor_absen', 'kodeunik'],
            [1, '0098765432', 'Siswa Baru', 'L', '7A', 5, '02022013'],
            [2, '0098765433', '', 'P', '7A', 6, '03022013'], // bermasalah: nama kosong
        ]);
        $this->files[] = $path;

        UploadFixture::attach(['file' => ['path' => $path, 'name' => 'siswa.xlsx']]);
        $upload = $this->withSession($this->admin())->post('admin/siswa/impor', [csrf_token() => csrf_hash()]);
        $token  = substr($upload->response()->getHeaderLine('Location'), -32);

        // Tanpa konfirmasi baris bermasalah: ditolak, pratinjau tetap dapat dipakai lagi.
        $this->withSession($this->admin())->post('admin/siswa/impor/simpan', [csrf_token() => csrf_hash(), 'token' => $token])
            ->assertRedirectTo(site_url('admin/siswa/impor/cek/' . $token));
        $this->withSession($this->admin())->get('admin/siswa/impor/cek/' . $token)->assertStatus(200);

        // Impor pertama berhasil.
        $this->withSession($this->admin())->post('admin/siswa/impor/simpan', [csrf_token() => csrf_hash(), 'token' => $token, 'confirm_skip' => '1'])
            ->assertRedirectTo(site_url('admin/siswa/impor/selesai'));
        $this->seeInDatabase('students', ['nisn' => '0098765432']);

        // POST yang sama dikirim ulang: tidak diproses lagi.
        $this->withSession($this->admin())->post('admin/siswa/impor/simpan', [csrf_token() => csrf_hash(), 'token' => $token, 'confirm_skip' => '1'])
            ->assertRedirectTo(site_url('admin/siswa/impor'));
        $this->assertStringContainsString('sudah diimpor', (string) session('error'));

        $this->assertSame(1, $this->db->table('students')->where('nisn', '0098765432')->countAllResults());
        $this->assertSame(1, $this->db->table('audit_logs')->where('action', 'IMPORT_STUDENT')->countAllResults());
    }

    public function testImportClaimIsAtomicPerToken(): void
    {
        $store = new ImportStore($this->storeDir);
        $token = $store->save(1, \App\Services\VoterType::Student, ['rows' => []]);

        $this->assertNotNull($store->claim($token, 1, \App\Services\VoterType::Student));
        $this->assertNull($store->claim($token, 1, \App\Services\VoterType::Student), 'klaim kedua harus gagal');
        $this->assertNull($store->load($token, 1, \App\Services\VoterType::Student));

        $store->release($token);
        $this->assertNotNull($store->load($token, 1, \App\Services\VoterType::Student));

        $store->delete($token);
        $this->assertNull($store->claim($token, 1, \App\Services\VoterType::Student));
    }

    // ------------------------------------------------------------------
    // konfigurasi server & halaman error
    // ------------------------------------------------------------------

    public function testWebServerRulesProtectProjectAndUploads(): void
    {
        $uploads = (string) file_get_contents(FCPATH . 'uploads/.htaccess');
        $this->assertStringContainsString('Require all denied', $uploads);
        $this->assertMatchesRegularExpression('/FilesMatch "\(\?i\)\^\[a-z0-9\].*\(jpe\?g\|png\|webp\)\$"/', $uploads);
        $this->assertStringContainsString('php_flag engine off', $uploads);
        $this->assertStringContainsString('X-Content-Type-Options "nosniff"', $uploads);

        $root = (string) file_get_contents(ROOTPATH . '.htaccess');
        $this->assertStringContainsString('RewriteRule ^(.*)$ public/$1 [L]', $root);
        $this->assertStringContainsString('<FilesMatch "^\.">', $root);

        foreach (['app', 'writable', 'tests'] as $dir) {
            $this->assertStringContainsString('Require all denied', (string) file_get_contents(ROOTPATH . $dir . '/.htaccess'), $dir);
        }

        $robots = (string) file_get_contents(FCPATH . 'robots.txt');
        $this->assertStringContainsString('Disallow: /admin', $robots);
    }

    public function testProductionErrorPageRevealsNothingTechnical(): void
    {
        $html = view('errors/html/production', ['message' => 'SQLSTATE[42S02] rahasia teknis', 'exception' => null]);

        $this->assertStringContainsString('Maaf, terjadi kesalahan', $html);
        $this->assertStringContainsString('lang="id"', $html);
        $this->assertStringNotContainsString('SQLSTATE', $html);
        $this->assertStringNotContainsString('<script', $html);
    }
}
