<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stage 26: avatar pemilih untuk Living Election Wall (siswa & guru).
 *
 * - avatar_file       : nama file foto hasil encode ulang server di
 *                       public/uploads/avatars/ (s{id}-{16hex}.webp / t{id}-...);
 * - avatar_style      : gaya avatar DiceBear pilihan pemilih (adventurer,
 *                       avataaars, big-smile);
 * - avatar_seed       : seed avatar DiceBear, 16 heksa (dibuat browser);
 * - avatar_updated_at : perubahan avatar terakhir.
 *
 * Semua NULL = avatar bawaan, dihitung deterministik tanpa menulis database
 * (App\Libraries\AvatarIdentity::default()). Kolom ini sengaja TIDAK masuk
 * allowedFields StudentModel/TeacherModel (impor & VoterEditor tidak dapat
 * menyentuhnya); hanya App\Libraries\VoterAvatars yang menulisnya.
 */
class AddAvatarToVoters extends Migration
{
    private const TABLES = ['students', 'teachers'];

    private const COLUMNS = ['avatar_file', 'avatar_style', 'avatar_seed', 'avatar_updated_at'];

    public function up()
    {
        foreach (self::TABLES as $table) {
            $this->forge->addColumn($table, [
                'avatar_file' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 80,
                    'null'       => true,
                    'after'      => 'status_aktif',
                    'comment'    => 'Foto avatar (public/uploads/avatars), nama acak dari server',
                ],
                'avatar_style' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                    'after'      => 'avatar_file',
                    'comment'    => 'Gaya avatar DiceBear pilihan pemilih',
                ],
                'avatar_seed' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'null'       => true,
                    'after'      => 'avatar_style',
                    'comment'    => 'Seed avatar DiceBear (16 heksa)',
                ],
                'avatar_updated_at' => [
                    'type'  => 'DATETIME',
                    'null'  => true,
                    'after' => 'avatar_seed',
                ],
            ]);
        }
    }

    public function down()
    {
        foreach (self::TABLES as $table) {
            $this->forge->dropColumn($table, self::COLUMNS);
        }
    }
}
