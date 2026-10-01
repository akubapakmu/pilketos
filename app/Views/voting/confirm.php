<?= $this->extend('layouts/main') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/voting.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
/**
 * Konfirmasi tanpa JavaScript (fallback terakhir bila script/efek gagal).
 *
 * @var \App\Services\VoterType $type
 * @var array                   $candidate Hasil CandidateTheme::present()
 */
$c = $candidate;
?>
<section class="section confirm-page" style="<?= esc($c['style'], 'attr') ?>">
  <div class="container">
    <div class="confirm confirm--page">
      <p class="confirm__kicker">Konfirmasi pilihan</p>
      <h1 class="confirm__title">Pasangan <?= esc($c['label']) ?></h1>
      <div class="confirm__portraits">
        <?= view('voting/partials/portraits', ['c' => $c, 'size' => 'sm', 'lazy' => false]) ?>
      </div>
      <dl class="confirm__names">
        <div>
          <dt>Calon Ketua</dt>
          <dd><?= esc($c['ketua']) ?></dd>
        </div>
        <div>
          <dt>Calon Wakil</dt>
          <dd><?= esc($c['wakil']) ?></dd>
        </div>
      </dl>
      <p class="confirm__warning">
        <?= icon('lock') ?>
        <span>Setelah dikonfirmasi, pilihan akan <strong>dikunci</strong> dan tidak dapat diubah.</span>
      </p>
      <form action="<?= base_url($type->path('coblos')) ?>" method="post" class="modal__actions">
        <?= csrf_field() ?>
        <input type="hidden" name="candidate_id" value="<?= esc((string) $c['id'], 'attr') ?>">
        <button type="submit" class="btn btn--accent btn--lg" data-loading-text="MENYIMPAN SUARA...">KONFIRMASI PILIHAN</button>
        <a class="btn btn--outline" href="<?= base_url($type->path('bilik-suara')) ?>#surat-suara">Batal, pilih ulang</a>
      </form>
    </div>
  </div>
</section>

<?= $this->endSection() ?>
