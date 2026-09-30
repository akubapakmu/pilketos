<?php
/**
 * Foto pasangan calon di scene perolehan suara (fokus visual utama):
 * foto berdua (asset "hero") bila ada; selain itu foto ketua & wakil
 * berdampingan; foto yang belum diunggah diganti monogram inisial di atas
 * warna aksen pasangan (tetap punya nama untuk pembaca layar).
 *
 * @var array $pair CandidateTheme::present()
 */
$pairPeople = [
    ['role' => 'ketua', 'name' => $pair['ketua'], 'photo' => $pair['photo_ketua'], 'initials' => $pair['ketua_initials']],
    ['role' => 'wakil', 'name' => $pair['wakil'], 'photo' => $pair['photo_wakil'], 'initials' => $pair['wakil_initials']],
];
?>
<div class="pair__photo<?= $pair['hero'] !== null ? ' pair__photo--hero' : '' ?>">
  <?php if ($pair['hero'] !== null): ?>
    <img class="pair__img" src="<?= esc($pair['hero'], 'attr') ?>"
         alt="Foto pasangan <?= esc($pair['label'], 'attr') ?>: <?= esc($pair['ketua'], 'attr') ?> dan <?= esc($pair['wakil'], 'attr') ?>"
         decoding="async" fetchpriority="low">
  <?php else: ?>
    <?php foreach ($pairPeople as $person): ?>
      <?php if ($person['photo'] !== null): ?>
        <img class="pair__img pair__img--half" src="<?= esc($person['photo'], 'attr') ?>"
             alt="Foto <?= esc($person['name'], 'attr') ?>, calon <?= esc($person['role'], 'attr') ?> pasangan <?= esc($pair['label'], 'attr') ?>"
             decoding="async" fetchpriority="low">
      <?php else: ?>
        <span class="pair__mono" role="img"
              aria-label="<?= esc($person['name'], 'attr') ?>, calon <?= esc($person['role'], 'attr') ?> pasangan <?= esc($pair['label'], 'attr') ?> (foto belum tersedia)">
          <span class="pair__initials" aria-hidden="true"><?= esc($person['initials']) ?></span>
        </span>
      <?php endif; ?>
    <?php endforeach; ?>
  <?php endif; ?>
  <span class="pair__no" aria-hidden="true"><?= esc($pair['label']) ?></span>
</div>
