<?php
/**
 * Layout layar terbelah (Stage 13, login admin): tanpa navigasi situs dan
 * footer; halaman sendiri yang mengatur panel gambar & panel isi.
 * Kepala dokumen sama dengan layouts/main (font lokal, CSRF meta, kelas js).
 *
 * @var string|null $title
 * @var string|null $bodyClass
 */
?>
<!DOCTYPE html>
<html lang="id" class="no-js">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#15141A">
<?= csrf_meta() ?>
<title><?= isset($title) ? esc($title) . ' — Pemilihan OSIS SMP 1 DAWE' : 'Pemilihan Ketua &amp; Wakil Ketua OSIS SMP 1 DAWE 2026' ?></title>
<script <?= csp_script_nonce() ?>>document.documentElement.className = document.documentElement.className.replace('no-js', 'js');</script>
<link rel="preload" href="<?= base_url('assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= base_url('assets/fonts/newsreader-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
<?= $this->renderSection('head') ?>
</head>
<body class="<?= esc($bodyClass ?? '', 'attr') ?>">
<a class="skip-link" href="#main">Langsung ke konten</a>
<?= $this->renderSection('content') ?>
<script src="<?= asset_url('assets/js/app.js') ?>" defer></script>
<?= $this->include('partials/presence') ?>
<?= $this->renderSection('scripts') ?>
</body>
</html>
