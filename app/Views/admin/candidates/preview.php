<?= $this->extend('layouts/main') ?>

<?= $this->section('head') ?>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="<?= asset_url('assets/css/voting.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/paslon.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/admin.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
/**
 * Pratinjau halaman kandidat untuk admin. Stage 15: memakai partial yang sama
 * dengan halaman profil paslon pemilih (paslon/partials/profile) sehingga
 * tema, profil, dan media yang diisi terlihat persis. Surat suara tidak
 * ditampilkan; tombol penutup menuju form ubah.
 *
 * @var array      $candidate CandidateTheme::present()
 * @var bool       $active
 * @var list<array> $switcher
 */
?>
<h1 class="visually-hidden">Pratinjau Pasangan <?= esc($candidate['label']) ?></h1>
<div class="preview-bar">
  <div class="container preview-bar__row">
    <p class="preview-bar__text"><?= icon('eye') ?> <span><strong>Pratinjau admin.</strong> Tampilan sama dengan halaman kandidat pemilih; surat suara tidak ditampilkan.<?= $active ? '' : ' Pasangan ini nonaktif sehingga tidak tampil untuk pemilih.' ?></span></p>
    <nav class="preview-bar__nav" aria-label="Pratinjau pasangan lain">
      <?php foreach ($switcher as $c): ?>
        <a href="<?= site_url('admin/paslon/' . $c['id'] . '/intip') ?>" style="<?= esc($c['style'], 'attr') ?>"<?= $c['id'] === $candidate['id'] ? ' aria-current="page"' : '' ?>><span class="preview-bar__swatch" aria-hidden="true"></span><?= esc($c['label']) ?></a>
      <?php endforeach; ?>
      <a class="preview-bar__back" href="<?= site_url('admin/paslon/' . $candidate['id'] . '/ubah') ?>"><?= icon('edit') ?> Ubah</a>
    </nav>
  </div>
</div>

<?= view('paslon/partials/profile', [
    'c'       => $candidate,
    'actions' => [
        ['label' => 'Ubah data paslon', 'href' => site_url('admin/paslon/' . $candidate['id'] . '/ubah'), 'icon' => 'edit', 'primary' => true],
        ['label' => 'Daftar pasangan calon', 'href' => site_url('admin/paslon'), 'icon' => 'arrow-left'],
    ],
    'others' => array_map(static fn (array $o): array => [
        'label'   => $o['label'],
        'href'    => site_url('admin/paslon/' . $o['id'] . '/intip'),
        'current' => $o['id'] === $candidate['id'],
            'ketua'   => $o['ketua'],
        'wakil'   => $o['wakil'],
    ], $switcher),
]) ?>

<section class="section">
  <div class="container">
    <p class="notice"><?= icon('info') ?><span>Tombol &ldquo;Pilih Paslon&rdquo; dan surat suara hanya tersedia untuk siswa dan guru yang login saat pencoblosan dibuka. Admin tidak dapat memberikan suara.</span></p>
  </div>
</section>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_url('assets/js/paslon.js') ?>" defer></script>
<?= $this->endSection() ?>
