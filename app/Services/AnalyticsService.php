<?php

namespace App\Services;

use App\Libraries\CandidateTheme;
use App\Libraries\Grade;
use App\Models\CandidateModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Config\Database;

/**
 * Analitik, live count, dan detail suara panel admin (Stage 3).
 *
 * Definisi yang dipakai SEMUA angka (04-FINAL... bagian 9):
 * - pemilih aktif = students/teachers.status_aktif = 1;
 * - suara sah     = baris *_votes berstatus LOCKED pada election berjalan
 *                   milik pemilih aktif (riwayat UNLOCKED tidak dihitung);
 * - sudah memilih = pemilih aktif dengan suara sah (maksimal satu per
 *                   pemilih, dijamin unique key uq_*_votes_active);
 * - belum memilih = pemilih aktif - sudah memilih.
 * Sehingga: sudah + belum = total aktif, dan suara siswa + suara guru = total suara.
 *
 * Hitungan dilakukan MySQL (COUNT ... GROUP BY). Siswa dihitung dengan SATU
 * query yang dikelompokkan per (kelas, jenis_kelamin, kandidat); rekap kelas,
 * jenis kelamin, jenjang, dan total siswa dijumlahkan dari baris agregat yang
 * sama, sehingga angka antar-rekap tidak mungkin saling bertentangan.
 * PHP hanya menjumlahkan paling banyak beberapa ratus baris agregat.
 */
final class AnalyticsService
{
    public const DETAIL_STATUSES = ['LOCKED', 'UNLOCKED', 'all'];

    private const LOCKED = 'LOCKED';

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Seluruh angka dasbor/analitik untuk satu election (null = belum ada
     * election: semua pemilih tercatat "belum memilih").
     *
     * @return array{
     *     election: array|null,
     *     candidates: list<array<string, mixed>>,
     *     summary: array{students: array, teachers: array, all: array},
     *     groups: array{type: list<array>, gender: list<array>, grade: list<array>, class: list<array>},
     *     generated_at: string
     * }
     */
    public function snapshot(?array $election): array
    {
        $electionId = $election === null ? 0 : (int) $election['id'];
        $candidates = $this->candidateRows();
        $ids        = array_map(static fn (array $c): int => (int) $c['id'], $candidates);

        $studentRows = $this->studentAggregates($electionId);
        $teacherRows = $this->teacherAggregates($electionId);

        $students = $this->tally($ids, $studentRows);
        $teachers = $this->tally($ids, $teacherRows);
        $all      = $this->combine($ids, [$students, $teachers]);

        return [
            'election'   => $election === null ? null : [
                'id'       => (int) $election['id'],
                'nama'     => $election['nama'],
                'tahun'    => (int) $election['tahun'],
                'status'   => $election['status'],
                'label'    => election_status_label($election['status']),
                'start_at' => $election['start_at'],
                'end_at'   => $election['end_at'],
            ],
            'candidates' => $this->candidateResults($candidates, $students, $teachers, $all),
            'summary'    => [
                'students' => $students,
                'teachers' => $teachers,
                'all'      => $all,
            ],
            'groups' => [
                'type' => [
                    ['key' => 'student', 'label' => 'Siswa'] + $students,
                    ['key' => 'teacher', 'label' => 'Guru'] + $teachers,
                ],
                'gender' => $this->genderGroups($ids, $studentRows),
                'grade'  => $this->gradeGroups($ids, $studentRows),
                'class'  => $this->classGroups($ids, $studentRows),
            ],
            'generated_at' => Time::now()->toDateTimeString(),
        ];
    }

