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
 * Stage 16: satu tampilan untuk semua pasangan. Pembuka = satu gambar
 * landscape buatan paslon (banner) + tombol di bawahnya, navigasi bagian,
 * kepala bagian berikon, Pesan terpisah dari penutup, video dengan thumbnail
 * asli YouTube, penutup bergambar tanpa warna aksen.
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
        // Tanpa gambar pembuka: judul sederhana, satu h1 berisi kedua nama
        // ("dan" untuk pembaca layar), slogan, lalu tombol di bawahnya.
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertMatchesRegularExpression('/<h1 class="px-cover__names"[^>]*>.*Arka Wibisana.*<span class="visually-hidden"> dan <\/span>.*Naya Kirana.*<\/h1>/s', $html);
        $page->assertSee('<p class="px-cover__no">Paslon <strong>01</strong></p>');
        $page->assertSee('Calon Ketua');
        $page->assertSee('Calon Wakil');
        $page->assertSee('<p class="px-cover__slogan">“Bersama berkarya, bersama berprestasi.”</p>');
        $this->assertLessThan(strpos($html, '<a class="px-cta" href="#visi">Lihat Visi &amp; Misi'), strpos($html, 'class="px-cover__plain"'));

        // Kenali pasangan: bio + kata karakter dari data (maks. 3).
        $page->assertSee('Kenali pasangan');
        $page->assertSee('Dua individu, satu tujuan untuk SMP 1 DAWE.');
        $page->assertSee('Siswa kelas 8 yang aktif di pramuka dan majalah dinding.');
        $page->assertSee('<li>Bertanggung jawab</li>');

        // Visi & misi milik pasangan, program bernomor, pesan, penutup.
        $page->assertSee('Visi kami');
        $page->assertSee('Misi kami');
        $page->assertSee('<h3 class="px-programs__title">Student Voice</h3>');
        $page->assertSee('<p class="px-programs__text">Kotak aspirasi daring dan temu OSIS tiap bulan.</p>');
        $page->assertSee('Pesan dari kami');
        $page->assertSee('OSIS bukan milik pengurus saja.');
        $page->assertSee('class="px-finale"');

        // Kepala bagian berikon (judul dikapitalkan CSS, teks tetap apa adanya).
        foreach (['users' => 'Kenali pasangan', 'eye' => 'Visi kami', 'target' => 'Misi kami', 'bulb' => 'Program unggulan kami', 'message' => 'Pesan dari kami'] as $icon => $title) {
            $this->assertMatchesRegularExpression('/<span class="px-head__icon"><svg class="icon[^"]*icon--' . $icon . '"[^>]*>.*?<\/svg><\/span><div class="px-head__text"><h2 class="px-head__title" id="px-[a-z]+-1">' . $title . '<\/h2>/s', $html, $title);
        }

        // Navigasi bagian hanya untuk bagian yang ada, urut seperti halaman.
        $page->assertSee('class="px-shell px-shell--nav"');
        $page->assertSee('<p class="px-nav__title" aria-hidden="true">Paslon <strong>01</strong></p>');
        $positions = [];
        foreach (['profil' => 'Profil', 'visi' => 'Visi', 'misi' => 'Misi', 'program' => 'Program', 'pesan' => 'Pesan'] as $id => $label) {
            $page->assertSee('href="#' . $id . '" data-px-nav-link');
            $page->assertSee('<span class="px-nav__label">' . $label . '</span>');
            $positions[] = strpos($html, 'id="' . $id . '"');
        }
        $this->assertNotContains(false, $positions);
        $this->assertSame($positions, array_values(array_unique($positions)));
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'Profil, Visi, Misi, Program, Pesan');

        // Pesan adalah bagian sendiri; penutup berada di luar kolom isi.
        $this->assertMatchesRegularExpression('/<section class="px-section px-pesan" id="pesan"/', $html);
        $this->assertLessThan(strpos($html, '<section class="px-close'), strpos($html, 'id="pesan"'));
        foreach (['href="#poster"', 'href="#video"', 'data-px-lightbox', 'data-px-video', 'px-close--image', 'px-cover--image'] as $gone) {
            $page->assertDontSee($gone);
        }
    }

    public function testOneLayoutForEveryPairWithoutStageArtwork(): void
    {
        foreach (['01', '02', '03'] as $label) {
            $page = $this->withSession(self::STUDENT)->get('siswa/paslon/' . $label);

            $page->assertSee('<article class="paslon" style="--accent: ');
            foreach (['paslon--', 'px-hero', 'px-duo', 'portraits--xl', 'pattern', 'texture', 'px-hero__numeral', 'data-px-parallax', 'data-px-words', 'px-hero__theme'] as $gone) {
                $page->assertDontSee($gone);
            }
        }

        // Nama tema tidak tampil di profil (pembukanya kini gambar paslon).
        $this->withSession(self::STUDENT)->get('siswa/paslon/02')->assertDontSee('Deep Forest');
    }

    public function testBannerImageReplacesTheOpeningAndTheButtonSitsBelowIt(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 1)->update(['theme_asset' => json_encode(['banner' => 'c01-banner-a.webp'])]);

        $page = $this->withSession(self::STUDENT)->get('siswa/paslon/01');
        $html = (string) $page->response()->getBody();
        $url  = base_url('uploads/candidates/c01-banner-a.webp');

        $page->assertSee('<header class="px-cover px-cover--image">');
        // Judul tetap ada untuk pembaca layar; tampilan = gambar saja.
        $this->assertSame(1, substr_count($html, '<h1'));
        $page->assertSee('<h1 class="visually-hidden" id="px-title-1">Paslon 01: Arka Wibisana dan Naya Kirana</h1>');
        $page->assertSee('<img class="px-cover__img" src="' . $url . '"');
        $page->assertSee('alt="Gambar pembuka pasangan 01: “Bersama berkarya, bersama berprestasi.”"');
        $page->assertSee('fetchpriority="high"');
        // File tidak ada di folder upload: tanpa atribut ukuran.
        $this->assertDoesNotMatchRegularExpression('/<img class="px-cover__img"[^>]*width=/', $html);
        foreach (['px-cover__plain', 'px-cover__names', 'px-cover__slogan'] as $gone) {
            $page->assertDontSee($gone);
        }
        $cover = strpos($html, '<header class="px-cover');
        $this->assertLessThan(strpos($html, 'class="px-cta"'), strpos($html, 'class="px-cover__img"'));
        $this->assertLessThan(strpos($html, '</header>', $cover), strpos($html, 'class="px-cta"'));
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
        // Kenali pasangan selalu ada (nama, peran, monogram), tanpa bio & karakter.
        $page->assertSee('Kenali pasangan');
        $page->assertSee('<p class="px-meet__role">Calon Wakil Ketua OSIS</p>');
        $page->assertSee('<span class="px-meet__mono" aria-hidden="true">BP</span>');
        // Tanpa visi: tombol pembuka menuju profil.
        $page->assertSee('<a class="px-cta" href="#profil">Kenali lebih dekat');
        foreach (['Visi kami', 'Misi kami', 'Program unggulan kami', 'Pesan dari kami', 'px-meet__bio', 'px-tags', 'data-px-nav', 'px-shell--nav', 'px-cover__slogan', 'px-finale__slogan', 'belum diisi'] as $gone) {
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

        $page->assertSee('px-cover__name px-cover__name--long');
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

        $page->assertSee('Poster kampanye');
        $page->assertSee('href="#poster" data-px-nav-link');
        $page->assertSee('<section class="px-section px-poster" id="poster"');
        $page->assertSee('<img class="px-poster__img" src="' . $url . '"');
        $page->assertSee('href="' . $url . '" target="_blank" rel="noopener" data-px-zoom');
        $page->assertSee('data-px-lightbox');
        $page->assertDontSee('href="#video"');
    }

    public function testMediaSectionsFollowTheReferenceOrder(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 1)->update([
            'theme_asset'   => json_encode(['poster' => 'poster-01.webp']),
            'video_youtube' => 'dQw4w9WgXcQ',
        ]);

        $html = (string) $this->withSession(self::STUDENT)->get('siswa/paslon/01')->getBody();

        $order = array_map(static fn (string $id): int|false => strpos($html, 'id="' . $id . '"'), ['program', 'poster', 'pesan', 'video']);
        $this->assertNotContains(false, $order);
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order, 'Program, Poster, Pesan, Video');

        $labels = [];
        preg_match_all('/<span class="px-nav__label">([^<]+)<\/span>/', $html, $labels);
        $this->assertSame(['Profil', 'Visi', 'Misi', 'Program', 'Poster', 'Pesan', 'Video'], $labels[1]);
    }

    public function testVideoShowsTheRealThumbnailAndOnlyThatPageAllowsYoutube(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 1)->update(['video_youtube' => 'dQw4w9WgXcQ']);

        $result = $this->withSession(self::STUDENT)->get('siswa/paslon/01');
        $body   = (string) $result->getBody();

        $result->assertSee('Video kampanye');
        $result->assertSee('<section class="px-section px-media" id="video"');
        $result->assertSee('data-embed="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?autoplay=1&amp;rel=0"');
        // Thumbnail asli YouTube + tombol putar selalu tampil (tanpa JS: tautan ke YouTube).
        $result->assertSee('<a class="px-video__facade" href="https://www.youtube.com/watch?v=dQw4w9WgXcQ" target="_blank" rel="noopener noreferrer" data-px-play>');
        $result->assertSee('<img class="px-video__thumb" src="https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg" data-hd="https://i.ytimg.com/vi/dQw4w9WgXcQ/maxresdefault.jpg" alt=""');
        $result->assertSee('<span class="px-video__play" aria-hidden="true"><svg class="icon icon--play"');
        $result->assertSee('Putar video kampanye pasangan 01');
        $this->assertStringNotContainsString('data-px-play hidden', $body);
        $this->assertStringNotContainsString('<iframe', $body);
        $this->assertStringNotContainsString('px-video__numeral', $body);

        $csp = $this->csp($result);
        $this->assertStringContainsString('frame-src https://www.youtube-nocookie.com', $csp);
        $this->assertStringNotContainsString("frame-src 'none'", $csp);
        $this->assertMatchesRegularExpression('/img-src [^;]*https:\/\/i\.ytimg\.com/', $csp);

        // Halaman lain (tanpa video) tetap frame-src 'none' dan tanpa i.ytimg.com.
        // Di server tiap request memakai objek CSP baru; di test service bersama di-reset.
        foreach (['siswa/paslon/02', 'siswa/coblos'] as $path) {
            Services::resetSingle('csp');
            Services::resetSingle('response');
            $other = $this->csp($this->withSession(self::STUDENT)->get($path));
            $this->assertStringContainsString("frame-src 'none'", $other, $path);
            $this->assertStringNotContainsString('i.ytimg.com', $other, $path);
        }
    }

    // ------------------------------------------------------------------
    // penutup: gambar latar khusus, tanpa warna aksen
    // ------------------------------------------------------------------

    public function testClosingUsesTheCustomBackgroundImage(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 1)->update(['theme_asset' => json_encode(['closing' => 'c01-closing-a.webp'])]);

        $page = $this->withSession(self::STUDENT)->get('siswa/paslon/01');

        $page->assertSee('<section class="px-close px-close--image" aria-labelledby="px-close-1">');
        $page->assertSee('<img class="px-close__bg" src="' . base_url('uploads/candidates/c01-closing-a.webp') . '" alt="" loading="lazy"');
        $page->assertSee('<h2 class="visually-hidden" id="px-close-1">Penutup profil pasangan 01</h2>');
        $page->assertSee('<span class="px-finale__role"><span class="visually-hidden">, </span>Calon Wakil Ketua OSIS</span>');
        $page->assertSee('<p class="px-finale__slogan">“Bersama berkarya, bersama berprestasi.”</p>');

        // Tanpa gambar: latar netral (tanpa kelas gambar), isi sama.
        $plain = $this->withSession(self::STUDENT)->get('siswa/paslon/03');
        $plain->assertSee('<section class="px-close" aria-labelledby="px-close-3">');
        $plain->assertDontSee('px-close__bg');
    }

    public function testClosingStylesNeverUseTheAccentColor(): void
    {
        $css = (string) file_get_contents(FCPATH . 'assets/css/paslon.css');

        preg_match_all('/([^{}]*\.px-(?:close|finale|others)[^{}]*)\{([^}]*)\}/', $css, $rules, PREG_SET_ORDER);
        $this->assertGreaterThan(10, count($rules));

        foreach ($rules as [, $selector, $body]) {
            $this->assertStringNotContainsString('--accent', $body, trim($selector));
            $this->assertStringNotContainsString('--px-soft', $body, trim($selector));
        }

        // Tidak ada garis dekoratif lagi di halaman paslon.
        $this->assertDoesNotMatchRegularExpression('/border(-top|-bottom|-left|-right)?\s*:\s*(?!0)[^;]*solid/', $css);
    }

    // ------------------------------------------------------------------
    // pratinjau admin = partial yang sama
    // ------------------------------------------------------------------

    public function testAdminPreviewUsesTheSameProfileIncludingInactivePairs(): void
    {
        $this->db->table('candidates')->where('nomor_urut', 3)->update(['status_aktif' => 0, 'video_youtube' => 'dQw4w9WgXcQ']);

        $result = $this->withSession(self::ADMIN)->get('admin/paslon/3/intip');

        $result->assertStatus(200);
        $result->assertSee('<article class="paslon" style="--accent: #1B3A6B;');
        $result->assertDontSee('paslon--');
        $result->assertSee('Pasangan ini nonaktif');
        $result->assertSee('Ubah data paslon');
        $result->assertDontSee('Pilih Paslon 03');
        $this->assertStringContainsString('frame-src https://www.youtube-nocookie.com', $this->csp($result));
        $this->assertStringContainsString('https://i.ytimg.com', $this->csp($result));
    }
}
