<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Pengunjung online (Stage 19, hanya-baca).
 *
 * @var array<string, mixed>                         $summary   OnlineVisitors::summary()
 * @var array<string, list<array<string, mixed>>>    $breakdown OnlineVisitors::breakdown()
 * @var list<array<string, mixed>>                   $rows
 * @var int                                          $total
 * @var string                                       $scope
 * @var string                                       $who
 * @var string                                       $query
 * @var string                                       $pager
 * @var string|null                                  $mine      hash baris browser admin ini
 * @var int                                          $window    detik batas online
 * @var bool                                         $enabled
 * @var int                                          $refresh
 * @var \CodeIgniter\I18n\Time                       $updated
 */
use App\Services\OnlineVisitors;
use App\Services\VisitorTracker;

$roleLabels = OnlineVisitors::ROLES;
$kinds      = ['HP' => 'phone', 'Tablet' => 'tablet', 'Komputer' => 'monitor'];
$filtered   = $who !== '' || $query !== '';
$joinParts  = static fn (array $parts): string => implode(' · ', array_filter(array_map(static fn ($p): string => trim((string) $p), $parts), static fn (string $p): bool => $p !== ''));
// merek + model tanpa menulis merek dua kali ("Samsung" + "Galaxy A54" / "Apple" + "iPhone")
$deviceName = static function (?string $brand, ?string $model): string {
    if ($model === null || $model === '') {
        return (string) $brand;
    }

    return $brand === null || stripos($model, $brand) === 0 ? $model : $brand . ' ' . $model;
};
?>
<div class="admin-page visitors-page" data-visitors data-visitors-refresh="<?= (int) $refresh ?>">
  <header class="admin-head">
    <div class="admin-head__text">
      <div class="admin-head__heading">
        <h1 class="admin-head__title">Pengunjung online</h1>
        <?= view('admin/partials/head_hint') ?>
      </div>
      <p class="admin-head__lede" id="admin-head-lede" data-lede>
        Tamu dan pengguna login yang membuka situs. Dihitung online bila aktif dalam <?= esc(durasi($window)) ?> terakhir;
        halaman yang terbuka mengirim detak setiap menit. Perangkat, sistem operasi, dan browser dibaca dari User-Agent
        dan Client Hints, jadi hanya perkiraan. Halaman pemilih dicatat tanpa nomor paslon. Diperbarui otomatis setiap <?= (int) $refresh ?> detik.
      </p>
    </div>
  </header>

  <?php if (! $enabled): ?>
    <p class="notice"><?= icon('info') ?><span>Pencatatan pengunjung sedang dimatikan (<code>visitors.enabled = false</code>). Angka di bawah adalah catatan terakhir.</span></p>
  <?php endif; ?>

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

  <form class="filters" method="get" action="<?= site_url('admin/pengunjung') ?>" role="search" data-autosubmit data-live-search>
    <div class="field filters__search">
      <label for="v-q">Cari (nama, NISN/NIP, IP, browser, perangkat)</label>
      <span class="search-field"><?= icon('search', 'search-field__icon') ?><input type="search" id="v-q" name="q" value="<?= esc($query, 'attr') ?>" maxlength="100" autocomplete="off"></span>
    </div>
    <div class="field">
      <label for="v-waktu">Waktu</label>
      <select id="v-waktu" name="waktu">
        <?php foreach (OnlineVisitors::SCOPES as $key => $label): ?>
          <option value="<?= esc($key, 'attr') ?>"<?= $scope === $key ? ' selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="v-siapa">Pengunjung</label>
      <select id="v-siapa" name="siapa">
        <?php foreach (OnlineVisitors::WHO as $key => $label): ?>
          <option value="<?= esc($key, 'attr') ?>"<?= $who === $key ? ' selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="filters__actions" data-live-region="actions">
      <button type="submit" class="btn btn--sm"><?= icon('search') ?> Terapkan</button>
      <?php if ($filtered || $scope !== OnlineVisitors::SCOPE_ONLINE): ?><a class="btn btn--sm btn--outline" href="<?= site_url('admin/pengunjung') ?>">Reset</a><?php endif; ?>
    </div>
  </form>

  <div data-live-region="pengunjung">
    <section class="visitor-split" aria-label="Sebaran perangkat, browser, dan sistem operasi">
      <?php foreach (['device' => 'Perangkat', 'browser' => 'Browser', 'os' => 'Sistem operasi'] as $key => $heading): ?>
        <div class="visitor-split__col">
          <h2 class="visitor-split__title"><?= esc($heading) ?></h2>
          <?php if ($breakdown[$key] === []): ?>
            <p class="visitor-split__empty">Belum ada data.</p>
          <?php else: ?>
            <ul class="visitor-split__list">
              <?php foreach ($breakdown[$key] as $item): ?>
                <li class="visitor-split__item">
                  <span class="visitor-split__label"><?= esc($item['label']) ?></span>
                  <span class="visitor-split__count"><?= angka($item['count']) ?> <span class="visitor-split__pct"><?= persen($item['percent'], 0) ?></span></span>
                  <span class="visitor-split__bar" aria-hidden="true"><span style="width: <?= esc(number_format($item['percent'], 2, '.', ''), 'attr') ?>%;"></span></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </section>

    <p class="result-count" role="status"><strong><?= angka($total) ?></strong> pengunjung &middot; <?= esc(strtolower(OnlineVisitors::SCOPES[$scope])) ?><?= $who !== '' ? ' &middot; ' . esc(strtolower(OnlineVisitors::WHO[$who])) : '' ?>.</p>

    <?php if ($rows === []): ?>
      <p class="empty"><?= $scope === OnlineVisitors::SCOPE_ONLINE ? 'Tidak ada pengunjung online' : 'Belum ada pengunjung' ?><?= $filtered ? ' yang cocok' : '' ?>.</p>
    <?php else: ?>
      <div class="table-scroll" role="region" aria-label="Tabel pengunjung" tabindex="0">
        <table class="data-table data-table--visitors">
          <thead>
            <tr>
              <th scope="col">Pengunjung</th>
              <th scope="col">Halaman</th>
              <th scope="col">Perangkat</th>
              <th scope="col">Sistem &middot; browser</th>
              <th scope="col">Jaringan &middot; lokal</th>
              <th scope="col">Aktivitas</th>
              <th scope="col">User-Agent</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
              <?php
                $type     = $row['user_type'];
                $isMine   = $mine !== null && hash_equals((string) $row['visitor_hash'], $mine);
                $ident    = match ($type) {
                    'student' => $joinParts(['NISN ' . $row['nisn'], $row['kelas'] !== null ? 'Kelas ' . $row['kelas'] : null]),
                    'teacher' => 'NIP ' . $row['nip'],
                    'admin'   => '@' . $row['admin_username'],
                    default   => null,
                };
                $model    = $deviceName($row['device_brand'], $row['device_model']);
                $screen   = $joinParts([
                    $row['screen'] !== null ? 'Layar ' . str_replace('x', '×', (string) $row['screen']) : null,
                    $row['viewport'] !== null ? 'Jendela ' . str_replace('x', '×', (string) $row['viewport']) : null,
                    $row['pixel_ratio'] !== null ? rtrim(rtrim(number_format((float) $row['pixel_ratio'], 2, ',', ''), '0'), ',') . '×' : null,
                    $row['touch_points'] !== null ? ((int) $row['touch_points'] > 0 ? 'Layar sentuh' : 'Tanpa sentuh') : null,
                ]);
                $os       = $joinParts([trim($row['os_name'] . ' ' . $row['os_version']), $row['os_platform']]);
                $browser  = trim($row['browser_name'] . ' ' . $row['browser_version']);
                $engine   = $joinParts([trim($row['engine'] . ' ' . $row['engine_version']), $row['browser_type'] !== null && $row['browser_type'] !== 'browser' ? ['mobile app' => 'dalam aplikasi', 'feed reader' => 'pembaca feed', 'media player' => 'pemutar media', 'pim' => 'aplikasi email'][$row['browser_type']] ?? $row['browser_type'] : null]);
                $local    = $joinParts([$row['language'], $row['timezone'], $row['connection'] !== null ? strtoupper((string) $row['connection']) : null]);
                $duration = strtotime((string) $row['last_seen_at']) - strtotime((string) $row['first_seen_at']);
                $hints    = $row['client_hints'] !== null ? json_decode((string) $row['client_hints'], true) : null;
              ?>
              <tr class="<?= $row['is_online'] ? 'is-online' : 'is-away' ?><?= $isMine ? ' is-mine' : '' ?>">
                <td>
                  <span class="visitor-who">
                    <span class="visitor-state" title="<?= $row['is_online'] ? 'Online' : 'Tidak aktif' ?>"></span>
                    <span class="visitor-who__body">
                      <span class="visually-hidden"><?= $row['is_online'] ? 'Online' : 'Tidak aktif' ?>: </span>
                      <strong><?= esc($type !== null ? ($row['name'] ?? 'Akun dihapus') : 'Tamu') ?></strong>
                      <?php if ($isMine): ?><span class="pill pill--muted">Anda</span><?php endif; ?>
                      <span class="cell-sub"><?= esc($type !== null ? ($roleLabels[$type] ?? $type) : 'Belum login') ?><?= $ident !== null && $row['name'] !== null ? ' &middot; ' . esc($ident) : '' ?></span>
                    </span>
                  </span>
                </td>
                <td>
                  <?= esc(VisitorTracker::pageLabel($row['current_path'])) ?>
                  <span class="cell-sub mono">/<?= esc((string) $row['current_path']) ?></span>
                </td>
                <td>
                  <span class="visitor-device">
                    <?= isset($kinds[$row['device_type']]) ? icon($kinds[$row['device_type']], 'visitor-device__icon') : '' ?>
                    <span><?= esc($row['device_type'] ?? 'Tidak diketahui') ?><?= $model !== '' ? ' &middot; ' . esc($model) : '' ?></span>
                  </span>
                  <?php if ($screen !== ''): ?><span class="cell-sub"><?= esc($screen) ?></span><?php endif; ?>
                </td>
                <td>
                  <?= esc($os !== '' ? $os : 'Tidak diketahui') ?>
                  <span class="cell-sub"><?= esc($browser !== '' ? $browser : 'Browser tidak diketahui') ?><?= $engine !== '' ? ' &middot; ' . esc($engine) : '' ?></span>
                </td>
                <td>
                  <span class="mono"><?= esc((string) ($row['ip_address'] ?? '-')) ?></span>
                  <?php if ($local !== ''): ?><span class="cell-sub"><?= esc($local) ?></span><?php endif; ?>
                </td>
                <td class="nowrap">
                  <time datetime="<?= esc((string) $row['last_seen_at'], 'attr') ?>"><?= esc(waktu_lalu($row['last_seen_at'])) ?></time>
                  <span class="cell-sub"><?= angka($row['page_views']) ?> halaman &middot; <?= esc(durasi($duration)) ?></span>
                  <span class="cell-sub">sejak <?= esc(format_waktu($row['first_seen_at'], 'd MMM, HH.mm')) ?></span>
                </td>
                <td class="visitor-ua">
                  <details class="visitor-ua__details" data-visitor-detail="<?= (int) $row['id'] ?>">
                    <summary>Lihat</summary>
                    <dl class="visitor-ua__list">
                      <div><dt>User-Agent</dt><dd class="mono"><?= esc((string) ($row['user_agent'] ?? '-')) ?></dd></div>
                      <?php if (is_array($hints) && $hints !== []): ?>
                        <div><dt>Client Hints</dt><dd class="mono"><?php foreach ($hints as $name => $value): ?><?= esc($name) ?>: <?= esc((string) $value) ?><br><?php endforeach; ?></dd></div>
                      <?php endif; ?>
                      <div><dt>Halaman pertama</dt><dd class="mono">/<?= esc((string) $row['landing_path']) ?></dd></div>
                      <div><dt>Datang dari</dt><dd class="mono"><?= esc((string) ($row['referrer'] ?? 'langsung / tidak diketahui')) ?></dd></div>
                      <?php if ($row['color_scheme'] !== null): ?>
                        <div><dt>Tema perangkat</dt><dd><?= $row['color_scheme'] === 'dark' ? 'Gelap' : 'Terang' ?></dd></div>
                      <?php endif; ?>
                      <div><dt>Terakhir aktif</dt><dd><?= esc(format_waktu($row['last_seen_at'], 'd MMMM yyyy, HH.mm.ss')) ?></dd></div>
                    </dl>
                  </details>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?= $pager ?>
    <?php endif; ?>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('crumbs') ?>
<?= view('admin/partials/crumbs', ['trail' => [['Pengunjung online']]]) ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_url('assets/js/admin-visitors.js') ?>" defer></script>
<?= $this->endSection() ?>
