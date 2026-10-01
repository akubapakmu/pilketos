<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stage 19: pengunjung online (Config\Visitors, App\Services\VisitorTracker).
 *
 * Satu baris per browser (cookie penanda acak; yang disimpan hanya hash
 * SHA-256-nya). Baris berpindah dari tamu ke pengguna saat masuk dan kembali
 * menjadi tamu setelah keluar, jadi komputer lab yang dipakai bergantian
 * tetap satu baris. Nama pengguna tidak disalin: halaman admin menggabungkan
 * user_type + user_id ke tabel students/teachers/admins saat ditampilkan.
 *
 * Tanpa foreign key: akun yang dihapus tidak boleh tertahan oleh catatan
 * kunjungan, dan baris lama dihapus otomatis (Config\Visitors::$retentionDays).
 */
class CreateVisitorsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'visitor_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
                'comment'    => 'SHA-256 cookie penanda browser',
            ],
            'user_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => true,
                'comment'    => 'student/teacher/admin; NULL = tamu (belum login)',
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 512,
                'null'       => true,
            ],
            'ua_hash' => [
                'type'       => 'CHAR',
                'constraint' => 40,
                'null'       => true,
                'comment'    => 'SHA-1 User-Agent + Client Hints: urai ulang hanya bila berubah',
            ],
            'client_hints' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'device_type'     => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'device_brand'    => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'device_model'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'os_name'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'os_version'      => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'os_platform'     => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
            'browser_name'    => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'browser_version' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'browser_type'    => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'engine'          => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'engine_version'  => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'language'        => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            // dari detak presence.js (null sampai detak pertama)
            'screen'       => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
            'viewport'     => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
            'pixel_ratio'  => ['type' => 'DECIMAL', 'constraint' => '4,2', 'null' => true],
            'timezone'     => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'touch_points' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'null' => true],
            'connection'   => ['type' => 'VARCHAR', 'constraint' => 16, 'null' => true],
            'color_scheme' => ['type' => 'VARCHAR', 'constraint' => 8, 'null' => true],
            'referrer' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Situs asal saat pertama datang (tanpa query string)',
            ],
            'landing_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'current_path' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'page_views' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'first_seen_at' => [
                'type' => 'DATETIME',
            ],
            'last_seen_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('visitor_hash');
        $this->forge->addKey('last_seen_at');
        $this->forge->addKey(['user_type', 'user_id']);

        $this->forge->createTable('visitors', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('visitors', true);
    }
}
