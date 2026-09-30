<?php

use App\Database\Seeds\DatabaseSeeder;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

/**
 * Stage 15: halaman profil paslon (siswa|guru/paslon/0X) dan pratinjau admin
 * yang memakai partial yang sama (paslon/partials/profile).
 *
 * @internal
 */
final class PaslonProfileTest extends CIUnitTestCase
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

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    private function scheduleAt(string $now): void
    {
        $this->db->table('elections')->where('id', 1)->update(['start_at' => '2026-10-01 07:00:00', 'end_at' => '2026-10-01 12:00:00']);
        Time::setTestNow($now);
    }

    /**
     * CSP disusun saat respons dikirim (Response::send()); di test dijalankan
     * manual seperti SecurityHardeningTest.
     */
    private function csp($result): string
    {
        $response = $result->response();
        $response->getCSP()->finalize($response);

        return $response->getHeaderLine('Content-Security-Policy');
    }

    // ------------------------------------------------------------------
    // akses & route
    // ------------------------------------------------------------------

    public function testProfileRequiresTheMatchingVoterLogin(): void
    {
        $this->get('siswa/paslon/01')->assertRedirectTo(site_url('siswa/masuk'));
        $this->get('guru/paslon/01')->assertRedirectTo(site_url('guru/masuk'));
        $this->withSession(self::STUDENT)->get('guru/paslon/01')->assertRedirectTo(site_url('guru/masuk'));

        $this->withSession(self::STUDENT)->get('siswa/paslon/01')->assertStatus(200);
        $this->withSession(self::TEACHER)->get('guru/paslon/1')->assertStatus(200);
    }

    public function testUnknownNumberIs404(): void
    {
        $this->expectException(PageNotFoundException::class);
        $this->withSession(self::STUDENT)->get('siswa/paslon/09');
    }

    public function testInactivePairIs404ForVoters(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 3)->update(['status_aktif' => 0]);

        $this->expectException(PageNotFoundException::class);
        $this->withSession(self::STUDENT)->get('siswa/paslon/03');
    }

    // ------------------------------------------------------------------
    // isi halaman
    // ------------------------------------------------------------------

    public function testProfileShowsThePairAsOneTeam(): void
    {
        $page = $this->withSession(self::STUDENT)->get('siswa/paslon/01');
        $html = (string) $page->response()->getBody();

        $this->assertStringContainsString('<title>Paslon 01: Arka Wibisana &amp; Naya Kirana', $html);
        // Satu h1 berisi kedua nama (dan "dan" untuk pembaca layar).
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('/<h1 class="px-hero__names"[^>]*>.*Arka Wibisana.*<span class="visually-hidden"> dan <\/span>.*Naya Kirana.*<\/h1>/s', $html);
        $page->assertSee('<span class="px-hero__tag">Paslon <strong>01</strong></span>');
        $page->assertSee('Calon Ketua');
        $page->assertSee('Calon Wakil');
        $page->assertSee('“Bersama berkarya, bersama berprestasi.”');

        // Tanpa foto berdua: dua potret satu komposisi dengan tanda x.
        $page->assertSee('portraits portraits--xl');
        $this->assertStringContainsString('<span class="px-duo__x" aria-hidden="true">&times;</span>', $html);

        // Kenali pasangan: bio + kata karakter dari data (maks. 3).
        $page->assertSee('Kenali pasangan');
        $page->assertSee('Siswa kelas 8 yang aktif di pramuka dan majalah dinding.');
        $page->assertSee('<li>Bertanggung jawab</li>');

        // Visi & misi milik pasangan, program bernomor, pesan + penutup.
        $page->assertSee('Visi kami');
        $page->assertSee('Misi kami');
        $page->assertSee('<h3 class="px-programs__title">Student Voice</h3>');
        $page->assertSee('<p class="px-programs__text">Kotak aspirasi daring dan temu OSIS tiap bulan.</p>');
        $page->assertSee('Pesan dari kami');
        $page->assertSee('OSIS bukan milik pengurus saja.');

        // Navigasi bagian hanya untuk bagian yang ada (tanpa poster/video).
        foreach (['profil' => 'Profil', 'visi' => 'Visi', 'misi' => 'Misi', 'program' => 'Program', 'pesan' => 'Pesan'] as $id => $label) {
            $page->assertSee('href="#' . $id . '" data-px-nav-link');
            $page->assertSee('id="' . $id . '"');
        }
        $page->assertDontSee('href="#media"');
        $page->assertDontSee('data-px-lightbox');
        $page->assertDontSee('data-px-video');
    }

    public function testIncompleteDataHidesEmptySectionsSilently(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 2)->update([
            'slogan' => null, 'bio_ketua' => null, 'bio_wakil' => null, 'karakter_ketua' => null,
            'karakter_wakil' => null, 'program' => null, 'pesan_ketua' => null, 'pesan_wakil' => null,
            'visi' => null, 'misi' => null,
        ]);

        $page = $this->withSession(self::STUDENT)->get('siswa/paslon/02');

        $page->assertStatus(200);
        $page->assertSee('Bagas Prayoga');
        $page->assertSee('Citra Maheswari');
        foreach (['Kenali pasangan', 'Visi kami', 'Misi kami', 'Program unggulan kami', 'Pesan dari kami', 'data-px-nav', 'px-hero__slogan', 'belum diisi'] as $gone) {
            $page->assertDontSee($gone);
        }
        // Penutup & deret nomor paslon tetap ada.
        $page->assertSee('class="px-finale"');
        $page->assertSee('aria-label="Profil paslon lain"');
    }

    public function testLongTextsGetSmallerTypeClasses(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 1)->update([
            'nama_ketua' => 'Muhammad Rizky Ramadhani Prasetyo',
            'visi'       => str_repeat('Sekolah yang ramah, aktif, dan kreatif untuk semua siswa. ', 5),
        ]);

        $page = $this->withSession(self::STUDENT)->get('siswa/paslon/01');

        $page->assertSee('px-person__name px-person__name--long');
        $page->assertSee('px-visi__text px-visi__text--long');
    }

    public function testCandidateTextIsEscaped(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 1)->update([
            'slogan'  => '<script>alert(1)</script>',
            'program' => '<img src=x onerror=alert(1)> — "><b>',
            'pesan_ketua' => '<svg onload=alert(1)>',
        ]);

        $body = $this->withSession(self::STUDENT)->get('siswa/paslon/01')->getBody();

        $this->assertStringNotContainsString('<script>alert(1)', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
        $this->assertStringNotContainsString('<svg onload', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    // ------------------------------------------------------------------
    // tombol penutup mengikuti keadaan pemilih
    // ------------------------------------------------------------------

    public function testOpenVotingOffersPickingThisPairOnTheBallot(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');

        $page = $this->withSession(self::STUDENT)->get('siswa/paslon/02');

        $page->assertSee('href="' . site_url('siswa/coblos') . '#coblos-02"');
        $page->assertSee('Pilih Paslon 02');
        $page->assertSee('href="' . site_url('siswa/coblos') . '#sekilas"');
        // Deret nomor setara, paslon yang dibuka ditandai.
        foreach (['01', '02', '03'] as $label) {
            $page->assertSee('href="' . site_url('siswa/paslon/' . $label) . '"');
        }
        $page->assertSee('href="' . site_url('siswa/paslon/02') . '" aria-current="page"');
    }

    public function testClosedOrUpcomingVotingOnlyLinksBackToTheList(): void
    {
        foreach (['2026-09-30 08:00:00', '2026-10-02 00:00:00'] as $now) {
            $this->scheduleAt($now);

            $page = $this->withSession(self::TEACHER)->get('guru/paslon/01');
            $page->assertDontSee('Pilih Paslon');
            $page->assertDontSee('#coblos-01');
            $page->assertSee('href="' . site_url('guru/coblos') . '#sekilas"');
        }
    }

    public function testVoterWhoAlreadyVotedSeesOwnChoiceLink(): void
    {
        $this->scheduleAt('2026-10-01 09:00:00');
        $this->db->table('student_votes')->insert([
            'election_id' => 1, 'student_id' => 1, 'candidate_id' => 3, 'status' => 'LOCKED', 'voted_at' => '2026-10-01 08:00:00',
        ]);

        $page = $this->withSession(self::STUDENT)->get('siswa/paslon/01');

        $page->assertStatus(200);
        $page->assertDontSee('Pilih Paslon');
        $page->assertSee('href="' . site_url('siswa/pilihanku') . '"');
        // Tidak ada data suara/pilihan di halaman profil.
        $page->assertDontSee('suara sah');
    }

    // ------------------------------------------------------------------
    // media: poster (lightbox) & video (YouTube, CSP per halaman)
    // ------------------------------------------------------------------

    public function testPosterOpensInLightboxWithPlainLinkFallback(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 1)->update(['theme_asset' => json_encode(['poster' => 'poster-01.webp'])]);

        $page = $this->withSession(self::STUDENT)->get('siswa/paslon/01');
        $url  = base_url('uploads/candidates/poster-01.webp');

        $page->assertSee('Poster paslon');
        $page->assertSee('href="#media" data-px-nav-link');
        $page->assertSee('<img class="px-poster__img" src="' . $url . '"');
        $page->assertSee('href="' . $url . '" target="_blank" rel="noopener" data-px-zoom');
        $page->assertSee('data-px-lightbox');
    }

    public function testVideoIsClickToLoadAndOnlyThatPageAllowsTheYoutubeFrame(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 1)->update(['video_youtube' => 'dQw4w9WgXcQ']);

        $result = $this->withSession(self::STUDENT)->get('siswa/paslon/01');
        $body   = (string) $result->getBody();

        $result->assertSee('Video kampanye');
        $result->assertSee('data-embed="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&amp;rel=0"');
        $result->assertSee('href="https://www.youtube.com/watch?v=dQw4w9WgXcQ"');
        // Tombol putar tersembunyi tanpa JS; iframe tidak ada sebelum diklik.
        $result->assertSee('data-px-play hidden');
        $this->assertStringNotContainsString('<iframe', $body);

        $this->assertStringContainsString('frame-src https://www.youtube-nocookie.com', $this->csp($result));
        $this->assertStringNotContainsString("frame-src 'none'", $this->csp($result));

        // Halaman lain (tanpa video) tetap frame-src 'none'. Di server tiap
        // request memakai objek CSP baru; di test service bersama di-reset.
        foreach (['siswa/paslon/02', 'siswa/coblos'] as $path) {
            Services::resetSingle('csp');
            Services::resetSingle('response');
            $this->assertStringContainsString("frame-src 'none'", $this->csp($this->withSession(self::STUDENT)->get($path)), $path);
        }
    }

    // ------------------------------------------------------------------
    // pratinjau admin = partial yang sama
    // ------------------------------------------------------------------

    public function testAdminPreviewUsesTheSameProfileIncludingInactivePairs(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 3)->update(['status_aktif' => 0, 'video_youtube' => 'dQw4w9WgXcQ']);

        $result = $this->withSession(self::ADMIN)->get('admin/paslon/3/intip');

        $result->assertStatus(200);
        $result->assertSee('class="paslon paslon--column"');
        $result->assertSee('Pasangan ini nonaktif');
        $result->assertSee('Ubah data paslon');
        $result->assertDontSee('Pilih Paslon 03');
        $this->assertStringContainsString('frame-src https://www.youtube-nocookie.com', $this->csp($result));
    }
}
