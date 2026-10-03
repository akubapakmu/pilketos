<?php
/**
 * Kartu avatar di dasbor siswa & guru (Stage 26), di dalam aside.dash-aside
 * setelah panel Jadwal pemilihan (di HP di bawah kartu status, jadi tombol
 * memilih tetap yang pertama). Tidak dikunci status pemilihan.
 *
 * - Unggah foto: dengan JavaScript foto dipotong bulat di browser
 *   (dashboard-avatar.js: geser, zoom 1-3x, panah & +/-), dikompres ke WebP
 *   512 px, lalu dikirim lewat fetch; tanpa JavaScript formulir biasa dan
 *   server memotong persegi di tengah.
 * - Acak avatar: pratinjau gaya + seed baru (DiceBear), lalu "Pakai avatar
 *   ini". Tanpa JavaScript server langsung mengacak dan menyimpan.
 * - Hapus foto: kembali ke avatar bawaan (hanya bila ada foto).
 *
 * @var \App\Services\VoterType $type
 * @var array                   $avatar VoterAvatars::present()
 * @var bool                    $voted  Sudah berpartisipasi (lencana seperti di wall)
 */
use App\Libraries\VoterAvatars;
use App\Services\VoterType;

$isStudent = $type === VoterType::Student;
$hasPhoto  = $avatar['photo'] !== null;
$caption   = $hasPhoto ? 'Foto ' . ($isStudent ? 'kamu' : 'Anda') : ($avatar['custom'] ? 'Avatar pilihan' . ($isStudent ? 'mu' : ' Anda') : 'Avatar bawaan');
?>
<section class="avatar-card" id="avatar" aria-labelledby="avatar-title" data-avatar-card
         data-upload-url="<?= esc(site_url($type->path('avatar')), 'attr') ?>"
         data-random-url="<?= esc(site_url($type->path('avatar/acak')), 'attr') ?>"
         data-remove-url="<?= esc(site_url($type->path('avatar/hapus')), 'attr') ?>"
         data-max-bytes="<?= VoterAvatars::CLIENT_MAX_BYTES ?>"
         data-pronoun="<?= $isStudent ? 'kamu' : 'Anda' ?>"
         data-avatar-custom="<?= $avatar['custom'] ? '1' : '0' ?>"
         data-art-module="<?= esc(asset_url('assets/js/avatar-art.js'), 'attr') ?>">
  <h2 class="dash-aside__title" id="avatar-title">Avatar</h2>

  <div class="avatar-card__stage" data-avatar-tilt>
    <span class="avatar-card__ring" aria-hidden="true"></span>
    <?= view('partials/avatar', ['avatar' => $avatar, 'class' => 'avatar--xl avatar-card__avatar', 'attrs' => 'data-avatar-current', 'eager' => true]) ?>
    <?php if ($voted): ?>
      <span class="avatar-card__badge" aria-hidden="true"><?= icon('check') ?></span>
    <?php endif; ?>
  </div>
  <p class="avatar-card__caption" data-avatar-caption><?= esc($caption) ?></p>

  <div class="avatar-card__actions">
    <form class="avatar-card__form" action="<?= site_url($type->path('avatar')) ?>" method="post" enctype="multipart/form-data" data-avatar-upload>
      <?= csrf_field() ?>
      <label class="btn btn--sm avatar-card__pick">
        <?= icon('upload') ?><span>Unggah foto</span>
        <input class="avatar-card__file" type="file" name="avatar" accept="<?= esc(VoterAvatars::ACCEPT) ?>" data-avatar-file>
      </label>
      <button type="submit" class="btn btn--sm btn--outline avatar-card__send">Kirim foto</button>
    </form>

    <form class="avatar-card__form" action="<?= site_url($type->path('avatar/acak')) ?>" method="post" data-avatar-random>
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--sm btn--outline"><?= icon('refresh') ?><span>Acak avatar</span></button>
    </form>

    <button type="button" class="btn btn--sm avatar-card__apply" data-avatar-apply hidden><?= icon('check') ?><span>Pakai avatar ini</span></button>
    <button type="button" class="avatar-card__cancel" data-avatar-cancel hidden>Batal</button>

    <form class="avatar-card__form" action="<?= site_url($type->path('avatar/hapus')) ?>" method="post" data-avatar-remove<?= $hasPhoto ? '' : ' hidden' ?>>
      <?= csrf_field() ?>
      <button type="submit" class="btn btn--sm btn--outline avatar-card__remove"><?= icon('trash') ?><span>Hapus foto</span></button>
    </form>
  </div>

  <p class="avatar-card__status" role="status" aria-live="polite" data-avatar-status></p>

  <p class="avatar-card__note">
    Foto atau avatar ini akan dipakai di
    <a href="<?= site_url('partisipasi') ?>"><strong>Living Election Wall</strong></a>.
    Wall hanya menandai partisipasi, tidak pernah menampilkan <?= $isStudent ? 'pilihanmu' : 'pilihan Anda' ?>.
  </p>
  <p class="avatar-card__credit">Ilustrasi avatar: DiceBear &mdash; Adventurer oleh Lisa Wischofsky dan Big Smile oleh Ashley Seo (CC BY 4.0), Avataaars oleh Pablo Stanley.</p>

  <dialog class="modal avatar-crop" id="avatar-crop" aria-labelledby="avatar-crop-title" aria-describedby="avatar-crop-hint" data-avatar-crop data-modal-static>
    <h2 class="avatar-crop__title" id="avatar-crop-title">Atur foto</h2>
    <p class="avatar-crop__hint" id="avatar-crop-hint">Geser foto agar wajah berada di dalam lingkaran. Perbesar dengan penggeser, tombol + dan &minus;, atau tombol panah dan +/&minus; di keyboard.</p>
    <div class="avatar-crop__stage" tabindex="0" role="group" aria-label="Posisi foto" aria-describedby="avatar-crop-hint" data-crop-stage>
      <canvas class="avatar-crop__canvas" data-crop-canvas></canvas>
      <span class="avatar-crop__mask" aria-hidden="true"></span>
    </div>
    <div class="avatar-crop__zoom">
      <button type="button" class="avatar-crop__step" data-crop-step="-1" aria-label="Perkecil foto"><?= icon('minus') ?></button>
      <input class="avatar-crop__range" type="range" min="1" max="3" step="0.01" value="1" aria-label="Perbesar foto" data-crop-range>
      <button type="button" class="avatar-crop__step" data-crop-step="1" aria-label="Perbesar foto"><?= icon('plus') ?></button>
    </div>
    <p class="avatar-crop__error" role="alert" data-crop-error hidden></p>
    <div class="modal__actions">
      <button type="button" class="btn" data-crop-save><?= icon('check') ?><span>Simpan foto</span></button>
      <button type="button" class="btn btn--outline" data-crop-cancel>Batal</button>
    </div>
  </dialog>
</section>
