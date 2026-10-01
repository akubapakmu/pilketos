<?= $this->extend('layouts/main') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/voting.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
/**
 * Pembuka alur memilih (siswa & guru), GET <siswa|guru>/coblos.
 *
 * Stage 22: bilik suara dipecah menjadi halaman berurutan: pembuka ini ->
 * Sekilas paslon (sekilas-paslon) -> profil paslon (paslon/0X, tombol 3D
 * "Pilih Paslon 0X") -> bilik suara (bilik-suara, surat suara + paku).
 *
 * Pembuka = satu layar satu langkah: tulisan besar di tengah layar diketik
 * (booth-steps.js) langkah demi langkah, lalu tombol "Selanjutnya" muncul.
 * Teks lengkap ketiga langkah selalu ada di markup: tanpa JavaScript, dengan
 * prefers-reduced-motion, atau setelah animasi ditonton di sesi ini, ketiga
 * langkah tampil bertumpuk bersama tombolnya. Countdown pindah ke bilik suara.
 *
 * @var \App\Services\VoterType $type
 * @var array|null              $election
 * @var bool                    $canVote
 * @var list<array>             $candidates Hasil CandidateTheme::presentAll()
 */
$status = $election['status'] ?? null;
$steps  = [
    ['Kenali Calonmu', esc('Yuk, kenalan lebih dekat dengan setiap pasangan calon Ketua & Wakil Ketua OSIS. Lihat profil, visi, misi, dan gagasan mereka sebelum menentukan pilihanmu!')],
    ['Pilih Pasanganmu', esc('Tekan Coblos, atau seret paku ke kotak pasangan yang kamu pilih.')],
    ['Cek Lagi Pilihanmu', esc('Sudah yakin? Periksa kembali pilihanmu sebelum dikunci. Setelah dikunci, pilihan ') . '<strong>tidak dapat diubah</strong>.'],
];
?>

<section class="steps" aria-labelledby="steps-title" data-steps>
  <div class="container steps__inner">
    <h1 class="visually-hidden" id="steps-title">Langkah memilih</h1>

    <ol class="steps__list" data-steps-list>
      <?php foreach ($steps as $i => [$stepTitle, $stepText]): ?>
        <li class="steps__item" style="--i: <?= $i ?>;" data-step>
          <p class="steps__count" aria-hidden="true"><span><?= sprintf('%02d', $i + 1) ?></span> / <?= sprintf('%02d', count($steps)) ?></p>
          <h2 class="steps__title" data-type="title"><?= esc($stepTitle) ?></h2>
          <p class="steps__desc" data-type="desc"><?= $stepText ?></p>
        </li>
      <?php endforeach; ?>
    </ol>

    <div class="steps__end" data-steps-end>
      <a class="btn btn--lg steps__next" href="<?= esc(site_url($type->path('sekilas-paslon')), 'attr') ?>" data-steps-next>Selanjutnya <?= icon('arrow-right') ?></a>
      <?php if (! $canVote): ?>
        <p class="notice steps__notice" role="status">
          <?= icon($status === 'FINISHED' ? 'lock' : 'clock') ?>
          <span>
            <?php if ($status === 'UPCOMING'): ?>
              Pencoblosan belum dibuka. Halaman ini hanya untuk mengenal pasangan calon.
            <?php elseif ($status === 'FINISHED'): ?>
              Pemilihan telah ditutup. Pencoblosan tidak tersedia.
            <?php else: ?>
              Jadwal pemilihan belum tersedia.
            <?php endif; ?>
          </span>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <div class="steps__bar" data-steps-bar hidden>
    <span class="steps__dots" aria-hidden="true">
      <?php foreach ($steps as $i => $unused): ?><span class="steps__dot" data-steps-dot></span><?php endforeach; ?>
    </span>
    <button type="button" class="steps__skip" data-steps-skip>Lewati <?= icon('arrow-right') ?></button>
  </div>
</section>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_url('assets/js/booth-steps.js') ?>" defer></script>
<?= $this->endSection() ?>
