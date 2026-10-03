<?php
/**
 * Avatar bulat pemilih (Stage 26): kartu avatar dasbor, Living Election Wall,
 * detail pemilih di panel admin.
 *
 * Foto: <img> langsung (lazy). Avatar DiceBear: inisial nama sebagai
 * tampilan tanpa JavaScript; avatar-art.js menggambar avatar dari
 * data-avatar-style + data-avatar-seed (malas, IntersectionObserver).
 *
 * @var array{photo: string|null, style: string, seed: string, initials: string, custom: bool} $avatar VoterAvatars::present()
 * @var string                                                                                  $class  Kelas tambahan (ukuran)
 * @var string                                                                                  $attrs  Atribut tambahan (sudah di-escape pemanggil)
 * @var bool                                                                                    $eager  Foto dimuat langsung (bukan lazy)
 */
$avatarPhoto = $avatar['photo'] ?? null;
?>
<span class="avatar<?= $avatarPhoto !== null ? ' avatar--photo' : '' ?><?= ($class ?? '') !== '' ? ' ' . esc($class, 'attr') : '' ?>" data-avatar data-avatar-style="<?= esc($avatar['style'], 'attr') ?>" data-avatar-seed="<?= esc($avatar['seed'], 'attr') ?>"<?= ($attrs ?? '') !== '' ? ' ' . $attrs : '' ?>>
  <?php if ($avatarPhoto !== null): ?>
    <img class="avatar__img" src="<?= esc($avatarPhoto, 'attr') ?>" alt="" width="320" height="320" <?= ($eager ?? false) ? '' : 'loading="lazy" ' ?>decoding="async">
  <?php endif; ?>
  <span class="avatar__initials" aria-hidden="true"><?= esc($avatar['initials']) ?></span>
</span>
