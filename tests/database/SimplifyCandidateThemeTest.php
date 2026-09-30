<?php

use App\Database\Migrations\SimplifyCandidateTheme;
use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\CandidateAssets;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;
use Config\Services;

/**
 * Stage 16: migration 2026-09-30-000001_SimplifyCandidateTheme (menggantikan
 * Stage2SchemaTest yang menguji theme_layout). Satu tampilan untuk semua
 * pasangan: kolom theme_layout (+ CHECK) dan theme_background dihapus,
 * kunci texture/artwork di theme_asset dibuang, dan file gambarnya dihapus.
 *
 * File disimpan ke folder sementara (service candidateAssets di-mock).
 * down() ikut teruji: DatabaseTestTrait menjalankan down() seluruh migration
 * sebelum setiap test ($refresh = true), dan test di bawah memanggilnya lagi.
 *
 * @internal
 */
final class SimplifyCandidateThemeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cand-mig-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        Services::injectMock('candidateAssets', new CandidateAssets($this->dir));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function migration(): SimplifyCandidateTheme
    {
        return new SimplifyCandidateTheme(Database::forge($this->DBGroup));
    }

    /**
     * @return list<string>
     */
    private function columns(): array
    {
        $this->db->resetDataCache();

        return $this->db->getFieldNames('candidates');
    }

    public function testCandidatesNoLongerHaveLayoutOrStageBackground(): void
    {
        $this->assertNotContains('theme_layout', $this->columns());
        $this->assertNotContains('theme_background', $this->columns());
        $this->assertContains('theme_asset', $this->columns());
        $this->assertSame(3, $this->db->table('candidates')->countAllResults());
    }

    public function testDownRestoresTheOldColumnsEmpty(): void
    {
        $this->migration()->down();

        $this->assertContains('theme_layout', $this->columns());
        $this->assertContains('theme_background', $this->columns());
        $this->seeInDatabase('candidates', ['id' => 1, 'theme_layout' => null, 'theme_background' => null]);

        $this->migration()->up();

        $this->assertNotContains('theme_layout', $this->columns());
    }

    public function testUpDropsRetiredAssetsAndDeletesOnlyTheirFiles(): void
    {
        $this->migration()->down();

        foreach (['bg-01.webp', 'tex-01.webp', 'art-01.webp', 'hero-01.webp', 'poster-01.webp', 'ketua-01.webp'] as $file) {
            file_put_contents($this->dir . DIRECTORY_SEPARATOR . $file, 'x');
        }

        $this->db->table('candidates')->where('id', 1)->update([
            'theme_layout'     => 'poster',
            'theme_background' => 'bg-01.webp',
            'foto_ketua'       => 'ketua-01.webp',
            'theme_asset'      => json_encode(['hero' => 'hero-01.webp', 'texture' => 'tex-01.webp', 'artwork' => 'art-01.webp', 'poster' => 'poster-01.webp']),
        ]);
        // File yang sama di dua pasangan dan data rusak tidak menggagalkan migration.
        $this->db->table('candidates')->where('id', 2)->update(['theme_asset' => json_encode(['texture' => 'tex-01.webp', 'artwork' => ['bukan-string']])]);
        $this->db->table('candidates')->where('id', 3)->update(['theme_background' => '../../.env', 'theme_asset' => 'bukan json']);

        $this->migration()->up();

        $this->assertNotContains('theme_layout', $this->columns());
        $this->assertNotContains('theme_background', $this->columns());

        $rows = array_column($this->db->table('candidates')->orderBy('id')->get()->getResultArray(), null, 'id');
        $this->assertSame(['hero' => 'hero-01.webp', 'poster' => 'poster-01.webp'], json_decode($rows[1]['theme_asset'], true));
        $this->assertSame('ketua-01.webp', $rows[1]['foto_ketua']);
        $this->assertNull($rows[2]['theme_asset']);
        $this->assertSame('bukan json', $rows[3]['theme_asset']);

        $left = array_map('basename', glob($this->dir . DIRECTORY_SEPARATOR . '*') ?: []);
        sort($left);
        $this->assertSame(['hero-01.webp', 'ketua-01.webp', 'poster-01.webp'], $left);
        $this->assertFileExists(ROOTPATH . '.env.example');
    }
}
