<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\CandidateAssets;
use App\Models\CandidateModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\UploadFixture;

/**
 * Stage 3: kelola pasangan calon + unggah tema lewat HTTP, dan bukti bahwa
 * tema yang diunggah admin dipakai halaman kandidat pemilih (Stage 2).
 *
 * File disimpan ke folder sementara (service candidateAssets di-mock),
 * bukan ke public/uploads/candidates.
 *
 * Stage 15: halaman kandidat pemilih = profil paslon (siswa/paslon/0X);
 * fixture memakai batas 4 pasangan aktif agar pasangan ke-4 dapat ditambah
 * (penolakan melewati batas diuji CandidateLimitTest).
 *
 * @internal
 */
final class AdminCandidateTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private string $dir;

    /**
     * @var list<string>
     */
    private array $sources = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cand-http-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        Services::injectMock('candidateAssets', new CandidateAssets($this->dir));

        $this->db->table('elections')->update(['batas_pasangan' => 4]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir);

        foreach ($this->sources as $file) {
            @unlink($file);
        }

        UploadFixture::reset();
        Time::setTestNow();
        parent::tearDown();
    }

    private function admin(): array
    {
        return ['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true];
    }

    private function image(int $width, int $height, string $type = 'png'): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 40, 90, 160));
        $path = tempnam(sys_get_temp_dir(), 'up');
        $type === 'png' ? imagepng($image, $path) : imagejpeg($image, $path);

        return $this->sources[] = $path;
    }

    /**
     * @param array<string, array{path: string, name: string}> $files
     */
    private function submit(string $path, array $fields, array $files = [])
    {
        UploadFixture::attach($files);

        return $this->withSession($this->admin())->post($path, [csrf_token() => csrf_hash()] + $fields);
    }

    private function fields(array $override = []): array
    {
        return $override + [
            'nomor_urut'   => '4',
            'nama_ketua'   => 'Gilang Ramadhan',
            'nama_wakil'   => 'Intan Permata',
            'visi'         => 'Sekolah yang ramah untuk semua.',
            'misi'         => "1. Satu\n2. Dua",
            'theme_name'   => 'Ochre',
            'theme_accent' => 'b8860b',
            'theme_layout' => 'poster',
            'status_aktif' => '1',
        ];
    }

    private function stored(): array
    {
        return array_map('basename', glob($this->dir . '/*') ?: []);
    }

    public function testCandidateListShowsThemeAndAssetCompleteness(): void
    {
        $result = $this->withSession($this->admin())->get('admin/paslon');

        $result->assertStatus(200);
        foreach (['Arka Wibisana', 'Bagas Prayoga', 'Dewi Anggraini', 'Terracotta', '#C4432B', 'Poster', '0 / 7'] as $text) {
            $result->assertSee($text);
        }
        $result->assertSee('href="' . site_url('admin/paslon/1/ubah') . '"');
        $result->assertSee('href="' . site_url('admin/paslon/1/intip') . '"');
    }

    public function testCreateCandidateWithUploadsStoresRandomNamesAndAudits(): void
    {
        $result = $this->submit('admin/paslon', $this->fields(), [
            'asset_foto_ketua' => ['path' => $this->image(480, 600), 'name' => '../../foto ketua.php.png'],
            'asset_texture'    => ['path' => $this->image(200, 200), 'name' => 'pola.png'],
        ]);

        $result->assertRedirectTo(site_url('admin/paslon'));

        $row = model(CandidateModel::class)->where('nomor_urut', 4)->first();
        $this->assertNotNull($row);
        $this->assertSame('#B8860B', $row['theme_accent']);
        $this->assertSame('poster', $row['theme_layout']);
        $ext = CandidateAssets::outputExtension('image/png'); // webp, atau png bila GD tanpa WebP
        $this->assertMatchesRegularExpression('/^c04-foto-ketua-[a-f0-9]{16}\.' . $ext . '$/', $row['foto_ketua']);
        $this->assertSame(['texture'], array_keys($row['theme_asset']));
        $this->assertMatchesRegularExpression('/^c04-texture-[a-f0-9]{16}\.' . $ext . '$/', $row['theme_asset']['texture']);
        $this->assertEqualsCanonicalizing([$row['foto_ketua'], $row['theme_asset']['texture']], $this->stored());

        $audit = $this->db->table('audit_logs')->where('action', 'CANDIDATE_CREATE')->get()->getRowArray();
        $this->assertStringContainsString('Pasangan 04 (Gilang Ramadhan & Intan Permata)', $audit['description']);
        $this->assertStringContainsString('foto calon ketua diunggah', $audit['description']);
    }

    public function testUploadedThemeIsUsedByVoterCandidatePage(): void
    {
        $this->submit('admin/paslon/2', $this->fields([
            'nomor_urut'   => '2',
            'nama_ketua'   => 'Bagas Prayoga',
            'nama_wakil'   => 'Citra Maheswari',
            'theme_accent' => '#1F6F5C',
            'theme_layout' => 'split',
        ]), [
            'asset_foto_wakil' => ['path' => $this->image(400, 500, 'jpg'), 'name' => 'wakil.jpg'],
            'asset_hero'       => ['path' => $this->image(1200, 900, 'jpg'), 'name' => 'hero.jpg'],
            'asset_background' => ['path' => $this->image(1600, 900, 'jpg'), 'name' => 'bg.jpg'],
            'asset_artwork'    => ['path' => $this->image(600, 600), 'name' => 'art.png'],
            'asset_poster'     => ['path' => $this->image(600, 900), 'name' => 'poster.png'],
        ])->assertRedirectTo(site_url('admin/paslon'));

        $row = model(CandidateModel::class)->find(2);
        $this->assertSame(['hero', 'artwork', 'poster'], array_keys($row['theme_asset']));

        $profile = $this->withSession(['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true])->get('siswa/paslon/02');

        $profile->assertStatus(200);
        $profile->assertSee('--accent: #1F6F5C;');
        $profile->assertSee('class="paslon paslon--split"');
        foreach ([$row['foto_wakil'], $row['theme_background'], $row['theme_asset']['hero'], $row['theme_asset']['artwork'], $row['theme_asset']['poster']] as $file) {
            $profile->assertSee(base_url('uploads/candidates/' . $file));
        }
        $profile->assertSee('Poster kampanye pasangan 02');

        // Sekilas paslon di bilik suara memakai foto yang sama dan menaut ke profil.
        $ballot = $this->withSession(['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true])->get('siswa/coblos');
        $ballot->assertSee(base_url('uploads/candidates/' . $row['foto_wakil']));
        $ballot->assertSee('href="' . site_url('siswa/paslon/02') . '"');
    }

    public function testReplacingAndRemovingAssetsDeletesOldFiles(): void
    {
        $this->submit('admin/paslon/1', $this->fields(['nomor_urut' => '1', 'nama_ketua' => 'Arka Wibisana', 'nama_wakil' => 'Naya Kirana']), [
            'asset_foto_ketua' => ['path' => $this->image(300, 300), 'name' => 'a.png'],
            'asset_poster'     => ['path' => $this->image(500, 700), 'name' => 'p.png'],
        ]);
        $first = model(CandidateModel::class)->find(1);

        $this->submit('admin/paslon/1', $this->fields([
            'nomor_urut'   => '1',
            'nama_ketua'   => 'Arka Wibisana',
            'nama_wakil'   => 'Naya Kirana',
            'theme_accent' => '#C4432B',
            'remove'       => ['poster'],
        ]), [
            'asset_foto_ketua' => ['path' => $this->image(320, 320), 'name' => 'b.png'],
        ])->assertRedirectTo(site_url('admin/paslon'));

        $second = model(CandidateModel::class)->find(1);
        $this->assertNotSame($first['foto_ketua'], $second['foto_ketua']);
        $this->assertNull($second['theme_asset']);
        $this->assertSame([$second['foto_ketua']], $this->stored());

        $audit = $this->db->table('audit_logs')->where('action', 'CANDIDATE_UPDATE')->orderBy('id', 'DESC')->get()->getRowArray();
        $this->assertStringContainsString('foto calon ketua diganti', $audit['description']);
        $this->assertStringContainsString('poster kampanye dihapus', $audit['description']);
        $this->assertStringContainsString('warna aksen', $audit['description']);
    }

    public function testInvalidFieldsAndFilesAreRejectedWithoutSavingAnything(): void
    {
        $php = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($php, '<?php echo "pwned"; ?>');
        $this->sources[] = $php;

        // Nomor urut dipakai pasangan lain + warna bukan hex.
        $this->submit('admin/paslon', $this->fields(['nomor_urut' => '1', 'theme_accent' => 'linear-gradient(red, blue)']))
            ->assertRedirectTo(site_url('admin/paslon/tambah'));
        $errors = session('errors');
        $this->assertSame('Nomor urut sudah dipakai pasangan lain.', $errors['nomor_urut']);
        $this->assertStringContainsString('#RRGGBB', $errors['theme_accent']);

        // File PHP menyamar sebagai gambar + gambar terlalu kecil: tidak ada yang tersimpan.
        $this->submit('admin/paslon', $this->fields(), [
            'asset_foto_ketua' => ['path' => $php, 'name' => 'foto.jpg'],
            'asset_foto_wakil' => ['path' => $this->image(120, 120), 'name' => 'kecil.png'],
            'asset_texture'    => ['path' => $this->image(100, 100), 'name' => 'ok.png'],
        ])->assertRedirectTo(site_url('admin/paslon/tambah'));

        $errors = session('errors');
        $this->assertStringContainsString('bukan gambar JPG/PNG/WebP', $errors['asset_foto_ketua']);
        $this->assertStringContainsString('terlalu kecil', $errors['asset_foto_wakil']);
        $this->assertSame([], $this->stored(), 'File valid lain ikut dibatalkan');
        $this->dontSeeInDatabase('candidates', ['nomor_urut' => 4]);
        $this->assertSame(0, $this->db->table('audit_logs')->countAllResults());

        // Form menampilkan kembali isian + pesan per field.
        $form = $this->withSession()->get('admin/paslon/tambah');
        $form->assertSee('value="Gilang Ramadhan"');
        $form->assertSee('bukan gambar JPG/PNG/WebP');
    }

    public function testMassAssignmentFieldsAreIgnored(): void
    {
        $this->submit('admin/paslon/3', $this->fields([
            'nomor_urut'  => '3',
            'id'          => '99',
            'created_at'  => '2000-01-01 00:00:00',
            'foto_ketua'  => '../../app/Config/Database.php',
            'theme_asset' => '{"hero":"../../.env"}',
        ]))->assertRedirectTo(site_url('admin/paslon'));

        $row = model(CandidateModel::class)->find(3);
        $this->assertSame('Gilang Ramadhan', $row['nama_ketua']);
        $this->assertNull($row['foto_ketua']);
        $this->assertNull($row['theme_asset']);
        $this->assertNotSame('2000-01-01 00:00:00', $row['created_at']);
        $this->dontSeeInDatabase('candidates', ['id' => 99]);
    }

    public function testCandidateWithVotesCannotBeDeletedButCanBeDeactivated(): void
    {
        $this->db->table('student_votes')->insert([
            'election_id' => 1, 'student_id' => 1, 'candidate_id' => 1, 'status' => 'LOCKED', 'voted_at' => Time::now()->toDateTimeString(),
        ]);

        $this->submit('admin/paslon/1/hapus', [])->assertRedirectTo(site_url('admin/paslon'));
        $this->assertStringContainsString('tidak dapat dihapus', (string) session('error'));
        $this->seeInDatabase('candidates', ['id' => 1]);

        $this->submit('admin/paslon/1', $this->fields(['nomor_urut' => '1', 'status_aktif' => '0']))
            ->assertRedirectTo(site_url('admin/paslon'));
        $this->seeInDatabase('candidates', ['id' => 1, 'status_aktif' => 0]);

        // Nonaktif: hilang dari surat suara, suara lama tetap dihitung di analitik.
        $ballot = $this->withSession(['user_type' => 'student', 'student_id' => 2, 'isLoggedIn' => true])->get('siswa/coblos');
        $ballot->assertDontSee('Coblos Pasangan 01');
        $live = json_decode($this->withSession($this->admin())->get('admin/hitung-suara')->getJSON(), true);
        $this->assertSame(1, $live['candidates'][0]['votes']);
        $this->assertFalse($live['candidates'][0]['active']);
    }

    public function testCandidateWithoutVotesCanBeDeletedWithItsFiles(): void
    {
        $this->submit('admin/paslon', $this->fields(), [
            'asset_foto_ketua' => ['path' => $this->image(300, 300), 'name' => 'a.png'],
        ]);
        $row = model(CandidateModel::class)->where('nomor_urut', 4)->first();
        $this->assertCount(1, $this->stored());

        $this->submit('admin/paslon/' . $row['id'] . '/hapus', [])->assertRedirectTo(site_url('admin/paslon'));

        $this->dontSeeInDatabase('candidates', ['id' => $row['id']]);
        $this->assertSame([], $this->stored());
        $this->seeInDatabase('audit_logs', ['action' => 'CANDIDATE_DELETE']);
    }

    public function testPreviewRendersVoterProfileForAdmin(): void
    {
        $result = $this->withSession($this->admin())->get('admin/paslon/2/intip');

        $result->assertStatus(200);
        $result->assertSee('Pratinjau admin.');
        $result->assertSee('class="paslon paslon--poster"');
        $result->assertSee('Bagas Prayoga');
        $result->assertSee('assets/css/voting.css');
        $result->assertSee('assets/css/paslon.css');
        $result->assertSee('assets/js/paslon.js');
        $result->assertSee('Admin tidak dapat memberikan suara');
        $result->assertSee('href="' . site_url('admin/paslon/2/ubah') . '"');
        $result->assertSee('href="' . site_url('admin/paslon/1/intip') . '"');
        $result->assertDontSee('data-coblos');
        $result->assertDontSee('#coblos-');
    }

    /**
     * Stage 15: field profil & kampanye tersimpan dari form admin; tautan
     * YouTube disimpan sebagai ID video saja.
     */
    public function testProfileFieldsAreSavedAndVideoLinkBecomesAnId(): void
    {
        $this->submit('admin/paslon/1', $this->fields([
            'nomor_urut'     => '1',
            'slogan'         => "  Bersama   berkarya.\n ",
            'bio_ketua'      => "Ketua kelas.\nSuka membaca.",
            'karakter_ketua' => "Kreatif\nAktif, Jujur",
            'program'        => "Student Voice — Wadah aspirasi.\nCreative Day: Pameran karya.",
            'pesan_wakil'    => 'Ayo memilih!',
            'video_youtube'  => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=42s',
        ]))->assertRedirectTo(site_url('admin/paslon'));

        $row = model(CandidateModel::class)->find(1);
        $this->assertSame('Bersama berkarya.', $row['slogan']);
        $this->assertSame('Ketua kelas. Suka membaca.', $row['bio_ketua']);
        $this->assertSame('Kreatif, Aktif, Jujur', $row['karakter_ketua']);
        $this->assertSame("Student Voice — Wadah aspirasi.\nCreative Day: Pameran karya.", $row['program']);
        $this->assertNull($row['pesan_ketua']);
        $this->assertSame('Ayo memilih!', $row['pesan_wakil']);
        $this->assertSame('dQw4w9WgXcQ', $row['video_youtube']);

        $audit = $this->db->table('audit_logs')->where('action', 'CANDIDATE_UPDATE')->orderBy('id', 'DESC')->get()->getRowArray();
        foreach (['slogan', 'bio ketua', 'karakter ketua', 'program unggulan', 'pesan wakil', 'video'] as $label) {
            $this->assertStringContainsString($label, $audit['description']);
        }

        // Form ubah menampilkan ID sebagai tautan pendek.
        $this->withSession($this->admin())->get('admin/paslon/1/ubah')->assertSee('value="https://youtu.be/dQw4w9WgXcQ"');
    }

    public function testUnknownVideoLinkAndOverlongProfileTextAreRejected(): void
    {
        $this->submit('admin/paslon/1', $this->fields([
            'nomor_urut'    => '1',
            'video_youtube' => 'https://vimeo.com/123456',
            'pesan_ketua'   => str_repeat('a', 301),
        ]))->assertRedirectTo(site_url('admin/paslon/1/ubah'));

        $errors = session('errors');
        $this->assertStringContainsString('Tautan YouTube tidak dikenali', $errors['video_youtube']);
        $this->assertSame('Pesan calon ketua maksimal 300 karakter.', $errors['pesan_ketua']);
        $this->seeInDatabase('candidates', ['id' => 1, 'video_youtube' => null, 'nama_ketua' => 'Arka Wibisana']);
    }

    public function testFormHasProfileAndCampaignFieldset(): void
    {
        $result = $this->withSession($this->admin())->get('admin/paslon/1/ubah');

        $this->assertStringContainsString('<span class="form-x__no">03</span> Profil &amp; kampanye</legend>', (string) $result->getBody());
        foreach (['slogan', 'bio_ketua', 'bio_wakil', 'karakter_ketua', 'karakter_wakil', 'program', 'pesan_ketua', 'pesan_wakil', 'video_youtube'] as $field) {
            $result->assertSee('name="' . $field . '"');
        }
        $result->assertSee('value="Bersama berkarya, bersama berprestasi."');
        $html = (string) $result->getBody();
        $this->assertLessThan(strpos($html, 'Tema visual</legend>'), strpos($html, 'Profil &amp; kampanye</legend>'));
    }

    public function testEditFormShowsCurrentValuesAndUploadLimits(): void
    {
        $result = $this->withSession($this->admin())->get('admin/paslon/1/ubah');

        $result->assertStatus(200);
        $result->assertSee('value="Arka Wibisana"');
        $result->assertSee('value="#C4432B"');
        $result->assertSee('name="theme_layout" value="split" checked');
        $result->assertSee('enctype="multipart/form-data"');
        foreach (array_keys(CandidateAssets::SLOTS) as $slot) {
            $result->assertSee('name="asset_' . $slot . '"');
        }
        $result->assertSee('data-max-bytes="' . CandidateAssets::maxBytes('foto_ketua') . '"');
    }
}
