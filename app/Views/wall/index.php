<?= $this->extend('layouts/main') ?>

<?php
/**
 * Living Election Wall (Stage 26): GET partisipasi.
 *
 * Halaman gelap tanpa sidebar: header sticky (partials/wall_nav), blok
 * partisipasi, toolbar sticky (cari nama, filter Kelas, Urutkan, jumlah hasil,
 * Buka/Tutup semua), lalu bagian per rombel (<details open>, dikelompokkan
 * per jenjang) dan bagian Guru. Kartu = avatar bulat + nama; yang sudah
 * berpartisipasi diberi lencana centang (teks tersembunyi untuk pembaca
 * layar). Tidak ada warna paslon, NISN/NIP, waktu, maupun pilihan.
 *
 * Tanpa JavaScript halaman tetap terbaca: kartu dirender server, avatar
 * DiceBear tampil sebagai inisial, toolbar = formulir GET yang disaring
 * server. wall.js menyaring di tempat (kartu disembunyikan, galeri utuh),
 * menyimpan filter di URL, dan memperbarui status tiap 20 detik.
 *
 * @var array{status: string|null, label: string, notice: string, totals: array, grades: list<array>, poll: array{interval: int}} $wall    ParticipationWall::page()
 * @var array{kelas: string, q: string, urut: string}                                                                             $filters ParticipationWall::filters()
 * @var array{total: int, voted: int, not_voted: int, percent: float, noun: string}                                               $scope   ParticipationWall::scope()
 */
use App\Services\ParticipationWall;

$visibleTotal = 0;
$hasTeachers  = false;
$hasVoters    = $wall['grades'] !== [];

// Kartu tampil / bagian tampil dihitung server agar tanpa JavaScript pun
// filter dari URL berlaku (wall.js memakai aturan yang sama).
foreach ($wall['grades'] as $g => $grade) {
    $gradeVisible = 0;

    foreach ($grade['groups'] as $i => $group) {
        $hasTeachers = $hasTeachers || $group['type'] === 'teacher';
        $shown       = 0;

        // index = urutan nama (kunci kedua urutan lain di wall.js)
        foreach ($group['members'] as $m => $member) {
            $group['members'][$m]['index'] = $m;
        }

        $members = ParticipationWall::sort($group['members'], $filters['urut']);

        foreach ($members as $m => $member) {
            $members[$m]['visible'] = ParticipationWall::visible($member, $group, $filters);
            $shown += $members[$m]['visible'] ? 1 : 0;
        }

        $wall['grades'][$g]['groups'][$i]['members'] = $members;
        $wall['grades'][$g]['groups'][$i]['shown']   = $shown;
        $gradeVisible += $shown;
    }

    $wall['grades'][$g]['shown'] = $gradeVisible;
    $visibleTotal += $gradeVisible;
}

$percentText = static fn (int $part, int $whole): string => number_format($whole > 0 ? round($part / $whole * 100, 1) : 0, 1, ',', '.') . '%';
$ratio       = static fn (int $part, int $whole): string => $whole > 0 ? rtrim(rtrim(number_format($part / $whole, 4, '.', ''), '0'), '.') : '0';
?>

