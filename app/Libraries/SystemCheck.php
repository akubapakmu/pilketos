<?php

namespace App\Libraries;

use App\Models\ElectionModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;
use Config\Minify;
use Throwable;

/**
 * Pemeriksaan kesiapan server sebelum hari pemilihan (Stage 4).
 * Dipakai `php spark osis:check` (lihat README bagian Deployment Laragon).
 *
 * Setiap hasil: status OK / PERINGATAN / GAGAL + keterangan yang langsung
 * menyebut perbaikannya. GAGAL = aplikasi tidak aman/tidak dapat dipakai.
 */
final class SystemCheck
{
    public const OK   = 'OK';
    public const WARN = 'PERINGATAN';
    public const FAIL = 'GAGAL';

    public const REQUIRED_EXTENSIONS = ['intl', 'mbstring', 'mysqli', 'gd', 'zip', 'fileinfo'];

    public const TRIGGERS = [
        'trg_student_votes_guard_update',
        'trg_student_votes_guard_delete',
        'trg_teacher_votes_guard_update',
        'trg_teacher_votes_guard_delete',
        'trg_vote_unlock_logs_guard_update',
        'trg_vote_unlock_logs_guard_delete',
        'trg_audit_logs_guard_update',
        'trg_audit_logs_guard_delete',
    ];

    /**
     * @var list<array{status: string, item: string, detail: string}>
     */
    private array $results = [];

    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    /**
     * @return list<array{status: string, item: string, detail: string}>
     */
    public function run(): array
    {
        $this->results = [];

        $this->checkPhp();
        $this->checkEnvironment();
        $this->checkPhpIni();
        $this->checkFolders();
        $this->checkDatabase();

        return $this->results;
    }

    /**
     * @param list<array{status: string, item: string, detail: string}> $results
     */
    public static function hasFailure(array $results): bool
    {
        return in_array(self::FAIL, array_column($results, 'status'), true);
    }

    // ------------------------------------------------------------------

    private function add(string $status, string $item, string $detail): void
    {
        $this->results[] = ['status' => $status, 'item' => $item, 'detail' => $detail];
    }

    private function checkPhp(): void
    {
        $this->add(
            version_compare(PHP_VERSION, '8.2.0', '>=') ? self::OK : self::FAIL,
            'Versi PHP',
            PHP_VERSION . ' (butuh 8.2 atau lebih baru untuk CodeIgniter 4.7)',
        );

        $missing = array_values(array_filter(self::REQUIRED_EXTENSIONS, static fn (string $ext): bool => ! extension_loaded($ext)));
        $this->add(
            $missing === [] ? self::OK : self::FAIL,
            'Ekstensi PHP wajib',
            $missing === [] ? implode(', ', self::REQUIRED_EXTENSIONS) : 'Belum aktif: ' . implode(', ', $missing) . ' (Laragon: Menu > PHP > Extensions)',
        );

        $webp = function_exists('imagewebp') && (imagetypes() & IMG_WEBP) !== 0;
        $this->add($webp ? self::OK : self::WARN, 'GD WebP', $webp ? 'Foto kandidat disimpan sebagai WebP' : 'GD tanpa WebP: foto disimpan JPEG/PNG (lebih besar)');

        $this->add(
            extension_loaded('exif') ? self::OK : self::WARN,
            'Ekstensi exif (disarankan)',
            extension_loaded('exif') ? 'Foto HP yang miring diluruskan otomatis' : 'Tanpa exif, orientasi foto HP tidak diluruskan otomatis',
        );
    }

