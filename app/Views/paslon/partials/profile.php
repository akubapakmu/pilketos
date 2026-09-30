<?php
/**
 * Profil lengkap satu pasangan calon (Stage 15, redesain Stage 16): Ketua x
 * Wakil sebagai satu tim. Dipakai halaman pemilih (paslon/show) dan pratinjau
 * admin (admin/candidates/preview) sehingga keduanya selalu sama persis.
 *
 * Stage 16: satu tampilan untuk semua pasangan, tanpa varian layout, pola,
 * latar panggung, artwork, atau garis dekoratif. Yang berbeda hanya warna
 * aksen, gambar, dan isi. Urutan:
 * - pembuka: SATU gambar landscape buatan paslon (asset banner, tampil utuh)
 *   dengan tombol "Lihat Visi & Misi" di bawahnya. Tanpa gambar: judul
 *   sederhana (Paslon 0X, nama, slogan);
 * - Kenali pasangan (selalu ada: foto/monogram, nama, peran; bio & karakter
 *   bila diisi) -> Visi -> Misi -> Program -> Poster -> Pesan -> Video;
 * - penutup selebar layar: gambar latar khusus (asset closing) tanpa warna
 *   aksen, nama, slogan, tombol, dan deret nomor paslon.
 * Bagian yang datanya kosong tidak dirender, begitu juga butir navigasinya.
 * Navigasi bagian: bar menempel di HP/tablet, kolom kanan dengan semua label
 * terlihat di desktop (paslon.css).
 *
 * Interaksi (paslon.js, progressive enhancement; tanpa JS semua konten tetap
 * tampil utuh dan tautan tetap berfungsi):
 * - [data-px-nav]    : penanda bagian aktif;
 * - [data-px-reveal] : muncul bertahap saat masuk layar;
 * - [data-px-zoom]   : poster dibuka di lightbox (<dialog>);
 * - [data-px-video]  : thumbnail asli YouTube + tombol putar; iframe baru dibuat
 *                      setelah diklik (tanpa JS: tautan ke YouTube).
 *
 * @var array                                                                $c       CandidateTheme::present()
 * @var list<array{label: string, href: string, current: bool, ketua: string, wakil: string}> $others Deret nomor paslon (urut nomor, tampil setara)
 * @var list<array{label: string, href: string, icon?: string, primary?: bool}> $actions Tombol penutup
 * @var array{label: string, href: string}|null                              $back    Tautan kembali di atas pembuka
 */
use App\Libraries\CandidateTheme;

$people = [
    'ketua' => ['role' => 'Calon Ketua', 'long' => 'Calon Ketua OSIS', 'name' => $c['ketua'], 'photo' => $c['photo_ketua'], 'bio' => $c['bio_ketua'], 'tags' => $c['karakter_ketua'], 'pesan' => $c['pesan_ketua']],
    'wakil' => ['role' => 'Calon Wakil', 'long' => 'Calon Wakil Ketua OSIS', 'name' => $c['wakil'], 'photo' => $c['photo_wakil'], 'bio' => $c['bio_wakil'], 'tags' => $c['karakter_wakil'], 'pesan' => $c['pesan_wakil']],
];
$hasPesan = array_filter($people, static fn (array $p): bool => $p['pesan'] !== '') !== [];
$sections = array_filter([
    'profil'  => 'Profil',
    'visi'    => $c['visi'] !== '' ? 'Visi' : null,
    'misi'    => $c['misi'] !== [] ? 'Misi' : null,
    'program' => $c['program'] !== [] ? 'Program' : null,
    'poster'  => $c['poster'] !== null ? 'Poster' : null,
    'pesan'   => $hasPesan ? 'Pesan' : null,
    'video'   => $c['video'] !== null ? 'Video' : null,
]);
$cta  = isset($sections['visi']) ? ['visi', 'Lihat Visi & Misi'] : ['profil', 'Kenali lebih dekat'];
$long = static fn (string $name): bool => mb_strlen($name) > 24;
$id   = static fn (string $key): string => 'px-' . $key . '-' . $c['id'];
// Kepala bagian: ikon (warna aksen) + judul (dikapitalkan CSS) + subjudul.
$head = static fn (string $key, string $icon, string $title, string $sub = ''): string => '<div class="px-head">'
    . '<span class="px-head__icon">' . icon($icon) . '</span>'
    . '<div class="px-head__text">'
    . '<h2 class="px-head__title" id="' . esc($id($key), 'attr') . '">' . esc($title) . '</h2>'
    . ($sub === '' ? '' : '<p class="px-head__sub">' . esc($sub) . '</p>')
    . '</div></div>';
