<?php
/**
 * Keadaan final di dasbor (Stage 4): tampil hanya bila pemilihan FINISHED.
 * Tanpa confetti; perayaan hanya di halaman hasil akhir.
 *
 * Stage 19: judul pemenang dua baris berhuruf kapital ("PASANGAN 0X
 * TERPILIH" lalu nama ketua & wakil di baris kedua, kapital lewat CSS);
 * kalimat "Pencoblosan ditolak server sejak waktu selesai; ..." dihapus.
 *
 * @var array<string, mixed> $final FinalResult::build()
 */
use App\Services\FinalResult;

$winner = $final['winner'];
?>
<section class="final-banner<?= $winner !== null ? ' final-banner--winner' : '' ?>"
  <?php if ($winner !== null): ?>style="--accent: <?= esc($winner['accent'], 'attr') ?>; --accent-ink: <?= esc($winner['accent_ink'], 'attr') ?>;"<?php endif; ?>
  aria-labelledby="final-banner-title">
  <?php if ($winner !== null): ?>
    <span class="final-banner__no" aria-hidden="true"><?= esc($winner['label']) ?></span>
  <?php endif; ?>
  <div class="final-banner__body">
    <p class="final-banner__eyebrow"><?= icon('lock') ?> Pemilihan selesai &middot; hasil akhir</p>
    <h2 class="final-banner__title" id="final-banner-title">
      <?php if ($final['state'] === FinalResult::WINNER): ?>
        <span class="final-banner__lead">Pasangan <?= esc($winner['label']) ?> terpilih<span class="visually-hidden">:</span></span>
        <span class="final-banner__names"><?= esc($winner['ketua']) ?> &amp; <?= esc($winner['wakil']) ?></span>
      <?php elseif ($final['state'] === FinalResult::TIE): ?>
        Perolehan suara tertinggi sama
      <?php else: ?>
        Pemilihan selesai tanpa suara sah
      <?php endif; ?>
    </h2>
    <?php if ($winner !== null): ?>
      <p class="final-banner__text"><?= angka($winner['votes']) ?> suara (<?= persen($winner['percent']) ?> dari <?= angka($final['total_votes']) ?> suara sah).</p>
    <?php endif; ?>
  </div>
  <a class="btn final-banner__cta" href="<?= site_url('admin/hasil') ?>"><?= icon('award') ?> Buka hasil akhir</a>
</section>
