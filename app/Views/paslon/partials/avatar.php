<?php
/**
 * Avatar kecil satu orang (Stage 15; Stage 16: kotak bersudut membulat, di
 * kartu "Pesan dari kami"): foto dipotong, atau monogram inisial di atas
 * warna aksen. Dekoratif: nama selalu ditulis di sebelahnya.
 *
 * @var array  $c    Hasil CandidateTheme::present()
 * @var string $role 'ketua' | 'wakil'
 */
$photo    = $c['photo_' . $role];
$initials = $c[$role . '_initials'];
?>
<span class="px-avatar" aria-hidden="true">
  <?php if ($photo !== null): ?>
    <img class="px-avatar__img" src="<?= esc($photo, 'attr') ?>" alt="" loading="lazy" decoding="async" width="96" height="96">
  <?php else: ?>
    <span class="px-avatar__mono"><?= esc($initials) ?></span>
  <?php endif; ?>
</span>
