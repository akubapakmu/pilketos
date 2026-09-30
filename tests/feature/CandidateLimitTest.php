<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\CandidateAssets;
use App\Libraries\SystemCheck;
use App\Models\ElectionModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;
use Tests\Support\UploadFixture;

/**
 * Stage 15: batas jumlah pasangan calon AKTIF diatur admin di halaman
 * Pasangan calon (elections.batas_pasangan, 2-10, bawaan 3).
 *
 * Seeder: 3 pasangan aktif, pemilihan id 1 dengan batas bawaan 3.
 *
 * @internal
 */
final class CandidateLimitTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private const ADMIN = ['user_type' => 'admin', 'admin_id' => 1, 'isLoggedIn' => true];

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cand-limit-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        Services::injectMock('candidateAssets', new CandidateAssets($this->dir));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir);

        UploadFixture::reset();
        Time::setTestNow();
        parent::tearDown();
    }

    private function post(string $path, array $fields)
    {
        UploadFixture::attach([]);

        return $this->withSession(self::ADMIN)->call('POST', $path, [csrf_token() => csrf_hash()] + $fields);
    }

    private function pair(array $override = []): array
    {
        return $override + [
            'nomor_urut'   => '4',
            'nama_ketua'   => 'Gilang Ramadhan',
            'nama_wakil'   => 'Intan Permata',
            'status_aktif' => '1',
        ];
    }

    private function batas(): int
    {
        return (int) $this->db->table('elections')->where('id', 1)->get()->getRow()->batas_pasangan;
    }

    // ------------------------------------------------------------------
    // pengaturan batas
    // ------------------------------------------------------------------

    public function testDefaultLimitIsThreeAndShownOnTheCandidatePage(): void
    {
        $this->assertSame(3, $this->batas());
        $this->assertSame(3, ElectionModel::batasPasangan(null));
        $this->assertSame(3, ElectionModel::batasPasangan(['batas_pasangan' => 'x']));
        $this->assertSame(10, ElectionModel::batasPasangan(['batas_pasangan' => 99]));

        $page = $this->withSession(self::ADMIN)->get('admin/paslon');

        $page->assertSee('Batas pasangan aktif');
        $this->assertStringContainsString('<strong>3</strong> dari <strong>3</strong> pasangan aktif', (string) $page->getBody());
        $page->assertSee('Batas penuh.');
        $page->assertSee('action="' . site_url('admin/paslon/batas') . '"');
        $page->assertSee('name="batas_pasangan"');
        $page->assertDontSee('dirancang untuk');
    }

    public function testAdminRaisesTheLimitAndItIsAudited(): void
    {
        $this->post('admin/paslon/batas', ['batas_pasangan' => '5'])->assertRedirectTo(site_url('admin/paslon'));

        $this->assertSame(5, $this->batas());
        $this->assertSame('Batas pasangan aktif sekarang 5.', session('success'));

        $audit = $this->db->table('audit_logs')->where('action', 'CANDIDATE_LIMIT')->get()->getRowArray();
        $this->assertSame('Batas pasangan aktif 3 -> 5 (saat ini 3 aktif).', $audit['description']);
        $this->assertSame('1', (string) $audit['election_id']);

        $this->withSession(self::ADMIN)->get('admin/paslon')->assertSee('Masih ada tempat untuk 2 pasangan aktif lagi.');
        $this->withSession(self::ADMIN)->get('admin/riwayat')->assertSee('Ubah batas pasangan calon');
    }

    public function testLimitMustBeTwoToTenAndNotBelowActivePairs(): void
    {
        foreach (['1', '11', 'dua', '', '3.5'] as $value) {
            $this->post('admin/paslon/batas', ['batas_pasangan' => $value])->assertRedirectTo(site_url('admin/paslon'));
            $this->assertSame('Batas pasangan berupa angka 2 sampai 10.', session('errors')['batas_pasangan'] ?? null, $value);
        }

        $this->post('admin/paslon/batas', ['batas_pasangan' => '2']);
        $this->assertStringContainsString('Saat ini ada 3 pasangan aktif', session('errors')['batas_pasangan']);
        $this->assertSame(3, $this->batas());
        $this->assertSame(0, $this->db->table('audit_logs')->countAllResults());

        // Pasangan nonaktif tidak dihitung: setelah satu dinonaktifkan, batas 2 boleh.
        $this->db->table('candidates')->where('nomor_urut', 3)->update(['status_aktif' => 0]);
        $this->post('admin/paslon/batas', ['batas_pasangan' => '2']);
        $this->assertSame(2, $this->batas());

        // Database menolak nilai di luar rentang walau lewat jalur lain.
        $this->expectException(\CodeIgniter\Database\Exceptions\DatabaseException::class);
        $this->db->table('elections')->where('id', 1)->update(['batas_pasangan' => 11]);
    }

    public function testLimitIsLockedAfterTheElectionFinishes(): void
    {
        Time::setTestNow('2026-10-02 00:00:00');
        $this->db->table('elections')->where('id', 1)->update(['start_at' => '2026-10-01 07:00:00', 'end_at' => '2026-10-01 12:00:00']);

        $this->post('admin/paslon/batas', ['batas_pasangan' => '6'])->assertRedirectTo(site_url('admin/paslon'));

        $this->assertSame(3, $this->batas());
        $this->assertStringContainsString('Pemilihan sudah selesai', session('error'));
        $this->withSession(self::ADMIN)->get('admin/paslon')->assertSee('Dikunci: pemilihan sudah selesai.');
    }

    public function testWithoutElectionTheDefaultApplies(): void
    {
        $this->db->table('elections')->delete(['id' => 1]);

        $page = $this->withSession(self::ADMIN)->get('admin/paslon');
        $this->assertStringContainsString('<strong>3</strong> dari <strong>3</strong> pasangan aktif', (string) $page->getBody());
        $page->assertSee('Batas dapat diubah setelah');

        $this->post('admin/paslon/batas', ['batas_pasangan' => '5'])->assertRedirectTo(site_url('admin/paslon'));
        $this->assertStringContainsString('Buat jadwal pemilihan dulu', session('error'));
    }

    // ------------------------------------------------------------------
    // penegakan batas saat menambah / mengaktifkan pasangan
    // ------------------------------------------------------------------

    public function testAddingAnActivePairBeyondTheLimitIsRejected(): void
    {
        $this->post('admin/paslon', $this->pair())->assertRedirectTo(site_url('admin/paslon/tambah'));

        $this->assertStringContainsString('Batas 3 pasangan aktif sudah penuh', session('errors')['status_aktif']);
        $this->dontSeeInDatabase('candidates', ['nomor_urut' => 4]);

        // Sebagai nonaktif tetap boleh disimpan (disiapkan dulu).
        $this->post('admin/paslon', $this->pair(['status_aktif' => '0']))->assertRedirectTo(site_url('admin/paslon'));
        $this->seeInDatabase('candidates', ['nomor_urut' => 4, 'status_aktif' => 0]);

        // Mengaktifkannya ditolak sampai batas dinaikkan.
        $id = (int) $this->db->table('candidates')->where('nomor_urut', 4)->get()->getRow()->id;
        $this->post('admin/paslon/' . $id, $this->pair());
        $this->seeInDatabase('candidates', ['id' => $id, 'status_aktif' => 0]);

        $this->post('admin/paslon/batas', ['batas_pasangan' => '4']);
        $this->post('admin/paslon/' . $id, $this->pair())->assertRedirectTo(site_url('admin/paslon'));
        $this->seeInDatabase('candidates', ['id' => $id, 'status_aktif' => 1]);
    }

    public function testNewFormDefaultsToInactiveWhenTheLimitIsFull(): void
    {
        $form = $this->withSession(self::ADMIN)->get('admin/paslon/tambah');
        $form->assertSee('Batas 3 pasangan aktif sudah penuh.');
        $this->assertStringNotContainsString('name="status_aktif" value="1" checked', (string) $form->getBody());

        $this->post('admin/paslon/batas', ['batas_pasangan' => '4']);

        $form = $this->withSession(self::ADMIN)->get('admin/paslon/tambah');
        $form->assertDontSee('sudah penuh');
        $this->assertStringContainsString('name="status_aktif" value="1" checked', (string) $form->getBody());
    }

    public function testEditingAnAlreadyActivePairIsAlwaysAllowed(): void
    {
        // Data lama: 3 aktif walau batas diturunkan langsung di database.
        $this->db->query('SET SESSION check_constraint_checks = 0');
        $this->db->table('elections')->where('id', 1)->update(['batas_pasangan' => 2]);
        $this->db->query('SET SESSION check_constraint_checks = 1');

        $this->post('admin/paslon/1', $this->pair(['nomor_urut' => '1', 'nama_ketua' => 'Arka W.']))->assertRedirectTo(site_url('admin/paslon'));
        $this->seeInDatabase('candidates', ['id' => 1, 'nama_ketua' => 'Arka W.', 'status_aktif' => 1]);

        $this->withSession(self::ADMIN)->get('admin/paslon')->assertSee('Jumlah pasangan aktif melebihi batas.');
    }

    // ------------------------------------------------------------------
    // tampilan untuk N pasangan & pemeriksaan server
    // ------------------------------------------------------------------

    public function testBoothLayoutsFollowTheNumberOfPairs(): void
    {
        $this->post('admin/paslon/batas', ['batas_pasangan' => '5']);

        foreach ([4, 5] as $number) {
            $this->db->table('candidates')->insert([
                'nomor_urut' => $number, 'nama_ketua' => 'Ketua ' . $number, 'nama_wakil' => 'Wakil ' . $number, 'status_aktif' => 1,
            ]);
        }

        $booth = $this->withSession(['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true])->get('siswa/coblos');
        $html  = (string) $booth->getBody();

        $this->assertSame(5, substr_count($html, 'class="pair-card"'));
        $this->assertSame(5, substr_count($html, 'class="ballot-cell"'));
        // 5 pasangan: 3 + 2 di layar lebar dan tablet.
        $this->assertSame(2, substr_count($html, 'style="--cols: 3; --cols-md: 3;"'));
        $booth->assertSee('Bandingkan semua pasangan, buka profil lengkapnya.');
        $booth->assertDontSee('ketiga');
        $booth->assertSee('href="' . site_url('siswa/paslon/05') . '"');

        $this->withSession(['user_type' => 'student', 'student_id' => 1, 'isLoggedIn' => true])->get('siswa/paslon/05')->assertStatus(200);
    }

    public function testSystemCheckUsesTheConfiguredLimit(): void
    {
        $result = fn (): array => array_column((new SystemCheck($this->db))->run(), null, 'item')['Pasangan calon aktif'];

        $this->assertSame(SystemCheck::OK, $result()['status']);
        $this->assertSame('3 pasangan (batas 3)', $result()['detail']);

        $this->db->table('candidates')->whereIn('nomor_urut', [2, 3])->update(['status_aktif' => 0]);
        $this->assertSame(SystemCheck::WARN, $result()['status']);
        $this->assertStringContainsString('minimal 2', $result()['detail']);
    }
}
