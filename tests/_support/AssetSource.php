<?php

namespace Tests\Support;

use App\Libraries\AssetMinifier;

/**
 * Isi file aset untuk test (Stage 23). CSS/JS di public/assets adalah hasil
 * minify, jadi test yang memeriksa aturan CSS / kode JS membaca sumber rapinya
 * di resources/assets. File lain (SVG, gambar, robots.txt) dibaca dari public/.
 */
final class AssetSource
{
    public static function read(string $path): string
    {
        $path   = ltrim($path, '/');
        $type   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $source = isset(AssetMinifier::FOLDERS[$type]) && dirname($path) === AssetMinifier::FOLDERS[$type]
            ? ROOTPATH . AssetMinifier::SOURCE_DIR . '/' . $path
            : FCPATH . $path;

        return (string) file_get_contents($source);
    }
}
