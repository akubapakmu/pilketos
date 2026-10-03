<?php

namespace App\Models;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Model;

/**
 * Jejak tindakan administratif penting (MASTER section 19).
 *
 * Dicatat secara proporsional: unlock hak suara, perubahan jadwal,
 * perubahan kandidat, impor siswa/guru, tambah/ubah pemilih lewat form
 * (Stage 11), serta perubahan status/penghapusan pemilih. Stage 24: perubahan
 * teks hero halaman utama. Stage 26: penghapusan foto avatar pemilih oleh
 * admin (moderasi Living Election Wall). Login dan tampilan halaman tidak
 * dicatat.
 *
 * Konteks (Stage 3, migration 2026-03-01-000001):
 * - election_id        : election terkait tindakan;
 * - vote_unlock_log_id : detail unlock (pemilih, suara, alasan) di vote_unlock_logs;
 * - ip_address         : IP admin.
 */
class AuditLogModel extends Model
{
    public const UNLOCK_VOTE      = 'UNLOCK_VOTE';
    public const ELECTION_CREATE  = 'ELECTION_CREATE';
    public const SCHEDULE_UPDATE  = 'SCHEDULE_UPDATE';
    public const CANDIDATE_CREATE = 'CANDIDATE_CREATE';
    public const CANDIDATE_UPDATE = 'CANDIDATE_UPDATE';
    public const CANDIDATE_DELETE = 'CANDIDATE_DELETE';
    public const CANDIDATE_LIMIT  = 'CANDIDATE_LIMIT';
    public const IMPORT_STUDENT   = 'IMPORT_STUDENT';
    public const IMPORT_TEACHER   = 'IMPORT_TEACHER';
    public const STUDENT_STATUS   = 'STUDENT_STATUS';
    public const TEACHER_STATUS   = 'TEACHER_STATUS';
    public const STUDENT_DELETE   = 'STUDENT_DELETE';
    public const TEACHER_DELETE   = 'TEACHER_DELETE';
    public const STUDENT_CREATE   = 'STUDENT_CREATE';
    public const TEACHER_CREATE   = 'TEACHER_CREATE';
    public const STUDENT_UPDATE   = 'STUDENT_UPDATE';
    public const TEACHER_UPDATE   = 'TEACHER_UPDATE';
    public const ADMIN_USERNAME   = 'ADMIN_USERNAME';
    public const ADMIN_PASSWORD   = 'ADMIN_PASSWORD';
    public const HOMEPAGE_TEXT    = 'HOMEPAGE_TEXT';
    public const AVATAR_REMOVE    = 'AVATAR_REMOVE';

    /**
     * Label tindakan untuk UI (urutan = urutan filter di halaman Audit).
     */
    public const LABELS = [
        self::UNLOCK_VOTE      => 'Unlock hak suara',
        self::SCHEDULE_UPDATE  => 'Ubah jadwal pemilihan',
        self::ELECTION_CREATE  => 'Buat pemilihan',
        self::CANDIDATE_CREATE => 'Tambah pasangan calon',
        self::CANDIDATE_UPDATE => 'Ubah pasangan calon',
        self::CANDIDATE_DELETE => 'Hapus pasangan calon',
        self::CANDIDATE_LIMIT  => 'Ubah batas pasangan calon',
        self::IMPORT_STUDENT   => 'Impor data siswa',
        self::IMPORT_TEACHER   => 'Impor data guru',
        self::STUDENT_CREATE   => 'Tambah data siswa',
        self::TEACHER_CREATE   => 'Tambah data guru',
        self::STUDENT_UPDATE   => 'Ubah data siswa',
        self::TEACHER_UPDATE   => 'Ubah data guru',
        self::STUDENT_STATUS   => 'Ubah status akun siswa',
        self::TEACHER_STATUS   => 'Ubah status akun guru',
        self::STUDENT_DELETE   => 'Hapus data siswa',
        self::TEACHER_DELETE   => 'Hapus data guru',
        self::ADMIN_USERNAME   => 'Ganti nama pengguna admin',
        self::ADMIN_PASSWORD   => 'Ganti kata sandi admin',
        self::HOMEPAGE_TEXT    => 'Ubah teks halaman utama',
        self::AVATAR_REMOVE    => 'Hapus foto avatar pemilih',
    ];

    protected $table         = 'audit_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'admin_id',
        'action',
        'description',
        'election_id',
        'vote_unlock_log_id',
        'ip_address',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = '';

    /**
     * Catat satu tindakan admin. Mengembalikan id baris audit.
     *
     * @param array{election_id?: int|null, vote_unlock_log_id?: int|null, ip_address?: string|null} $context
     */
    public function log(int $adminId, string $action, ?string $description = null, array $context = []): int
    {
        $this->insert([
            'admin_id'           => $adminId,
            'action'             => $action,
            'description'        => $description === null ? null : mb_substr($description, 0, 5000),
            'election_id'        => $context['election_id'] ?? null,
            'vote_unlock_log_id' => $context['vote_unlock_log_id'] ?? null,
            'ip_address'         => $context['ip_address'] ?? self::requestIp(),
        ]);

        return (int) $this->getInsertID();
    }

    public static function label(string $action): string
    {
        return self::LABELS[$action] ?? $action;
    }

    /**
     * IP request web saat ini, atau null (CLI/test tanpa request HTTP).
     */
    public static function requestIp(): ?string
    {
        $request = service('request');

        if (! $request instanceof IncomingRequest) {
            return null;
        }

        $ip = $request->getIPAddress();

        return $ip === '' ? null : mb_substr($ip, 0, 45);
    }
}
