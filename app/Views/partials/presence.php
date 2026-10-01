<?php
/**
 * Stage 19: skrip detak pengunjung online (presence.js) untuk semua layout,
 * dipasang setelah app.js. Tidak dipasang bila Config\Visitors::$enabled false.
 * Stage 21: bila angka online publik aktif, presence.js juga membaca
 * GET kunjungan/online tiap $counterRefreshSeconds detik di halaman yang
 * menampilkan angka itu (beranda).
 */
$presenceConfig = config(\Config\Visitors::class);
?>
<?php if ($presenceConfig->enabled): ?>
<script src="<?= asset_url('assets/js/presence.js') ?>" defer data-presence-url="<?= esc(site_url('kunjungan/detak'), 'attr') ?>" data-presence-interval="<?= max(30, $presenceConfig->heartbeatSeconds) ?>"<?php if ($presenceConfig->publicCounter): ?> data-online-url="<?= esc(site_url('kunjungan/online'), 'attr') ?>" data-online-refresh="<?= max(1, $presenceConfig->counterRefreshSeconds) ?>"<?php endif; ?>></script>
<?php endif; ?>
