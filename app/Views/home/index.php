<?= $this->extend('layouts/main') ?>

<?= $this->section('head') ?>
<?php
/* Yang pertama terlihat di layar pembuka diminta sejak <head>, sebelum
   browser sampai ke <body>: latar pembuka (media sama dengan <source> di
   <picture>: potret -> versi HP, lanskap -> versi desktop; URL ?v= sama agar
   dipakai ulang), lockup, dan lambang sekolah bila dipasang. Latar hero
   tidak perlu: tertutup layar pembuka dan sudah fetchpriority="high". */
$firstScreen = [
    [$home->introMobile, '(orientation: portrait)'],
    [$home->introDesktop, '(orientation: landscape)'],
    [$home->logoOnDark, null],
    [($home->schoolEmblem ?? '') !== '' ? $home->schoolEmblem : null, null],
];
?>
<?php foreach ($firstScreen as [$path, $media]): ?>
<?php if ($path !== null): ?>
<link rel="preload" href="<?= esc(asset_url($path), 'attr') ?>" as="image"<?= $media !== null ? ' media="' . esc($media, 'attr') . '"' : '' ?> fetchpriority="high">
<?php endif; ?>
<?php endforeach; ?>
<link rel="preload" href="<?= base_url('assets/fonts/jetbrains-mono-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset_url('assets/css/home.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('overlay') ?>
<?= view('home/partials/splash', ['home' => $home, 'identity' => $identity]) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
/**
 * Beranda imersif (Stage 5, STAGE5-NOTES.md) dengan identitas visual SMP 1
 * DAWE (Stage 6, STAGE6-NOTES.md; dokumen 06-10).
 *
 * Tiga scene layar penuh yang berpindah satu per satu (home.js): LERENG
 * (hero berlapis: latar lereng Muria, lapisan depan opsional, tombol masuk &
 * perolehan suara; Stage 18: tanpa teks terlihat, kontur, dan perforasi),
 * PEMILIH (pintu masuk Siswa/Guru), dan SUARA (perolehan suara /
 * pasangan calon; hanya palet netral + aksen pasangan). Panel status
 * bergaya terminal menempel di bawah layar. Tanpa JavaScript, scene tampil
 * sebagai halaman bergulir biasa dengan scroll-snap.
 *
 * Stage 10 (scene SUARA): tanpa label status di samping judul (status tetap
 * di panel dock); judul & pasangan rata tengah; "Suara masuk" tidak lagi
 * sekolom dengan pasangan, tetapi menjadi baris penutup (menggantikan
 * catatan) berisi persentase, meter, dan jumlah pemilih.
 *
 * @var array|null       $election
 * @var list<array>      $candidates Pasangan aktif (CandidateTheme::present())
 * @var array|null       $live       PublicLiveCount::build() + pairs; null = live count publik mati
 * @var \Config\Homepage $home
 * @var string|null      $identity   Warna parijoto bila netral terhadap semua pasangan
 */