    /**
     * Bentuk JSON untuk endpoint live count: map suara per kandidat dijadikan
     * objek {"<id>": n} agar urutan/kunci tidak berubah menjadi array JSON.
     */
    public static function toJson(array $snapshot): array
    {
        $objectify = static function (array $tally): array {
            $tally['votes']  = (object) $tally['votes'];
            $tally['shares'] = (object) $tally['shares'];

            return $tally;
        };

        foreach ($snapshot['summary'] as $key => $tally) {
            $snapshot['summary'][$key] = $objectify($tally);
        }

        foreach ($snapshot['groups'] as $name => $groups) {
            $snapshot['groups'][$name] = array_map($objectify, $groups);
        }

        return $snapshot;
    }

    // ------------------------------------------------------------------
    // Detail suara
    // ------------------------------------------------------------------

    /**
     * Normalisasi filter detail suara dari query string. Nilai yang tidak
     * dikenal diabaikan (bukan error) sehingga URL rusak tetap aman.
     * Stage 11: rombel (7A) dari ?rombel=; ?kelas= lama tetap diterima.
     *
     * @param list<int>    $candidateIds
     * @param list<string> $classes
     *
     * @return array{q: string, type: string, rombel: string, gender: string, candidate: int, status: string}
     */
    public static function detailFilters(array $input, array $candidateIds, array $classes): array
    {
        $text = static fn (mixed $v): string => is_string($v) ? trim($v) : '';

        $type      = $text($input['type'] ?? '');
        $rombel    = Grade::normalizeKelas($text($input['rombel'] ?? $input['kelas'] ?? ''));
        $gender    = strtoupper($text($input['gender'] ?? ''));
        $candidate = $text($input['candidate'] ?? '');
        $status    = $text($input['status'] ?? '');

        return [
            'q'         => mb_substr($text($input['q'] ?? ''), 0, 100),
            'type'      => in_array($type, ['student', 'teacher'], true) ? $type : '',
            'rombel'    => in_array($rombel, $classes, true) ? $rombel : '',
            'gender'    => in_array($gender, ['L', 'P'], true) ? $gender : '',
            'candidate' => ctype_digit($candidate) && in_array((int) $candidate, $candidateIds, true) ? (int) $candidate : 0,
            'status'    => in_array($status, self::DETAIL_STATUSES, true) ? $status : self::LOCKED,
        ];
    }

    /**
     * Baris suara untuk tabel detail (siswa + guru), terbaru lebih dulu.
     *
     * Status LOCKED (bawaan) = suara sah yang dihitung, sehingga jumlah baris
     * sama dengan total suara di dasbor. UNLOCKED = riwayat yang dibuka admin.
     * Filter rombel/jenis kelamin hanya berlaku untuk siswa (guru tidak punya
     * rombel/jenis kelamin), jadi guru otomatis tidak ikut.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function detailVotes(?array $election, array $filters, int $page, int $perPage): array
    {
        if ($election === null) {
            return ['rows' => [], 'total' => 0];
        }

        $electionId = (int) $election['id'];
        $build      = fn (): array => array_values(array_filter([
            $this->studentVotePart($electionId, $filters),
            $this->teacherVotePart($electionId, $filters),
        ]));

        $total = 0;

        foreach ($build() as $part) {
            $total += $part->countAllResults();
        }

        if ($total === 0) {
            return ['rows' => [], 'total' => 0];
        }

        $parts = $build();
        $union = array_shift($parts);

        foreach ($parts as $part) {
            $union->unionAll($part);
        }

        $rows = $this->db->newQuery()
            ->fromSubquery($union, 'detail')
            ->orderBy('voted_at', 'DESC')
            ->orderBy('voter_type', 'ASC')
            ->orderBy('vote_id', 'DESC')
            ->limit($perPage, max(0, ($page - 1) * $perPage))
            ->get()
            ->getResultArray();

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Daftar kelas siswa (bentuk baku) untuk filter, urut jenjang lalu nama.
     *
     * @return list<string>
     */
    public function classes(): array
    {
        $rows = $this->db->table('students')
            ->distinct()
            ->select('kelas')
            ->get()
            ->getResultArray();

        $classes = array_values(array_unique(array_map(
            static fn (array $row): string => Grade::normalizeKelas($row['kelas']),
            $rows,
        )));

        usort($classes, self::compareClass(...));

        return $classes;
    }

