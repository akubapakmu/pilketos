<?php
/**
 * Foto ketua & wakil. Bila foto belum diunggah admin, tampil monogram
 * inisial polos di atas warna aksen pasangan (Stage 16: tanpa pola), tetap
 * punya nama yang dapat dibaca pembaca layar.
 *
 * Stage 16: profil paslon tidak memakai partial ini lagi (ukuran 'xl',
 * $rolePrefix, dan $priority dihapus).
 *
 * @var array  $c    Hasil CandidateTheme::present()
 * @var string $size 'sm' (Sekilas paslon, surat suara, konfirmasi, pilihan saya)
 * @var bool   $lazy true = loading="lazy"
 */
$size   = $size ?? 'sm';
$lazy   = $lazy ?? true;
$people = [
    ['role' => 'Ketua', 'name' => $c['ketua'], 'photo' => $c['photo_ketua'], 'initials' => $c['ketua_initials']],
    ['role' => 'Wakil', 'name' => $c['wakil'], 'photo' => $c['photo_wakil'], 'initials' => $c['wakil_initials']],
];
?>
<div class="portraits portraits--<?= esc($size, 'attr') ?>" data-portraits>
  <?php foreach ($people as $i => $p): ?>
    <figure class="portrait portrait--<?= $i === 0 ? 'ketua' : 'wakil' ?>">
      <?php if ($p['photo'] !== null): ?>
        <img class="portrait__img" src="<?= esc($p['photo'], 'attr') ?>"
             alt="Foto <?= esc($p['name'], 'attr') ?>, calon <?= esc(strtolower($p['role']), 'attr') ?> pasangan <?= esc($c['label'], 'attr') ?>"
             <?= $lazy ? 'loading="lazy"' : '' ?> decoding="async" width="480" height="600">
      <?php else: ?>
        <div class="portrait__mono" role="img"
             aria-label="<?= esc($p['name'], 'attr') ?>, calon <?= esc(strtolower($p['role']), 'attr') ?> pasangan <?= esc($c['label'], 'attr') ?> (foto belum tersedia)">
          <span class="portrait__initials" aria-hidden="true"><?= esc($p['initials']) ?></span>
        </div>
      <?php endif; ?>
      <figcaption class="portrait__role"><?= esc($p['role']) ?></figcaption>
    </figure>
  <?php endforeach; ?>
</div>
