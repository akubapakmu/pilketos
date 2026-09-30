<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pasangan calon Ketua & Wakil Ketua OSIS.
 *
 * Kolom file (foto_ketua, foto_wakil, theme_background) dan isi theme_asset
 * menyimpan NAMA FILE di public/uploads/candidates/ (bukan URL penuh).
 * theme_asset = JSON object, contoh: {"hero":"x.webp","texture":"y.webp","artwork":"z.webp","poster":"p.webp"}.
 * theme_layout = art direction halaman kandidat (split | poster | column, NULL = otomatis).
 * Stage 15: slogan, bio, karakter, program, pesan, dan video_youtube (hanya ID
 * video) untuk halaman profil paslon; semuanya opsional.
 * Data tampilan (URL, warna, layout) dibentuk oleh App\Libraries\CandidateTheme.
 *
 * File diunggah admin lewat App\Libraries\CandidateAssets (Stage 3): nama
 * file acak, gambar diperiksa lalu di-encode ulang di server.
 */
class CandidateModel extends Model
{
    public const UPLOAD_DIR = 'uploads/candidates';

    protected $table         = 'candidates';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'nomor_urut',
        'nama_ketua',
        'nama_wakil',
        'foto_ketua',
        'foto_wakil',
        'visi',
        'misi',
        'slogan',
        'bio_ketua',
        'bio_wakil',
        'karakter_ketua',
        'karakter_wakil',
        'program',
        'pesan_ketua',
        'pesan_wakil',
        'video_youtube',
        'theme_name',
        'theme_background',
        'theme_accent',
        'theme_asset',
        'theme_layout',
        'status_aktif',
    ];

    protected array $casts = [
        'theme_asset' => '?json-array',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Nomor urut 1-99 (ditampilkan dua digit "01"). Warna aksen hanya
     * #RRGGBB solid: nilai lain (nama warna, gradient, CSS lain) ditolak.
     */
    protected $validationRules = [
        // Wajib ada untuk placeholder {id} di is_unique (CodeIgniter 4.7).
        'id'             => 'permit_empty|is_natural_no_zero',
        'nomor_urut'     => 'required|is_natural_no_zero|less_than_equal_to[99]|is_unique[candidates.nomor_urut,id,{id}]',
        'nama_ketua'     => 'required|max_length[150]',
        'nama_wakil'     => 'required|max_length[150]',
        'visi'           => 'permit_empty|max_length[2000]',
        'misi'           => 'permit_empty|max_length[5000]',
        'slogan'         => 'permit_empty|max_length[160]',
        'bio_ketua'      => 'permit_empty|max_length[600]',
        'bio_wakil'      => 'permit_empty|max_length[600]',
        'karakter_ketua' => 'permit_empty|max_length[120]',
        'karakter_wakil' => 'permit_empty|max_length[120]',
        'program'        => 'permit_empty|max_length[3000]',
        'pesan_ketua'    => 'permit_empty|max_length[300]',
        'pesan_wakil'    => 'permit_empty|max_length[300]',
        'video_youtube'  => 'permit_empty|regex_match[/^[A-Za-z0-9_-]{11}$/]',
        'theme_name'     => 'permit_empty|max_length[100]',
        'theme_accent'   => 'permit_empty|regex_match[/^#[0-9A-Fa-f]{6}$/]',
        'theme_layout'   => 'permit_empty|in_list[split,poster,column]',
        'status_aktif'   => 'permit_empty|in_list[0,1]',
    ];

    protected $validationMessages = [
        'nomor_urut' => [
            'required'              => 'Nomor urut wajib diisi.',
            'is_natural_no_zero'    => 'Nomor urut berupa angka mulai 1.',
            'less_than_equal_to'    => 'Nomor urut maksimal 99.',
            'is_unique'             => 'Nomor urut sudah dipakai pasangan lain.',
        ],
        'nama_ketua' => [
            'required'   => 'Nama calon ketua wajib diisi.',
            'max_length' => 'Nama calon ketua maksimal 150 karakter.',
        ],
        'nama_wakil' => [
            'required'   => 'Nama calon wakil wajib diisi.',
            'max_length' => 'Nama calon wakil maksimal 150 karakter.',
        ],
        'visi' => [
            'max_length' => 'Visi maksimal 2.000 karakter.',
        ],
        'misi' => [
            'max_length' => 'Misi maksimal 5.000 karakter.',
        ],
        'slogan' => [
            'max_length' => 'Slogan maksimal 160 karakter.',
        ],
        'bio_ketua' => [
            'max_length' => 'Bio calon ketua maksimal 600 karakter.',
        ],
        'bio_wakil' => [
            'max_length' => 'Bio calon wakil maksimal 600 karakter.',
        ],
        'karakter_ketua' => [
            'max_length' => 'Kata karakter calon ketua maksimal 120 karakter.',
        ],
        'karakter_wakil' => [
            'max_length' => 'Kata karakter calon wakil maksimal 120 karakter.',
        ],
        'program' => [
            'max_length' => 'Program unggulan maksimal 3.000 karakter.',
        ],
        'pesan_ketua' => [
            'max_length' => 'Pesan calon ketua maksimal 300 karakter.',
        ],
        'pesan_wakil' => [
            'max_length' => 'Pesan calon wakil maksimal 300 karakter.',
        ],
        'video_youtube' => [
            'regex_match' => 'Tautan YouTube tidak dikenali. Tempel tautan video, contoh https://youtu.be/xxxxxxxxxxx.',
        ],
        'theme_name' => [
            'max_length' => 'Nama tema maksimal 100 karakter.',
        ],
        'theme_accent' => [
            'regex_match' => 'Warna aksen harus berformat #RRGGBB, contoh #C4432B.',
        ],
        'theme_layout' => [
            'in_list' => 'Pilih layout otomatis, split, poster, atau kolom.',
        ],
    ];

    /**
     * Ambil pasangan calon aktif, diurutkan berdasarkan nomor urut.
     */
    public function getActiveCandidates(): array
    {
        return $this->where('status_aktif', 1)
            ->orderBy('nomor_urut', 'ASC')
            ->findAll();
    }

    /**
     * Semua pasangan (aktif & nonaktif) untuk panel admin dan analitik.
     */
    public function getAllOrdered(): array
    {
        return $this->orderBy('nomor_urut', 'ASC')->findAll();
    }

    /**
     * Jumlah seluruh baris suara (LOCKED maupun riwayat UNLOCKED) yang
     * menunjuk kandidat ini. > 0 berarti kandidat tidak dapat dihapus (FK RESTRICT).
     */
    public function voteRowCount(int $candidateId): int
    {
        $students = $this->db->table('student_votes')->where('candidate_id', $candidateId)->countAllResults();
        $teachers = $this->db->table('teacher_votes')->where('candidate_id', $candidateId)->countAllResults();

        return $students + $teachers;
    }

    /**
     * URL publik untuk asset kandidat, atau null bila belum diunggah.
     */
    public static function assetUrl(?string $filename): ?string
    {
        if ($filename === null || $filename === '') {
            return null;
        }

        return base_url(self::UPLOAD_DIR . '/' . rawurlencode(basename($filename)));
    }
}
