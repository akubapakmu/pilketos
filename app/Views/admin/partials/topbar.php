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
 * Stage 19: logo HP diatur di Config\Homepage ($adminLogoMobile,
 * $adminLogoMobileHeight, $adminLogoMobileAlt), seperti logo beranda.
 *
 * @var string $crumbs HTML breadcrumb
 */
$topBrand      = config(\Config\Homepage::class);
$topLogo       = $topBrand->adminLogoMobile !== '' ? $topBrand->adminLogoMobile : 'assets/img/brand/logo-admin-mobile.svg';
$topLogoSize   = asset_size($topLogo);
$topLogoHeight = max(24, min(48, $topBrand->adminLogoMobileHeight));
?>
<header class="admin-top">
  <button type="button" class="admin-top__menu" aria-controls="admin-nav" aria-expanded="false" data-admin-menu>
    <?= icon('menu') ?><span class="visually-hidden">Menu</span>
  </button>
  <a class="admin-top__logo" href="<?= site_url('admin') ?>" style="--admin-logo-h: <?= $topLogoHeight ?>px;">
    <img src="<?= asset_url($topLogo) ?>" alt="<?= esc($topBrand->adminLogoMobileAlt, 'attr') ?>"
         <?= $topLogoSize !== null ? 'width="' . $topLogoSize[0] . '" height="' . $topLogoSize[1] . '"' : '' ?> decoding="async">
  </a>
  <div class="admin-top__title"><?= $crumbs ?? '' ?></div>
</header>