$status   = $election['status'] ?? null;
$liveId   = $live !== null ? 'perolehan' : 'pasangan';
$liveName = $live !== null ? 'Perolehan suara' : 'Pasangan calon';
$scenes   = [
    ['id' => 'beranda', 'label' => 'Beranda'],
    ['id' => 'masuk', 'label' => 'Masuk'],
    ['id' => $liveId, 'label' => $liveName],
];
$entries = [
    ['key' => 'student', 'who' => 'Siswa', 'no' => '01', 'href' => base_url('siswa/masuk'), 'img' => $home->entryStudent, 'mobile' => $home->entryStudentMobile],
    ['key' => 'teacher', 'who' => 'Guru', 'no' => '02', 'href' => base_url('guru/masuk'), 'img' => $home->entryTeacher, 'mobile' => $home->entryTeacherMobile],
];
?>
<div class="scenes" data-scenes>

  <?php /* ---------------------------------------------------- 01 LERENG (hero) */ ?>
  <section class="scene scene--hero is-active" id="beranda" data-scene aria-labelledby="hero-title" tabindex="-1">
    <?php /* Lapisan visual (belakang -> depan): latar lereng Muria (A03/A04),
             lapisan gelap solid, lapisan depan parijoto (A05, opsional).
             Stage 18: tanpa kontur punggungan. Tanpa warna/nomor/foto pasangan. */ ?>
    <div class="hero-bg" aria-hidden="true" data-hero-bg>
      <picture class="hero-bg__photo">
        <source media="(orientation: portrait)" srcset="<?= esc(asset_url($home->heroMobile), 'attr') ?>">
        <img class="hero-bg__img" src="<?= esc(asset_url($home->heroDesktop), 'attr') ?>" alt="" fetchpriority="high" decoding="async" data-hero-img>
      </picture>
      <span class="hero-bg__shade"></span>
      <?php if (($home->heroForeground ?? '') !== ''): ?>
        <span class="hero-bg__fore">
          <img class="hero-bg__fore-img" src="<?= esc(asset_url($home->heroForeground), 'attr') ?>" alt="" decoding="async">
        </span>
      <?php endif; ?>
    </div>
    <canvas class="field" data-field aria-hidden="true"></canvas>
    <div class="scene__inner" data-scene-scroll>
      <div class="hero">
        <?php /* Stage 18: tanpa teks terlihat di atas gambar; judul tetap ada
                 untuk pembaca layar (nama scene & satu-satunya <h1>). */ ?>
        <h1 class="visually-hidden" id="hero-title">Pemilihan Ketua &amp; Wakil Ketua OSIS SMP 1 DAWE 2026</h1>
        <div class="hero__actions" data-reveal>
          <a class="hbtn hbtn--primary" href="#masuk" data-scene-link>Masuk untuk memilih <?= icon('arrow-right') ?></a>
          <a class="hlink" href="#<?= esc($liveId, 'attr') ?>" data-scene-link><?= esc($live !== null ? 'Lihat perolehan suara' : 'Kenali pasangan calon') ?></a>
        </div>
      </div>
    </div>
  </section>

  <?php /* ------------------------------------------ 02 PEMILIH (pintu masuk) */ ?>
  <section class="scene scene--entry" id="masuk" data-scene aria-labelledby="entry-title" tabindex="-1">
    <div class="scene__inner" data-scene-scroll>
      <div class="entry">
        <h2 class="entry__title" id="entry-title" data-reveal>Masuk sebagai</h2>
        <div class="entry__portals">
          <?php foreach ($entries as $e): ?>
            <a class="portal portal--<?= esc($e['key'], 'attr') ?>" href="<?= esc($e['href'], 'attr') ?>"
               aria-label="Masuk sebagai <?= esc($e['who'], 'attr') ?>" data-portal data-reveal>
              <span class="portal__media" aria-hidden="true">
                <picture>
                  <?php if (($e['mobile'] ?? '') !== ''): ?>
                    <source media="(orientation: portrait) and (max-width: 767px)" srcset="<?= esc(asset_url($e['mobile']), 'attr') ?>">
                  <?php endif; ?>
                  <img class="portal__img" src="<?= esc(asset_url($e['img']), 'attr') ?>" alt="" decoding="async" fetchpriority="low">
                </picture>
              </span>
              <span class="portal__shade" aria-hidden="true"></span>
              <span class="portal__no" aria-hidden="true"><?= esc($e['no']) ?></span>
              <span class="portal__who"><?= esc($e['who']) ?></span>
              <span class="portal__go" aria-hidden="true"><?= icon('arrow-up-right') ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <a class="scene-next scene-next--compact" href="#<?= esc($liveId, 'attr') ?>" data-scene-link aria-label="Lanjut ke bagian <?= esc($liveName, 'attr') ?>">
        <span class="scene-next__text" aria-hidden="true"><?= esc($liveName) ?></span>
        <?= icon('chevron-down') ?>
      </a>
    </div>
  </section>

  <?php /* ---------------------- 03 SUARA (perolehan suara / pasangan calon) */ ?>
  <section class="scene scene--live" id="<?= esc($liveId, 'attr') ?>" data-scene aria-labelledby="live-title" tabindex="-1"
    <?php if ($live !== null): ?>
      data-live
      data-live-url="<?= esc(site_url('hitung-suara'), 'attr') ?>"
      data-live-status="<?= esc((string) $status, 'attr') ?>"
      data-live-interval="<?= esc((string) $live['poll']['interval'], 'attr') ?>"
    <?php endif; ?>>
    <div class="scene__inner" data-scene-scroll>
      <div class="live<?= $live === null ? ' live--teaser' : '' ?> live--<?= esc(strtolower($status ?? 'none'), 'attr') ?>">
        <div class="live__head" data-reveal>
          <h2 class="live__title" id="live-title"><?= esc($liveName) ?></h2>
        </div>

        <?php $pairs = $live !== null ? $live['pairs'] : $candidates; ?>
        <?php if ($pairs === []): ?>
          <p class="live__empty" data-reveal>Pasangan calon belum ditetapkan.</p>
        <?php else: ?>
          <?php /* kolom seimbang untuk berapa pun jumlah pasangan: desktop maks 4, HP 3 (4+ pasangan: 2) */ ?>
          <ol class="live__pairs" style="--cols: <?= min(count($pairs), 4) ?>; --cols-sm: <?= count($pairs) <= 3 ? count($pairs) : 2 ?>;">
            <?php foreach ($pairs as $p): ?>
              <li class="pair" style="<?= esc($p['style'], 'attr') ?>"<?= $live !== null ? ' data-live-pair="' . esc((string) $p['id'], 'attr') . '"' : '' ?> data-reveal>
                <?= view('home/partials/pair_photo', ['pair' => $p]) ?>
                <p class="pair__names">
                  <span class="visually-hidden">Pasangan <?= esc($p['label']) ?>:</span>
                  <span class="pair__ketua"><?= esc($p['ketua']) ?></span>
                  <span class="pair__wakil">&amp; <?= esc($p['wakil']) ?></span>
                </p>
                <?php if ($live !== null): ?>
                  <p class="pair__pct">
                    <span class="pair__num" data-live-pct data-value="<?= esc(number_format((float) $p['percent'], 2, '.', ''), 'attr') ?>"><?= esc(number_format((float) $p['percent'], 1, ',', '.')) ?></span><span class="pair__unit">%</span><span class="visually-hidden"> suara sah</span>
                  </p>
                  <span class="pair__bar" aria-hidden="true"><span class="pair__fill" data-live-bar style="--share: <?= esc(number_format((float) $p['percent'], 2, '.', ''), 'attr') ?>;"></span></span>
                <?php else: ?>
                  <p class="pair__theme"><?= esc($p['theme_name']) ?></p>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ol>
        <?php endif; ?>

        <?php if ($live !== null): ?>
          <?php $turnoutShare = number_format((float) $live['turnout']['percent'], 2, '.', ''); ?>
          <div class="live__foot" data-reveal>
            <section class="turnout" aria-labelledby="turnout-title">
              <h3 class="turnout__title" id="turnout-title">Suara masuk</h3>
              <p class="turnout__pct">
                <span class="turnout__num" data-live-turnout data-value="<?= esc($turnoutShare, 'attr') ?>"><?= esc(number_format((float) $live['turnout']['percent'], 1, ',', '.')) ?></span><span class="turnout__unit">%</span>
              </p>
              <p class="turnout__count"><span data-live-voted><?= esc(angka($live['turnout']['voted'])) ?></span> dari <span data-live-total><?= esc(angka($live['turnout']['total'])) ?></span> pemilih telah memberikan suara</p>
              <p class="live__updated">Diperbarui <time datetime="<?= esc($live['updated_at'], 'attr') ?>" data-live-updated><?= esc($live['updated_label']) ?></time></p>
              <span class="turnout__meter" aria-hidden="true"><span class="turnout__fill" data-live-meter style="--share: <?= esc($turnoutShare, 'attr') ?>;"></span></span>
            </section>
          </div>
          <p class="visually-hidden" aria-live="polite" data-live-announce></p>
        <?php else: ?>
          <div class="live__foot" data-reveal>
            <p class="live__note">Visi, misi, dan surat suara tersedia setelah masuk.</p>
            <a class="hbtn hbtn--ghost" href="#masuk" data-scene-link>Masuk untuk memilih</a>
          </div>
        <?php endif; ?>
      </div>

      <div class="scene__end">
        <a class="scene-top" href="#beranda" data-scene-link aria-label="Kembali ke awal"><?= icon('arrow-up') ?></a>
        <?= view('partials/footer', ['footerInScene' => true]) ?>
      </div>
    </div>
  </section>
</div>

<nav class="pager" aria-label="Bagian beranda" data-pager>
  <ol class="pager__list">
    <?php foreach ($scenes as $i => $s): ?>
      <li>
        <a class="pager__link" href="#<?= esc($s['id'], 'attr') ?>" data-scene-link<?= $i === 0 ? ' aria-current="true"' : '' ?>>
          <span class="pager__label"><span class="pager__no"><?= sprintf('%02d', $i + 1) ?></span> <?= esc($s['label']) ?></span>
          <span class="pager__tick" aria-hidden="true"></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>

<?= view('home/partials/dock', ['election' => $election]) ?>

<p class="visually-hidden" aria-live="polite" data-scene-announce></p>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_url('assets/js/countdown.js') ?>" defer></script>
<script src="<?= asset_url('assets/js/home.js') ?>" defer></script>
<?= $this->endSection() ?>