<?= $this->section('head') ?>
<link rel="preload" href="<?= base_url('assets/fonts/jetbrains-mono-latin-wght-normal.woff2') ?>" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= asset_url('assets/css/avatar.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/wall.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="wall" data-wall
     data-status-url="<?= esc(site_url('partisipasi/status'), 'attr') ?>"
     data-poll="<?= (int) $wall['poll']['interval'] ?>"
     data-status="<?= esc((string) $wall['status'], 'attr') ?>"
     data-totals="<?= esc(json_encode($wall['totals']), 'attr') ?>"
     data-art-module="<?= esc(asset_url('assets/js/avatar-art.js'), 'attr') ?>">

  <section class="wall-hero" aria-labelledby="wall-title">
    <h1 class="wall-kicker" id="wall-title"><span class="visually-hidden">Living Election Wall: </span>Partisipasi pemilihan</h1>
    <p class="wall-count">
      <span class="wall-count__num" data-scope-voted><?= angka($scope['voted']) ?></span>
      <span class="wall-count__of">dari <span data-scope-total><?= angka($scope['total']) ?></span> <span data-scope-noun><?= esc($scope['noun']) ?></span></span>
    </p>
    <div class="wall-meter">
      <span class="wall-meter__track" aria-hidden="true"><span class="wall-meter__fill" data-scope-bar style="--p: <?= $ratio($scope['voted'], $scope['total']) ?>"></span></span>
      <span class="wall-meter__pct" data-scope-percent><?= $percentText($scope['voted'], $scope['total']) ?></span>
    </div>
    <ul class="wall-split">
      <li><span class="wall-split__num" data-scope-yes><?= angka($scope['voted']) ?></span> sudah berpartisipasi</li>
      <li><span class="wall-split__num" data-scope-no><?= angka($scope['not_voted']) ?></span> belum berpartisipasi</li>
    </ul>
    <p class="wall-note">Halaman ini hanya menandai partisipasi. Pilihan setiap pemilih tetap rahasia.</p>
    <p class="wall-state wall-state--<?= esc(strtolower((string) ($wall['status'] ?? 'none')), 'attr') ?>" data-wall-state>
      <span class="wall-state__dot" aria-hidden="true"></span><span data-wall-notice><?= esc($wall['notice']) ?></span>
    </p>
  </section>

  <form class="wall-tools" action="<?= site_url('partisipasi') ?>" method="get" role="search" aria-label="Cari dan saring pemilih" data-wall-tools>
    <div class="wall-tools__inner">
      <label class="wall-search">
        <?= icon('search', 'wall-search__icon') ?>
        <span class="visually-hidden">Cari nama</span>
        <input class="wall-search__input" type="search" name="q" value="<?= esc($filters['q'], 'attr') ?>" placeholder="Cari nama" autocomplete="off" spellcheck="false" maxlength="60" data-wall-q>
        <span class="wall-search__count" aria-live="polite" data-wall-count><?= $visibleTotal === 0 ? 'Tidak ada nama yang cocok' : angka($visibleTotal) . ' nama' ?></span>
      </label>
      <label class="wall-select wall-select--kelas">
        <span class="wall-select__label">Kelas</span>
        <select class="wall-select__input" name="kelas" data-wall-kelas>
          <option value=""<?= $filters['kelas'] === '' ? ' selected' : '' ?>>Semua</option>
          <?php if ($wall['totals']['students']['total'] > 0): ?>
            <option value="<?= ParticipationWall::SCOPE_STUDENTS ?>"<?= $filters['kelas'] === ParticipationWall::SCOPE_STUDENTS ? ' selected' : '' ?>>Siswa</option>
          <?php endif; ?>
          <?php foreach ($wall['grades'] as $grade): ?>
            <?php if ($grade['key'] === 'guru') { continue; } ?>
            <optgroup label="<?= esc($grade['label'], 'attr') ?>">
              <?php foreach ($grade['groups'] as $group): ?>
                <option value="<?= esc($group['key'], 'attr') ?>"<?= $filters['kelas'] === (string) $group['key'] ? ' selected' : '' ?>><?= esc($group['label']) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
          <?php if ($hasTeachers): ?>
            <option value="<?= ParticipationWall::SCOPE_TEACHERS ?>"<?= $filters['kelas'] === ParticipationWall::SCOPE_TEACHERS ? ' selected' : '' ?>>Guru</option>
          <?php endif; ?>
        </select>
      </label>
      <label class="wall-select wall-select--urut">
        <span class="wall-select__label">Urutkan</span>
        <select class="wall-select__input" name="urut" data-wall-sort>
          <?php foreach (ParticipationWall::SORTS as $value => $label): ?>
            <option value="<?= $value ?>"<?= $filters['urut'] === $value ? ' selected' : '' ?>><?= esc($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button type="submit" class="wall-tools__apply">Terapkan</button>
      <button type="button" class="wall-tools__toggle" data-wall-toggle hidden><?= icon('chevron-down', 'wall-tools__toggle-icon') ?><span data-wall-toggle-label>Tutup semua</span></button>
    </div>
  </form>

  <div class="wall-sections" data-wall-sections>
    <?php if (! $hasVoters): ?>
      <p class="wall-empty">Belum ada data pemilih.</p>
    <?php endif; ?>

    <?php foreach ($wall['grades'] as $grade): ?>
      <section class="wall-grade" data-grade="<?= esc($grade['key'], 'attr') ?>" aria-labelledby="wall-grade-<?= esc($grade['key'], 'attr') ?>"<?= $grade['shown'] === 0 ? ' hidden' : '' ?>>
        <h2 class="wall-grade__title" id="wall-grade-<?= esc($grade['key'], 'attr') ?>">
          <span><?= esc($grade['label']) ?></span>
          <?php if ($grade['key'] !== 'guru'): ?><span class="wall-grade__meta"><?= count($grade['groups']) ?> rombel</span><?php endif; ?>
        </h2>

        <?php foreach ($grade['groups'] as $group): ?>
          <?php $groupId = 'wall-' . strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $group['type'] . '-' . $group['key'])); ?>
          <details class="wall-group" id="<?= esc($groupId, 'attr') ?>" data-group="<?= esc($group['key'], 'attr') ?>" data-type="<?= esc($group['type'], 'attr') ?>" style="--n: <?= count($group['members']) ?>" open<?= $group['shown'] === 0 ? ' hidden' : '' ?>>
            <summary class="wall-group__head">
              <span class="wall-group__names">
                <span class="wall-group__kicker"><?= esc($group['kicker']) ?></span>
                <h3 class="wall-group__title"><?= esc($group['label']) ?></h3>
              </span>
              <span class="wall-group__tally">
                <span class="wall-group__count"><span data-group-voted><?= angka($group['voted']) ?></span>/<span data-group-total><?= angka($group['total']) ?></span><span class="visually-hidden"> sudah berpartisipasi</span></span>
                <span class="wall-group__bar" aria-hidden="true"><span class="wall-group__fill" data-group-bar style="--p: <?= $ratio($group['voted'], $group['total']) ?>"></span></span>
              </span>
              <?= icon('chevron-down', 'wall-group__chevron') ?>
            </summary>

            <ul class="wall-grid" role="list">
              <?php foreach ($group['members'] as $member): ?>
                <li class="wall-card<?= $member['voted'] ? ' is-voted' : '' ?>" data-key="<?= esc($member['key'], 'attr') ?>" data-name="<?= esc($member['search']) ?>" data-index="<?= $member['index'] ?>"<?= $member['visible'] ? '' : ' hidden' ?>>
                  <span class="wall-card__figure">
                    <?= view('partials/avatar', ['avatar' => $member['avatar'], 'class' => 'wall-card__avatar']) ?>
                    <span class="wall-card__badge" aria-hidden="true"><?= icon('check') ?></span>
                  </span>
                  <span class="wall-card__name"><?= esc($member['name']) ?></span>
                  <span class="visually-hidden" data-card-state><?= $member['voted'] ? 'Sudah berpartisipasi' : 'Belum berpartisipasi' ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          </details>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>

    <p class="wall-empty" data-wall-empty<?= $hasVoters && $visibleTotal === 0 ? '' : ' hidden' ?>>Tidak ada nama yang cocok dengan pencarian ini.</p>
  </div>

  <p class="wall-credit">Ilustrasi avatar: DiceBear &mdash; Adventurer oleh Lisa Wischofsky dan Big Smile oleh Ashley Seo (CC BY 4.0), Avataaars oleh Pablo Stanley.</p>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script type="module" src="<?= asset_url('assets/js/wall.js') ?>"></script>
<?= $this->endSection() ?>
