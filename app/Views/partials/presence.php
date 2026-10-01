<?php
/**
 * Stage 19: skrip detak pengunjung online (presence.js) untuk semua layout,
 * dipasang setelah app.js. Tidak dipasang bila Config\Visitors::$enabled false.
 */
$presenceConfig = config(\Config\Visitors::class);
?>
<?php if ($presenceConfig->enabled): ?>
<script src="<?= asset_url('assets/js/presence.js') ?>" defer data-presence-url="<?= esc(site_url('kunjungan/detak'), 'attr') ?>" data-presence-interval="<?= max(30, $presenceConfig->heartbeatSeconds) ?>"></script>
<?php endif; ?>
