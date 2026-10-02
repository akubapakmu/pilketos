<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\I18n\Time;

/**
 * Stage 24: pengaturan situs yang diubah admin dari panel (satu baris per
 * pengaturan, App\Models\SettingModel). Tidak ditempel ke tabel elections:
 * pengaturan tetap ada walaupun pemilihan belum dibuat.
 *
 * Isi awal: hero_lines = kalimat yang diketik bergantian di tengah hero
 * halaman utama (App\Libraries\HeroText, menu admin "Teks Halaman Utama"),
 * satu kalimat per baris. Langsung terisi di production setelah
 * `php spark migrate`; "SMP 1 DAWE" selalu kapital (aturan nama sekolah,
 * 07-TYPOGRAPHY-FONT-SYSTEM-PILKETOS.md §3).
 */
class CreateSettingsTable extends Migration
{
    private const HERO_LINES = [
        'PILKETOS SMP 1 DAWE dimulai di sini.',
        'Kenali calon ketua dan wakil ketua OSIS pilihanmu.',
        'Sampaikan pilihanmu dengan jujur dan bijak.',
        'Satu suara, satu pilihan, untuk masa depan sekolah.',
        'Gunakan hak pilihmu sebagai bagian dari demokrasi.',
        'Pilih pemimpin yang mewakili aspirasimu.',
        'Jadilah bagian dari pemilihan OSIS yang demokratis.',
        'Suaramu adalah bagian penting dari perubahan.',
        'Kenali calonnya. Tentukan pilihanmu.',
        'Ayo gunakan hak pilihmu dengan bertanggung jawab.',
        'Bersama menentukan pemimpin OSIS SMP 1 DAWE.',
        'PILKETOS: Jujur, demokratis, dan bertanggung jawab.',
    ];

    public function up()
    {
        $this->forge->addField([
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'comment'    => 'Kunci pengaturan, contoh hero_lines',
            ],
            'value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('name', true);

        $this->forge->createTable('settings', true, [
            'ENGINE'  => 'InnoDB',
            'CHARSET' => 'utf8mb4',
            'COLLATE' => 'utf8mb4_unicode_ci',
        ]);

        $this->db->table('settings')->insert([
            'name'       => 'hero_lines',
            'value'      => implode("\n", self::HERO_LINES),
            'updated_at' => Time::now()->toDateTimeString(),
        ]);
    }

    public function down()
    {
        $this->forge->dropTable('settings', true);
    }
}
