<?php

namespace App\Services;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\I18n\Time;
use Config\Visitors;

/**
 * Angka & daftar pengunjung (Stage 19), dibaca dari tabel `visitors`
 * (VisitorTracker).
 *
 * Definisi yang dipakai di semua tampilan:
 * - online      = aktif dalam Config\Visitors::$onlineSeconds terakhir;
 * - tamu        = browser online yang belum login;
 * - pengguna    = AKUN siswa/guru/admin berbeda yang online (satu akun di
 *                 dua perangkat dihitung satu);
 * - total       = tamu + pengguna (angka yang tampil di beranda);
 * - persentase  = pengguna online / seluruh pengguna sistem (siswa aktif +
 *                 guru aktif + admin), juga per peran.
 */
final class OnlineVisitors
{
    public const SCOPE_ONLINE = 'online';
    public const SCOPE_TODAY  = 'hari-ini';
    public const SCOPE_WEEK   = '7-hari';

    public const SCOPES = [
        self::SCOPE_ONLINE => 'Online sekarang',
        self::SCOPE_TODAY  => 'Hari ini',
        self::SCOPE_WEEK   => '7 hari terakhir',
    ];

    public const WHO = [
        ''        => 'Semua pengunjung',
        'tamu'    => 'Tamu (belum login)',
        'login'   => 'Pengguna login',
        'siswa'   => 'Siswa',
        'guru'    => 'Guru',
        'admin'   => 'Admin',
    ];

    public const ROLES = [
        'student' => 'Siswa',
        'teacher' => 'Guru',
        'admin'   => 'Admin',
    ];

    private const WHO_TYPES = ['siswa' => 'student', 'guru' => 'teacher', 'admin' => 'admin'];

    private Visitors $config;

    public function __construct(?Visitors $config = null)
    {
        $this->config = $config ?? config(Visitors::class);
    }

    /**
     * Angka online sekarang (cache singkat, Config::$countCacheSeconds).
     *
     * @return array{total: int, guests: int, users: int, devices: int, roles: array<string, int>}
     */
    public function counts(): array
    {
        $compute = fn (): array => $this->computeCounts();

        if ($this->config->countCacheSeconds <= 0) {
            return $compute();
        }

        return cache()->remember('visitors_online_counts', $this->config->countCacheSeconds, $compute);
    }

    /**
     * Angka ringkas untuk publik (beranda & balasan detak).
     *
     * @return array{total: int, guests: int, users: int}
     */
    public function publicCounts(): array
    {
        $counts = $this->counts();

        return ['total' => $counts['total'], 'guests' => $counts['guests'], 'users' => $counts['users']];
    }

    /**
     * Ringkasan halaman admin: angka online + jumlah akun & persentase.
     *
     * @return array{
     *     total: int, guests: int, users: int, devices: int,
     *     accounts: int, percent: float,
     *     roles: array<string, array{label: string, online: int, accounts: int, percent: float}>
     * }
     */
    public function summary(): array
    {
        $counts   = $this->computeCounts();
        $accounts = $this->accounts();
        $roles    = [];

        foreach (self::ROLES as $type => $label) {
            $roles[$type] = [
                'label'    => $label,
                'online'   => $counts['roles'][$type],
                'accounts' => $accounts[$type],
                'percent'  => self::percent($counts['roles'][$type], $accounts[$type]),
            ];
        }

        $all = array_sum($accounts);

        return [
            'total'    => $counts['total'],
            'guests'   => $counts['guests'],
            'users'    => $counts['users'],
            'devices'  => $counts['devices'],
            'accounts' => $all,
            'percent'  => self::percent($counts['users'], $all),
            'roles'    => $roles,
        ];
    }

