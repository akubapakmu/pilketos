<?php
/**
 * Profil lengkap satu pasangan calon (Stage 15): Ketua x Wakil sebagai satu
 * tim. Dipakai halaman pemilih (paslon/show) dan pratinjau admin
 * (admin/candidates/preview) sehingga keduanya selalu sama persis.
 *
 * Urutan: pembuka (nomor raksasa, duo potret / foto berdua, nama, slogan) ->
 * Kenali Pasangan -> Visi Kami -> Misi Kami -> Program Unggulan -> Poster &
 * Video -> Pesan dari Kami + penutup (slogan, tombol, deret nomor paslon).
 * Bagian yang datanya kosong tidak dirender, begitu juga butir navigasinya.
 * Strukturnya sama untuk semua paslon: yang berbeda hanya warna aksen, asset,
 * dan varian pembuka (theme_layout split | poster | column).
 *
 * Setiap foto tampil besar sekali saja: bila pembuka memakai dua potret,
 * "Kenali Pasangan" dan "Pesan" memakai avatar kecil; bila pembuka memakai
 * foto berdua (asset hero), potret individu tampil di "Kenali Pasangan".
 *
 * Interaksi (paslon.js, progressive enhancement; tanpa JS semua konten tetap
 * tampil utuh dan tautan navigasi tetap berfungsi):
 * - [data-px-nav]      : penanda bagian aktif + rel progres;
 * - [data-px-reveal]   : muncul bertahap saat masuk layar;
 * - [data-px-parallax] : nomor raksasa bergerak halus;
 * - [data-px-words]    : visi menyala kata demi kata mengikuti scroll;
 * - [data-px-zoom]     : poster dibuka di lightbox (<dialog>);
 * - [data-px-video]    : iframe YouTube baru dibuat setelah tombol putar ditekan.
 *
 * @var array                                                                $c       CandidateTheme::present()
 * @var list<array{label: string, href: string, current: bool, ketua: string, wakil: string}> $others Deret nomor paslon (urut nomor, tampil setara)
 * @var list<array{label: string, href: string, icon?: string, primary?: bool}> $actions Tombol penutup
 * @var array{label: string, href: string}|null                              $back    Tautan kembali di atas pembuka
 */
