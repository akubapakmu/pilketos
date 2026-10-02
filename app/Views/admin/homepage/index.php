<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Teks Halaman Utama (Stage 24): kalimat yang diketik bergantian di tengah
 * gambar hero halaman utama (home.js HeroType). Satu baris textarea = satu
 * kalimat (App\Libraries\HeroText); kosong = hero tanpa teks. Panel kanan
 * menampilkan urutan kalimat yang tersimpan.
 *
 * @var list<string>          $lines  Kalimat tersimpan
 * @var array<string, string> $errors
 */
use App\Libraries\HeroText;

$old     = session()->getFlashdata('_ci_old_input')['post'] ?? [];
$value   = is_string($old['hero_lines'] ?? null) ? $old['hero_lines'] : HeroText::text($lines);
$invalid = isset($errors['hero_lines']);
?>
<div class="admin-page">
  <header class="admin-head">
    <div class="admin-head__text">
      <div class="admin-head__heading">
        <h1 class="admin-head__title">Teks halaman utama</h1>
        <?= view('admin/partials/head_hint') ?>
      </div>
      <p class="admin-head__lede" id="admin-head-lede" data-lede>Kalimat yang diketik satu per satu di tengah gambar halaman utama. Setelah kalimat terakhir, teks berhenti 3 detik lalu diulang dari kalimat pertama. Setiap perubahan tercatat di audit log.</p>
    </div>
  </header>

  <div class="detail-grid">
    <section class="panel" aria-labelledby="hero-text-title">
      <header class="panel__head">
        <h2 class="panel__title" id="hero-text-title">Ubah kalimat</h2>
      </header>
      <form action="<?= site_url('admin/halaman-utama') ?>" method="post" class="stack" novalidate>
        <?= csrf_field() ?>
        <div class="field">
          <label for="hero_lines">Kalimat, satu baris satu kalimat</label>
          <textarea class="hero-lines-input" id="hero_lines" name="hero_lines" rows="14" maxlength="4000" spellcheck="true"
                    aria-describedby="hint-hero_lines<?= $invalid ? ' err-hero_lines' : '' ?>"<?= $invalid ? ' aria-invalid="true"' : '' ?>><?= esc($value) ?></textarea>
          <p class="field-hint" id="hint-hero_lines">Tekan Enter untuk kalimat berikutnya. Maksimal <?= HeroText::MAX_LINES ?> kalimat, masing-masing maksimal <?= HeroText::MAX_LENGTH ?> karakter. Baris kosong diabaikan dan nama sekolah otomatis ditulis SMP 1 DAWE. Kosongkan semua bila gambar hero ingin tampil tanpa teks.</p>
          <?php if ($invalid): ?><p class="field-error" id="err-hero_lines"><?= esc($errors['hero_lines']) ?></p><?php endif; ?>
        </div>
        <div>
          <button type="submit" class="btn" data-loading-text="Menyimpan..."><?= icon('check') ?> Simpan teks</button>
        </div>
      </form>
    </section>

    <section class="panel" aria-labelledby="hero-order-title">
      <header class="panel__head">
        <h2 class="panel__title" id="hero-order-title">Urutan tampil</h2>
        <a class="panel__link" href="<?= base_url('/') ?>" target="_blank" rel="noopener">Lihat halaman utama <?= icon('external') ?><span class="visually-hidden"> (tab baru)</span></a>
      </header>
      <?php if ($lines === []): ?>
        <p class="notice"><?= icon('info') ?><span>Belum ada kalimat. Gambar hero halaman utama tampil tanpa teks.</span></p>
      <?php else: ?>
        <ol class="hero-lines" aria-label="Kalimat tersimpan, sesuai urutan tampil">
          <?php foreach ($lines as $i => $line): ?>
            <li class="hero-lines__item">
              <span class="hero-lines__no" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
              <span class="hero-lines__text"><?= esc($line) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
        <p class="field-hint hero-lines__note">Tiap kalimat diketik, tampil sebentar, lalu dihapus sebelum kalimat berikutnya. Tanpa JavaScript atau dengan pengaturan &ldquo;kurangi gerakan&rdquo;, kalimat berganti tanpa efek ketik.</p>
      <?php endif; ?>
    </section>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('crumbs') ?>
<?= view('admin/partials/crumbs', ['trail' => [['Teks halaman utama']]]) ?>
<?= $this->endSection() ?>
