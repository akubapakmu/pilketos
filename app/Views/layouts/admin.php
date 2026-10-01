<!DOCTYPE html>
<html lang="id" class="no-js">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#15141A">
<?= csrf_meta() ?>
<title><?= esc($title ?? 'Panel Admin') ?> — Admin Pemilihan OSIS SMP 1 DAWE</title>
<script <?= csp_script_nonce() ?>>document.documentElement.className = document.documentElement.className.replace('no-js', 'js');</script>
<link rel="preload" href="<?= base_url('assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= base_url('assets/fonts/newsreader-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/admin.css') ?>">
<?= $this->renderSection('head') ?>
</head>
<?php
/**
 * Layout panel admin (Stage 3): sidebar (drawer di HP), topbar, konten.
 *
 * Stage 12: section "crumbs" (breadcrumb halaman) dirender sekali lalu
 * dipasang dua kali: di topbar (desktop, ikon + teks) dan di awal konten
 * (HP, ikon saja).
 *
 * @var array       $admin    Admin yang login (AdminController::admin())
 * @var array|null  $election Election berjalan
 * @var string      $nav      Menu aktif
 */
$crumbs = $this->renderSection('crumbs');
$this->setVar('crumbs', $crumbs);
?>
<body class="admin">
<a class="skip-link" href="#main">Langsung ke konten</a>

<div class="admin-shell" data-admin-shell>
  <?= $this->include('admin/partials/sidebar') ?>

  <div class="admin-main" data-admin-main>
    <?= $this->include('admin/partials/topbar') ?>
    <?= $this->include('partials/flash') ?>

    <main id="main" class="admin-content" tabindex="-1">
      <div class="admin-crumbs"><?= $crumbs ?></div>
      <?= $this->renderSection('content') ?>
    </main>

    <footer class="admin-foot">
      <p>&copy; <?= date('Y') ?> SMP 1 DAWE</p>
    </footer>
  </div>
</div>

<div class="admin-scrim" data-admin-scrim hidden></div>

<dialog id="admin-confirm" class="modal modal--confirm" aria-labelledby="admin-confirm-title" aria-describedby="admin-confirm-text">
  <p class="modal__eyebrow">Konfirmasi tindakan</p>
  <h2 class="modal__title" id="admin-confirm-title">Lanjutkan?</h2>
  <p class="modal__text" id="admin-confirm-text"></p>
  <div class="modal__actions">
    <button type="button" class="btn" data-confirm-accept>Lanjutkan</button>
    <button type="button" class="btn btn--outline" data-modal-close>Batal</button>
  </div>
</dialog>

<script src="<?= asset_url('assets/js/app.js') ?>" defer></script>
<?= $this->include('partials/presence') ?>
<script src="<?= asset_url('assets/js/admin.js') ?>" defer></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
