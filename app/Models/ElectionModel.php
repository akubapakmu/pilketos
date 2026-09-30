<?php

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;
use DateTimeInterface;

class ElectionModel extends Model
{
    public const STATUS_UPCOMING = 'UPCOMING';
    public const STATUS_ONGOING  = 'ONGOING';
    public const STATUS_FINISHED = 'FINISHED';

    /**
     * Stage 15: batas jumlah pasangan calon AKTIF (kolom batas_pasangan),
     * diatur admin di halaman Pasangan calon. Nonaktif tidak dihitung.
     */
    public const BATAS_PASANGAN_DEFAULT = 3;
    public const BATAS_PASANGAN_MIN     = 2;
    public const BATAS_PASANGAN_MAX     = 10;

    protected $table         = 'elections';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'nama',
        'tahun',
        'start_at',
        'end_at',
        'status',
        'batas_pasangan',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $validationRules = [
        'nama'           => 'required|max_length[150]',
        'tahun'          => 'required|is_natural_no_zero',
        'start_at'       => 'required|valid_date[Y-m-d H:i:s]',
        'end_at'         => 'required|valid_date[Y-m-d H:i:s]',
        'status'         => 'permit_empty|in_list[UPCOMING,ONGOING,FINISHED]',
        'batas_pasangan' => 'permit_empty|is_natural_no_zero|greater_than_equal_to[2]|less_than_equal_to[10]',
    ];

    /**
     * Batas pasangan aktif untuk election ini; bawaan 3 bila election belum
     * dibuat atau kolom belum dimigrasi.
     */
    public static function batasPasangan(?array $election): int
    {
        $value = $election['batas_pasangan'] ?? null;

        if (! is_numeric($value)) {
            return self::BATAS_PASANGAN_DEFAULT;
        }

        return max(self::BATAS_PASANGAN_MIN, min(self::BATAS_PASANGAN_MAX, (int) $value));
    }

    /**
     * Election yang sedang berlaku (satu sistem = satu election aktif, yang terbaru).
     * Kolom status dikembalikan sudah sesuai jadwal & waktu server, dan
     * disinkronkan ke database bila berbeda.
     */
    public function getCurrentElection(): ?array
    {
        $election = $this->orderBy('id', 'DESC')->first();

        return $election === null ? null : $this->syncStatus($election);
    }

    /**
     * Status election dihitung dari jadwal terhadap waktu SERVER
     * (MASTER section 16). Kolom status hanya cermin, bukan sumber kebenaran.
     *
     * - now <  start_at          : UPCOMING
     * - start_at <= now < end_at : ONGOING
     * - now >= end_at            : FINISHED
     */
    public function resolveStatus(array $election, ?DateTimeInterface $now = null): string
    {
        $now ??= Time::now();
        $nowTs = $now->getTimestamp();

        if ($nowTs < Time::parse($election['start_at'])->getTimestamp()) {
            return self::STATUS_UPCOMING;
        }

        if ($nowTs >= Time::parse($election['end_at'])->getTimestamp()) {
            return self::STATUS_FINISHED;
        }

        return self::STATUS_ONGOING;
    }

    /**
     * Samakan kolom status dengan hasil resolveStatus() dan kembalikan
     * election dengan status efektif.
     */
    public function syncStatus(array $election, ?DateTimeInterface $now = null): array
    {
        $status = $this->resolveStatus($election, $now);

        if (($election['status'] ?? null) !== $status) {
            $this->builder()
                ->where('id', $election['id'])
                ->update(['status' => $status, 'updated_at' => Time::now()->toDateTimeString()]);
            $election['status'] = $status;
        }

        return $election;
    }
}
