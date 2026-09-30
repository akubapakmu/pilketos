<?= $this->extend('layouts/admin') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/results.css') ?>">
<?php if ($result['available']): ?>
<?php /* Stage 13: cetak lewat browser (Ctrl+P) = catatan kaki + nomor halaman di
   kotak margin bawah setiap halaman (@page margin box, Chrome/Edge 131+).
   Cetak utama tetap PDF (admin/hasil/cetak). */ ?>
<style <?= csp_style_nonce() ?>>
@page {
  @bottom-center {
    content: "<?= esc(\App\Services\FinalResult::footnote($snapshot['generated_at']), 'css') ?>\A Halaman " counter(page) " dari " counter(pages);
    white-space: pre-line;
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 7.5pt;
    font-style: italic;
    line-height: 1.5;
    color: #514E45;
    text-align: center;
    vertical-align: top;
    padding-top: 3mm;
  }
}
</style>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('crumbs') ?>
<?= view('admin/partials/crumbs', ['trail' => [['Hasil akhir']]]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
/**
 * Hasil akhir pemilihan (Stage 4). Aktif hanya saat pemilihan FINISHED.
 *
 * @var array|null                      $election
 * @var array|null                      $snapshot AnalyticsService::snapshot() (null bila belum selesai)
 * @var array<string, mixed>            $result   FinalResult::build()
 * @var array<int, array<string, mixed>> $themes  CandidateTheme::present() per id pasangan
 */
use App\Services\FinalResult;

$state   = $result['state'];
$winner  = $result['winner'];
$theme   = static fn (array $c): array => $themes[(int) $c['id']] ?? [];
$accents = array_values(array_unique(array_merge(
    $winner === null ? [] : [$winner['accent']],
    array_column($result['ranking'], 'accent'),
)));
?>
<div class="admin-page final-page" id="hasil-akhir" data-final-stage>
  <header class="admin-head">
    <div class="admin-head__text">
      <div class="admin-head__heading">
        <h1 class="admin-head__title">Hasil akhir</h1>
        <?php if ($result['available']): ?><img class="admin-head__logo" src="<?= base_url('assets/img/brand/logo-smp1dawe.png') ?>" alt="Logo SMP 1 DAWE" width="120" height="120"><?php endif; ?>
        <?php if (! $result['available']): ?><?= view('admin/partials/head_hint') ?><?php endif; ?>
      </div>
      <?php if (! $result['available']): ?>
        <p class="admin-head__lede" id="admin-head-lede" data-lede>Hasil akhir terbuka otomatis saat pemilihan selesai menurut jam server.</p>
      <?php endif; ?>
    </div>
    <?php if ($result['available']): ?>
      <div class="admin-head__actions final-actions">
        <button type="button" class="btn btn--outline" data-fullscreen="#hasil-akhir" hidden><?= icon('expand') ?> <span class="btn__label" data-fullscreen-label>Layar penuh</span></button>
        <a class="btn btn--outline" href="<?= site_url('admin/hasil/cetak') ?>" target="_blank" rel="noopener" data-print-pdf><?= icon('printer') ?> <span class="btn__label">Cetak PDF</span><span class="visually-hidden"> (tab baru)</span></a>
        <a class="btn btn--outline" href="<?= site_url('admin/analitik') ?>"><?= icon('chart') ?> <span class="btn__label">Analitik lengkap</span></a>
      </div>
    <?php endif; ?>
  </header>

  <?php if (! $result['available']): ?>
    <section class="final-locked" aria-labelledby="locked-title">
      <span class="final-locked__seal" aria-hidden="true"><?= icon('lock') ?></span>
      <div class="final-locked__body">
        <h2 class="final-locked__title" id="locked-title">Hasil akhir belum tersedia</h2>
        <?php if ($election === null): ?>
          <p>Pemilihan belum dijadwalkan. Hasil akhir baru ada setelah pemilihan berlangsung dan selesai.</p>
          <p><a class="btn" href="<?= site_url('admin/jadwal') ?>"><?= icon('calendar') ?> Atur jadwal</a></p>
        <?php else: ?>
          <?php /* Stage 17: tanpa label status ("Sedang Berlangsung", dst.); keadaan
             pemilihan terbaca dari label countdown (Dibuka dalam / Ditutup dalam). */ ?>
          <p>
            Hasil akhir terbuka pada
            <strong><time datetime="<?= esc($election['end_at'], 'attr') ?>"><?= esc(format_waktu($election['end_at'])) ?></time></strong>
            (sejak waktu selesai, pencoblosan ditolak server). Selama pemilihan berjalan, pantau angka sementara di Beranda.
          </p>
          <?= view('partials/countdown', ['election' => $election, 'variant' => 'compact']) ?>
          <p><a class="btn" href="<?= site_url('admin') ?>"><?= icon('grid') ?> Buka Beranda</a></p>
        <?php endif; ?>
      </div>
    </section>
  <?php else: ?>
    <?php $all = $snapshot['summary']['all']; ?>
    <article class="final" aria-labelledby="final-title">
      <header class="final__masthead">
        <p class="final__kicker">Rekapitulasi resmi penghitungan suara</p>
        <h2 class="final__title" id="final-title">
          Pemilihan Ketua &amp; Wakil Ketua OSIS <span class="final__school">SMP 1 DAWE <?= esc((string) $election['tahun']) ?></span>
        </h2>
        <dl class="final__stamp">
          <div><dt>Ditutup</dt><dd><time datetime="<?= esc($election['end_at'], 'attr') ?>"><?= esc(format_waktu($election['end_at'])) ?></time></dd></div>
          <div><dt>Suara sah</dt><dd><?= angka($all['voted']) ?></dd></div>
          <div><dt>Partisipasi</dt><dd><?= persen($all['participation']) ?> <span class="final__of">dari <?= angka($all['total']) ?> pemilih</span></dd></div>
        </dl>
      </header>

      <?php if ($state === FinalResult::WINNER): ?>
        <?php $t = $theme($winner); ?>
        <section class="victor" style="<?= esc($t['style'] ?? '', 'attr') ?>" aria-labelledby="victor-title">
          <p class="victor__label"><?= icon('award') ?> Pasangan terpilih</p>
          <p class="victor__no" aria-hidden="true"><?= esc($winner['label']) ?></p>
          <div class="victor__people">
            <?php if (($t['hero'] ?? null) !== null): ?>
              <img class="victor__hero" src="<?= esc($t['hero'], 'attr') ?>" alt="Foto pasangan <?= esc($winner['label'], 'attr') ?>: <?= esc($winner['ketua'], 'attr') ?> dan <?= esc($winner['wakil'], 'attr') ?>" decoding="async">
            <?php else: ?>
              <?php foreach ([['Ketua', $winner['ketua'], $t['photo_ketua'] ?? null, $t['ketua_initials'] ?? '?'], ['Wakil', $winner['wakil'], $t['photo_wakil'] ?? null, $t['wakil_initials'] ?? '?']] as [$role, $name, $photo, $initials]): ?>
                <figure class="victor__person">
                  <?php if ($photo !== null): ?>
                    <img src="<?= esc($photo, 'attr') ?>" alt="Foto <?= esc($name, 'attr') ?>, <?= esc(strtolower($role), 'attr') ?> terpilih" width="480" height="600" decoding="async">
                  <?php else: ?>
                    <span class="victor__mono" role="img" aria-label="<?= esc($name, 'attr') ?>, <?= esc(strtolower($role), 'attr') ?> terpilih (foto belum tersedia)"><span aria-hidden="true"><?= esc($initials) ?></span></span>
                  <?php endif; ?>
                  <figcaption><?= esc($role) ?></figcaption>
                </figure>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
          <h3 class="victor__names" id="victor-title">
            <span class="visually-hidden">Pasangan <?= esc($winner['label']) ?>:</span>
            <span class="victor__person-name"><span class="victor__role">Ketua OSIS</span> <?= esc($winner['ketua']) ?></span>
            <span class="victor__person-name"><span class="victor__role">Wakil Ketua OSIS</span> <?= esc($winner['wakil']) ?></span>
          </h3>
          <p class="victor__tally">
            <strong><?= angka($winner['votes']) ?></strong> suara &middot; <strong><?= persen($winner['percent']) ?></strong> dari suara sah
            <?php if ($result['margin'] > 0 && count($result['ranking']) > 1): ?>
              &middot; unggul <?= angka($result['margin']) ?> suara dari peringkat 2
            <?php endif; ?>
          </p>
        </section>
      <?php elseif ($state === FinalResult::TIE): ?>
        <div class="final-note final-note--tie" role="note">
          <?= icon('alert') ?>
          <p>
            <strong>Perolehan suara tertinggi sama.</strong>
            <?= esc(implode(' dan ', array_map(static fn (array $c): string => 'Pasangan ' . $c['label'], $result['tied']))) ?>
            sama-sama memperoleh <?= angka($result['tied'][0]['votes']) ?> suara. Sistem tidak menetapkan pemenang;
            penetapan mengikuti ketentuan panitia pemilihan.
          </p>
        </div>
      <?php else: ?>
        <div class="final-note" role="note">
          <?= icon('info') ?>
          <p><strong>Tidak ada suara sah.</strong> Pemilihan selesai tanpa suara terkunci dari pemilih aktif, sehingga tidak ada pasangan yang ditetapkan.</p>
        </div>
      <?php endif; ?>

      <section class="standings-wrap" aria-labelledby="standings-title">
        <h3 class="final__section" id="standings-title">Perolehan suara</h3>
        <ol class="standings">
          <?php foreach ($result['ranking'] as $c): ?>
            <?php $isWinner = $winner !== null && (int) $winner['id'] === (int) $c['id']; ?>
            <li class="standing<?= $isWinner ? ' is-winner' : '' ?>" style="--accent: <?= esc($c['accent'], 'attr') ?>; --accent-ink: <?= esc($c['accent_ink'], 'attr') ?>;">
              <span class="standing__rank"><span class="visually-hidden">Peringkat </span><?= (int) $c['rank'] ?></span>
              <span class="standing__no" aria-hidden="true"><?= esc($c['label']) ?></span>
              <div class="standing__body">
                <p class="standing__names">
                  <span class="visually-hidden">Pasangan <?= esc($c['label']) ?>:</span>
                  <strong><?= esc($c['ketua']) ?></strong> <span>&amp; <?= esc($c['wakil']) ?></span>
                  <?php if ($isWinner): ?><span class="pill pill--ink"><?= icon('award') ?> Terpilih</span><?php endif; ?>
                  <?php if (! $c['active']): ?><span class="pill pill--muted">nonaktif</span><?php endif; ?>
                </p>
                <span class="standing__track" aria-hidden="true"><span class="standing__fill" style="width: <?= esc(number_format($c['percent'], 2, '.', ''), 'attr') ?>%;"></span></span>
                <p class="standing__split">Siswa <?= angka($c['student_votes']) ?> &middot; Guru <?= angka($c['teacher_votes']) ?></p>
              </div>
              <p class="standing__count">
                <span class="standing__votes"><?= angka($c['votes']) ?><span class="visually-hidden"> suara</span></span>
                <span class="standing__pct"><?= persen($c['percent']) ?></span>
              </p>
            </li>
          <?php endforeach; ?>
        </ol>
        <?php if ($result['ranking'] === []): ?>
          <p class="empty">Tidak ada pasangan calon.</p>
        <?php endif; ?>
      </section>

      <section class="final__turnout" aria-labelledby="turnout-title">
        <h3 class="final__section" id="turnout-title">Partisipasi</h3>
        <dl class="turnout">
          <?php foreach (['all' => 'Seluruh pemilih', 'students' => 'Siswa', 'teachers' => 'Guru'] as $key => $label): ?>
            <?php $s = $snapshot['summary'][$key]; ?>
            <div class="turnout__item">
              <dt><?= esc($label) ?></dt>
              <dd class="turnout__big"><?= persen($s['participation']) ?></dd>
              <dd class="turnout__sub"><?= angka($s['voted']) ?> memilih dari <?= angka($s['total']) ?> &middot; <?= angka($s['not_voted']) ?> tidak memilih</dd>
            </div>
          <?php endforeach; ?>
        </dl>
      </section>

      <section class="final__recap" aria-labelledby="recap-title">
        <h3 class="final__section" id="recap-title">Rekap</h3>
        <?= view('admin/partials/recap_table', [
            'groups'     => $snapshot['groups']['type'],
            'candidates' => $snapshot['candidates'],
            'key'        => 'type',
            'label'      => 'Pemilih',
            'caption'    => 'Hasil akhir per pemilih (siswa dan guru)',
        ]) ?>
        <?= view('admin/partials/recap_table', [
            'groups'     => $snapshot['groups']['grade'],
            'candidates' => $snapshot['candidates'],
            'key'        => 'grade',
            'label'      => 'Kelas',
            'caption'    => 'Hasil akhir siswa per kelas (7/8/9)',
        ]) ?>
        <?= view('admin/partials/recap_table', [
            'groups'     => $snapshot['groups']['gender'],
            'candidates' => $snapshot['candidates'],
            'key'        => 'gender',
            'label'      => 'Jenis Kelamin Siswa',
            'caption'    => 'Hasil akhir siswa per jenis kelamin',
        ]) ?>
      </section>

      <footer class="final__foot">
        <p><?= esc(FinalResult::footnote($snapshot['generated_at'])) ?></p>
      </footer>
    </article>

    <?php if ($result['celebrate']): ?>
      <canvas class="confetti" data-confetti hidden aria-hidden="true"
              data-confetti-key="<?= esc($election['id'] . '-' . strtotime($election['end_at']), 'attr') ?>"
              data-confetti-colors="<?= esc(implode(',', $accents), 'attr') ?>"></canvas>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if (! $result['available'] && $election !== null): ?>
  <script src="<?= asset_url('assets/js/countdown.js') ?>" defer></script>
<?php endif; ?>
<?php if ($result['celebrate']): ?>
  <script src="<?= asset_url('assets/js/confetti.js') ?>" defer></script>
<?php endif; ?>
<?= $this->endSection() ?>