$people = [
    'ketua' => ['role' => 'Calon Ketua', 'long' => 'Calon Ketua OSIS', 'name' => $c['ketua'], 'photo' => $c['photo_ketua'], 'bio' => $c['bio_ketua'], 'tags' => $c['karakter_ketua'], 'pesan' => $c['pesan_ketua']],
    'wakil' => ['role' => 'Calon Wakil', 'long' => 'Calon Wakil Ketua OSIS', 'name' => $c['wakil'], 'photo' => $c['photo_wakil'], 'bio' => $c['bio_wakil'], 'tags' => $c['karakter_wakil'], 'pesan' => $c['pesan_wakil']],
];
$pairPhoto = $c['hero'] !== null;
// "Kenali pasangan" tampil bila ada bio/karakter, atau bila pembuka memakai
// foto berdua sementara foto masing-masing tersedia (siapa ketua, siapa wakil).
$hasMeet   = array_filter($people, static fn (array $p): bool => $p['bio'] !== '' || $p['tags'] !== [] || ($pairPhoto && $p['photo'] !== null)) !== [];
$hasPesan  = array_filter($people, static fn (array $p): bool => $p['pesan'] !== '') !== [];
$hasMedia  = $c['poster'] !== null || $c['video'] !== null;
$mediaName = $c['poster'] !== null && $c['video'] !== null ? 'Poster & video' : ($c['poster'] !== null ? 'Poster paslon' : 'Video kampanye');
$sections  = array_filter([
    'profil'  => $hasMeet ? 'Profil' : null,
    'visi'    => $c['visi'] !== '' ? 'Visi' : null,
    'misi'    => $c['misi'] !== [] ? 'Misi' : null,
    'program' => $c['program'] !== [] ? 'Program' : null,
    'media'   => $hasMedia ? ($c['poster'] !== null ? 'Poster' : 'Video') : null,
    'pesan'   => $hasPesan ? 'Pesan' : null,
]);
$first     = array_key_first($sections);
$cta       = match (true) {
    isset($sections['visi']) => ['#visi', 'Lihat Visi & Misi'],
    $first !== null          => ['#' . $first, 'Kenali lebih dekat'],
    default                  => null,
};
$long      = static fn (string $name): bool => mb_strlen($name) > 24;
$id        = static fn (string $key): string => 'px-' . $key . '-' . $c['id'];
?>
<article class="paslon paslon--<?= esc($c['layout'], 'attr') ?>" style="<?= esc($c['style'], 'attr') ?>" data-paslon aria-labelledby="<?= esc($id('title'), 'attr') ?>">

  <?php /* ------------------------------------------------------- pembuka */ ?>
  <header class="px-hero<?= $pairPhoto ? ' px-hero--photo' : '' ?>" data-px-hero>
    <div class="px-hero__stage" aria-hidden="true">
      <span class="px-hero__pattern px-hero__pattern--<?= esc($c['pattern'], 'attr') ?><?= $c['texture'] !== null ? ' px-hero__pattern--texture' : '' ?>"
            <?php if ($c['texture'] !== null): ?>style="background-image: url('<?= esc($c['texture'], 'attr') ?>');"<?php endif; ?>></span>
      <?php if ($c['background'] !== null): ?>
        <img class="px-hero__backdrop" src="<?= esc($c['background'], 'attr') ?>" alt="" decoding="async">
      <?php endif; ?>
      <span class="px-hero__numeral" data-px-parallax="-0.14"><?= esc($c['label']) ?></span>
      <?php if ($c['artwork'] !== null): ?>
        <img class="px-hero__artwork" src="<?= esc($c['artwork'], 'attr') ?>" alt="" loading="lazy" decoding="async" data-px-parallax="0.08">
      <?php endif; ?>
    </div>

    <div class="container px-hero__inner">
      <?php if (($back ?? null) !== null): ?>
        <a class="px-back" href="<?= esc($back['href'], 'attr') ?>"><?= icon('arrow-left') ?> <?= esc($back['label']) ?></a>
      <?php endif; ?>

      <p class="px-hero__kicker">
        <span class="px-hero__tag">Paslon <strong><?= esc($c['label']) ?></strong></span>
        <?php if ($c['theme_distinct']): ?>
          <span class="px-hero__theme"><?= esc($c['theme_name']) ?></span>
        <?php endif; ?>
      </p>

      <div class="px-duo" data-px-duo>
        <?php if ($pairPhoto): ?>
          <img class="px-duo__photo" src="<?= esc($c['hero'], 'attr') ?>"
               alt="Foto pasangan <?= esc($c['label'], 'attr') ?>: <?= esc($c['ketua'], 'attr') ?> dan <?= esc($c['wakil'], 'attr') ?>"
               fetchpriority="high" decoding="async">
        <?php else: ?>
          <?= view('voting/partials/portraits', ['c' => $c, 'size' => 'xl', 'lazy' => false, 'priority' => true, 'rolePrefix' => 'Calon ']) ?>
          <span class="px-duo__x" aria-hidden="true">&times;</span>
        <?php endif; ?>
      </div>

      <h1 class="px-hero__names" id="<?= esc($id('title'), 'attr') ?>">
        <?php $i = 0; foreach ($people as $key => $p): ?>
          <?php if ($i++ === 1): ?>
            <span class="px-hero__x" aria-hidden="true">&times;</span><span class="visually-hidden"> dan </span>
          <?php endif; ?>
          <span class="px-person px-person--<?= esc($key, 'attr') ?>">
            <span class="px-person__name<?= $long($p['name']) ? ' px-person__name--long' : '' ?>"><?= esc($p['name']) ?></span>
            <span class="px-person__role"><span class="visually-hidden">, </span><?= esc($p['role']) ?></span>
          </span>
        <?php endforeach; ?>
      </h1>

      <?php if ($c['slogan'] !== ''): ?>
        <p class="px-hero__slogan">&ldquo;<?= esc($c['slogan']) ?>&rdquo;</p>
      <?php endif; ?>

      <?php if ($cta !== null): ?>
        <a class="px-hero__cta" href="<?= esc($cta[0], 'attr') ?>"><?= esc($cta[1]) ?> <?= icon('arrow-down') ?></a>
      <?php endif; ?>
    </div>
  </header>

  <?php /* ---------------------------------------------- navigasi bagian */ ?>
  <?php if (count($sections) > 1): ?>
    <nav class="px-nav" aria-label="Bagian profil paslon <?= esc($c['label'], 'attr') ?>" data-px-nav>
      <ol class="px-nav__list">
        <?php foreach ($sections as $key => $label): ?>
          <li class="px-nav__item">
            <a class="px-nav__link" href="#<?= esc($key, 'attr') ?>" data-px-nav-link>
              <span class="px-nav__dot" aria-hidden="true"></span>
              <span class="px-nav__label"><?= esc($label) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ol>
      <span class="px-nav__rail" aria-hidden="true"><span class="px-nav__fill" data-px-nav-fill></span></span>
    </nav>
  <?php endif; ?>

  <?php /* ------------------------------------------------ kenali pasangan */ ?>
  <?php if ($hasMeet): ?>
    <section class="px-section px-meet" id="profil" aria-labelledby="<?= esc($id('profil'), 'attr') ?>" data-px-section>
      <div class="container">
        <h2 class="px-title" id="<?= esc($id('profil'), 'attr') ?>">Kenali pasangan</h2>
        <div class="px-meet__grid<?= $pairPhoto ? ' px-meet__grid--photos' : '' ?>">
          <?php $i = 0; foreach ($people as $key => $p): ?>
            <?php if ($i++ === 1): ?>
              <span class="px-meet__x" aria-hidden="true">&times;</span>
            <?php endif; ?>
            <div class="px-meet__person px-meet__person--<?= esc($key, 'attr') ?>" data-px-reveal>
              <?php if ($pairPhoto): ?>
                <div class="px-meet__photo">
                  <?php if ($p['photo'] !== null): ?>
                    <img src="<?= esc($p['photo'], 'attr') ?>" alt="Foto <?= esc($p['name'], 'attr') ?>, <?= esc(strtolower($p['role']), 'attr') ?> pasangan <?= esc($c['label'], 'attr') ?>" loading="lazy" decoding="async" width="480" height="600">
                  <?php else: ?>
                    <span class="px-meet__mono" aria-hidden="true"><?= esc($c[$key . '_initials']) ?></span>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <?= view('paslon/partials/avatar', ['c' => $c, 'role' => $key]) ?>
              <?php endif; ?>
              <p class="px-meet__role"><?= esc($p['long']) ?></p>
              <h3 class="px-meet__name"><?= esc($p['name']) ?></h3>
              <?php if ($p['bio'] !== ''): ?>
                <p class="px-meet__bio"><?= esc($p['bio']) ?></p>
              <?php endif; ?>
              <?php if ($p['tags'] !== []): ?>
                <ul class="px-tags" aria-label="Karakter <?= esc($p['name'], 'attr') ?>">
                  <?php foreach ($p['tags'] as $tag): ?>
                    <li><?= esc($tag) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php /* ------------------------------------------------------ visi kami */ ?>
  <?php if ($c['visi'] !== ''): ?>
    <section class="px-section px-visi" id="visi" aria-labelledby="<?= esc($id('visi'), 'attr') ?>" data-px-section>
      <div class="container">
        <h2 class="px-label" id="<?= esc($id('visi'), 'attr') ?>">Visi kami</h2>
        <blockquote class="px-visi__text<?= mb_strlen($c['visi']) > 180 ? ' px-visi__text--long' : '' ?>" data-px-words>
          <p><?= esc($c['visi']) ?></p>
        </blockquote>
      </div>
    </section>
  <?php endif; ?>

  <?php /* ------------------------------------------------------ misi kami */ ?>
  <?php if ($c['misi'] !== []): ?>
    <section class="px-section px-misi" id="misi" aria-labelledby="<?= esc($id('misi'), 'attr') ?>" data-px-section>
      <div class="container">
        <h2 class="px-title" id="<?= esc($id('misi'), 'attr') ?>">Misi kami</h2>
        <ol class="px-list">
          <?php foreach ($c['misi'] as $i => $item): ?>
            <li class="px-list__item" style="--i: <?= (int) $i ?>;" data-px-reveal>
              <span class="px-list__n" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
              <p class="px-list__text"><?= esc($item) ?></p>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>
  <?php endif; ?>

  <?php /* ----------------------------------------------- program unggulan */ ?>
  <?php if ($c['program'] !== []): ?>
    <section class="px-section px-program" id="program" aria-labelledby="<?= esc($id('program'), 'attr') ?>" data-px-section>
      <div class="container">
        <h2 class="px-title" id="<?= esc($id('program'), 'attr') ?>">Program unggulan kami</h2>
        <ol class="px-programs">
          <?php foreach ($c['program'] as $i => $item): ?>
            <li class="px-programs__item" style="--i: <?= (int) $i ?>;" data-px-reveal>
              <span class="px-programs__n" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
              <div class="px-programs__body">
                <h3 class="px-programs__title"><?= esc($item['title']) ?></h3>
                <?php if ($item['text'] !== ''): ?>
                  <p class="px-programs__text"><?= esc($item['text']) ?></p>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    </section>
  <?php endif; ?>

  <?php /* ------------------------------------------------ poster & video */ ?>
  <?php if ($hasMedia): ?>
    <section class="px-section px-media" id="media" aria-labelledby="<?= esc($id('media'), 'attr') ?>" data-px-section>
      <div class="container">
        <h2 class="px-title" id="<?= esc($id('media'), 'attr') ?>"><?= esc($mediaName) ?></h2>
        <div class="px-media__grid<?= $c['poster'] !== null && $c['video'] !== null ? ' px-media__grid--both' : '' ?>">
          <?php if ($c['poster'] !== null): ?>
            <figure class="px-poster" data-px-reveal>
              <img class="px-poster__img" src="<?= esc($c['poster'], 'attr') ?>" alt="Poster kampanye pasangan <?= esc($c['label'], 'attr') ?>" loading="lazy" decoding="async" data-px-zoom>
              <figcaption class="px-poster__bar">
                <span>Poster kampanye pasangan <?= esc($c['label']) ?></span>
                <a class="px-btn px-btn--quiet" href="<?= esc($c['poster'], 'attr') ?>" target="_blank" rel="noopener" data-px-zoom><?= icon('expand') ?> Lihat fullscreen</a>
              </figcaption>
            </figure>
            <dialog class="px-lightbox" aria-label="Poster kampanye pasangan <?= esc($c['label'], 'attr') ?>" data-px-lightbox>
              <img class="px-lightbox__img" src="<?= esc($c['poster'], 'attr') ?>" alt="Poster kampanye pasangan <?= esc($c['label'], 'attr') ?>" loading="lazy" decoding="async">
              <button type="button" class="px-lightbox__close" data-px-close><?= icon('close') ?><span class="visually-hidden">Tutup poster</span></button>
            </dialog>
          <?php endif; ?>

          <?php if ($c['video'] !== null): ?>
            <div class="px-video" data-px-reveal data-px-video
                 data-embed="<?= esc($c['video_embed'], 'attr') ?>"
                 data-title="Video kampanye pasangan <?= esc($c['label'], 'attr') ?>">
              <div class="px-video__frame" data-px-video-frame>
                <button type="button" class="px-video__play" data-px-play hidden>
                  <span class="px-video__icon" aria-hidden="true"><?= icon('play') ?></span>
                  <span class="px-video__label">Putar video kampanye</span>
                  <span class="px-video__note">Diputar dari YouTube setelah tombol ditekan</span>
                </button>
                <span class="px-video__numeral" aria-hidden="true"><?= esc($c['label']) ?></span>
              </div>
              <p class="px-video__bar">
                <span>Video kampanye pasangan <?= esc($c['label']) ?></span>
                <a class="px-btn px-btn--quiet" href="<?= esc($c['video_url'], 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= icon('external') ?> Buka di YouTube</a>
              </p>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>
  <?php endif; ?>

  <?php /* ------------------------------------------ pesan + penutup */ ?>
  <section class="px-section px-close" <?= $hasPesan ? 'id="pesan" data-px-section' : '' ?> aria-labelledby="<?= esc($id('close'), 'attr') ?>">
    <div class="container">
      <?php if ($hasPesan): ?>
        <h2 class="px-title" id="<?= esc($id('close'), 'attr') ?>">Pesan dari kami</h2>
        <div class="px-quotes">
          <?php foreach ($people as $key => $p): ?>
            <?php if ($p['pesan'] !== ''): ?>
              <figure class="px-quote" data-px-reveal>
                <blockquote class="px-quote__text"><p>&ldquo;<?= esc($p['pesan']) ?>&rdquo;</p></blockquote>
                <figcaption class="px-quote__who">
                  <?= view('paslon/partials/avatar', ['c' => $c, 'role' => $key]) ?>
                  <span><strong><?= esc($p['name']) ?></strong><span><?= esc($p['role']) ?></span></span>
                </figcaption>
              </figure>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <h2 class="visually-hidden" id="<?= esc($id('close'), 'attr') ?>">Penutup profil pasangan <?= esc($c['label']) ?></h2>
      <?php endif; ?>

      <div class="px-finale" data-px-reveal>
        <p class="px-finale__no">Paslon <strong><?= esc($c['label']) ?></strong></p>
        <p class="px-finale__names">
          <span><?= esc($c['ketua']) ?></span>
          <span class="px-finale__x" aria-hidden="true">&times;</span><span class="visually-hidden"> dan </span>
          <span><?= esc($c['wakil']) ?></span>
        </p>
        <?php if ($c['slogan'] !== ''): ?>
          <p class="px-finale__slogan">&ldquo;<?= esc($c['slogan']) ?>&rdquo;</p>
        <?php endif; ?>

        <?php if (($actions ?? []) !== []): ?>
          <div class="px-finale__actions">
            <?php foreach ($actions as $action): ?>
              <a class="px-btn<?= ($action['primary'] ?? false) ? ' px-btn--primary' : '' ?>" href="<?= esc($action['href'], 'attr') ?>"<?= ($action['pick'] ?? false) ? ' data-pick' : '' ?>>
                <?= esc($action['label']) ?><?= isset($action['icon']) ? ' ' . icon($action['icon']) : '' ?>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <?php if (count($others ?? []) > 1): ?>
        <nav class="px-others" aria-label="Profil paslon lain">
          <p class="px-others__label" aria-hidden="true">Profil paslon</p>
          <ol class="px-others__list">
            <?php foreach ($others as $o): ?>
              <li>
                <a class="px-others__link" href="<?= esc($o['href'], 'attr') ?>"<?= $o['current'] ? ' aria-current="page"' : '' ?>>
                  <span class="px-others__no" aria-hidden="true"><?= esc($o['label']) ?></span>
                  <span class="visually-hidden">Paslon <?= esc($o['label']) ?>: <?= esc($o['ketua']) ?> dan <?= esc($o['wakil']) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ol>
        </nav>
      <?php endif; ?>
    </div>
  </section>
</article>
