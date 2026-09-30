<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>
<?php
/**
 * Form tambah/ubah pasangan calon + tema + unggah asset.
 *
 * @var array|null            $candidate Baris kandidat (null = baru)
 * @var array                 $values    Nilai form (baris / input lama)
 * @var array|null            $theme     CandidateTheme::present() kandidat tersimpan
 * @var array<string, string> $files     Asset tersimpan per slot
 * @var array<string, string> $errors
 * @var int                   $postMax   post_max_size (byte)
 * @var array|null            $election
 * @var bool                  $resultsLocked Stage 4: pemilihan selesai
 * @var int                   $batas         Stage 15: batas pasangan aktif
 * @var int                   $activeOthers  Stage 15: pasangan aktif selain pasangan ini
 */
use App\Libraries\CandidateAssets;
use App\Libraries\CandidateTheme;
use App\Models\CandidateModel;

$value  = static fn (string $key): string => esc((string) ($values[$key] ?? ''), 'attr');
$error  = static fn (string $key): string => isset($errors[$key]) ? '<p class="field-error" id="err-' . esc($key, 'attr') . '">' . esc($errors[$key]) . '</p>' : '';
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="err-' . esc($key, 'attr') . '"' : '';
$action = $candidate === null ? site_url('admin/paslon') : site_url('admin/paslon/' . $candidate['id']);
$accent = CandidateTheme::accent($values['theme_accent'] ?? null);
$layout = (string) ($values['theme_layout'] ?? '');
$active = (string) ($values['status_aktif'] ?? '1') === '1';
// Stage 15: ID YouTube tersimpan ditampilkan kembali sebagai tautan pendek.
$video  = (string) ($values['video_youtube'] ?? '');
$video  = preg_match('/^[A-Za-z0-9_-]{11}$/', $video) === 1 ? 'https://youtu.be/' . $video : $video;
$locked = ($resultsLocked ?? false) && $candidate !== null; // Stage 4: hasil akhir final
// Stage 15: batas pasangan aktif penuh (pasangan yang sudah aktif tetap boleh diubah).
$full   = ! $locked && $activeOthers >= $batas && ! ($candidate !== null && (int) $candidate['status_aktif'] === 1);
$layouts = [
    ''       => ['Otomatis', 'Mengikuti nomor urut: 01 split, 02 poster, 03 kolom.'],
    'split'  => ['Split', 'Panggung & teks berdampingan, pola grid.'],
    'poster' => ['Poster', 'Blok warna penuh ala poster, pola arsir.'],
    'column' => ['Kolom', 'Potret bertumpuk tegak, pola titik.'],
];
?>
<div class="admin-page">
  <header class="admin-head">
    <div class="admin-head__text">
      <div class="admin-head__heading">
        <h1 class="admin-head__title"><?= $candidate === null ? 'Tambah pasangan' : esc(sprintf('Pasangan %02d', $candidate['nomor_urut'])) ?></h1>
      </div>
    </div>
    <?php if ($candidate !== null): ?>
      <div class="admin-head__actions">
        <a class="btn btn--outline" href="<?= site_url('admin/paslon/' . $candidate['id'] . '/intip') ?>"><?= icon('eye') ?> Pratinjau halaman pemilih</a>
      </div>
    <?php endif; ?>
  </header>

  <?php if (($election['status'] ?? null) === 'ONGOING'): ?>
    <p class="notice"><?= icon('alert') ?><span>Pemilihan sedang berlangsung. Perubahan langsung terlihat oleh pemilih; bila pasangan dinonaktifkan, pemilih yang sedang membuka surat suara akan diminta memilih ulang.</span></p>
  <?php elseif ($locked): ?>
    <p class="notice"><?= icon('lock') ?><span>Pemilihan sudah selesai: nomor urut dan status aktif dikunci agar susunan hasil akhir tidak berubah. Nama, visi-misi, dan foto tetap dapat dirapikan (tercatat di audit log).</span></p>
  <?php endif; ?>

  <form class="form-x" action="<?= $action ?>" method="post" enctype="multipart/form-data" novalidate data-upload-form data-post-max="<?= (int) $postMax ?>">
    <?= csrf_field() ?>

    <fieldset class="form-x__part">
      <legend><span class="form-x__no">01</span> Identitas pasangan</legend>
      <div class="form-grid">
        <div class="field field--short">
          <label for="nomor_urut">Nomor urut</label>
          <input type="number" id="nomor_urut" name="nomor_urut" min="1" max="99" inputmode="numeric" required value="<?= $value('nomor_urut') ?>"<?= $invalid('nomor_urut') ?><?= $locked ? ' readonly aria-describedby="locked-hint"' : '' ?>>
          <?= $error('nomor_urut') ?>
        </div>
        <div class="field field--switch">
          <input type="hidden" name="status_aktif" value="0">
          <label class="switch">
            <input type="checkbox" name="status_aktif" value="1"<?= $active ? ' checked' : '' ?><?= $locked ? ' disabled' : '' ?>>
            <span class="switch__track" aria-hidden="true"></span>
            <span class="switch__label">Aktif di surat suara</span>
          </label>
          <p class="field-hint" id="locked-hint"><?= $locked
              ? 'Dikunci setelah pemilihan selesai: nomor urut dan status aktif tidak dapat diubah.'
              : 'Pasangan nonaktif tidak tampil dan tidak dapat dipilih. Suara yang sudah masuk tetap tersimpan.' ?></p>
          <?php if ($full): ?>
            <p class="field-hint"><?= icon('info') ?> Batas <?= $batas ?> pasangan aktif sudah penuh. Simpan sebagai nonaktif, atau naikkan batas di halaman <a href="<?= site_url('admin/paslon') ?>">Pasangan calon</a>.</p>
          <?php endif; ?>
          <?= $error('status_aktif') ?>
        </div>
        <div class="field">
          <label for="nama_ketua">Nama calon ketua</label>
          <input type="text" id="nama_ketua" name="nama_ketua" maxlength="150" required value="<?= $value('nama_ketua') ?>"<?= $invalid('nama_ketua') ?>>
          <?= $error('nama_ketua') ?>
        </div>
        <div class="field">
          <label for="nama_wakil">Nama calon wakil</label>
          <input type="text" id="nama_wakil" name="nama_wakil" maxlength="150" required value="<?= $value('nama_wakil') ?>"<?= $invalid('nama_wakil') ?>>
          <?= $error('nama_wakil') ?>
        </div>
      </div>
    </fieldset>

    <fieldset class="form-x__part">
      <legend><span class="form-x__no">02</span> Visi &amp; misi</legend>
      <div class="form-grid form-grid--wide">
        <div class="field">
          <label for="visi">Visi</label>
          <textarea id="visi" name="visi" rows="3" maxlength="2000"<?= $invalid('visi') ?>><?= esc((string) ($values['visi'] ?? '')) ?></textarea>
          <p class="field-hint">Satu kalimat utama. Di halaman pemilih tampil sebagai tipografi besar yang menyala mengikuti scroll.</p>
          <?= $error('visi') ?>
        </div>
        <div class="field">
          <label for="misi">Misi</label>
          <textarea id="misi" name="misi" rows="6" maxlength="5000"<?= $invalid('misi') ?>><?= esc((string) ($values['misi'] ?? '')) ?></textarea>
          <p class="field-hint">Satu poin per baris. Penomoran seperti "1." atau "-" dibuang otomatis dan diganti nomor editorial.</p>
          <?= $error('misi') ?>
        </div>
      </div>
    </fieldset>

    <fieldset class="form-x__part">
      <legend><span class="form-x__no">03</span> Profil &amp; kampanye</legend>
      <p class="form-x__intro">Tampil di halaman profil paslon. Semua opsional: bagian yang kosong tidak ditampilkan ke pemilih.</p>
      <div class="form-grid">
        <div class="field form-grid__full">
          <label for="slogan">Slogan pasangan</label>
          <input type="text" id="slogan" name="slogan" maxlength="160" value="<?= $value('slogan') ?>" placeholder="mis. Bersama berkarya, bersama berprestasi"<?= $invalid('slogan') ?>>
          <p class="field-hint">Satu kalimat pendek, tampil besar di pembuka dan penutup halaman profil.</p>
          <?= $error('slogan') ?>
        </div>
        <div class="field">
          <label for="bio_ketua">Bio calon ketua</label>
          <textarea id="bio_ketua" name="bio_ketua" rows="4" maxlength="600"<?= $invalid('bio_ketua') ?>><?= esc((string) ($values['bio_ketua'] ?? '')) ?></textarea>
          <p class="field-hint">2&ndash;4 kalimat perkenalan.</p>
          <?= $error('bio_ketua') ?>
        </div>
        <div class="field">
          <label for="bio_wakil">Bio calon wakil</label>
          <textarea id="bio_wakil" name="bio_wakil" rows="4" maxlength="600"<?= $invalid('bio_wakil') ?>><?= esc((string) ($values['bio_wakil'] ?? '')) ?></textarea>
          <p class="field-hint">2&ndash;4 kalimat perkenalan.</p>
          <?= $error('bio_wakil') ?>
        </div>
        <div class="field">
          <label for="karakter_ketua">Kata karakter calon ketua</label>
          <input type="text" id="karakter_ketua" name="karakter_ketua" maxlength="120" value="<?= $value('karakter_ketua') ?>" placeholder="mis. Kreatif, Aktif, Bertanggung jawab"<?= $invalid('karakter_ketua') ?>>
          <p class="field-hint">Pisahkan dengan koma; yang tampil maksimal 3 kata.</p>
          <?= $error('karakter_ketua') ?>
        </div>
        <div class="field">
          <label for="karakter_wakil">Kata karakter calon wakil</label>
          <input type="text" id="karakter_wakil" name="karakter_wakil" maxlength="120" value="<?= $value('karakter_wakil') ?>" placeholder="mis. Teliti, Ramah, Peduli"<?= $invalid('karakter_wakil') ?>>
          <p class="field-hint">Pisahkan dengan koma; yang tampil maksimal 3 kata.</p>
          <?= $error('karakter_wakil') ?>
        </div>
        <div class="field form-grid__full">
          <label for="program">Program unggulan</label>
          <textarea id="program" name="program" rows="5" maxlength="3000" placeholder="Student Voice — Wadah siswa menyampaikan aspirasi dan ide."<?= $invalid('program') ?>><?= esc((string) ($values['program'] ?? '')) ?></textarea>
          <p class="field-hint">Satu program per baris: judul, tanda &ldquo;&mdash;&rdquo; atau &ldquo;:&rdquo;, lalu penjelasan singkat. Penomoran dibuang otomatis.</p>
          <?= $error('program') ?>
        </div>
        <div class="field">
          <label for="pesan_ketua">Pesan calon ketua</label>
          <textarea id="pesan_ketua" name="pesan_ketua" rows="3" maxlength="300"<?= $invalid('pesan_ketua') ?>><?= esc((string) ($values['pesan_ketua'] ?? '')) ?></textarea>
          <p class="field-hint">Kutipan singkat untuk pemilih, maksimal 300 karakter.</p>
          <?= $error('pesan_ketua') ?>
        </div>
        <div class="field">
          <label for="pesan_wakil">Pesan calon wakil</label>
          <textarea id="pesan_wakil" name="pesan_wakil" rows="3" maxlength="300"<?= $invalid('pesan_wakil') ?>><?= esc((string) ($values['pesan_wakil'] ?? '')) ?></textarea>
          <p class="field-hint">Kutipan singkat untuk pemilih, maksimal 300 karakter.</p>
          <?= $error('pesan_wakil') ?>
        </div>
        <div class="field form-grid__full">
          <label for="video_youtube">Video kampanye (YouTube)</label>
          <input type="url" id="video_youtube" name="video_youtube" maxlength="200" value="<?= esc($video, 'attr') ?>" placeholder="https://youtu.be/..." autocomplete="off" spellcheck="false"<?= $invalid('video_youtube') ?>>
          <p class="field-hint">Tempel tautan video YouTube (youtube.com/watch, youtu.be, atau shorts). Video baru dimuat setelah pemilih menekan tombol putar.</p>
          <?= $error('video_youtube') ?>
        </div>
      </div>
    </fieldset>

    <fieldset class="form-x__part">
      <legend><span class="form-x__no">04</span> Tema visual</legend>
      <div class="form-grid">
        <div class="field">
          <label for="theme_name">Nama tema</label>
          <input type="text" id="theme_name" name="theme_name" maxlength="100" value="<?= $value('theme_name') ?>" placeholder="mis. Terracotta"<?= $invalid('theme_name') ?>>
          <?= $error('theme_name') ?>
        </div>
        <div class="field">
          <label for="theme_accent">Warna aksen (solid)</label>
          <div class="accent-field">
            <input type="color" value="<?= esc($accent, 'attr') ?>" aria-label="Pilih warna aksen" data-accent-picker>
            <input type="text" id="theme_accent" name="theme_accent" maxlength="7" value="<?= $value('theme_accent') ?>" placeholder="#C4432B" autocomplete="off" spellcheck="false" data-accent-input<?= $invalid('theme_accent') ?>>
          </div>
          <p class="field-hint">Format #RRGGBB. Tanpa gradient; warna teks di atas aksen dipilih otomatis agar kontras.</p>
          <?= $error('theme_accent') ?>
        </div>
      </div>

      <div class="accent-preview" style="<?= esc(CandidateTheme::style(['accent' => $accent, 'accent_ink' => CandidateTheme::inkOn($accent), 'accent_text' => CandidateTheme::textOnPaper($accent)]), 'attr') ?>" data-accent-preview aria-hidden="true">
        <span class="accent-preview__block"><?= esc(sprintf('%02d', (int) ($values['nomor_urut'] ?? 0))) ?></span>
        <span class="accent-preview__text">
          <strong><?= esc((string) ($values['nama_ketua'] ?? 'Nama ketua')) ?></strong>
          <span>Contoh warna aksen pada kertas</span>
        </span>
      </div>

      <div class="field">
        <span class="field__label" id="layout-label">Layout halaman kandidat</span>
        <div class="layout-pick" role="radiogroup" aria-labelledby="layout-label">
          <?php foreach ($layouts as $key => [$name, $desc]): ?>
            <label class="layout-pick__item">
              <input type="radio" name="theme_layout" value="<?= esc($key, 'attr') ?>"<?= $layout === $key ? ' checked' : '' ?>>
              <span class="layout-pick__art layout-pick__art--<?= esc($key === '' ? 'auto' : $key, 'attr') ?>" aria-hidden="true"><i></i><i></i><i></i></span>
              <span class="layout-pick__name"><?= esc($name) ?></span>
              <span class="layout-pick__desc"><?= esc($desc) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <?= $error('theme_layout') ?>
      </div>
    </fieldset>

    <fieldset class="form-x__part">
      <legend><span class="form-x__no">05</span> Foto &amp; asset tema</legend>
      <p class="form-x__intro">
        JPG, PNG, atau WebP. Setiap gambar diperiksa (tipe isi file, ukuran, dimensi) lalu diolah ulang oleh server:
        metadata foto (termasuk lokasi) dibuang, orientasi diluruskan, dan resolusi disesuaikan agar ringan di HP.
        Total unggahan sekali simpan maksimal <?= esc(CandidateAssets::formatBytes($postMax)) ?>.
      </p>

      <div class="slots">
        <?php foreach (CandidateAssets::SLOTS as $slot => $config): ?>
          <?php
            $field   = 'asset_' . $slot;
            $current = $files[$slot] ?? null;
            $max     = CandidateAssets::maxBytes($slot);
          ?>
          <div class="slot<?= isset($errors[$field]) ? ' slot--error' : '' ?>" data-slot>
            <div class="slot__preview">
              <?php if ($current !== null): ?>
                <img src="<?= esc(CandidateModel::assetUrl($current), 'attr') ?>" alt="<?= esc($config['label'] . ' saat ini', 'attr') ?>" loading="lazy" data-slot-current>
              <?php else: ?>
                <span class="slot__empty" data-slot-current><?= icon('image') ?><span>Belum ada</span></span>
              <?php endif; ?>
              <img class="slot__new" alt="" hidden data-file-preview>
            </div>
            <div class="slot__body">
              <label class="slot__label" for="<?= esc($field, 'attr') ?>"><?= esc($config['label']) ?></label>
              <p class="slot__hint" id="hint-<?= esc($field, 'attr') ?>">
                <?= esc($config['hint']) ?> Maks <?= esc(CandidateAssets::formatBytes($max)) ?>.
              </p>
              <input type="file" id="<?= esc($field, 'attr') ?>" name="<?= esc($field, 'attr') ?>"
                     accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                     data-max-bytes="<?= $max ?>" data-file-input
                     aria-describedby="hint-<?= esc($field, 'attr') ?><?= isset($errors[$field]) ? ' err-' . esc($field, 'attr') : '' ?>">
              <?php if ($current !== null): ?>
                <label class="check">
                  <input type="checkbox" name="remove[]" value="<?= esc($slot, 'attr') ?>">
                  <span>Hapus gambar ini</span>
                </label>
              <?php endif; ?>
              <?= $error($field) ?>
              <p class="field-error" data-file-error hidden></p>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </fieldset>

    <div class="form-x__submit">
      <button type="submit" class="btn btn--lg" data-loading-text="Menyimpan &amp; memproses gambar..."><?= icon('check') ?> <?= $candidate === null ? 'Simpan pasangan' : 'Simpan perubahan' ?></button>
      <a class="btn btn--lg btn--outline" href="<?= site_url('admin/paslon') ?>">Batal</a>
      <p class="field-error" data-upload-total-error hidden></p>
    </div>
  </form>
</div>
<?= $this->endSection() ?>

<?= $this->section('crumbs') ?>
<?= view('admin/partials/crumbs', ['trail' => [['Pasangan calon', 'admin/paslon'], [$candidate === null ? 'Baru' : 'Ubah']]]) ?>
<?= $this->endSection() ?>
