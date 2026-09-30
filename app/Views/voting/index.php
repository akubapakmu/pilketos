<?= $this->extend('layouts/main') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/voting.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
/**
 * Bilik suara (siswa & guru). Stage 7: pembuka ringkas -> "Sekilas paslon"
 * (bandingkan & lompat) -> bab tiap pasangan (visi-misi interaktif) ->
 * surat suara + paku. Pemilih yang sudah mantap cukup satu ketukan dari
 * kartu ke kotak surat suara (#coblos-0X).
 *
 * Stage 8: pembuka rata tengah tanpa sapaan; langkah memilih menjadi
 * journey timeline vertikal (langkah 1-2 tautan ke bagiannya, langkah aktif
 * diperbarui candidates.js/ballot.js); countdown dalam panel terminal lebar
 * penuh. Di HP tiap bagian setinggi satu layar.
 *
 * Stage 9: panel terminal tanpa teks bilah judul & baris perintah (hanya
 * tiga titik jendela); pembuka + "Sekilas paslon" dibungkus .booth-open
 * sehingga di HP keduanya berbagi satu layar.
 *
 * Stage 10: navigasi bab punya id #navigasi-paslon, tujuan panah di bawah
 * "Sekilas paslon" (HP).
 *
 * Stage 15: bab tiap pasangan (visi-misi) pindah ke halaman profil paslon
 * (<siswa|guru>/paslon/0X, tombol "Profil lengkap" di kartu Sekilas paslon).
 * Bilik suara cukup: pembuka -> Sekilas paslon -> surat suara, sehingga
 * tetap ringkas berapa pun jumlah pasangan (batas diatur admin).
 *
 * @var \App\Services\VoterType $type
 * @var array                   $voter
 * @var array|null              $election
 * @var bool                    $canVote
 * @var list<array>             $candidates Hasil CandidateTheme::presentAll()
 */
$status  = $election['status'] ?? null;
$journey = [
    ['Kenali paslon', 'Bandingkan semua pasangan, buka profil lengkapnya.', $candidates !== [] ? '#sekilas' : null],
    ['Coblos satu', $canVote ? 'Tekan Coblos, atau seret paku ke kotak pilihanmu.' : 'Lihat surat suara dan kotak tiap pasangan.', '#surat-suara'],
    ['Konfirmasi & kunci', 'Periksa sekali lagi. Setelah dikunci, pilihan tidak dapat diubah.', null],
];
?>

<div class="booth-open">
<section class="booth-intro" aria-labelledby="booth-title">
  <div class="container booth-intro__inner">
    <h1 class="booth-intro__title" id="booth-title">Kenali, lalu <span class="booth-intro__verb">coblos</span>.</h1>

    <div class="booth-intro__panel">
      <ol class="journey" aria-label="Langkah memilih" data-journey>
        <?php foreach ($journey as $i => [$stepTitle, $stepText, $stepHref]): ?>
          <li class="journey__step<?= $i === 0 ? ' is-current' : '' ?>" style="--i: <?= $i ?>;" data-journey-step<?= $i === 0 ? ' aria-current="step"' : '' ?>>
            <<?= $stepHref !== null ? 'a href="' . esc($stepHref, 'attr') . '"' : 'div' ?> class="journey__item">
              <span class="journey__node" aria-hidden="true">
                <span class="journey__no"><?= $i + 1 ?></span>
                <?= icon('check', 'journey__check') ?>
              </span>
              <span class="journey__text">
                <span class="journey__title"><?= esc($stepTitle) ?><?= $stepHref !== null ? ' ' . icon('arrow-down') : '' ?></span>
                <span class="journey__desc"><?= esc($stepText) ?></span>
              </span>
            </<?= $stepHref !== null ? 'a' : 'div' ?>>
          </li>
        <?php endforeach; ?>
      </ol>

      <div class="booth-intro__status">
        <?php if ($election && in_array($status, ['UPCOMING', 'ONGOING'], true)): ?>
          <div class="term">
            <span class="term__dots" aria-hidden="true"><span></span><span></span><span></span></span>
            <div class="term__body">
              <?= view('partials/countdown', ['election' => $election, 'variant' => 'terminal']) ?>
              <p class="term__meta" aria-hidden="true">
                <?= $status === 'ONGOING' ? 'selesai' : 'mulai' ?> <?= esc(format_waktu($status === 'ONGOING' ? $election['end_at'] : $election['start_at'])) ?>
                <span class="term__cursor"></span>
              </p>
            </div>
          </div>
        <?php endif; ?>
        <?php if (! $canVote): ?>
          <p class="notice" role="status">
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
  </div>
</section>

<?php if ($candidates !== []): ?>
  <?= view('voting/partials/lineup', ['candidates' => $candidates, 'canVote' => $canVote, 'type' => $type]) ?>
<?php endif; ?>
</div>

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
<script src="<?= asset_url('assets/js/countdown.js') ?>" defer></script>
<script src="<?= asset_url('assets/js/candidates.js') ?>" defer></script>
<?php if ($canVote && $candidates !== []): ?>
  <script src="<?= asset_url('assets/js/ballot.js') ?>" defer></script>
<?php endif; ?>
<?= $this->endSection() ?>
