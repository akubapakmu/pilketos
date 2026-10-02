<?php
/**
 * Navigasi panel admin. Di layar lebar menjadi sidebar tetap; di HP menjadi
 * drawer (admin.js). Tanpa JavaScript tampil sebagai menu biasa di atas konten.
 *
 * Stage 9: brand satu baris "SMP 1 DAWE" + "Panel Admin"; menu tanpa nomor
 * dan tanpa judul kelompok (kelompok hanya dipisah garis tipis); dasbor
 * bernama "Beranda".
 *
 * Stage 13: label menu Title Case (huruf pertama setiap kata kapital).
 * "Halaman Utama" (dulu tombol "Home") dan "Keluar" pindah dari kaki sidebar
 * menjadi kelompok menu terakhir (paling bawah), bersama "Akun Admin"
 * (ganti nama pengguna & kata sandi). Kaki sidebar tinggal "Masuk sebagai".
 *
 * Stage 19: menu "Pengunjung Online" (admin/pengunjung) di kelompok ketiga.
 *
 * Stage 24: menu "Teks Halaman Utama" (admin/halaman-utama, teks hero yang
 * diketik di halaman utama) setelah Jadwal Pemilihan.
 *
 * @var array  $admin
 * @var string $nav
 */
$groups = [
    [
        'dashboard' => ['admin', 'grid', 'Beranda'],
        'analytics' => ['admin/analitik', 'chart', 'Analitik'],
        'votes'     => ['admin/analitik/suara', 'list', 'Detail Suara'],
        'results'   => ['admin/hasil', 'award', 'Hasil Akhir'],
    ],
    [
        'candidates' => ['admin/paslon', 'flag', 'Pasangan Calon'],
        'students'   => ['admin/siswa', 'users', 'Siswa'],
        'teachers'   => ['admin/guru', 'user', 'Guru'],
    ],
    [
        'election' => ['admin/jadwal', 'calendar', 'Jadwal Pemilihan'],
        'homepage' => ['admin/halaman-utama', 'type', 'Teks Halaman Utama'],
        'unlock'   => ['admin/buka-kunci', 'unlock', 'Unlock Hak Suara'],
        'audit'    => ['admin/riwayat', 'file', 'Audit Log'],
        'visitors' => ['admin/pengunjung', 'eye', 'Pengunjung Online'],
    ],
];
?>
<aside class="admin-side" id="admin-nav" aria-label="Menu admin" data-admin-nav>
  <div class="admin-side__brand">
    <a href="<?= site_url('admin') ?>" class="admin-side__mark">SMP 1 DAWE</a>
    <p class="admin-side__school">Panel Admin</p>
    <button type="button" class="admin-side__close" data-admin-menu-close aria-label="Tutup menu"><?= icon('close') ?></button>
  </div>

  <nav class="admin-side__nav">
    <?php foreach ($groups as $items): ?>
      <ul class="admin-side__list">
        <?php foreach ($items as $key => [$path, $iconName, $label]): ?>
          <li>
            <a href="<?= site_url($path) ?>" class="admin-side__link"<?= ($nav ?? '') === $key ? ' aria-current="page"' : '' ?>>
              <?= icon($iconName, 'admin-side__icon') ?>
              <span><?= esc($label) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>

    <ul class="admin-side__list admin-side__list--end">
      <li>
        <a href="<?= site_url('admin/akun') ?>" class="admin-side__link"<?= ($nav ?? '') === 'account' ? ' aria-current="page"' : '' ?>>
          <?= icon('key', 'admin-side__icon') ?>
          <span>Akun Admin</span>
        </a>
      </li>
      <li>
        <a href="<?= base_url('/') ?>" class="admin-side__link" target="_blank" rel="noopener">
          <?= icon('home', 'admin-side__icon') ?>
          <span>Halaman Utama<span class="visually-hidden"> (tab baru)</span></span>
        </a>
      </li>
      <li>
        <form action="<?= site_url('admin/keluar') ?>" method="post" class="admin-side__form">
          <?= csrf_field() ?>
          <button type="submit" class="admin-side__link admin-side__link--button">
            <?= icon('logout', 'admin-side__icon') ?>
            <span>Keluar</span>
          </button>
        </form>
      </li>
    </ul>
  </nav>

  <div class="admin-side__foot">
    <p class="admin-side__who">
      <span class="admin-side__who-label">Masuk sebagai</span>
      <strong><?= esc($admin['name'] ?? 'Admin') ?></strong>
      <span class="admin-side__who-user">@<?= esc($admin['username'] ?? '') ?></span>
    </p>
  </div>
</aside>
