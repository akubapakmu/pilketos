<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stage 15: batas jumlah pasangan calon AKTIF per pemilihan, diatur admin di
 * halaman Pasangan calon (bawaan 3, rentang 2-10). Pasangan nonaktif tidak
 * dihitung. Lihat ElectionModel::batasPasangan().
 */
class AddBatasPasanganToElections extends Migration
{
    public function up()
    {
        $this->forge->addColumn('elections', [
            'batas_pasangan' => [
                'type'       => 'TINYINT',
                'constraint' => 2,
                'unsigned'   => true,
                'null'       => false,
                'default'    => 3,
                'after'      => 'end_at',
                'comment'    => 'Batas jumlah pasangan calon aktif (2-10)',
            ],
        ]);

        $table = $this->db->escapeIdentifiers($this->db->prefixTable('elections'));
        $this->db->query(
            "ALTER TABLE {$table} ADD CONSTRAINT `chk_elections_batas_pasangan` "
            . 'CHECK (`batas_pasangan` BETWEEN 2 AND 10)',
        );
    }

    public function down()
    {
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('elections'));
        $this->db->query("ALTER TABLE {$table} DROP CONSTRAINT `chk_elections_batas_pasangan`");

        $this->forge->dropColumn('elections', 'batas_pasangan');
    }
}
