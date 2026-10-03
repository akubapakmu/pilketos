<?php

namespace App\Services;

use App\Libraries\AvatarIdentity;
use App\Libraries\Grade;
use App\Libraries\VoterAvatars;
use App\Models\ElectionModel;
use CodeIgniter\Database\BaseConnection;
use Collator;
use Config\Database;
use Config\Homepage;
use Normalizer;

/**
 * Data Living Election Wall (Stage 26): siapa saja yang SUDAH berpartisipasi,
 * per rombel dan guru, untuk halaman publik GET partisipasi dan JSON
 * GET partisipasi/status.
 *
 * Definisi sama dengan AnalyticsService (satu definisi angka, bagian 2
 * README): pemilih = status_aktif = 1; berpartisipasi = punya suara LOCKED
 * pada pemilihan berjalan. Jadi total, sudah, dan belum di wall selalu sama
 * dengan dasbor admin.
 *
 * Isi per anggota HANYA: kunci (s{id} / t{id}), nama, avatar (URL foto atau
 * gaya + seed DiceBear), sudah/belum. TIDAK PERNAH: NISN/NIP, jenis kelamin,
 * nomor absen, waktu memilih, perangkat, paslon, maupun urutan siapa yang
 * terakhir memilih (daftar "voted" di JSON diurutkan menurut kunci, bukan
 * waktu).
 */
final class ParticipationWall
{
    /**
     * Pilihan urutan kartu (?urut=) => label. Label pendek (tanpa "dulu")
     * agar muat di HP; konteksnya label "Urutkan" (nama aksesibel select).
     */
    public const SORTS = [
        'nama'  => 'Nama A–Z',
        'sudah' => 'Sudah berpartisipasi',
        'belum' => 'Belum berpartisipasi',
    ];

    /**
     * Cakupan filter Kelas (?kelas=) selain rombel.
     */
    public const SCOPE_ALL      = '';
    public const SCOPE_STUDENTS = 'siswa';
    public const SCOPE_TEACHERS = 'guru';

    private const LOCKED = 'LOCKED';

    /**
     * Format waktu keterangan status (ringkas, muat satu baris di HP).
     */
    private const TIME_FORMAT = 'd MMM yyyy, HH.mm';

    private BaseConnection $db;

    private VoterAvatars $avatars;

    public function __construct(?BaseConnection $db = null, ?VoterAvatars $avatars = null)
    {
        $this->db      = $db ?? Database::connect();
        $this->avatars = $avatars ?? service('voterAvatars');
    }

    /**
     * Seluruh data halaman wall, lewat cache singkat (Homepage::$wallCacheSeconds).
     *
     * @param array<string, mixed>|null $election ElectionModel::getCurrentElection()
     *
     * @return array{
     *     status: string|null,
     *     label: string,
     *     notice: string,
     *     totals: array{all: array, students: array, teachers: array},
     *     grades: list<array{key: string, label: string, groups: list<array>}>,
     *     poll: array{interval: int}
     * }
     */
    public function page(?array $election, Homepage $config): array
    {
        return $this->cached('wall_page', $election, $config, fn (): array => $this->compute($election, $config));
    }

    /**
     * JSON partisipasi/status: status, total, kunci anggota yang sudah
     * berpartisipasi (urut kunci), irama polling.
     *
     * @return array{status: string|null, label: string, notice: string, totals: array, voted: list<string>, poll: array{interval: int}}
     */
    public function status(?array $election, Homepage $config): array
    {
        return $this->cached('wall_status', $election, $config, function () use ($election, $config): array {
            $voted  = [];
            $totals = [];

            foreach ([VoterType::Student, VoterType::Teacher] as $type) {
                $rows  = $this->rows($type, $election);
                $count = 0;

                foreach ($rows as $row) {
                    if ($row['vote_id'] !== null) {
                        $voted[] = AvatarIdentity::key($type, (int) $row['id']);
                        $count++;
                    }
                }

                $totals[$type === VoterType::Student ? 'students' : 'teachers'] = self::tally(count($rows), $count);
            }

            return [
                'status' => $election['status'] ?? null,
                'label'  => election_status_label($election['status'] ?? null),
                'notice' => self::notice($election),
                'totals' => ['all' => self::combine($totals['students'], $totals['teachers'])] + $totals,
                'voted'  => $voted,
                'poll'   => ['interval' => PublicLiveCount::interval($election['status'] ?? null, $config->wallPollSeconds)],
            ];
        });
    }

