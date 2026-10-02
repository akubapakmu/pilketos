<?php

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * Stage 24: pengaturan situs yang diubah admin dari panel (tabel settings,
 * satu baris per kunci; migration 2026-10-02-000001). Saat ini dipakai
 * hero_lines, teks hero halaman utama (App\Libraries\HeroText).
 */
class SettingModel extends Model
{
    protected $table            = 'settings';
    protected $primaryKey       = 'name';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $allowedFields    = ['name', 'value', 'updated_at'];

    /**
     * Nilai satu pengaturan, atau null bila belum pernah disimpan.
     */
    public function value(string $name): ?string
    {
        $row = $this->builder()->select('value')->where('name', $name)->get()->getRowArray();

        return isset($row['value']) ? (string) $row['value'] : null;
    }

    /**
     * Simpan satu pengaturan (baris dibuat bila belum ada).
     */
    public function put(string $name, ?string $value): void
    {
        $this->builder()->upsert([
            'name'       => $name,
            'value'      => $value,
            'updated_at' => Time::now()->toDateTimeString(),
        ]);
    }
}
