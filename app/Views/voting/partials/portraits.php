<?php
/**
 * Foto ketua & wakil. Bila foto belum diunggah admin, tampil monogram
 * inisial di atas warna aksen pasangan (tetap punya nama yang dapat dibaca
 * pembaca layar).
 *
 * Stage 15: ukuran 'xl' untuk hero profil paslon (paslon/partials/profile),
 * $rolePrefix mis. "Calon " sehingga keterangan menjadi "Calon Ketua", dan
 * $priority = true untuk foto di layar pertama (fetchpriority="high").
 *
 * @var array  $c          Hasil CandidateTheme::present()
 * @var string $size       'xl' (profil paslon) | 'sm' (Sekilas paslon, surat suara, konfirmasi)
 * @var bool   $lazy       true = loading="lazy"
 * @var string $rolePrefix Awalan keterangan peran (bawaan kosong)
 * @var bool   $priority   true = fetchpriority="high"
 */
$size       = $size ?? 'sm';
$lazy       = $lazy ?? true;
$rolePrefix = $rolePrefix ?? '';
$priority   = $priority ?? false;
$people     = [
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
             <?= $lazy ? 'loading="lazy"' : '' ?><?= $priority ? ' fetchpriority="high"' : '' ?> decoding="async" width="480" height="600">
      <?php else: ?>
        <div class="portrait__mono" role="img"
             aria-label="<?= esc($p['name'], 'attr') ?>, calon <?= esc(strtolower($p['role']), 'attr') ?> pasangan <?= esc($c['label'], 'attr') ?> (foto belum tersedia)">
          <span class="portrait__pattern portrait__pattern--<?= esc($c['pattern'], 'attr') ?>" aria-hidden="true"></span>
          <span class="portrait__initials" aria-hidden="true"><?= esc($p['initials']) ?></span>
        </div>
      <?php endif; ?>
      <figcaption class="portrait__role"><?= esc($rolePrefix . $p['role']) ?></figcaption>
    </figure>
  <?php endforeach; ?>
</div>
