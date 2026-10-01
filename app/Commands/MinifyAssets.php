<?php

namespace App\Commands;

use App\Libraries\AssetMinifier;
use App\Libraries\CandidateAssets;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Minify;

/**
 * php spark osis:minify [--clean]
 *
 * Stage 19: membuat ulang semua public/assets/css/*.min.css dan
 * public/assets/js/*.min.js dari sumbernya (Config\Minify). Di production file
 * ini juga dibuat otomatis saat sumber berubah; perintah ini dipakai setelah
 * update bila folder aset tidak dapat ditulis oleh web server, atau untuk
 * melihat hasilnya. --clean menghapus semua file *.min.* hasil buatan.
 */
class MinifyAssets extends BaseCommand
{
    protected $group       = 'Pemilihan OSIS';
    protected $name        = 'osis:minify';
    protected $description = 'Membuat ulang file *.min.css / *.min.js (minify aset untuk production).';
    protected $usage       = 'osis:minify [--clean]';
    protected $options     = ['--clean' => 'Hapus semua file *.min.css / *.min.js hasil buatan.'];

    public function run(array $params)
    {
        // Perintah eksplisit: berjalan di environment apa pun.
        $minifier = new AssetMinifier(null, 'production');

        if (array_key_exists('clean', $params) || CLI::getOption('clean') !== null) {
            CLI::write('Dihapus ' . $minifier->clean() . ' file *.min.css / *.min.js.', 'green');

            return EXIT_SUCCESS;
        }

        $config = config(Minify::class);

        if (! $config->enabled) {
            CLI::write('Minify nonaktif (Config\Minify::$enabled = false): browser menerima kode asli. Tidak ada yang dibuat.', 'yellow');

            return EXIT_SUCCESS;
        }

        $failed = 0;

        foreach ($minifier->sources() as $path) {
            $type = pathinfo($path, PATHINFO_EXTENSION);

            if (! $config->{$type}) {
                CLI::write(str_pad('[LEWATI]', 10) . $path . ' (Config\Minify::$' . $type . ' = false)', 'yellow');

                continue;
            }

            $result = $minifier->build($path);

            if ($result === null) {
                $failed++;
                CLI::write(str_pad('[GAGAL]', 10) . $path . ': tidak dapat dibuat (lihat writable/logs), file asli tetap disajikan', 'red');

                continue;
            }

            CLI::write(sprintf(
                '%s%s -> %s  %s -> %s (-%d%%)',
                str_pad('[OK]', 10),
                $path,
                basename($result['target']),
                CandidateAssets::formatBytes($result['before']),
                CandidateAssets::formatBytes($result['after']),
                $result['before'] > 0 ? (int) round(100 - $result['after'] * 100 / $result['before']) : 0,
            ), 'green');
        }

        if (ENVIRONMENT !== 'production') {
            CLI::newLine();
            CLI::write('Catatan: CI_ENVIRONMENT = ' . ENVIRONMENT . '; file *.min.* baru disajikan ke browser saat production.', 'yellow');
        }

        return $failed > 0 ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
