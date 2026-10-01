<?php

use App\Database\Seeds\DatabaseSeeder;
use App\Libraries\AdminAccount;
use App\Libraries\SystemCheck;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\StreamFilterTrait;

/**
 * Stage 4: alat deployment — admin production (php spark admin:create,
 * admin:password) dan pemeriksaan kesiapan (php spark osis:check).
 *
 * @internal
 */
final class DeploymentToolsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use StreamFilterTrait;

    protected $migrate   = true;
    protected $refresh   = true;
    protected $namespace = 'App';
    protected $seed      = DatabaseSeeder::class;

    public function testGeneratedPasswordsAreLongAndUnambiguous(): void
    {
        $passwords = array_map(static fn (): string => AdminAccount::generatePassword(), range(1, 30));

        foreach ($passwords as $password) {
            $this->assertSame(AdminAccount::PASSWORD_LENGTH, strlen($password));
            $this->assertMatchesRegularExpression('/^[a-km-zA-HJ-NP-Z2-9]+$/', $password);
        }

        $this->assertCount(30, array_unique($passwords));
    }

    public function testCreateAdminStoresOnlyAHashAndCanLogIn(): void
    {
        $account = (new AdminAccount())->create('  Panitia   Pemilihan ', 'Panitia');

        $row = $this->db->table('admins')->where('id', $account['id'])->get()->getRowArray();
        $this->assertSame('Panitia Pemilihan', $row['name']);
        $this->assertSame('panitia', $row['username']);
        $this->assertNotSame($account['password'], $row['password_hash']);
        $this->assertTrue(password_verify($account['password'], $row['password_hash']));

        $this->assertNotNull(model(\App\Models\AdminModel::class)->verifyCredentials('panitia', $account['password']));
    }

    public function testCreateAdminValidatesInput(): void
    {
        $account = new AdminAccount();

        foreach ([['', 'panitia2'], ['Nama', 'ab'], ['Nama', 'spasi di tengah'], ['Nama', 'admin']] as [$name, $username]) {
            try {
                $account->create($name, $username);
                $this->fail('Seharusnya ditolak: ' . $username);
            } catch (InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }

        $this->assertSame(1, $this->db->table('admins')->countAllResults());
    }

    public function testResetPasswordReplacesTheHash(): void
    {
        $password = (new AdminAccount())->resetPassword('admin');

        $this->assertNull(model(\App\Models\AdminModel::class)->verifyCredentials('admin', 'admin123'));
        $this->assertNotNull(model(\App\Models\AdminModel::class)->verifyCredentials('admin', $password));

        $this->expectException(InvalidArgumentException::class);
        (new AdminAccount())->resetPassword('tidak-ada');
    }

    public function testSparkCommandsPrintThePasswordOnce(): void
    {
        command('admin:create --username panitia --name "Panitia OSIS"');
        $output = $this->getStreamFilterBuffer();

        $this->assertStringContainsString('Admin "panitia" dibuat.', $output);
        $this->assertMatchesRegularExpression('/Kata sandi: ([A-Za-z0-9]{16})/', $output);
        preg_match('/Kata sandi: ([A-Za-z0-9]{16})/', $output, $match);
        $this->assertNotNull(model(\App\Models\AdminModel::class)->verifyCredentials('panitia', $match[1]));

        $this->resetStreamFilterBuffer();
        command('admin:password panitia');
        $this->assertStringContainsString('Kata sandi admin "panitia" diganti.', $this->getStreamFilterBuffer());
    }

    public function testSystemCheckReportsDatabaseGuardsAndDevelopmentData(): void
    {
        $results = (new SystemCheck($this->db))->run();
        $byItem  = array_column($results, null, 'item');

        $this->assertSame(SystemCheck::OK, $byItem['Versi PHP']['status']);
        $this->assertSame(SystemCheck::OK, $byItem['Ekstensi PHP wajib']['status']);
        $this->assertSame(SystemCheck::OK, $byItem['Versi database']['status']);
        $this->assertSame(SystemCheck::OK, $byItem['Migration']['status'], $byItem['Migration']['detail']);
        $this->assertSame(SystemCheck::OK, $byItem['Penjaga integritas suara (trigger)']['status'], $byItem['Penjaga integritas suara (trigger)']['detail']);
        $this->assertSame(SystemCheck::OK, $byItem['Akun admin']['status']);
        $this->assertSame(SystemCheck::OK, $byItem['Pasangan calon aktif']['status']);

        // Stage 19: minify siap (folder aset dapat ditulis), aktif saat production.
        $this->assertSame(SystemCheck::OK, $byItem['Minify HTML/CSS/JS']['status'], $byItem['Minify HTML/CSS/JS']['detail']);
        $this->assertStringContainsString('production', $byItem['Minify HTML/CSS/JS']['detail']);

        // Data development terdeteksi (di production menjadi GAGAL).
        $this->assertSame(SystemCheck::WARN, $byItem['Admin development']['status']);
        $this->assertSame(SystemCheck::WARN, $byItem['Data contoh (seeder)']['status']);
        $this->assertFalse(SystemCheck::hasFailure($results));

        // Trigger hilang (mis. database dipulihkan tanpa trigger) = GAGAL.
        $this->db->query('DROP TRIGGER trg_audit_logs_guard_delete');
        $after = array_column((new SystemCheck($this->db))->run(), null, 'item');
        $this->assertSame(SystemCheck::FAIL, $after['Penjaga integritas suara (trigger)']['status']);
        $this->assertStringContainsString('trg_audit_logs_guard_delete', $after['Penjaga integritas suara (trigger)']['detail']);
    }
}