?>
<article class="paslon" style="<?= esc($c['style'], 'attr') ?>" data-paslon aria-labelledby="<?= esc($id('title'), 'attr') ?>">
  <div class="px-shell<?= count($sections) > 1 ? ' px-shell--nav' : '' ?>">
    <?php if (($back ?? null) !== null): ?>
      <a class="px-back" href="<?= esc($back['href'], 'attr') ?>"><?= icon('arrow-left') ?> <?= esc($back['label']) ?></a>
    <?php endif; ?>

    <?php /* ------------------------------------------------------- pembuka */ ?>
    <header class="px-cover<?= $c['banner'] !== null ? ' px-cover--image' : '' ?>">
      <?php if ($c['banner'] !== null): ?>
        <h1 class="visually-hidden" id="<?= esc($id('title'), 'attr') ?>">Paslon <?= esc($c['label']) ?>: <?= esc($c['ketua']) ?> dan <?= esc($c['wakil']) ?></h1>
        <img class="px-cover__img" src="<?= esc($c['banner'], 'attr') ?>"
             alt="Gambar pembuka pasangan <?= esc($c['label'], 'attr') ?><?= $c['slogan'] !== '' ? ': &ldquo;' . esc($c['slogan'], 'attr') . '&rdquo;' : '' ?>"
             <?= $c['banner_size'] !== null ? 'width="' . (int) $c['banner_size'][0] . '" height="' . (int) $c['banner_size'][1] . '" ' : '' ?>fetchpriority="high" decoding="async">
      <?php else: ?>
        <div class="px-cover__plain">
          <p class="px-cover__no">Paslon <strong><?= esc($c['label']) ?></strong></p>
          <h1 class="px-cover__names" id="<?= esc($id('title'), 'attr') ?>">
            <?php $i = 0; foreach ($people as $key => $p): ?>
              <?php if ($i++ === 1): ?>
                <span class="px-cover__x" aria-hidden="true">&times;</span><span class="visually-hidden"> dan </span>
              <?php endif; ?>
              <span class="px-cover__person px-cover__person--<?= esc($key, 'attr') ?>">
                <span class="px-cover__name<?= $long($p['name']) ? ' px-cover__name--long' : '' ?>"><?= esc($p['name']) ?></span>
                <span class="px-cover__role"><span class="visually-hidden">, </span><?= esc($p['role']) ?></span>
              </span>
            <?php endforeach; ?>
          </h1>
          <?php if ($c['slogan'] !== ''): ?>
            <p class="px-cover__slogan">&ldquo;<?= esc($c['slogan']) ?>&rdquo;</p>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <a class="px-cta" href="#<?= esc($cta[0], 'attr') ?>"><?= esc($cta[1]) ?> <?= icon('arrow-down') ?></a>
    </header>

    <?php /* ---------------------------------------------- navigasi bagian */ ?>
    <?php if (count($sections) > 1): ?>
      <nav class="px-nav" aria-label="Bagian profil paslon <?= esc($c['label'], 'attr') ?>" data-px-nav>
        <div class="px-nav__inner">
          <p class="px-nav__title" aria-hidden="true">Paslon <strong><?= esc($c['label']) ?></strong></p>
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
        </div>
      </nav>
    <?php endif; ?>

    <?php /* ------------------------------------------------ kenali pasangan */ ?>
    <section class="px-section px-meet" id="profil" aria-labelledby="<?= esc($id('profil'), 'attr') ?>" data-px-section>
      <?= $head('profil', 'users', 'Kenali pasangan', 'Dua individu, satu tujuan untuk SMP 1 DAWE.') ?>
      <div class="px-meet__grid">
        <?php foreach ($people as $key => $p): ?>
          <div class="px-meet__card px-meet__card--<?= esc($key, 'attr') ?>" data-px-reveal>
            <div class="px-meet__photo">
              <?php if ($p['photo'] !== null): ?>
                <img src="<?= esc($p['photo'], 'attr') ?>" alt="Foto <?= esc($p['name'], 'attr') ?>, <?= esc(strtolower($p['role']), 'attr') ?> pasangan <?= esc($c['label'], 'attr') ?>" loading="lazy" decoding="async" width="480" height="600">
              <?php else: ?>
                <span class="px-meet__mono" aria-hidden="true"><?= esc($c[$key . '_initials']) ?></span>
              <?php endif; ?>
            </div>
            <div class="px-meet__body">
              <h3 class="px-meet__name"><?= esc($p['name']) ?></h3>
              <p class="px-meet__role"><?= esc($p['long']) ?></p>
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
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <?php /* ------------------------------------------------------ visi kami */ ?>
    <?php if ($c['visi'] !== ''): ?>
      <section class="px-section px-visi" id="visi" aria-labelledby="<?= esc($id('visi'), 'attr') ?>" data-px-section>
        <div class="px-visi__panel" data-px-reveal>
          <?= $head('visi', 'eye', 'Visi kami') ?>
          <blockquote class="px-visi__text<?= mb_strlen($c['visi']) > 180 ? ' px-visi__text--long' : '' ?>">
            <p><?= esc($c['visi']) ?></p>
          </blockquote>
        </div>
      </section>
    <?php endif; ?>

    <?php /* ------------------------------------------------------ misi kami */ ?>
    <?php if ($c['misi'] !== []): ?>
      <section class="px-section px-misi" id="misi" aria-labelledby="<?= esc($id('misi'), 'attr') ?>" data-px-section>
        <?= $head('misi', 'target', 'Misi kami', 'Langkah nyata untuk mewujudkan visi kami.') ?>
        <ol class="px-list" style="--rows: <?= (int) ceil(count($c['misi']) / 2) ?>;">
          <?php foreach ($c['misi'] as $i => $item): ?>
            <li class="px-list__item" style="--i: <?= (int) $i ?>;" data-px-reveal>
              <span class="px-list__n" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
              <p class="px-list__text"><?= esc($item) ?></p>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
    <?php endif; ?>

    <?php /* ----------------------------------------------- program unggulan */ ?>
    <?php if ($c['program'] !== []): ?>
      <section class="px-section px-program" id="program" aria-labelledby="<?= esc($id('program'), 'attr') ?>" data-px-section>
        <?= $head('program', 'bulb', 'Program unggulan kami', 'Ide dan aksi untuk sekolah yang lebih baik.') ?>
        <ol class="px-programs" style="<?= esc(CandidateTheme::gridStyle(count($c['program'])), 'attr') ?>">
          <?php foreach ($c['program'] as $i => $item): ?>
            <li class="px-programs__item" style="--i: <?= (int) $i ?>;" data-px-reveal>
              <span class="px-programs__n" aria-hidden="true"><?= sprintf('%02d', $i + 1) ?></span>
              <h3 class="px-programs__title"><?= esc($item['title']) ?></h3>
              <?php if ($item['text'] !== ''): ?>
                <p class="px-programs__text"><?= esc($item['text']) ?></p>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      </section>
    <?php endif; ?>

    <?php /* ---------------------------------------------- poster kampanye */ ?>
    <?php if ($c['poster'] !== null): ?>
      <section class="px-section px-poster" id="poster" aria-labelledby="<?= esc($id('poster'), 'attr') ?>" data-px-section>
        <?= $head('poster', 'image', 'Poster kampanye', 'Lihat lebih dekat poster kampanye kami.') ?>
        <figure class="px-poster__figure" data-px-reveal>
          <img class="px-poster__img" src="<?= esc($c['poster'], 'attr') ?>" alt="Poster kampanye pasangan <?= esc($c['label'], 'attr') ?>" loading="lazy" decoding="async" data-px-zoom>
          <figcaption class="px-poster__bar">
            <span class="visually-hidden">Poster kampanye pasangan <?= esc($c['label']) ?></span>
            <a class="px-btn px-btn--dark" href="<?= esc($c['poster'], 'attr') ?>" target="_blank" rel="noopener" data-px-zoom><?= icon('expand') ?> Lihat fullscreen</a>
          </figcaption>
        </figure>
        <dialog class="px-lightbox" aria-label="Poster kampanye pasangan <?= esc($c['label'], 'attr') ?>" data-px-lightbox>
          <img class="px-lightbox__img" src="<?= esc($c['poster'], 'attr') ?>" alt="Poster kampanye pasangan <?= esc($c['label'], 'attr') ?>" loading="lazy" decoding="async">
          <button type="button" class="px-lightbox__close" data-px-close><?= icon('close') ?><span class="visually-hidden">Tutup poster</span></button>
        </dialog>
      </section>
    <?php endif; ?>

    <?php /* ------------------------------------------------ pesan dari kami */ ?>
    <?php if ($hasPesan): ?>
      <section class="px-section px-pesan" id="pesan" aria-labelledby="<?= esc($id('pesan'), 'attr') ?>" data-px-section>
        <?= $head('pesan', 'message', 'Pesan dari kami', 'Dengarkan langsung pesan kami untuk seluruh warga SMP 1 DAWE.') ?>
        <div class="px-quotes">
          <?php foreach ($people as $key => $p): ?>
            <?php if ($p['pesan'] !== ''): ?>
              <figure class="px-quote" data-px-reveal>
                <?= view('paslon/partials/avatar', ['c' => $c, 'role' => $key]) ?>
                <blockquote class="px-quote__text"><p>&ldquo;<?= esc($p['pesan']) ?>&rdquo;</p></blockquote>
                <figcaption class="px-quote__who"><strong><?= esc($p['name']) ?></strong><span><?= esc($p['long']) ?></span></figcaption>
              </figure>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>

    <?php /* ------------------------------------------------ video kampanye */ ?>
    <?php if ($c['video'] !== null): ?>
      <section class="px-section px-media" id="video" aria-labelledby="<?= esc($id('video'), 'attr') ?>" data-px-section>
        <?= $head('video', 'video', 'Video kampanye', 'Lihat lebih dekat perjalanan kami.') ?>
        <div class="px-video" data-px-reveal data-px-video
             data-embed="<?= esc($c['video_embed'], 'attr') ?>"
             data-title="Video kampanye pasangan <?= esc($c['label'], 'attr') ?>">
          <div class="px-video__frame" data-px-video-frame>
            <a class="px-video__facade" href="<?= esc($c['video_url'], 'attr') ?>" target="_blank" rel="noopener noreferrer" data-px-play>
              <img class="px-video__thumb" src="<?= esc($c['video_thumb'], 'attr') ?>" data-hd="<?= esc($c['video_thumb_hd'], 'attr') ?>" alt="" loading="lazy" decoding="async" width="480" height="360">
              <span class="px-video__play" aria-hidden="true"><?= icon('play') ?></span>
              <span class="visually-hidden">Putar video kampanye pasangan <?= esc($c['label']) ?></span>
            </a>
          </div>
          <p class="px-video__bar">
            <span>Video kampanye pasangan <?= esc($c['label']) ?></span>
            <a class="px-btn px-btn--quiet" href="<?= esc($c['video_url'], 'attr') ?>" target="_blank" rel="noopener noreferrer"><?= icon('external') ?> Buka di YouTube</a>
          </p>
        </div>
      </section>
    <?php endif; ?>
  </div>

  <?php /* ------------------------------------------------------- penutup */ ?>
  <section class="px-close<?= $c['closing'] !== null ? ' px-close--image' : '' ?>" aria-labelledby="<?= esc($id('close'), 'attr') ?>">
    <?php if ($c['closing'] !== null): ?>
      <img class="px-close__bg" src="<?= esc($c['closing'], 'attr') ?>" alt="" loading="lazy" decoding="async">
    <?php endif; ?>
    <div class="px-close__inner">
      <h2 class="visually-hidden" id="<?= esc($id('close'), 'attr') ?>">Penutup profil pasangan <?= esc($c['label']) ?></h2>

      <div class="px-finale" data-px-reveal>
        <p class="px-finale__no">Paslon <strong><?= esc($c['label']) ?></strong></p>
        <p class="px-finale__names">
          <?php $i = 0; foreach ($people as $key => $p): ?>
            <?php if ($i++ === 1): ?>
              <span class="px-finale__x" aria-hidden="true">&times;</span><span class="visually-hidden"> dan </span>
            <?php endif; ?>
            <span class="px-finale__person">
              <strong><?= esc($p['name']) ?></strong>
              <span class="px-finale__role"><span class="visually-hidden">, </span><?= esc($p['long']) ?></span>
            </span>
          <?php endforeach; ?>
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