    /**
     * Daftar pengunjung (baris per browser) terbaru dulu, dengan nama akun.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function list(string $scope, string $who, string $query, int $page, int $perPage): array
    {
        $build = fn (): BaseBuilder => $this->filtered($scope, $who, $query);

        $total = $build()->countAllResults();
        $rows  = $build()
            ->select(
                'v.*, s.name AS student_name, s.nisn, s.kelas, t.name AS teacher_name, t.nip, '
                . 'a.name AS admin_name, a.username AS admin_username',
                false,
            )
            ->orderBy('v.last_seen_at', 'DESC')
            ->orderBy('v.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()
            ->getResultArray();

        $cutoff = $this->cutoff()->getTimestamp();

        foreach ($rows as &$row) {
            $row['is_online'] = strtotime((string) $row['last_seen_at']) >= $cutoff;
            $row['name']      = $row['student_name'] ?? $row['teacher_name'] ?? $row['admin_name'] ?? null;
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Sebaran perangkat, browser, dan sistem operasi pada cakupan waktu
     * (tanpa saringan nama), terbanyak dulu; sisa di atas $limit digabung
     * "Lainnya".
     *
     * @return array<string, list<array{label: string, count: int, percent: float}>>
     */
    public function breakdown(string $scope, int $limit = 5): array
    {
        $result = [];

        foreach (['device_type' => 'device', 'browser_name' => 'browser', 'os_name' => 'os'] as $column => $key) {
            $rows = $this->filtered($scope, '', '')
                ->select("COALESCE(v.{$column}, '') AS label, COUNT(*) AS n", false)
                ->groupBy('label')
                ->orderBy('n', 'DESC')
                ->orderBy('label', 'ASC')
                ->get()
                ->getResultArray();

            $sum   = array_sum(array_map('intval', array_column($rows, 'n')));
            $items = [];
            $other = 0;

            foreach ($rows as $i => $row) {
                if ($i < $limit) {
                    $items[] = ['label' => $row['label'] === '' ? 'Tidak diketahui' : (string) $row['label'], 'count' => (int) $row['n']];
                } else {
                    $other += (int) $row['n'];
                }
            }

            if ($other > 0) {
                $items[] = ['label' => 'Lainnya', 'count' => $other];
            }

            $result[$key] = array_map(
                static fn (array $item): array => $item + ['percent' => self::percent($item['count'], $sum)],
                $items,
            );
        }

        return $result;
    }

    /**
     * Batas waktu "online".
     */
    public function cutoff(): Time
    {
        return Time::now()->subSeconds(max(30, $this->config->onlineSeconds));
    }

    public static function percent(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }

    /**
     * @return array{total: int, guests: int, users: int, devices: int, roles: array<string, int>}
     */
    private function computeCounts(): array
    {
        $row = db_connect()->table('visitors')
            ->select(
                'COUNT(*) AS devices, '
                . 'SUM(CASE WHEN user_type IS NULL THEN 1 ELSE 0 END) AS guests, '
                . "COUNT(DISTINCT CASE WHEN user_type = 'student' THEN user_id END) AS students, "
                . "COUNT(DISTINCT CASE WHEN user_type = 'teacher' THEN user_id END) AS teachers, "
                . "COUNT(DISTINCT CASE WHEN user_type = 'admin' THEN user_id END) AS admins",
                false,
            )
            ->where('last_seen_at >=', $this->cutoff()->toDateTimeString())
            ->get()
            ->getRowArray() ?? [];

        $roles = [
            'student' => (int) ($row['students'] ?? 0),
            'teacher' => (int) ($row['teachers'] ?? 0),
            'admin'   => (int) ($row['admins'] ?? 0),
        ];
        $guests = (int) ($row['guests'] ?? 0);
        $users  = array_sum($roles);

        return [
            'total'   => $guests + $users,
            'guests'  => $guests,
            'users'   => $users,
            'devices' => (int) ($row['devices'] ?? 0),
            'roles'   => $roles,
        ];
    }

    /**
     * Seluruh akun yang dapat login: siswa & guru aktif, semua admin.
     *
     * @return array<string, int>
     */
    private function accounts(): array
    {
        $db = db_connect();

        return [
            'student' => $db->table('students')->where('status_aktif', 1)->countAllResults(),
            'teacher' => $db->table('teachers')->where('status_aktif', 1)->countAllResults(),
            'admin'   => $db->table('admins')->countAllResults(),
        ];
    }

    private function filtered(string $scope, string $who, string $query): BaseBuilder
    {
        $builder = db_connect()->table('visitors v')
            ->join('students s', "s.id = v.user_id AND v.user_type = 'student'", 'left', false)
            ->join('teachers t', "t.id = v.user_id AND v.user_type = 'teacher'", 'left', false)
            ->join('admins a', "a.id = v.user_id AND v.user_type = 'admin'", 'left', false);

        $since = match ($scope) {
            self::SCOPE_TODAY => Time::today(),
            self::SCOPE_WEEK  => Time::now()->subDays(7),
            default           => $this->cutoff(),
        };
        $builder->where('v.last_seen_at >=', $since->toDateTimeString());

        if ($who === 'tamu') {
            $builder->where('v.user_type', null);
        } elseif ($who === 'login') {
            $builder->where('v.user_type IS NOT NULL', null, false);
        } elseif (isset(self::WHO_TYPES[$who])) {
            $builder->where('v.user_type', self::WHO_TYPES[$who]);
        }

        if ($query !== '') {
            $builder->groupStart()
                ->like('s.name', $query)
                ->orLike('t.name', $query)
                ->orLike('a.name', $query)
                ->orLike('a.username', $query)
                ->orLike('s.nisn', $query)
                ->orLike('t.nip', $query)
                ->orLike('v.ip_address', $query)
                ->orLike('v.browser_name', $query)
                ->orLike('v.os_name', $query)
                ->orLike('v.device_brand', $query)
                ->orLike('v.device_model', $query)
                ->orLike('v.user_agent', $query)
                ->groupEnd();
        }

        return $builder;
    }
}
