<?php
/**
 * Layout halaman publik & pemilih (beranda, login, dasbor, voting).
 *
 * @var bool        $immersive Beranda imersif (redesign beranda): latar gelap,
 *                             viewport-fit=cover (safe-area HP), footer dirender
 *                             di dalam scene terakhir. Layar pembuka selalu tampil
 *                             (Stage 6), kecuali setelah muat ulang otomatis karena
 *                             status pemilihan berubah: penanda sekali pakai
 *                             sessionStorage "osis2026.skipIntro" (non-sensitif)
 *                             dibaca & dihapus di sini sebelum render pertama.
 * @var string|null $bodyClass
 */
$immersive = (bool) ($immersive ?? false);
?>
<!DOCTYPE html>
<html lang="id" class="no-js<?= $immersive ? ' is-immersive' : '' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1<?= $immersive ? ', viewport-fit=cover' : '' ?>">
<meta name="description" content="Pemilihan Ketua &amp; Wakil Ketua OSIS SMP 1 DAWE 2026">
<meta name="theme-color" content="<?= $immersive ? '#101312' : '#FAF9F6' ?>">
<?= csrf_meta() ?>
<title><?= isset($title) ? esc($title) . ' — Pemilihan OSIS SMP 1 DAWE' : 'Pemilihan Ketua &amp; Wakil Ketua OSIS SMP 1 DAWE 2026' ?></title>
<script <?= csp_script_nonce() ?>>document.documentElement.className = document.documentElement.className.replace('no-js', 'js');<?php if ($immersive): ?>
try { if (window.sessionStorage.getItem('osis2026.skipIntro') === '1') { window.sessionStorage.removeItem('osis2026.skipIntro'); document.documentElement.className += ' intro-seen'; } } catch (e) {}<?php endif; ?></script>
<link rel="preload" href="<?= base_url('assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<?php if (! $immersive): ?>
<link rel="preload" href="<?= base_url('assets/fonts/newsreader-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<?php endif; ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
<?= $this->renderSection('head') ?>
</head>
<body class="<?= esc($bodyClass ?? '', 'attr') ?>">
<a class="skip-link" href="#main">Langsung ke konten</a>
<?= $this->renderSection('overlay') ?>
<?= $this->include('partials/header') ?>
<?= $this->include('partials/flash') ?>
<main id="main" tabindex="-1">
<?= $this->renderSection('content') ?>
</main>
<?php if (! $immersive): ?>
<?= $this->include('partials/footer') ?>
<?php endif; ?>
<script src="<?= asset_url('assets/js/app.js') ?>" defer></script>
<?= $this->include('partials/presence') ?>
<?= $this->renderSection('scripts') ?>
</body>
</html>
