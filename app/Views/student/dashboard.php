<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<section class="dash-head">
  <div class="container">
    <h1 class="dash-head__name"><?= esc($student['name']) ?></h1>
    <dl class="dash-meta">
      <div>
        <dt class="visually-hidden">NISN</dt>
        <dd><?= esc($student['nisn']) ?></dd>
      </div>
      <div>
        <dt class="visually-hidden">Rombel</dt>
        <dd><?= esc($student['kelas']) ?></dd>
      </div>
      <div>
        <dt class="visually-hidden">Nomor Absen</dt>
        <dd><?= esc($student['nomor_absen'] ?? '-') ?></dd>
      </div>
    </dl>
  </div>
</section>

<section class="section">
  <div class="container dash-grid">
    <?= view('partials/vote_status', [
        'type'      => $type,
        'election'  => $election,
        'vote'      => $vote,
        'candidate' => $candidate,
        'canVote'   => $canVote,
    ]) ?>

    <aside class="dash-aside">
      <section class="dash-aside__panel" aria-labelledby="schedule-title">
        <h2 class="dash-aside__title" id="schedule-title">Jadwal pemilihan</h2>
        <?= $this->include('partials/election_status') ?>
      </section>
      <?php if ($avatar !== null): ?>
        <?= view('partials/avatar_card', ['type' => $type, 'avatar' => $avatar, 'voted' => $vote !== null]) ?>
      <?php endif; ?>
    </aside>
  </div>
</section>

<?= view('partials/float_clock', ['election' => $election]) ?>

<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?php if ($avatar !== null): ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/avatar.css') ?>">
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_url('assets/js/countdown.js') ?>" defer></script>
<?php if ($avatar !== null): ?>
<script type="module" src="<?= asset_url('assets/js/dashboard-avatar.js') ?>"></script>
<?php endif; ?>
<?= $this->endSection() ?>