    /**
     * Filter dari query string (?kelas=, ?q=, ?urut=); nilai asing diabaikan.
     *
     * @return array{kelas: string, q: string, urut: string}
     */
    public static function filters(array $input, array $page): array
    {
        $text  = static fn (mixed $v): string => is_string($v) ? trim($v) : '';
        $kelas = $text($input['kelas'] ?? '');
        $urut  = $text($input['urut'] ?? '');

        if (! in_array($kelas, [self::SCOPE_ALL, self::SCOPE_STUDENTS, self::SCOPE_TEACHERS], true)) {
            $kelas = Grade::normalizeKelas($kelas);
            $kelas = in_array($kelas, self::rombels($page), true) ? $kelas : self::SCOPE_ALL;
        }

        return [
            'kelas' => $kelas,
            'q'     => mb_substr($text($input['q'] ?? ''), 0, 60),
            'urut'  => isset(self::SORTS[$urut]) ? $urut : 'nama',
        ];
    }

    /**
     * Daftar rombel di halaman (urut jenjang lalu natural).
     *
     * @return list<string>
     */
    public static function rombels(array $page): array
    {
        $keys = [];

        foreach ($page['grades'] as $grade) {
            foreach ($grade['groups'] as $group) {
                if ($group['type'] === VoterType::Student->value) {
                    $keys[] = (string) $group['key'];
                }
            }
        }

        return $keys;
    }

    /**
     * Angka blok partisipasi untuk cakupan filter Kelas + kata bendanya:
     * "pemilih" (semua), "siswa", "siswa 7A", "guru".
     *
     * @return array{total: int, voted: int, not_voted: int, percent: float, noun: string}
     */
    public static function scope(array $page, string $kelas): array
    {
        return match ($kelas) {
            self::SCOPE_ALL      => $page['totals']['all'] + ['noun' => 'pemilih'],
            self::SCOPE_STUDENTS => $page['totals']['students'] + ['noun' => 'siswa'],
            self::SCOPE_TEACHERS => $page['totals']['teachers'] + ['noun' => 'guru'],
            default              => self::groupScope($page, $kelas),
        };
    }

    /**
     * Kartu tampil untuk filter ini (cakupan Kelas + pencarian nama).
     */
    public static function visible(array $member, array $group, array $filters): bool
    {
        $inScope = match ($filters['kelas']) {
            self::SCOPE_ALL      => true,
            self::SCOPE_STUDENTS => $group['type'] === VoterType::Student->value,
            self::SCOPE_TEACHERS => $group['type'] === VoterType::Teacher->value,
            default              => $group['type'] === VoterType::Student->value && (string) $group['key'] === $filters['kelas'],
        };

        return $inScope && ($filters['q'] === '' || str_contains($member['search'], self::searchKey($filters['q'])));
    }

    /**
     * Urutkan anggota satu bagian menurut ?urut= (nama tetap urutan kedua).
     *
     * @param list<array> $members
     *
     * @return list<array>
     */
    public static function sort(array $members, string $urut): array
    {
        if ($urut === 'nama') {
            return $members; // sudah urut nama dari compute()
        }

        $rank  = static fn (array $m): int => ($m['voted'] xor $urut === 'belum') ? 0 : 1;
        $index = array_flip(array_column($members, 'key'));

        usort($members, static fn (array $a, array $b): int => $rank($a) <=> $rank($b) ?: $index[$a['key']] <=> $index[$b['key']]);

        return $members;
    }

    /**
     * Kunci pencarian: huruf kecil tanpa tanda diakritik ("Zahrá" = "zahra").
     */
    public static function searchKey(string $text): string
    {
        $text = class_exists(Normalizer::class) ? (string) Normalizer::normalize($text, Normalizer::FORM_D) : $text;

        return mb_strtolower((string) preg_replace('/\p{Mn}+/u', '', $text));
    }

    /**
     * Keterangan status di bawah blok partisipasi.
     */
    public static function notice(?array $election): string
    {
        return match ($election['status'] ?? null) {
            ElectionModel::STATUS_UPCOMING => 'Pemilihan dibuka ' . format_waktu($election['start_at'], self::TIME_FORMAT),
            ElectionModel::STATUS_ONGOING  => 'Berlangsung hingga ' . format_waktu($election['end_at'], self::TIME_FORMAT),
            ElectionModel::STATUS_FINISHED => 'Pemilihan selesai ' . format_waktu($election['end_at'], self::TIME_FORMAT),
            default                        => 'Belum dijadwalkan',
        };
    }

    // ------------------------------------------------------------------

