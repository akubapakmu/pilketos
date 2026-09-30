<?php
/**
 * Foto pasangan (Stage 17): foto ketua dan wakil berdampingan dalam SATU kotak
 * persegi, pengganti kotak nomor pasangan di hasil per pasangan (Beranda &
 * Analitik) dan kartu Pasangan calon.
 *
 * Saat kotak diarahkan kursor (hover), nomor pasangan muncul di atas foto:
 * lapisan warna aksen semi transparan, jadi foto masih sedikit terlihat.
 * Perangkat tanpa hover (HP) menampilkan nomor kecil di pojok. Foto yang belum
 * diunggah diganti inisial di atas warna aksen.
 *
 * Dekoratif (aria-hidden): nomor & nama pasangan selalu tertulis di dekatnya
 * untuk pembaca layar. Struktur yang sama dibangun admin-live.js (buildThumb)
 * saat daftar pasangan berubah.
 *
 * @var array<string, mixed> $pair  label, ketua, wakil, photo_ketua, photo_wakil,
 *                                  ketua_initials, wakil_initials
 * @var string               $class Kelas tambahan (mis. "result__thumb")
 */
$class = trim('pair-thumb ' . ($class ?? ''));
?>
<span class="<?= esc($class) ?>" aria-hidden="true">
  <?php foreach ([['ketua', $pair['photo_ketua'] ?? null, $pair['ketua_initials'] ?? '?'], ['wakil', $pair['photo_wakil'] ?? null, $pair['wakil_initials'] ?? '?']] as [$role, $photo, $initials]): ?>
    <span class="pair-thumb__half pair-thumb__half--<?= $role ?>">
      <?php if ($photo !== null): ?>
        <img src="<?= esc($photo, 'attr') ?>" alt="" width="120" height="240" loading="lazy" decoding="async">
      <?php else: ?>
        <span class="pair-thumb__mono"><?= esc($initials) ?></span>
      <?php endif; ?>
    </span>
  <?php endforeach; ?>
  <span class="pair-thumb__no"><?= esc($pair['label']) ?></span>
</span>
