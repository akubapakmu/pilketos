<?php
/**
 * Papan angka halaman Pengunjung online (Stage 19). Stage 21: partial
 * tersendiri karena juga dikirim sebagai fragmen oleh GET
 * admin/pengunjung/ringkas, dibaca admin-visitors.js tiap detik.
 *
 * @var array<string, mixed>   $summary OnlineVisitors::summary()
 * @var \CodeIgniter\I18n\Time $updated
 */
?>
<div data-live-region="ringkas">
  <dl class="scoreboard visitor-board">
    <div class="scoreboard__cell">
      <dt>Online sekarang</dt>
      <dd class="scoreboard__value"><?= angka($summary['total']) ?></dd>
      <dd class="scoreboard__sub"><?= angka($summary['devices']) ?> browser terbuka</dd>
    </div>
    <div class="scoreboard__cell">
      <dt>Tamu</dt>
      <dd class="scoreboard__value"><?= angka($summary['guests']) ?></dd>
      <dd class="scoreboard__sub">belum login</dd>
    </div>
    <div class="scoreboard__cell scoreboard__cell--accent">
      <dt>Pengguna login</dt>
      <dd class="scoreboard__value"><?= angka($summary['users']) ?> <span class="visitor-board__pct"><?= persen($summary['percent']) ?></span></dd>
      <dd class="scoreboard__sub">
        dari <?= angka($summary['accounts']) ?> pengguna sistem
        <span class="meter" aria-hidden="true"><span class="meter__fill" style="width: <?= esc(number_format(min(100, $summary['percent']), 2, '.', ''), 'attr') ?>%;"></span></span>
      </dd>
    </div>
    <?php foreach ($summary['roles'] as $type => $role): ?>
      <div class="scoreboard__cell">
        <dt><?= esc($role['label']) ?></dt>
        <dd class="scoreboard__value"><?= angka($role['online']) ?> <span class="visitor-board__pct"><?= persen($role['percent']) ?></span></dd>
        <dd class="scoreboard__sub">dari <?= angka($role['accounts']) ?> <?= esc(strtolower($role['label'])) ?><?= $type === 'admin' ? '' : ' aktif' ?></dd>
      </div>
    <?php endforeach; ?>
  </dl>
  <p class="visitor-updated">Diperbarui <time datetime="<?= esc($updated->toDateTimeString(), 'attr') ?>"><?= esc(format_waktu($updated->toDateTimeString(), 'HH.mm.ss')) ?></time></p>
</div>