    private function compute(?array $election, Homepage $config): array
    {
        $collator = class_exists(Collator::class) ? new Collator('id_ID') : null;
        $collator?->setAttribute(Collator::NUMERIC_COLLATION, Collator::ON);
        $byName = static fn (array $a, array $b): int => ($collator?->compare($a['name'], $b['name']) ?? strnatcasecmp($a['name'], $b['name']))
            ?: strcmp($a['key'], $b['key']);

        $classes  = [];
        $students = $this->rows(VoterType::Student, $election);

        foreach ($students as $row) {
            $classes[Grade::normalizeKelas($row['kelas'])][] = $this->member(VoterType::Student, $row);
        }

        $rombels = array_map('strval', array_keys($classes)); // rombel "7" menjadi key int
        usort($rombels, AnalyticsService::compareClass(...));

        $grades = [];

        foreach ($rombels as $rombel) {
            $grade    = Grade::fromKelas($rombel);
            $gradeKey = $grade === null ? 'lainnya' : (string) $grade;
            $members  = $classes[$rombel];
            usort($members, $byName);

            $grades[$gradeKey] ??= ['key' => $gradeKey, 'label' => Grade::label($grade), 'groups' => []];
            $grades[$gradeKey]['groups'][] = self::group($rombel, $rombel, Grade::label($grade), VoterType::Student, $members);
        }

        $teachers = array_map(fn (array $row): array => $this->member(VoterType::Teacher, $row), $this->rows(VoterType::Teacher, $election));
        usort($teachers, $byName);

        if ($teachers !== []) {
            $grades['guru'] = [
                'key'    => 'guru',
                'label'  => 'Guru',
                'groups' => [self::group(self::SCOPE_TEACHERS, 'Guru', 'Pendidik', VoterType::Teacher, $teachers)],
            ];
        }

        $studentTally = self::tally(count($students), count(array_filter($students, static fn (array $r): bool => $r['vote_id'] !== null)));
        $teacherTally = self::tally(count($teachers), count(array_filter($teachers, static fn (array $m): bool => $m['voted'])));

        return [
            'status' => $election['status'] ?? null,
            'label'  => election_status_label($election['status'] ?? null),
            'notice' => self::notice($election),
            'totals' => [
                'all'      => self::combine($studentTally, $teacherTally),
                'students' => $studentTally,
                'teachers' => $teacherTally,
            ],
            'grades' => array_values($grades),
            'poll'   => ['interval' => PublicLiveCount::interval($election['status'] ?? null, $config->wallPollSeconds)],
        ];
    }

    /**
     * Pemilih aktif + id suara LOCKED pada pemilihan berjalan (null = belum).
     * Hanya kolom yang boleh tampil di wall (tanpa NISN/NIP, jenis kelamin,
     * nomor absen, waktu memilih).
     *
     * @return list<array<string, mixed>>
     */
    private function rows(VoterType $type, ?array $election): array
    {
        $columns = 'p.id, p.name, p.avatar_file, p.avatar_style, p.avatar_seed, v.id AS vote_id'
            . ($type === VoterType::Student ? ', p.kelas' : '');

        return $this->db->table($type->voterTable() . ' p')
            ->select($columns, false)
            ->join($type->voteTable() . ' v', sprintf(
                'v.%s = p.id AND v.election_id = %d AND v.status = %s',
                $type->voterKey(),
                (int) ($election['id'] ?? 0),
                $this->db->escape(self::LOCKED),
            ), 'left', false)
            ->where('p.status_aktif', 1)
            ->orderBy('p.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{key: string, name: string, search: string, voted: bool, avatar: array}
     */
    private function member(VoterType $type, array $row): array
    {
        return [
            'key'    => AvatarIdentity::key($type, (int) $row['id']),
            'name'   => (string) $row['name'],
            'search' => self::searchKey((string) $row['name']),
            'voted'  => $row['vote_id'] !== null,
            'avatar' => $this->avatars->present($type, $row),
        ];
    }

    private static function group(string $key, string $label, string $kicker, VoterType $type, array $members): array
    {
        return [
            'key'     => $key,
            'label'   => $label,
            'kicker'  => $kicker,
            'type'    => $type->value,
            'members' => $members,
        ] + self::tally(count($members), count(array_filter($members, static fn (array $m): bool => $m['voted'])));
    }

    private static function groupScope(array $page, string $kelas): array
    {
        foreach ($page['grades'] as $grade) {
            foreach ($grade['groups'] as $group) {
                if ($group['type'] === VoterType::Student->value && (string) $group['key'] === $kelas) {
                    return self::tally($group['total'], $group['voted']) + ['noun' => 'siswa ' . $kelas];
                }
            }
        }

        return $page['totals']['all'] + ['noun' => 'pemilih'];
    }

    /**
     * @return array{total: int, voted: int, not_voted: int, percent: float}
     */
    private static function tally(int $total, int $voted): array
    {
        return [
            'total'     => $total,
            'voted'     => $voted,
            'not_voted' => $total - $voted,
            'percent'   => $total > 0 ? round($voted / $total * 100, 2) : 0.0,
        ];
    }

    private static function combine(array $a, array $b): array
    {
        return self::tally($a['total'] + $b['total'], $a['voted'] + $b['voted']);
    }

    /**
     * Cache singkat; kunci memuat id & status pemilihan sehingga perubahan
     * status tidak pernah terbaca basi.
     */
    private function cached(string $prefix, ?array $election, Homepage $config, callable $compute): array
    {
        if ($config->wallCacheSeconds <= 0) {
            return $compute();
        }

        $key = sprintf('%s_%d_%s', $prefix, (int) ($election['id'] ?? 0), strtolower((string) ($election['status'] ?? 'none')));

        return cache()->remember($key, $config->wallCacheSeconds, $compute);
    }
}
