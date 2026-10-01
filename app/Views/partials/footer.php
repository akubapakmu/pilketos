<?php
/**
 * Footer minimal, rata tengah (redesign beranda). Halaman biasa: di bawah
 * <main>. Beranda imersif: dirender di dalam scene terakhir
 * ($footerInScene = true) karena halaman beranda tidak menggulir.
 *
 * Stage 19: beranda mengirim $online (OnlineVisitors::publicCounts()):
 * satu baris di atas teks footer berisi pengunjung yang belum login dan
 * yang sudah login, diperbarui presence.js.
 *
 * @var bool                                             $footerInScene
 * @var array{total: int, guests: int, users: int}|null $online
 */
?>
<footer class="site-footer<?= ($footerInScene ?? false) ? ' site-footer--scene' : '' ?>">
  <?php if (($online ?? null) !== null): ?>
    <p class="site-footer__online">
      <span class="site-footer__online-label">Online</span>
      <span><span class="site-footer__num" data-online-count="guests"><?= angka($online['guests']) ?></span> belum login</span>
      <span><span class="site-footer__num" data-online-count="users"><?= angka($online['users']) ?></span> sudah login</span>
    </p>
  <?php endif; ?>
  <p class="site-footer__text">&copy; <?= date('Y') ?> SMP 1 DAWE <span aria-hidden="true">&middot;</span> Pemilihan Ketua &amp; Wakil Ketua OSIS</p>
</footer>
