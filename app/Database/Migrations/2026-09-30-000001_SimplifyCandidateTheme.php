<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Stage 16: satu tampilan untuk semua pasangan calon.
 *
 * Halaman profil paslon kini dibuka satu gambar khusus buatan paslon
 * (theme_asset.banner) dan ditutup gambar latar (theme_asset.closing), sehingga
 * art direction per pasangan tidak dipakai lagi:
 * - theme_layout (split | poster | column) beserta CHECK-nya dihapus;
 * - theme_background (latar panggung) dihapus;
 * - kunci texture & artwork di theme_asset dihapus.
 *
 * File gambar milik tiga asset itu ikut dihapus dari public/uploads/candidates
 * lewat CandidateAssets::delete() (hanya nama file gambar di folder upload),
 * SETELAH skema berhasil diubah. down() memasang kembali kolomnya dalam
 * keadaan kosong; file yang sudah terhapus tidak dapat dikembalikan.
 */
class SimplifyCandidateTheme extends Migration
{
    /**
     * Kunci theme_asset yang tidak dipakai lagi.
     */
    private const RETIRED_ASSETS = ['texture', 'artwork'];

    public function up()
    {
        $files = [];
        $rows  = $this->db->table('candidates')->select('id, theme_background, theme_asset')->get()->getResultArray();

        foreach ($rows as $row) {
            $files[] = $row['theme_background'];
            $json    = is_string($row['theme_asset']) ? json_decode($row['theme_asset'], true) : null;

            if (! is_array($json)) {
                continue;
            }

            foreach (self::RETIRED_ASSETS as $key) {
                $files[] = $json[$key] ?? null;
                unset($json[$key]);
            }

            $json = array_filter($json, static fn ($value): bool => is_string($value) && $value !== '');

            $this->db->table('candidates')->where('id', (int) $row['id'])->update([
                'theme_asset' => $json === [] ? null : json_encode($json, JSON_UNESCAPED_UNICODE),
            ]);
        }

        $table = $this->db->escapeIdentifiers($this->db->prefixTable('candidates'));
        $this->db->query("ALTER TABLE {$table} DROP CONSTRAINT `chk_candidates_theme_layout`");
        $this->forge->dropColumn('candidates', ['theme_layout', 'theme_background']);

        $assets = service('candidateAssets');

        foreach (array_unique(array_filter($files, static fn ($file): bool => is_string($file) && $file !== '')) as $file) {
            $assets->delete($file);
        }
    }

    public function down()
    {
        $this->forge->addColumn('candidates', [
            'theme_background' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'after'      => 'theme_name',
                'comment'    => 'Path asset background/artwork pasangan',
            ],
            'theme_layout' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
                'after'      => 'theme_asset',
                'comment'    => 'Art direction halaman kandidat: split | poster | column (NULL = otomatis)',
            ],
        ]);

        $table = $this->db->escapeIdentifiers($this->db->prefixTable('candidates'));
        $this->db->query(
            "ALTER TABLE {$table} ADD CONSTRAINT `chk_candidates_theme_layout` "
            . "CHECK (`theme_layout` IS NULL OR `theme_layout` IN ('split', 'poster', 'column'))",
        );
    }
}