    private function checkEnvironment(): void
    {
        $this->add(
            ENVIRONMENT === 'production' ? self::OK : self::WARN,
            'CI_ENVIRONMENT',
            ENVIRONMENT === 'production'
                ? 'production (Debug Toolbar & pesan error teknis mati)'
                : ENVIRONMENT . ': gunakan CI_ENVIRONMENT = production di .env pada hari pemilihan',
        );

        $app     = config('App');
        $baseURL = (string) $app->baseURL;
        $this->add(
            $baseURL !== '' && $baseURL !== 'http://localhost:8080/' ? self::OK : self::WARN,
            'app.baseURL',
            $baseURL . ($baseURL === 'http://localhost:8080/' ? ' (bawaan; sesuaikan dengan alamat Laragon, mis. http://smp1dawe-osis-2026.test/)' : ''),
        );

        $this->add(
            $app->appTimezone === 'Asia/Jakarta' ? self::OK : self::WARN,
            'Zona waktu aplikasi',
            $app->appTimezone . ' (jadwal pemilihan memakai WIB)',
        );

        $this->add($app->CSPEnabled ? self::OK : self::WARN, 'Content-Security-Policy', $app->CSPEnabled ? 'aktif' : 'nonaktif (app.CSPEnabled = false)');

        // Stage 20: *.min.css / *.min.js dibuat otomatis saat production; bila
        // folder aset tidak dapat ditulis, file usang tidak pernah diperkecil.
        if (! config(Minify::class)->enabled) {
            $this->add(self::OK, 'Minify HTML/CSS/JS', 'nonaktif (Config\\Minify::$enabled = false): browser menerima kode asli');
        } else {
            $stuck = (new AssetMinifier(null, 'production'))->unbuildable();
            $this->add(
                $stuck === [] ? self::OK : self::WARN,
                'Minify HTML/CSS/JS',
                $stuck !== []
                    ? count($stuck) . ' file *.min.* belum dibuat / usang dan folder aset tidak dapat ditulis PHP: jalankan php spark osis:minify'
                    : (ENVIRONMENT === 'production' ? 'aktif' : 'siap; aktif otomatis saat CI_ENVIRONMENT = production'),
            );
        }

        if (str_starts_with($baseURL, 'https://')) {
            $secure = (bool) config('Cookie')->secure;
            $this->add($secure ? self::OK : self::WARN, 'Cookie secure (HTTPS)', $secure ? 'aktif' : 'Situs memakai HTTPS: set cookie.secure = true di .env');
        }
    }

    private function checkPhpIni(): void
    {
        $limits = [
            'upload_max_filesize' => [5 * 1024 * 1024, 'foto kandidat 5 MB per file'],
            'post_max_size'       => [40 * 1024 * 1024, 'form kandidat membawa hingga 7 gambar'],
            'memory_limit'        => [256 * 1024 * 1024, 'decode foto HP beresolusi tinggi'],
        ];

        foreach ($limits as $key => [$min, $why]) {
            $raw   = (string) ini_get($key);
            $bytes = CandidateAssets::iniBytes($raw);
            $ok    = $raw === '-1' || $bytes >= $min;
            $this->add($ok ? self::OK : self::WARN, 'php.ini ' . $key, $raw . ($ok ? '' : ' (disarankan minimal ' . CandidateAssets::formatBytes($min) . ': ' . $why . ')'));
        }

        $expose = filter_var(ini_get('expose_php'), FILTER_VALIDATE_BOOLEAN);
        $this->add($expose ? self::WARN : self::OK, 'php.ini expose_php', $expose ? 'On: set expose_php = Off agar versi PHP tidak diumumkan' : 'Off');
    }

