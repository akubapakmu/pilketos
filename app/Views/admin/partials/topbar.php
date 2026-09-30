<?php
/**
 * Topbar admin: tombol menu (HP), breadcrumb (Stage 12, dulu judul halaman).
 *
 * Breadcrumb = section "crumbs" halaman (admin/partials/crumbs) yang sudah
 * dirender layout; tampil mulai 720 px. Di HP breadcrumb ikon saja tampil di
 * atas judul (layouts/admin, .admin-crumbs).
 *
 * Stage 17: badge status pemilihan ("Sedang Berlangsung", dst.) dihapus dari
 * topbar di semua lebar layar (status tetap terbaca di indikator live dan
 * countdown). Di HP tombol menu tinggal ikon (tanpa teks & garis) dan header
 * menampilkan logo khusus "PILKETOS Panel Admin" (.admin-top__logo).
 *
 * @var string $crumbs HTML breadcrumb
 */
?>
<header class="admin-top">
  <button type="button" class="admin-top__menu" aria-controls="admin-nav" aria-expanded="false" data-admin-menu>
    <?= icon('menu') ?><span class="visually-hidden">Menu</span>
  </button>
  <a class="admin-top__logo" href="<?= site_url('admin') ?>">
    <img src="<?= asset_url('assets/img/brand/logo-admin-mobile.svg') ?>" alt="PILKETOS Panel Admin: Beranda" width="246" height="64">
  </a>
  <div class="admin-top__title"><?= $crumbs ?? '' ?></div>
</header>
