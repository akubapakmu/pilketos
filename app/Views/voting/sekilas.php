<?= $this->extend('layouts/main') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/voting.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
/**
 * "Sekilas paslon" (Stage 7; Stage 22: halaman sendiri, GET
 * <siswa|guru>/sekilas-paslon, tujuan tombol "Selanjutnya" di pembuka).
 *
 * Desain kartu sama dengan sebelumnya (garis aksen, nomor, potret, nama,
 * visi). Stage 22:
 * - tanpa petunjuk (lineup__hint) dan tanpa tombol "Pilih 0X": satu-satunya
 *   tombol kartu = "Profil Lengkap Paslon 0X" selebar kartu, latar aksen
 *   dengan motif SVG unik tiap paslon (CandidateTheme::MOTIFS, voting.css),
 *   menuju profil paslon; dari sana tombol 3D "Pilih Paslon 0X" membuka
 *   bilik suara;
 * - tanpa geser otomatis: HP satu kartu per baris (bertumpuk), >= 720 px dua,
 *   >= 1024 px paling banyak tiga per baris (--cols-md dari
 *   CandidateTheme::gridStyle()), baris terakhir rata tengah.
 *
 * @var list<array>             $candidates Hasil CandidateTheme::presentAll()
 * @var bool                    $canVote
 * @var \App\Services\VoterType $type
 */
use App\Libraries\CandidateTheme;
?>
<section class="lineup" id="sekilas" aria-labelledby="lineup-title">
  <div class="container">
    <header class="lineup__head">
      <h1 class="lineup__title" id="lineup-title">Sekilas paslon</h1>
    </header>

    <?php if ($candidates === []): ?>
      <p class="notice lineup__empty"><?= icon('info') ?><span>Belum ada pasangan calon aktif.</span></p>
    <?php else: ?>
      <ol class="lineup__list" aria-label="Pasangan calon" style="<?= esc(CandidateTheme::gridStyle(count($candidates)), 'attr') ?>">
        <?php foreach ($candidates as $c): ?>
          <li class="pair-card" style="<?= esc($c['style'], 'attr') ?>">
            <div class="pair-card__band">
              <p class="pair-card__no"><span class="visually-hidden">Pasangan </span><?= esc($c['label']) ?></p>
              <?= view('voting/partials/portraits', ['c' => $c, 'size' => 'sm', 'lazy' => false]) ?>
            </div>

            <p class="pair-card__theme">
              <?php if ($c['theme_distinct']): ?>
                <span><?= esc($c['theme_name']) ?></span>
              <?php endif; ?>
              <span class="pair-card__meta"><?= count($c['misi']) ?> poin misi</span>
            </p>
            <h2 class="pair-card__names">
              <span><span class="pair-card__role">Ketua</span> <?= esc($c['ketua']) ?></span>
              <span><span class="pair-card__role">Wakil</span> <?= esc($c['wakil']) ?></span>
            </h2>

            <?php if ($c['visi'] !== ''): ?>
              <p class="pair-card__visi"><?= esc($c['visi']) ?></p>
            <?php endif; ?>

            <a class="pair-card__profile pair-card__profile--<?= esc($c['motif'], 'attr') ?>" href="<?= esc(site_url($type->path('paslon/' . $c['label'])), 'attr') ?>">
              <span class="pair-card__profile-text">Profil Lengkap Paslon <?= esc($c['label']) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ol>
    <?php endif; ?>
  </div>
</section>
<?= $this->endSection() ?>