    // ------------------------------------------------------------------
    // Query agregat
    // ------------------------------------------------------------------

    /**
     * Semua kandidat (aktif & nonaktif) urut nomor. Kandidat nonaktif tetap
     * dihitung bila punya suara sah; yang tanpa suara disembunyikan dari hasil.
     *
     * @return list<array<string, mixed>>
     */
    private function candidateRows(): array
    {
        return $this->db->table('candidates')
            ->select('id, nomor_urut, nama_ketua, nama_wakil, theme_accent, foto_ketua, foto_wakil, status_aktif')
            ->orderBy('nomor_urut', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array{kelas: string, jenis_kelamin: string, candidate_id: string|null, n: string}>
     */
    private function studentAggregates(int $electionId): array
    {
        return $this->db->table('students s')
            ->select('s.kelas, s.jenis_kelamin, v.candidate_id, COUNT(*) AS n', false)
            ->join('student_votes v', $this->lockedJoin('student_id', $electionId), 'left', false)
            ->where('s.status_aktif', 1)
            ->groupBy(['s.kelas', 's.jenis_kelamin', 'v.candidate_id'])
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array{candidate_id: string|null, n: string}>
     */
    private function teacherAggregates(int $electionId): array
    {
        return $this->db->table('teachers t')
            ->select('v.candidate_id, COUNT(*) AS n', false)
            ->join('teacher_votes v', $this->lockedJoin('teacher_id', $electionId, 't'), 'left', false)
            ->where('t.status_aktif', 1)
            ->groupBy('v.candidate_id')
            ->get()
            ->getResultArray();
    }

    /**
     * Kondisi LEFT JOIN suara sah. Nilai berupa int & literal tetap sehingga
     * aman ditulis langsung (escape=false dipakai karena ada literal string).
     */
    private function lockedJoin(string $voterKey, int $electionId, string $voterAlias = 's'): string
    {
        return sprintf(
            'v.%s = %s.id AND v.election_id = %d AND v.status = %s',
            $voterKey,
            $voterAlias,
            $electionId,
            $this->db->escape(self::LOCKED),
        );
    }

    private function studentVotePart(int $electionId, array $f): ?BaseBuilder
    {
        if ($f['type'] === 'teacher') {
            return null;
        }

        $builder = $this->db->table('student_votes v')
            ->select(
                "'student' AS voter_type, v.id AS vote_id, p.id AS voter_id, p.name, p.nisn AS identifier, "
                . 'p.kelas, p.nomor_absen, p.jenis_kelamin, p.status_aktif AS voter_active, '
                . 'v.candidate_id, c.nomor_urut, c.nama_ketua, c.nama_wakil, v.status, v.voted_at, '
                . 'v.unlocked_at, v.device_info, v.browser_info',
                false,
            )
            ->join('students p', 'p.id = v.student_id')
            ->join('candidates c', 'c.id = v.candidate_id')
            ->where('v.election_id', $electionId);

        if ($f['rombel'] !== '') {
            $builder->where('p.kelas', $f['rombel']);
        }

        if ($f['gender'] !== '') {
            $builder->where('p.jenis_kelamin', $f['gender']);
        }

        return $this->applyVoteFilters($builder, $f, 'p.nisn');
    }

    private function teacherVotePart(int $electionId, array $f): ?BaseBuilder
    {
        if ($f['type'] === 'student' || $f['rombel'] !== '' || $f['gender'] !== '') {
            return null;
        }

        $builder = $this->db->table('teacher_votes v')
            ->select(
                "'teacher' AS voter_type, v.id AS vote_id, p.id AS voter_id, p.name, p.nip AS identifier, "
                . 'NULL AS kelas, NULL AS nomor_absen, NULL AS jenis_kelamin, p.status_aktif AS voter_active, '
                . 'v.candidate_id, c.nomor_urut, c.nama_ketua, c.nama_wakil, v.status, v.voted_at, '
                . 'v.unlocked_at, v.device_info, v.browser_info',
                false,
            )
            ->join('teachers p', 'p.id = v.teacher_id')
            ->join('candidates c', 'c.id = v.candidate_id')
            ->where('v.election_id', $electionId);

        return $this->applyVoteFilters($builder, $f, 'p.nip');
    }

    private function applyVoteFilters(BaseBuilder $builder, array $f, string $identifierColumn): BaseBuilder
    {
        if ($f['status'] === self::LOCKED) {
            // Sama dengan definisi suara sah di snapshot().
            $builder->where('v.status', self::LOCKED)->where('p.status_aktif', 1);
        } elseif ($f['status'] === 'UNLOCKED') {
            $builder->where('v.status', 'UNLOCKED');
        }

        if ($f['candidate'] > 0) {
            $builder->where('v.candidate_id', $f['candidate']);
        }

        if ($f['q'] !== '') {
            $builder->groupStart()
                ->like('p.name', $f['q'])
                ->orLike($identifierColumn, $f['q'])
                ->groupEnd();
        }

        return $builder;
    }

    // ------------------------------------------------------------------
    // Penjumlahan hasil agregat
    // ------------------------------------------------------------------

    /**
     * @param list<int>                                                  $candidateIds
     * @param iterable<array{candidate_id: int|string|null, n: int|string}> $rows
     */
    private function tally(array $candidateIds, iterable $rows): array
    {
        $votes = array_fill_keys($candidateIds, 0);
        $total = 0;

        foreach ($rows as $row) {
            $n = (int) $row['n'];
            $total += $n;

            if ($row['candidate_id'] !== null) {
                $id         = (int) $row['candidate_id'];
                $votes[$id] = ($votes[$id] ?? 0) + $n;
            }
        }

        return $this->finish($total, $votes);
    }

    /**
     * @param list<int>   $candidateIds
     * @param list<array> $tallies
     */
    private function combine(array $candidateIds, array $tallies): array
    {
        $votes = array_fill_keys($candidateIds, 0);
        $total = 0;

        foreach ($tallies as $tally) {
            $total += $tally['total'];

            foreach ($tally['votes'] as $id => $n) {
                $votes[$id] = ($votes[$id] ?? 0) + $n;
            }
        }

        return $this->finish($total, $votes);
    }

    /**
     * @param array<int, int> $votes
     *
     * @return array{total: int, voted: int, not_voted: int, participation: float, votes: array<int, int>, shares: array<int, float>}
     */
    private function finish(int $total, array $votes): array
    {
        $voted = array_sum($votes);

        return [
            'total'         => $total,
            'voted'         => $voted,
            'not_voted'     => $total - $voted,
            'participation' => self::percent($voted, $total),
            'votes'         => $votes,
            'shares'        => array_map(static fn (int $n): float => self::percent($n, $voted), $votes),
        ];
    }

    private function genderGroups(array $ids, array $rows): array
    {
        $groups = [];

        foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $key => $label) {
            $groups[] = ['key' => $key, 'label' => $label]
                + $this->tally($ids, array_filter($rows, static fn (array $r): bool => $r['jenis_kelamin'] === $key));
        }

        return $groups;
    }

    /**
     * Baris agregat dikelompokkan per kelas bentuk baku (kolasi database
     * sudah menyatukan "7a"/"7A", tetapi label tiap baris agregat bisa berbeda).
     *
     * @return array<string, list<array>>
     */
    private function rowsByClass(array $rows): array
    {
        $byClass = [];

        foreach ($rows as $row) {
            $byClass[Grade::normalizeKelas($row['kelas'])][] = $row;
        }

        return $byClass;
    }

    private function classGroups(array $ids, array $rows): array
    {
        $groups = [];

        foreach ($this->rowsByClass($rows) as $kelas => $classRows) {
            $kelas    = (string) $kelas; // kelas "7" menjadi key int di array PHP
            $groups[] = ['key' => $kelas, 'label' => $kelas, 'grade' => Grade::fromKelas($kelas)]
                + $this->tally($ids, $classRows);
        }

        usort($groups, static fn (array $a, array $b): int => self::compareClass($a['key'], $b['key']));

        return $groups;
    }

    /**
     * Jenjang 7/8/9 selalu tampil (walau 0); "Lainnya" hanya bila ada kelas
     * yang jenjangnya tidak dikenali, agar total jenjang = total siswa.
     */
    private function gradeGroups(array $ids, array $rows): array
    {
        $byGrade = ['7' => [], '8' => [], '9' => [], 'lainnya' => []];

        foreach ($this->rowsByClass($rows) as $kelas => $classRows) {
            $grade = Grade::fromKelas((string) $kelas);
            array_push($byGrade[$grade === null ? 'lainnya' : (string) $grade], ...$classRows);
        }

        $groups = [];

        // Kunci numerik "7" menjadi int di array PHP; dikembalikan ke string
        // agar tipe key konsisten di JSON live count.
        foreach ($byGrade as $key => $gradeRows) {
            $key = (string) $key;

            if ($key === 'lainnya' && $gradeRows === []) {
                continue;
            }

            $groups[] = [
                'key'   => $key,
                'label' => Grade::label($key === 'lainnya' ? null : (int) $key),
            ] + $this->tally($ids, $gradeRows);
        }

        return $groups;
    }

    /**
     * @param list<array<string, mixed>> $candidates
     *
     * @return list<array<string, mixed>>
     */
    private function candidateResults(array $candidates, array $students, array $teachers, array $all): array
    {
        $results = [];

        foreach ($candidates as $candidate) {
            $id     = (int) $candidate['id'];
            $votes  = $all['votes'][$id] ?? 0;
            $active = (int) $candidate['status_aktif'] === 1;

            if (! $active && $votes === 0) {
                continue;
            }

            $accent    = CandidateTheme::accent($candidate['theme_accent']);
            $results[] = [
                'id'             => $id,
                'number'         => (int) $candidate['nomor_urut'],
                'label'          => sprintf('%02d', (int) $candidate['nomor_urut']),
                'ketua'          => $candidate['nama_ketua'],
                'wakil'          => $candidate['nama_wakil'],
                'accent'         => $accent,
                'accent_ink'     => CandidateTheme::inkOn($accent),
                // Stage 17: dua foto pasangan dalam satu kotak (admin/partials/pair_thumb);
                // foto kosong diganti inisial.
                'photo_ketua'    => CandidateModel::assetUrl($candidate['foto_ketua'] ?? null),
                'photo_wakil'    => CandidateModel::assetUrl($candidate['foto_wakil'] ?? null),
                'ketua_initials' => CandidateTheme::initials((string) $candidate['nama_ketua']),
                'wakil_initials' => CandidateTheme::initials((string) $candidate['nama_wakil']),
                'active'         => $active,
                'votes'          => $votes,
                'percent'        => $all['shares'][$id] ?? 0.0,
                'student_votes'  => $students['votes'][$id] ?? 0,
                'teacher_votes'  => $teachers['votes'][$id] ?? 0,
            ];
        }

        return $results;
    }

    private static function percent(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : 0.0;
    }

    /**
     * Urut jenjang (7, 8, 9, lainnya) lalu natural ("7B" sebelum "7C", "7-2" sebelum "7-10").
     * Stage 26: dipakai juga urutan rombel Living Election Wall (ParticipationWall).
     */
    public static function compareClass(string $a, string $b): int
    {
        $ga = Grade::fromKelas($a) ?? 99;
        $gb = Grade::fromKelas($b) ?? 99;

        return $ga <=> $gb ?: strnatcasecmp($a, $b);
    }
}
