<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stage 15: profil & kampanye pasangan calon untuk halaman profil paslon
 * (siswa/paslon/0X, guru/paslon/0X). Semua kolom opsional: bagian halaman
 * yang datanya kosong tidak ditampilkan.
 *
 * - slogan                        : satu kalimat pendek milik pasangan;
 * - bio_ketua / bio_wakil         : 2-4 kalimat perkenalan;
 * - karakter_ketua / _wakil       : kata karakter dipisah koma (tampil maks. 3);
 * - program                       : program unggulan, satu per baris "Judul — penjelasan";
 * - pesan_ketua / pesan_wakil     : kutipan singkat;
 * - video_youtube                 : HANYA ID video YouTube (11 karakter), bukan
 *                                   URL bebas; diputar lewat youtube-nocookie.com.
 */
class AddProfileToCandidates extends Migration
{
    public function up()
    {
        $this->forge->addColumn('candidates', [
            'slogan' => [
                'type'       => 'VARCHAR',
                'constraint' => 160,
                'null'       => true,
                'after'      => 'misi',
            ],
            'bio_ketua' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'slogan',
            ],
            'bio_wakil' => [
                'type'  => 'TEXT',
                'null'  => true,
                'after' => 'bio_ketua',
            ],
            'karakter_ketua' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'after'      => 'bio_wakil',
                'comment'    => 'Kata karakter dipisah koma (tampil maks. 3)',
            ],
            'karakter_wakil' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'after'      => 'karakter_ketua',
                'comment'    => 'Kata karakter dipisah koma (tampil maks. 3)',
            ],
            'program' => [
                'type'    => 'TEXT',
                'null'    => true,
                'after'   => 'karakter_wakil',
                'comment' => 'Program unggulan, satu per baris: Judul — penjelasan',
            ],
            'pesan_ketua' => [
                'type'       => 'VARCHAR',
                'constraint' => 300,
                'null'       => true,
                'after'      => 'program',
            ],
            'pesan_wakil' => [
                'type'       => 'VARCHAR',
                'constraint' => 300,
                'null'       => true,
                'after'      => 'pesan_ketua',
            ],
            'video_youtube' => [
                'type'       => 'VARCHAR',
                'constraint' => 11,
                'null'       => true,
                'after'      => 'pesan_wakil',
                'comment'    => 'ID video YouTube (11 karakter), bukan URL',
            ],
        ]);

        $table = $this->db->escapeIdentifiers($this->db->prefixTable('candidates'));
        $this->db->query(
            "ALTER TABLE {$table} ADD CONSTRAINT `chk_candidates_video_youtube` "
            . "CHECK (`video_youtube` IS NULL OR `video_youtube` REGEXP '^[A-Za-z0-9_-]{11}$')",
        );
    }

    public function down()
    {
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('candidates'));
        $this->db->query("ALTER TABLE {$table} DROP CONSTRAINT `chk_candidates_video_youtube`");

        $this->forge->dropColumn('candidates', [
            'slogan', 'bio_ketua', 'bio_wakil', 'karakter_ketua', 'karakter_wakil',
            'program', 'pesan_ketua', 'pesan_wakil', 'video_youtube',
        ]);
    }
}
