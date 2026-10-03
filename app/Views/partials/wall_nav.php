<?php
/**
 * Header sticky Living Election Wall (Stage 26), pengganti navigasi situs:
 * kiri ikon kembali (ke scene perolehan suara beranda), tengah logo PILKETOS
 * versi latar gelap (Config\Homepage::$logoOnDark), kanan ikon dasbor sesuai
 * peran; tamu mendapat tautan "Masuk" ke portal masuk beranda (/#masuk).
 * Ikon tanpa garis tepi & latar (pola booth_nav, Stage 24).
 *
 * @var array{back: string, home: string, label: string, guest: bool} $wallNav
 */
$wallBrand    = config(\Config\Homepage::class);
$wallLogoSize = asset_size($wallBrand->logoOnDark);
?>
<header class="wall-nav">
  <nav class="wall-nav__row" aria-label="Navigasi Living Election Wall">
    <a class="wall-nav__icon" href="<?= esc($wallNav['back'], 'attr') ?>" title="Kembali ke perolehan suara"><?= icon('arrow-left') ?><span class="visually-hidden">Kembali ke perolehan suara</span></a>
    <a class="wall-nav__brand" href="<?= base_url('/') ?>">
      <img class="wall-nav__logo" src="<?= asset_url($wallBrand->logoOnDark) ?>" alt="Pemilihan Ketua OSIS SMP 1 DAWE 2026, ke beranda"
           <?= $wallLogoSize !== null ? 'width="' . $wallLogoSize[0] . '" height="' . $wallLogoSize[1] . '"' : '' ?> decoding="async">
    </a>
    <?php if ($wallNav['guest']): ?>
      <a class="wall-nav__icon wall-nav__icon--text" href="<?= esc($wallNav['home'], 'attr') ?>"><?= icon('grid') ?><span><?= esc($wallNav['label']) ?></span></a>
    <?php else: ?>
      <a class="wall-nav__icon" href="<?= esc($wallNav['home'], 'attr') ?>" title="<?= esc($wallNav['label']) ?>"><?= icon('grid') ?><span class="visually-hidden"><?= esc($wallNav['label']) ?></span></a>
    <?php endif; ?>
  </nav>
</header>
