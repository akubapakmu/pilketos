<?php
/**
 * Breadcrumb bergaya stepper (Stage 9, pengganti eyebrow "Kelompok · Halaman").
 * "Beranda" (dasbor admin) selalu menjadi langkah pertama; langkah terakhir =
 * halaman ini (aria-current).
 *
 * Stage 12: titik diganti ikon (sama dengan ikon menu samping). Halaman
 * mengisi section "crumbs"; layout merendernya dua kali:
 * - topbar (.admin-top__title, >= 720 px): ikon + teks;
 * - awal konten (.admin-crumbs, HP < 720 px): ikon saja, teks .crumbs__label
 *   tetap ada untuk pembaca layar.
 * Salinan yang tidak dipakai disembunyikan (display: none), jadi pembaca
 * layar hanya menemukan satu landmark Breadcrumb.
 *
 * Stage 13: di Beranda (trail hanya "Beranda") salinan HP tidak ditampilkan
 * (.crumbs--root): satu ikon tanpa langkah lain tidak memberi arah apa pun.
 *
 * @var list<array{0: string, 1?: string|null, 2?: string}> $trail [label, path admin|null, ikon] setelah Beranda
 */
$icons = [
    'Beranda'            => 'grid',
    'Analitik'           => 'chart',
    'Detail suara'       => 'list',
    'Hasil akhir'        => 'award',
    'Pasangan calon'     => 'flag',
    'Siswa'              => 'users',
    'Guru'               => 'user',
    'Jadwal pemilihan'   => 'calendar',
    'Teks halaman utama' => 'type',
    'Unlock hak suara'   => 'unlock',
    'Audit log'          => 'file',
    'Akun Admin'         => 'key',
    'Pengunjung online'  => 'eye',
    'Detail'             => 'eye',
    'Pratinjau'          => 'eye',
    'Tambah'             => 'plus',
    'Baru'               => 'plus',
    'Ubah'               => 'edit',
    'Impor Excel'        => 'upload',
    'Hasil'              => 'check',
];
$trail = array_merge([['Beranda', 'admin']], $trail ?? []);
$last  = count($trail) - 1;
?>
<nav class="crumbs<?= $last === 0 ? ' crumbs--root' : '' ?>" aria-label="Breadcrumb">
  <ol class="crumbs__list">
    <?php foreach ($trail as $i => $crumb): ?>
      <?php
        $label = $crumb[0];
        $path  = $crumb[1] ?? null;
        $inner = icon($crumb[2] ?? $icons[$label] ?? 'arrow-right', 'crumbs__icon') . '<span class="crumbs__label">' . esc($label) . '</span>';
      ?>
      <li class="crumbs__item<?= $i === $last ? ' is-current' : '' ?>">
        <?php if ($i === $last): ?>
          <span class="crumbs__step" aria-current="page"><?= $inner ?></span>
        <?php elseif ($path !== null): ?>
          <a class="crumbs__step" href="<?= site_url($path) ?>"><?= $inner ?></a>
        <?php else: ?>
          <span class="crumbs__step"><?= $inner ?></span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>
