<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Daftar pasangan calon + kelengkapan tema.
 * Stage 12: tanpa eyebrow "Pasangan 01 · tema" (tema & status Nonaktif
 * pindah ke meta), kelengkapan asset berupa timeline.
 *
 * Stage 15: notice "dirancang untuk 3 pasangan" diganti panel batas
 * pasangan aktif yang dapat diatur admin (2-10, per pemilihan).
 *
 * @var list<array{raw: array, theme: array, votes: int, vote_rows: int, assets: array<string, string>}> $rows
 * @var int                   $activeCount
 * @var int                   $batas
 * @var array<string, string> $errors
 */
use App\Libraries\CandidateAssets;
use App\Models\ElectionModel;

?>
<div class="admin-page">
  <header class="admin-head">
    <div class="admin-head__text">
      <div class="admin-head__heading">
        <h1 class="admin-head__title">Pasangan calon</h1>
        <?= view('admin/partials/head_hint') ?>
      </div>
      <p class="admin-head__lede" id="admin-head-lede" data-lede>Identitas, visi-misi, dan tema visual yang tampil di halaman kandidat pemilih. Setiap pasangan punya warna aksen solid dan gambar sendiri; tampilan halamannya sama untuk semua pasangan.</p>
    </div>
    <?php if (! ($resultsLocked ?? false)): ?>
      <div class="admin-head__actions">
        <a class="btn" href="<?= site_url('admin/paslon/tambah') ?>"><?= icon('plus') ?> Tambah pasangan</a>
      </div>
    <?php endif; ?>
  </header>

  <?php if ($resultsLocked ?? false): ?>
    <p class="notice"><?= icon('lock') ?><span>Pemilihan sudah selesai: susunan pasangan (tambah, hapus, nomor urut, status aktif) dikunci agar hasil akhir tidak berubah.</span></p>
  <?php endif; ?>

  <?php
    $oldBatas    = session()->getFlashdata('_ci_old_input')['post']['batas_pasangan'] ?? $batas;
    $batasError  = $errors['batas_pasangan'] ?? null;
    $canSetBatas = ($election ?? null) !== null && ! ($resultsLocked ?? false);
  ?>
  <section class="quota" aria-labelledby="quota-title">
    <div class="quota__status">
      <h2 class="quota__title" id="quota-title">Batas pasangan aktif</h2>
      <p class="quota__count"><strong><?= $activeCount ?></strong> dari <strong><?= $batas ?></strong> pasangan aktif</p>
      <ol class="quota__slots" aria-hidden="true">
        <?php for ($i = 1; $i <= max($batas, $activeCount); $i++): ?>
          <li class="quota__slot<?= $i <= $activeCount ? ' is-filled' : '' ?><?= $i > $batas ? ' is-over' : '' ?>"></li>
        <?php endfor; ?>
      </ol>
      <p class="quota__hint">
        <?php if ($activeCount > $batas): ?>
          Jumlah pasangan aktif melebihi batas. Nonaktifkan pasangan yang tidak ikut atau naikkan batas.
        <?php elseif ($activeCount === $batas): ?>
          Batas penuh. Pasangan baru disimpan nonaktif sampai batas dinaikkan atau pasangan lain dinonaktifkan.
        <?php else: ?>
          Masih ada tempat untuk <?= $batas - $activeCount ?> pasangan aktif lagi. Pasangan nonaktif tidak dihitung.
        <?php endif; ?>
      </p>
    </div>

    <?php if ($canSetBatas): ?>
      <form class="quota__form" action="<?= site_url('admin/paslon/batas') ?>" method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="batas_pasangan">Ubah batas</label>
          <div class="quota__row">
            <input type="number" id="batas_pasangan" name="batas_pasangan" min="<?= ElectionModel::BATAS_PASANGAN_MIN ?>" max="<?= ElectionModel::BATAS_PASANGAN_MAX ?>"
                   inputmode="numeric" required value="<?= esc((string) $oldBatas, 'attr') ?>"
                   aria-describedby="batas-hint<?= $batasError !== null ? ' err-batas_pasangan' : '' ?>"<?= $batasError !== null ? ' aria-invalid="true"' : '' ?>>
            <button type="submit" class="btn" data-loading-text="Menyimpan..."><?= icon('check') ?> Simpan batas</button>
          </div>
          <p class="field-hint" id="batas-hint"><?= ElectionModel::BATAS_PASANGAN_MIN ?> sampai <?= ElectionModel::BATAS_PASANGAN_MAX ?> pasangan, minimal sebanyak pasangan yang sedang aktif.</p>
          <?php if ($batasError !== null): ?><p class="field-error" id="err-batas_pasangan"><?= esc($batasError) ?></p><?php endif; ?>
        </div>
      </form>
    <?php elseif (($election ?? null) === null): ?>
      <p class="quota__note"><?= icon('info') ?> <span>Batas dapat diubah setelah <a href="<?= site_url('admin/jadwal') ?>">jadwal pemilihan</a> dibuat. Sementara memakai bawaan <?= ElectionModel::BATAS_PASANGAN_DEFAULT ?> pasangan.</span></p>
    <?php else: ?>
      <p class="quota__note"><?= icon('lock') ?> <span>Dikunci: pemilihan sudah selesai.</span></p>
    <?php endif; ?>
  </section>

  <?php if ($rows === []): ?>
    <p class="empty">Belum ada pasangan calon. <a href="<?= site_url('admin/paslon/tambah') ?>">Tambah pasangan pertama</a>.</p>
  <?php endif; ?>

  <ol class="cand-list">
    <?php foreach ($rows as $row): ?>
      <?php
        $t      = $row['theme'];
        $active = (int) $row['raw']['status_aktif'] === 1;
        $filled = count($row['assets']);
      ?>
      <li class="cand-card<?= $active ? '' : ' cand-card--inactive' ?>" style="<?= esc($t['style'], 'attr') ?>">
        <div class="cand-card__no" aria-hidden="true"><?= esc($t['label']) ?></div>

        <div class="cand-card__body">
          <h2 class="cand-card__names">
            <span class="visually-hidden">Pasangan <?= esc($t['label']) ?>: </span>
            <span><?= esc($t['ketua']) ?></span>
            <span class="cand-card__wakil">&amp; <?= esc($t['wakil']) ?></span>
          </h2>

          <dl class="cand-card__meta">
            <div><dt>Tema</dt><dd><?= esc($t['theme_name']) ?></dd></div>
            <div><dt>Aksen</dt><dd><span class="swatch" aria-hidden="true"></span><span class="mono"><?= esc($t['accent']) ?></span></dd></div>
            <div><dt>Suara sah</dt><dd><?= angka($row['votes']) ?></dd></div>
            <div><dt>Asset</dt><dd><?= $filled ?> / <?= count(CandidateAssets::SLOTS) ?></dd></div>
            <?php if (! $active): ?><div><dt>Status</dt><dd><span class="pill pill--muted">Nonaktif</span></dd></div><?php endif; ?>
          </dl>

          <ol class="asset-dots" aria-label="Kelengkapan asset">
            <?php foreach (CandidateAssets::SLOTS as $slot => $config): ?>
              <?php $has = isset($row['assets'][$slot]); ?>
              <li class="asset-dots__item<?= $has ? ' is-filled' : '' ?>">
                <span class="asset-dots__node" aria-hidden="true"><?= $has ? icon('check') : '' ?></span>
                <span class="asset-dots__label"><?= esc($config['label']) ?><span class="visually-hidden"><?= $has ? ': sudah diunggah' : ': belum ada' ?></span></span>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>

        <div class="cand-card__actions">
          <a class="btn btn--sm" href="<?= site_url('admin/paslon/' . $t['id'] . '/ubah') ?>"><?= icon('edit') ?> Ubah</a>
          <a class="btn btn--sm btn--outline" href="<?= site_url('admin/paslon/' . $t['id'] . '/intip') ?>"><?= icon('eye') ?> Pratinjau</a>
          <?php if ($resultsLocked ?? false): ?>
            <p class="cand-card__lock"><?= icon('lock') ?> Dikunci: pemilihan sudah selesai</p>
          <?php elseif ($row['vote_rows'] === 0): ?>
            <form action="<?= site_url('admin/paslon/' . $t['id'] . '/hapus') ?>" method="post"
                  data-confirm="Hapus Pasangan <?= esc($t['label'], 'attr') ?> (<?= esc($t['ketua'], 'attr') ?> &amp; <?= esc($t['wakil'], 'attr') ?>) beserta semua file temanya? Tindakan ini tidak dapat dibatalkan."
                  data-confirm-button="Hapus pasangan" data-confirm-danger>
              <?= csrf_field() ?>
              <button type="submit" class="btn btn--sm btn--ghost-danger"><?= icon('trash') ?> Hapus</button>
            </form>
          <?php else: ?>
            <p class="cand-card__lock"><?= icon('lock') ?> Sudah menerima suara, tidak dapat dihapus</p>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ol>
</div>
<?= $this->endSection() ?>

<?= $this->section('crumbs') ?>
<?= view('admin/partials/crumbs', ['trail' => [['Pasangan calon']]]) ?>
<?= $this->endSection() ?>
