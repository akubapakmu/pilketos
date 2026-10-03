<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Detail siswa / guru: identitas, status suara, riwayat, log unlock, aksi akun.
 *
 * @var \App\Services\VoterType    $type
 * @var array                      $voter
 * @var array|null                 $vote      Suara LOCKED pada election berjalan
 * @var list<array<string, mixed>> $history
 * @var list<array<string, mixed>> $unlocks
 * @var bool                       $deletable
 * @var array|null                 $election
 * @var bool                       $resultsLocked Stage 4: pemilihan selesai, aksi akun dikunci
 * @var array                      $avatar    Stage 26: VoterAvatars::present() (Living Election Wall)
 */
use App\Libraries\AvatarIdentity;
use App\Libraries\CandidateTheme;
use App\Services\VoterType;

$isStudent = $type === VoterType::Student;
$active    = (int) $voter['status_aktif'] === 1;
?>
<div class="admin-page">
  <header class="admin-head">
    <div class="admin-head__text">
      <div class="admin-head__heading">
        <h1 class="admin-head__title"><?= esc($voter['name']) ?></h1>
        <?= view('admin/partials/head_hint') ?>
      </div>
      <p class="admin-head__lede" id="admin-head-lede" data-lede>
        <?= esc($type->label()) ?> &middot; <?= esc($type->identifierLabel()) ?> <span class="mono"><?= esc($voter[$type->identifierColumn()]) ?></span>
        <?php if (! $active): ?><span class="pill pill--muted">Akun nonaktif</span><?php endif; ?>
      </p>
    </div>
    <?php if (! ($resultsLocked ?? false)): ?>
      <div class="admin-head__actions">
        <a class="btn btn--outline" href="<?= site_url($type->adminPath($voter['id'] . '/ubah')) ?>"><?= icon('edit') ?> Ubah data</a>
      </div>
    <?php endif; ?>
  </header>

  <div class="detail-grid">
    <section class="panel" aria-labelledby="identity-title">
      <header class="panel__head"><h2 class="panel__title" id="identity-title">Identitas</h2></header>
      <dl class="kv-list secrets" data-secrets id="identity-list">
        <div class="kv-list__row"><dt><?= esc($type->identifierLabel()) ?></dt><dd class="mono"><?= esc($voter[$type->identifierColumn()]) ?></dd></div>
        <div class="kv-list__row"><dt>Nama</dt><dd><?= esc($voter['name']) ?></dd></div>
        <?php if ($isStudent): ?>
          <div class="kv-list__row"><dt>Jenis kelamin</dt><dd><?= $voter['jenis_kelamin'] === 'L' ? 'Laki-laki (L)' : 'Perempuan (P)' ?></dd></div>
          <div class="kv-list__row"><dt>Rombel</dt><dd><?= esc($voter['kelas']) ?></dd></div>
          <div class="kv-list__row"><dt>Nomor absen</dt><dd><?= esc($voter['nomor_absen'] ?? '-') ?></dd></div>
        <?php endif; ?>
        <div class="kv-list__row">
          <dt>Kode unik (login)</dt>
          <dd class="mono"><span class="secret"><?= esc($voter['kodeunik']) ?></span>
            <button type="button" class="link-btn" data-secret-toggle aria-pressed="false" aria-controls="identity-list" hidden><span data-secret-toggle-label>Tampilkan</span></button>
          </dd>
        </div>
        <div class="kv-list__row"><dt>Status akun</dt><dd><?= $active ? 'Aktif' : 'Nonaktif (tidak dapat login, tidak dihitung)' ?></dd></div>
      </dl>
    </section>

    <section class="panel" aria-labelledby="vote-title">
      <header class="panel__head"><h2 class="panel__title" id="vote-title">Status hak suara</h2></header>
      <?php if ($vote !== null): ?>
        <?php $c = CandidateTheme::present(['id' => $vote['candidate_id'], 'nomor_urut' => $vote['nomor_urut'], 'nama_ketua' => $vote['nama_ketua'], 'nama_wakil' => $vote['nama_wakil'], 'theme_accent' => $vote['theme_accent']]); ?>
        <div class="vote-state vote-state--locked" style="<?= esc($c['style'], 'attr') ?>">
          <p class="vote-state__label"><?= icon('lock') ?> Sudah memilih &middot; terkunci</p>
          <p class="vote-state__choice"><span class="cand-chip"><?= esc($c['label']) ?></span> <?= esc($c['ketua']) ?> &amp; <?= esc($c['wakil']) ?></p>
          <dl class="vote-state__meta">
            <div><dt>Waktu</dt><dd><?= esc(format_waktu($vote['voted_at'], 'd MMMM yyyy, HH.mm.ss')) ?></dd></div>
            <div><dt>Perangkat</dt><dd><?= esc($vote['device_info'] ?? '-') ?></dd></div>
            <div><dt>Browser</dt><dd><?= esc($vote['browser_info'] ?? '-') ?></dd></div>
          </dl>
          <?php if (($election['status'] ?? null) === 'ONGOING'): ?>
            <a class="btn btn--danger" href="<?= site_url($type->unlockPath($voter['id'])) ?>"><?= icon('unlock') ?> Unlock Hak Suara</a>
          <?php else: ?>
            <p class="field-hint">Unlock hanya dapat dilakukan saat pemilihan berlangsung.</p>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="vote-state">
          <p class="vote-state__label">Belum memilih<?= $election === null ? '' : ' pada ' . esc($election['nama']) ?></p>
          <p class="field-hint"><?= $active ? 'Pemilih login dengan ' . esc($type->identifierLabel()) . ' dan kode unik, lalu memilih sendiri.' : 'Akun nonaktif tidak dapat login.' ?></p>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <section class="panel" aria-labelledby="avatar-title">
    <header class="panel__head">
      <h2 class="panel__title" id="avatar-title">Avatar Living Election Wall</h2>
      <?= view('admin/partials/note', ['id' => 'avatar-note', 'text' => 'Avatar tampil di halaman publik Living Election Wall bersama nama pemilih, tanpa NISN/NIP, waktu, maupun pilihan.']) ?>
    </header>
    <div class="voter-avatar">
      <?= view('partials/avatar', ['avatar' => $avatar, 'class' => 'avatar--lg', 'eager' => true]) ?>
      <div class="voter-avatar__text">
        <p class="voter-avatar__kind">
          <?php if ($avatar['photo'] !== null): ?>
            Foto unggahan pemilih
          <?php elseif ($avatar['custom']): ?>
            Avatar pilihan pemilih &middot; <?= esc(AvatarIdentity::STYLES[$avatar['style']]) ?>
          <?php else: ?>
            Avatar bawaan &middot; <?= esc(AvatarIdentity::STYLES[$avatar['style']]) ?>
          <?php endif; ?>
        </p>
        <?php if ($avatar['photo'] !== null): ?>
          <form action="<?= site_url($type->adminPath($voter['id'] . '/avatar/hapus')) ?>" method="post"
                data-confirm="<?= esc('Hapus foto avatar ' . $voter['name'] . '? Pemilih kembali memakai avatar bawaan dan dapat mengunggah foto lain.', 'attr') ?>"
                data-confirm-button="Hapus foto" data-confirm-danger>
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--ghost-danger"><?= icon('trash') ?> Hapus foto avatar</button>
            <p class="field-hint">Untuk foto yang tidak pantas. Tercatat di audit log.</p>
          </form>
        <?php else: ?>
          <p class="field-hint">Pemilih dapat mengunggah foto atau mengacak avatar dari dasbornya.</p>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <section class="panel" aria-labelledby="history-title">
    <header class="panel__head">
      <h2 class="panel__title" id="history-title">Riwayat suara</h2>
      <?= view('admin/partials/note', ['id' => 'history-note', 'text' => 'Baris suara tidak pernah dihapus: unlock mengubah status menjadi riwayat UNLOCKED.']) ?>
    </header>
    <?php if ($history === []): ?>
      <p class="empty">Belum ada riwayat suara.</p>
    <?php else: ?>
      <div class="table-scroll" role="region" aria-label="Tabel riwayat suara" tabindex="0">
        <table class="data-table">
          <thead>
            <tr>
              <th scope="col">Pemilihan</th>
              <th scope="col">Pilihan</th>
              <th scope="col">Dicoblos</th>
              <th scope="col">Perangkat / browser</th>
              <th scope="col">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h): ?>
              <tr>
                <td><?= esc($h['election_nama']) ?> <?= esc((string) $h['election_tahun']) ?></td>
                <td><span class="cand-chip cand-chip--plain"><?= sprintf('%02d', (int) $h['nomor_urut']) ?></span> <?= esc($h['nama_ketua']) ?> &amp; <?= esc($h['nama_wakil']) ?></td>
                <td class="nowrap"><?= esc(format_waktu($h['voted_at'], 'd MMM yyyy, HH.mm.ss')) ?></td>
                <?php $device = device_summary($h['device_info'] ?? null, $h['browser_info'] ?? null); ?>
                <td class="device-cell"><?= $device['kind'] !== null ? icon($device['kind'], 'device-cell__icon') : '' ?><span class="device-cell__main"><?= esc($device['main']) ?></span><?php if ($device['sub'] !== ''): ?><span class="cell-sub"><?= esc($device['sub']) ?></span><?php endif; ?></td>
                <td>
                  <?php if ($h['status'] === 'LOCKED'): ?>
                    <span class="pill pill--ink"><?= icon('lock') ?> Terkunci</span>
                  <?php else: ?>
                    <span class="pill pill--outline"><?= icon('unlock') ?> Dibuka <?= esc(format_waktu($h['unlocked_at'], 'd MMM, HH.mm')) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($unlocks !== []): ?>
    <section class="panel" aria-labelledby="unlock-title">
      <header class="panel__head"><h2 class="panel__title" id="unlock-title">Log unlock</h2></header>
      <ol class="log-list">
        <?php foreach ($unlocks as $u): ?>
          <li class="log-list__item">
            <p class="log-list__meta"><?= esc(format_waktu($u['unlocked_at'], 'd MMM yyyy, HH.mm.ss')) ?> &middot; oleh <?= esc($u['admin_name']) ?></p>
            <p class="log-list__text"><?= esc($u['reason']) ?></p>
          </li>
        <?php endforeach; ?>
      </ol>
    </section>
  <?php endif; ?>

  <section class="panel panel--danger-zone" aria-labelledby="account-title">
    <header class="panel__head"><h2 class="panel__title" id="account-title">Akun pemilih</h2></header>
    <?php if ($resultsLocked ?? false): ?>
    <p class="notice"><?= icon('lock') ?><span>Pemilihan sudah selesai: status akun dan data pemilih dikunci agar hasil akhir tidak berubah. Status saat ini: <strong><?= $active ? 'aktif' : 'nonaktif' ?></strong>.</span></p>
    <?php else: ?>
    <div class="account-actions">
      <form action="<?= site_url($type->adminPath($voter['id'] . '/status')) ?>" method="post"
            data-confirm="<?= $active
                ? esc('Nonaktifkan ' . $voter['name'] . '? Pemilih tidak dapat login' . ($vote !== null ? ' dan suaranya tidak dihitung selama nonaktif' : '') . '.', 'attr')
                : esc('Aktifkan kembali ' . $voter['name'] . '? Pemilih dapat login lagi.', 'attr') ?>"
            data-confirm-button="<?= $active ? 'Nonaktifkan' : 'Aktifkan' ?>"<?= $active ? ' data-confirm-danger' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="status_aktif" value="<?= $active ? '0' : '1' ?>">
        <button type="submit" class="btn btn--outline"><?= $active ? 'Nonaktifkan akun' : 'Aktifkan akun' ?></button>
        <p class="field-hint"><?= $active
            ? 'Pemilih nonaktif tidak dapat login dan tidak dihitung sebagai pemilih maupun suara di analitik.'
            : 'Setelah aktif, pemilih dapat login dan dihitung kembali.' ?></p>
      </form>

      <?php if ($deletable): ?>
        <form action="<?= site_url($type->adminPath($voter['id'] . '/hapus')) ?>" method="post"
              data-confirm="<?= esc('Hapus data ' . $voter['name'] . ' secara permanen? Gunakan hanya untuk data salah impor.', 'attr') ?>"
              data-confirm-button="Hapus data" data-confirm-danger>
          <?= csrf_field() ?>
          <button type="submit" class="btn btn--ghost-danger"><?= icon('trash') ?> Hapus data</button>
          <p class="field-hint">Hanya untuk data tanpa riwayat suara (mis. salah impor).</p>
        </form>
      <?php else: ?>
        <p class="field-hint">Data ini memiliki riwayat suara sehingga tidak dapat dihapus; gunakan nonaktifkan.</p>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </section>
</div>
<?= $this->endSection() ?>

<?= $this->section('crumbs') ?>
<?= view('admin/partials/crumbs', ['trail' => [[$type->label(), $type->adminPath()], ['Detail']]]) ?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/avatar.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="module" src="<?= asset_url('assets/js/avatar-art.js') ?>"></script>
<?= $this->endSection() ?>
