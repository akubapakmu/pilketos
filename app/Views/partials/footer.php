<?php
/**
 * Footer minimal, rata tengah (redesign beranda). Halaman biasa: di bawah
 * <main>. Beranda imersif: dirender di dalam scene terakhir
 * ($footerInScene = true) karena halaman beranda tidak menggulir.
 *
 * Stage 19: beranda mengirim $online (OnlineVisitors::publicCounts()):
 * satu baris di atas teks footer berisi pengunjung yang belum login dan
 * yang sudah login, diperbarui presence.js (Stage 21: tiap detik). Di HP
 * label "Online" diganti ikon siaran biru muda (teks tetap untuk pembaca
 * layar).
 *
 * Stage 26: di scene perolehan suara beranda, tautan "Living Election Wall"
 * (GET partisipasi) sebaris dengan angka online di desktop (dipisah garis
 * tipis) dan tepat di bawahnya di HP; keduanya dibungkus .site-footer__row.
 * Hanya bila Config\Homepage::$electionWall aktif.
 *
 * @var bool                                             $footerInScene
 * @var array{total: int, guests: int, users: int}|null $online
 */
$footerWall = ($footerInScene ?? false) && config(\Config\Homepage::class)->electionWall;
?>
<footer class="site-footer<?= ($footerInScene ?? false) ? ' site-footer--scene' : '' ?>">
  <?php if (($online ?? null) !== null || $footerWall): ?>
    <div class="site-footer__row">
      <?php if (($online ?? null) !== null): ?>
        <p class="site-footer__online">
          <span class="site-footer__online-label"><?= icon('live', 'site-footer__online-icon') ?><span class="site-footer__online-text">Online</span></span>
          <span><span class="site-footer__num" data-online-count="guests"><?= angka($online['guests']) ?></span> belum login</span>
          <span><span class="site-footer__num" data-online-count="users"><?= angka($online['users']) ?></span> sudah login</span>
        </p>
      <?php endif; ?>
      <?php if ($footerWall): ?>
        <a class="site-footer__wall" href="<?= site_url('partisipasi') ?>">Living Election Wall<?= icon('arrow-up-right', 'site-footer__wall-icon') ?></a>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <p class="site-footer__text">&copy; <?= date('Y') ?> SMP 1 DAWE <span aria-hidden="true">&middot;</span> Pemilihan Ketua &amp; Wakil Ketua OSIS</p>
</footer>