    private function checkFolders(): void
    {
        $folders = [
            'writable/cache'           => WRITEPATH . 'cache',
            'writable/logs'            => WRITEPATH . 'logs',
            'writable/session'         => WRITEPATH . 'session',
            'writable/uploads'         => WRITEPATH . 'uploads',
            'public/uploads/candidates' => FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'candidates',
        ];

        foreach ($folders as $label => $path) {
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }

            $ok = is_dir($path) && is_writable($path);
            $this->add($ok ? self::OK : self::FAIL, 'Folder ' . $label, $ok ? 'dapat ditulis' : 'tidak ada / tidak dapat ditulis oleh PHP: ' . $path);
        }
    }

    private function checkDatabase(): void
    {
        try {
            $db = $this->db ?? Database::connect();
            $db->initialize();
            $version = (string) $db->getVersion();
        } catch (Throwable $e) {
            $this->add(self::FAIL, 'Koneksi database', 'Gagal terhubung: ' . $e->getMessage() . ' (periksa database.default.* di .env)');

            return;
        }

        $mariadb = stripos($version, 'mariadb') !== false;
        $number  = preg_match('/\d+\.\d+\.\d+/', $version, $match) === 1 ? $match[0] : '0.0.0';
        $okVer   = $mariadb ? version_compare($number, '10.4.0', '>=') : version_compare($number, '8.0.16', '>=');
        $this->add($okVer ? self::OK : self::FAIL, 'Versi database', ($mariadb ? 'MariaDB ' : 'MySQL ') . $number . ' (butuh MySQL 8.0.16+ atau MariaDB 10.4+: generated column & CHECK)');

        $this->checkMigrations($db);
        $this->checkTriggers($db);
        $this->checkData($db);
    }

    private function checkMigrations(BaseConnection $db): void
    {
        try {
            $runner = service('migrations', null, $db, false);
            $runner->setNamespace('App');
            $files = array_values(array_map(static fn (object $m): string => (string) $m->version, $runner->findMigrations()));
            $done  = $db->tableExists('migrations')
                ? array_column($db->table('migrations')->select('version')->where('namespace', 'App')->get()->getResultArray(), 'version')
                : [];
        } catch (Throwable $e) {
            $this->add(self::FAIL, 'Migration', 'Tidak dapat dibaca: ' . $e->getMessage());

            return;
        }

        $pending = array_values(array_diff($files, $done));
        $this->add(
            $pending === [] ? self::OK : self::FAIL,
            'Migration',
            $pending === [] ? count($files) . ' migration sudah dijalankan' : count($pending) . ' belum dijalankan: php spark migrate',
        );
    }

    private function checkTriggers(BaseConnection $db): void
    {
        try {
            $rows = $db->query('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = DATABASE()')->getResultArray();
        } catch (Throwable) {
            $rows = [];
        }

        $missing = array_values(array_diff(self::TRIGGERS, array_column($rows, 'TRIGGER_NAME')));
        $this->add(
            $missing === [] ? self::OK : self::FAIL,
            'Penjaga integritas suara (trigger)',
            $missing === [] ? count(self::TRIGGERS) . ' trigger aktif: suara & audit tidak dapat dihapus/diubah' : 'Belum ada: ' . implode(', ', $missing) . ' (jalankan php spark migrate)',
        );
    }

    private function checkData(BaseConnection $db): void
    {
        if (! $db->tableExists('admins')) {
            return;
        }

        $admins = $db->table('admins')->select('username, password_hash')->get()->getResultArray();
        $this->add($admins === [] ? self::FAIL : self::OK, 'Akun admin', $admins === [] ? 'Belum ada admin: php spark admin:create --username ... --name "..."' : count($admins) . ' admin');

        foreach ($admins as $admin) {
            if ($admin['username'] === 'admin' && password_verify('admin123', (string) $admin['password_hash'])) {
                $this->add(
                    ENVIRONMENT === 'production' ? self::FAIL : self::WARN,
                    'Admin development',
                    'Akun admin/admin123 dari seeder masih aktif: ganti dengan php spark admin:password admin',
                );
            }
        }

        $election = $db->table('elections')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
        $this->add(
            $election === null ? self::WARN : self::OK,
            'Jadwal pemilihan',
            $election === null ? 'Belum ada: atur di panel admin > Jadwal pemilihan' : $election['nama'] . ' ' . $election['tahun'] . ': ' . $election['start_at'] . ' s.d. ' . $election['end_at'] . ' WIB',
        );

        // Stage 15: batas pasangan aktif diatur admin (elections.batas_pasangan).
        $candidates = $db->table('candidates')->where('status_aktif', 1)->countAllResults();
        $batas      = ElectionModel::batasPasangan($election);
        $this->add(
            $candidates >= 2 && $candidates <= $batas ? self::OK : self::WARN,
            'Pasangan calon aktif',
            $candidates . ' pasangan (batas ' . $batas . ($candidates < 2 ? '; minimal 2 agar ada pilihan' : '') . ')',
        );

        $students = $db->table('students')->where('status_aktif', 1)->countAllResults();
        $teachers = $db->table('teachers')->where('status_aktif', 1)->countAllResults();
        $this->add($students > 0 && $teachers > 0 ? self::OK : self::WARN, 'Data pemilih aktif', $students . ' siswa, ' . $teachers . ' guru');

        $samples = $db->table('students')->like('nisn', '00000000', 'after')->countAllResults();
        if ($samples > 0) {
            $this->add(
                ENVIRONMENT === 'production' ? self::FAIL : self::WARN,
                'Data contoh (seeder)',
                $samples . ' siswa contoh (NISN 00000000xx) terdeteksi: jangan dipakai pada pemilihan sebenarnya',
            );
        }
    }
}
