<?php

namespace App\Controllers\Admin;

use App\Libraries\HeroText;
use App\Models\AuditLogModel;
use App\Models\SettingModel;
use CodeIgniter\HTTP\RedirectResponse;

/**
 * Stage 24: Teks Halaman Utama (admin/halaman-utama). Kalimat yang diketik
 * bergantian di tengah gambar hero halaman utama, satu baris = satu kalimat
 * (App\Libraries\HeroText); kosong = hero tanpa teks.
 *
 * Teks hero tidak memengaruhi hasil, jadi tetap dapat diubah setelah
 * pemilihan selesai. Setiap perubahan tercatat di audit log beserta isinya.
 */
class HomepageController extends AdminController
{
    /**
     * GET admin/halaman-utama
     */
    public function index()
    {
        return $this->render('admin/homepage/index', [
            'title'  => 'Teks Halaman Utama',
            'lines'  => HeroText::lines(),
            'errors' => (array) (session()->getFlashdata('errors') ?? []),
        ], 'homepage');
    }

    /**
     * POST admin/halaman-utama
     */
    public function save(): RedirectResponse
    {
        $raw = $this->request->getPost('hero_lines');

        // Isian hilang/berbentuk array tidak pernah dianggap "kosongkan teks".
        if (! is_string($raw)) {
            return redirect()->to('admin/halaman-utama')->with('errors', ['hero_lines' => 'Isi teks tidak valid. Muat ulang halaman lalu coba lagi.']);
        }

        $lines  = HeroText::parse($raw);
        $errors = HeroText::errors($lines);

        if ($errors !== []) {
            return redirect()->to('admin/halaman-utama')->withInput()->with('errors', ['hero_lines' => implode(' ', $errors)]);
        }

        $before = HeroText::lines();

        if ($before === $lines) {
            return redirect()->to('admin/halaman-utama')->with('success', 'Tidak ada perubahan teks.');
        }

        model(SettingModel::class)->put(HeroText::SETTING, HeroText::text($lines));

        $this->audit(AuditLogModel::HOMEPAGE_TEXT, sprintf(
            'Teks halaman utama: %d kalimat (sebelumnya %d).%s',
            count($lines),
            count($before),
            $lines === [] ? ' Hero tampil tanpa teks.' : ' ' . implode(' | ', $lines),
        ));

        return redirect()->to('admin/halaman-utama')->with('success', $lines === []
            ? 'Teks halaman utama dikosongkan. Hero tampil tanpa teks.'
            : sprintf('Teks halaman utama disimpan: %d kalimat diketik bergantian.', count($lines)));
    }
}
