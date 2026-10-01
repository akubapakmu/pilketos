<?php
/**
 * Stage 21: foto pasangan ukuran penuh (dibuka dari foto di scene perolehan
 * suara / pasangan calon, button.pair__zoom; home.js). Satu <dialog> native
 * per pasangan yang tampil: fokus terkunci di dalam, Escape & klik latar
 * menutup, fokus kembali ke foto yang diklik.
 *
 * Bagian atas: nomor & nama pasangan, lalu tautan "Lihat profil pasangan"
 * menuju profil paslon di panel pengguna yang sedang login (Home::profileUrl;
 * tamu tanpa tautan). Di bawahnya foto tampil utuh (tidak dipotong): foto
 * berdua bila ada; selain itu foto ketua & wakil berdampingan, monogram
 * inisial untuk foto yang belum diunggah.
 *
 * @var array $pair CandidateTheme::present() + profile (URL|null)
 */
$viewId     = 'pair-view-' . (int) $pair['id'];
$viewPeople = [
    ['role' => 'Calon Ketua', 'name' => $pair['ketua'], 'photo' => $pair['photo_ketua'], 'initials' => $pair['ketua_initials']],
    ['role' => 'Calon Wakil', 'name' => $pair['wakil'], 'photo' => $pair['photo_wakil'], 'initials' => $pair['wakil_initials']],
];
?>
<dialog class="pair-view" id="<?= esc($viewId, 'attr') ?>" style="<?= esc($pair['style'], 'attr') ?>" aria-labelledby="<?= esc($viewId, 'attr') ?>-title" data-pair-view>
  <div class="pair-view__inner">
    <div class="pair-view__top">
      <p class="pair-view__title" id="<?= esc($viewId, 'attr') ?>-title">
        <span class="pair-view__no"><span class="visually-hidden">Pasangan </span><?= esc($pair['label']) ?></span>
        <span class="pair-view__names"><?= esc($pair['ketua']) ?> <span aria-hidden="true">&amp;</span><span class="visually-hidden">dan</span> <?= esc($pair['wakil']) ?></span>
      </p>
      <?php if (($pair['profile'] ?? null) !== null): ?>
        <a class="pair-view__profile" href="<?= esc($pair['profile'], 'attr') ?>">Lihat profil pasangan <?= icon('arrow-right') ?></a>
      <?php endif; ?>
    </div>

    <?php if ($pair['hero'] !== null): ?>
      <div class="pair-view__photo pair-view__photo--hero">
        <img class="pair-view__img" src="<?= esc($pair['hero'], 'attr') ?>"
             alt="Foto pasangan <?= esc($pair['label'], 'attr') ?>: <?= esc($pair['ketua'], 'attr') ?> dan <?= esc($pair['wakil'], 'attr') ?>"
             loading="lazy" decoding="async">
      </div>
    <?php else: ?>
      <div class="pair-view__photo pair-view__photo--duo">
        <?php foreach ($viewPeople as $person): ?>
          <figure class="pair-view__person">
            <?php if ($person['photo'] !== null): ?>
              <img class="pair-view__img" src="<?= esc($person['photo'], 'attr') ?>"
                   alt="Foto <?= esc($person['name'], 'attr') ?>, <?= esc(strtolower($person['role']), 'attr') ?> pasangan <?= esc($pair['label'], 'attr') ?>"
                   loading="lazy" decoding="async">
            <?php else: ?>
              <span class="pair-view__mono" aria-hidden="true"><?= esc($person['initials']) ?></span>
            <?php endif; ?>
            <figcaption class="pair-view__caption"><strong><?= esc($person['name']) ?></strong> <span><?= esc($person['role']) ?></span></figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <button type="button" class="pair-view__close" data-pair-close><?= icon('close') ?><span class="visually-hidden">Tutup foto pasangan <?= esc($pair['label']) ?></span></button>
</dialog>
