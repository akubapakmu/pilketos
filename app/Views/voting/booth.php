<?= $this->extend('layouts/main') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/voting.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
/**
 * Bilik suara (Stage 22: halaman sendiri, GET <siswa|guru>/bilik-suara).
 * Tujuan tombol 3D "Pilih Paslon 0X" di profil paslon (bilik-suara#coblos-0X:
 * kotak pasangan itu disorot dan tombol Coblos-nya difokuskan, ballot.js).
 *
 * Isi sama dengan surat suara Stage 2-15: kotak per pasangan, paku coblos
 * (tekan-tahan-geser-lepas, 3D WebGL dengan cadangan 2D), modal konfirmasi
 * + POST JSON ke <siswa|guru>/coblos. Tanpa JavaScript tombol "Coblos" tetap
 * membuka halaman konfirmasi biasa. Stage 24: menu melayang (Kembali +
 * Dasbor, partials/booth_nav lewat $boothNav dari controller) menggantikan
 * navigasi situs; countdown dihapus (server tetap menolak coblosan di luar
 * jadwal).
 *
 * @var \App\Services\VoterType $type
 * @var array|null              $election
 * @var bool                    $canVote
 * @var list<array>             $candidates Hasil CandidateTheme::presentAll()
 */
?>
<?= view('voting/partials/ballot', [
    'candidates' => $candidates,
    'canVote'    => $canVote,
    'election'   => $election,
    'type'       => $type,
]) ?>

<?php if ($canVote && $candidates !== []): ?>
  <?= view('voting/partials/confirm_dialog', ['type' => $type]) ?>
  <div class="nail2d" data-nail2d hidden aria-hidden="true">
    <span class="nail2d__shadow"></span>
    <span class="nail2d__body"><?= view('voting/partials/nail_svg') ?></span>
  </div>
  <canvas class="nail3d" data-nail3d hidden aria-hidden="true"></canvas>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($canVote && $candidates !== []): ?>
  <script src="<?= asset_url('assets/js/ballot.js') ?>" defer></script>
<?php endif; ?>
<?= $this->endSection() ?>
