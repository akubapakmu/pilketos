<?php
/**
 * Surat suara: satu kotak per pasangan aktif sebagai "papan" sasaran paku
 * coblos. Stage 15: jumlah pasangan mengikuti batas admin (2-10); kolom
 * seimbang lewat CandidateTheme::gridStyle(), baris terakhir rata tengah
 * dengan ukuran kotak yang sama.
 *
 * - Dengan JavaScript: ballot.js menangani paku (tekan-tahan-geser-lepas),
 *   animasi tusuk, dampak pada kotak, lalu modal konfirmasi + POST JSON.
 * - Tanpa JavaScript: tombol "Coblos" adalah tautan ke halaman konfirmasi
 *   biasa (form POST), jadi voting tidak bergantung pada efek visual.
 * - Stage 7: tiap kotak punya id "coblos-0X" (tujuan tombol "Pilih" di
 *   kartu Sekilas paslon & akhir bab); kotak tujuan disorot lewat :target.
 * - Stage 8: paku 3D selalu nyala (tombol "Efek 3D" dihapus); kertas bolong
 *   terlihat 3 detik sebelum modal konfirmasi.
 * - Stage 22: halaman sendiri (voting/booth, GET bilik-suara): judul "Surat
 *   Suara" = h1, tautan kembali ke Sekilas paslon, countdown terminal (dulu
 *   di pembuka) di kolom kanan kepala. Tujuan "Pilih Paslon 0X" di profil
 *   paslon = bilik-suara#coblos-0X.
 *
 * @var list<array>             $candidates Hasil CandidateTheme::presentAll()
 * @var bool                    $canVote
 * @var array|null              $election
 * @var \App\Services\VoterType $type
 */
$status = $election['status'] ?? null;
?>
<section class="ballot<?= $canVote ? '' : ' ballot--closed' ?>" id="surat-suara" aria-labelledby="ballot-title"
         data-ballot<?php if ($canVote): ?>
         data-submit-url="<?= esc(site_url($type->path('coblos')), 'attr') ?>"
         data-myvote-url="<?= esc(site_url($type->path('pilihanku')), 'attr') ?>"
         data-webgl-src="<?= esc(asset_url('assets/js/nail-webgl.js'), 'attr') ?>"<?php endif; ?>>
  <div class="container">
    <div class="ballot__top">
      <header class="ballot__head">
        <a class="ballot__back" href="<?= esc(site_url($type->path('sekilas-paslon')), 'attr') ?>"><?= icon('arrow-left') ?> Sekilas paslon</a>
        <p class="ballot__kicker">Bilik suara digital &middot; <?= esc($type->label()) ?></p>
        <h1 class="ballot__title" id="ballot-title">Surat Suara</h1>
        <?php if ($canVote): ?>
          <p class="ballot__hint" id="ballot-hint">Tekan <strong>Coblos</strong> di kotak pilihanmu, atau tahan paku lalu seret ke kotaknya.</p>
        <?php elseif ($status === 'UPCOMING'): ?>
          <p class="ballot__hint"><?= icon('clock') ?> Surat suara dapat dicoblos mulai <strong><?= esc(format_waktu($election['start_at'])) ?></strong>.</p>
        <?php elseif ($status === 'FINISHED'): ?>
          <p class="ballot__hint"><?= icon('lock') ?> Pemilihan telah ditutup. Surat suara tidak dapat dicoblos lagi.</p>
        <?php else: ?>
          <p class="ballot__hint">Jadwal pemilihan belum tersedia.</p>
        <?php endif; ?>
      </header>

      <?php if ($election && in_array($status, ['UPCOMING', 'ONGOING'], true)): ?>
        <div class="term ballot__term">
          <span class="term__dots" aria-hidden="true"><span></span><span></span><span></span></span>
          <div class="term__body">
            <?= view('partials/countdown', ['election' => $election, 'variant' => 'terminal']) ?>
            <p class="term__meta" aria-hidden="true">
              <?= $status === 'ONGOING' ? 'selesai' : 'mulai' ?> <?= esc(format_waktu($status === 'ONGOING' ? $election['end_at'] : $election['start_at'])) ?>
              <span class="term__cursor"></span>
            </p>
          </div>
        </div>
      <?php endif; ?>
    </div>

    <div class="ballot__sheet">
      <p class="ballot__sheet-title" aria-hidden="true">
        <span>Pemilihan Ketua dan Wakil Ketua OSIS</span>
        <span>SMP 1 DAWE &middot; 2026</span>
      </p>

      <?php if ($candidates === []): ?>
        <p class="ballot__empty">Belum ada pasangan calon aktif.</p>
      <?php else: ?>
        <ol class="ballot__grid" aria-label="Pasangan calon pada surat suara" style="<?= esc(\App\Libraries\CandidateTheme::gridStyle(count($candidates)), 'attr') ?>">
          <?php foreach ($candidates as $c): ?>
            <li class="ballot-cell" id="coblos-<?= esc($c['label'], 'attr') ?>" style="<?= esc($c['style'], 'attr') ?>" data-cell
                data-candidate-id="<?= esc((string) $c['id'], 'attr') ?>"
                data-number="<?= esc($c['label'], 'attr') ?>"
                data-ketua="<?= esc($c['ketua'], 'attr') ?>"
                data-wakil="<?= esc($c['wakil'], 'attr') ?>"
                data-accent="<?= esc($c['accent'], 'attr') ?>">
              <div class="ballot-cell__target" data-target>
                <span class="ballot-cell__no"><span class="visually-hidden">Pasangan </span><?= esc($c['label']) ?></span>
                <?= view('voting/partials/portraits', ['c' => $c, 'size' => 'sm', 'lazy' => true]) ?>
                <p class="ballot-cell__names">
                  <span><?= esc($c['ketua']) ?></span>
                  <span><?= esc($c['wakil']) ?></span>
                </p>
                <span class="ballot-cell__holes" data-holes aria-hidden="true"></span>
                <span class="ballot-cell__aim" aria-hidden="true">Lepas untuk mencoblos</span>
              </div>
              <?php if ($canVote): ?>
                <a class="ballot-cell__btn" href="<?= esc(site_url($type->path('coblos/yakin/' . $c['id'])), 'attr') ?>" data-coblos>
                  <?= icon('nail') ?> Coblos Pasangan <?= esc($c['label']) ?>
                </a>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>
    </div>

    <?php if ($canVote && $candidates !== []): ?>
      <div class="ballot__dock" data-dock hidden>
        <button type="button" class="nail-grip" data-nail-grip aria-describedby="ballot-hint">
          <span class="nail-grip__nail" aria-hidden="true"><?= view('voting/partials/nail_svg') ?></span>
          <span class="nail-grip__text">
            <strong>Ambil paku</strong>
            <span>Tahan, geser ke pasangan, lepaskan</span>
          </span>
        </button>
      </div>
      <p class="visually-hidden" aria-live="polite" data-ballot-live></p>
    <?php endif; ?>
  </div>
</section>
