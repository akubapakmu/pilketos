<?php
/**
 * Suara per pasangan: batang horizontal berwarna aksen pasangan + donat.
 * Warna hanya penanda identitas; angka & persentase selalu tertulis.
 * Stage 17: kotak nomor diganti dua foto pasangan dalam satu kotak persegi
 * (admin/partials/pair_thumb); nomor muncul saat kotak diarahkan kursor.
 *
 * @var list<array<string, mixed>> $candidates Snapshot::candidates
 * @var int                        $total      Total suara sah
 * @var bool                       $donut      Tampilkan donat
 */
$donut  = $donut ?? true;
$radius = 52;
$circ   = 2 * M_PI * $radius;
$offset = 0.0;
?>
<div class="results<?= $donut ? ' results--donut' : '' ?>" data-live-results>
  <?php if ($candidates === []): ?>
    <p class="empty">Belum ada pasangan calon aktif. <a href="<?= site_url('admin/paslon/tambah') ?>">Tambah pasangan</a>.</p>
  <?php else: ?>
    <ol class="results__list">
      <?php foreach ($candidates as $c): ?>
        <li class="result" style="--accent: <?= esc($c['accent'], 'attr') ?>; --accent-ink: <?= esc($c['accent_ink'], 'attr') ?>;" data-live-candidate="<?= (int) $c['id'] ?>">
          <?= view('admin/partials/pair_thumb', ['pair' => $c, 'class' => 'result__thumb']) ?>
          <div class="result__body">
            <p class="result__names">
              <span class="visually-hidden">Pasangan <?= esc($c['label']) ?>:</span>
              <strong><?= esc($c['ketua']) ?></strong>
              <span>&amp; <?= esc($c['wakil']) ?></span>
              <?php if (! $c['active']): ?><span class="pill pill--muted">nonaktif</span><?php endif; ?>
            </p>
            <span class="result__track" aria-hidden="true"><span class="result__fill" style="width: <?= esc(number_format($c['percent'], 2, '.', ''), 'attr') ?>%;" data-live-bar></span></span>
            <p class="result__split">Siswa <span data-live-field="student_votes"><?= angka($c['student_votes']) ?></span> &middot; Guru <span data-live-field="teacher_votes"><?= angka($c['teacher_votes']) ?></span></p>
          </div>
          <p class="result__count">
            <span class="result__votes"><span data-live-field="votes"><?= angka($c['votes']) ?></span><span class="visually-hidden"> suara</span></span>
            <span class="result__pct" data-live-field="percent"><?= persen($c['percent']) ?></span>
          </p>
        </li>
      <?php endforeach; ?>
    </ol>

    <?php if ($donut): ?>
      <figure class="donut" aria-hidden="true">
        <svg viewBox="0 0 140 140" width="140" height="140" data-live-donut data-radius="<?= $radius ?>">
          <circle class="donut__track" cx="70" cy="70" r="<?= $radius ?>"></circle>
          <?php foreach ($candidates as $c): ?>
            <?php $length = $total > 0 ? $circ * $c['votes'] / $total : 0; ?>
            <circle class="donut__seg" cx="70" cy="70" r="<?= $radius ?>" data-candidate="<?= (int) $c['id'] ?>"
                    style="stroke: <?= esc($c['accent'], 'attr') ?>;"
                    stroke-dasharray="<?= esc(number_format($length, 3, '.', ''), 'attr') ?> <?= esc(number_format($circ - $length, 3, '.', ''), 'attr') ?>"
                    stroke-dashoffset="<?= esc(number_format(-$offset, 3, '.', ''), 'attr') ?>"></circle>
            <?php $offset += $length; ?>
          <?php endforeach; ?>
        </svg>
        <figcaption class="donut__center">
          <span class="donut__total" data-live-value="summary.all.voted"><?= angka($total) ?></span>
          <span class="donut__label">suara sah</span>
        </figcaption>
      </figure>
    <?php endif; ?>
  <?php endif; ?>
</div>
