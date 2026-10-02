<?php
/**
 * Menu melayang bilik suara (Stage 24), pengganti navigasi situs (logo,
 * Dasbor, Keluar) di halaman bilik-suara: kiri panah + "Kembali" (ke Sekilas
 * paslon), kanan ikon Dasbor tanpa teks (nama tetap untuk pembaca layar).
 * Ikon tanpa garis tepi & latar; .container selebar surat suara sehingga di
 * desktop ikon sejajar tepi isi bilik suara (voting.css .booth-nav).
 *
 * @var array{back: string, home: string, label: string} $boothNav
 */
?>
<header class="booth-nav">
  <nav class="container booth-nav__row" aria-label="Navigasi bilik suara">
    <a class="booth-nav__back" href="<?= esc($boothNav['back'], 'attr') ?>"><?= icon('arrow-left') ?><span>Kembali</span></a>
    <a class="booth-nav__home" href="<?= esc($boothNav['home'], 'attr') ?>" title="<?= esc($boothNav['label'], 'attr') ?>"><?= icon('grid') ?><span class="visually-hidden"><?= esc($boothNav['label']) ?></span></a>
  </nav>
</header>
