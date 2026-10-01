<?php

namespace App\Commands;

use App\Libraries\AssetMinifier;
use App\Libraries\CandidateAssets;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * php spark osis:minify [--clean]
 *
 * Stage 22: membangun ulang semua public/assets/css/*.css dan
 * public/assets/js/*.js (ter-minify + watermark, Config\Minify) dari kode
 * sumber rapi di resources/assets/. Jalankan setiap selesai mengedit file di
 * resources/assets/, lalu commit sumber & hasilnya bersama. Sisa file
 * *.min.css / *.min.js Stage 20 ikut dihapus; --clean hanya menghapus sisa itu.
 */
class MinifyAssets extends BaseCommand
{
    protected $group       = 'Pemilihan OSIS';
    protected $name        = 'osis:minify';
    protected $description = 'Membangun ulang CSS/JS di public/assets (minify + watermark) dari resources/assets.';
    protected $usage       = 'osis:minify [--clean]';
    protected $options     = ['--clean' => 'Hanya hapus sisa file *.min.css / *.min.js Stage 20.'];

    public function run(array $params)
    {
        $minifier = new AssetMinifier();

        if (array_key_exists('clean', $params) || CLI::getOption('clean') !== null) {
            CLI::write('Dihapus ' . $minifier->clean() . ' file sisa *.min.css / *.min.js.', 'green');

            return EXIT_SUCCESS;
        }

        if (! AssetMinifier::available() && ($minifier->active('css') || $minifier->active('js'))) {
            CLI::error('Library minify belum terpasang: jalankan "composer install" lalu ulangi. File di public/assets tidak diubah.');

            return EXIT_ERROR;
        }

        $failed = 0;

        foreach ($minifier->sources() as $path) {
            $type   = pathinfo($path, PATHINFO_EXTENSION);
            $result = $minifier->build($path);

            if ($result === null) {
                $failed++;
                CLI::write(str_pad('[GAGAL]', 10) . $path . ': tidak dapat dibuat (lihat writable/logs), file lama tetap disajikan', 'red');

                continue;
            }

            CLI::write(sprintf(
                '%s%s  %s -> %s%s',
                str_pad($result['changed'] ? '[OK]' : '[SAMA]', 10),
                $path,
                CandidateAssets::formatBytes($result['before']),
                CandidateAssets::formatBytes($result['after']),
                $minifier->active($type)
                    ? sprintf(' (-%d%%)', $result['before'] > 0 ? (int) round(100 - $result['after'] * 100 / $result['before']) : 0)
                    : ' (minify ' . $type . ' nonaktif: salinan sumber)',
            ), $minifier->active($type) ? 'green' : 'yellow');
        }

        $cleaned = $minifier->clean();

        if ($cleaned > 0) {
            CLI::write('Dihapus ' . $cleaned . ' file sisa *.min.css / *.min.js Stage 20.', 'green');
        }

        return $failed > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
