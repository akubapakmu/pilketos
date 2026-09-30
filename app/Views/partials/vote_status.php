<?php
/**
 * Kartu status hak suara di dasbor siswa/guru.
 * Sudah memilih: hanya pilihan sendiri + status terkunci, tanpa CTA voting.
 *
 * @var \App\Services\VoterType $type
 * @var array|null              $election
 * @var array|null              $vote      Suara LOCKED milik pemilih ini
 * @var array|null              $candidate Hasil CandidateTheme::present() untuk kandidat yang dipilih
 * @var bool                    $canVote
 */
$status = $election['status'] ?? null;
?>
<section class="vote-card <?= $vote !== null ? 'vote-card--locked' : 'vote-card--open' ?>"
  <?php if ($candidate !== null): ?>style="<?= esc($candidate['style'], 'attr') ?>"<?php endif; ?>
  aria-labelledby="vote-card-title">
  <p class="vote-card__eyebrow">Status hak suara</p>

  <?php if ($vote !== null && $candidate !== null): ?>
    <h2 class="vote-card__title" id="vote-card-title"><?= icon('lock') ?> Sudah memilih &middot; Terkunci</h2>
    <div class="vote-card__choice">
      <span class="vote-card__no" aria-hidden="true"><?= esc($candidate['label']) ?></span>
      <p>
        <span class="vote-card__pair">Pasangan <?= esc($candidate['label']) ?></span>
        <strong><?= esc($candidate['ketua']) ?> &amp; <?= esc($candidate['wakil']) ?></strong>
      </p>
    </div>
    <p class="vote-card__meta">Dicoblos pada <time datetime="<?= esc($vote['voted_at'], 'attr') ?>"><?= esc(format_waktu($vote['voted_at'])) ?></time></p>
    <a class="btn btn--outline" href="<?= base_url($type->path('pilihanku')) ?>">Lihat pilihan saya</a>
  <?php elseif ($canVote): ?>
    <h2 class="vote-card__title" id="vote-card-title">Belum memilih</h2>
    <p class="vote-card__text">Surat suara sudah dibuka. Kenali semua pasangan calon, lalu coblos satu pasangan. Setelah dikonfirmasi, pilihan dikunci.</p>
    <a class="btn btn--lg" href="<?= base_url($type->path('coblos')) ?>">Lihat kandidat &amp; coblos <?= icon('arrow-right') ?></a>
  <?php elseif ($status === 'UPCOMING'): ?>
    <h2 class="vote-card__title" id="vote-card-title">Belum memilih</h2>
    <p class="vote-card__text">Pencoblosan dibuka pada <strong><?= esc(format_waktu($election['start_at'])) ?></strong>. Sambil menunggu, kenali semua pasangan calon.</p>
    <a class="btn btn--outline" href="<?= base_url($type->path('coblos')) ?>">Lihat kandidat</a>
  <?php elseif ($status === 'FINISHED'): ?>
    <h2 class="vote-card__title" id="vote-card-title">Tidak memberikan suara</h2>
    <p class="vote-card__text">Pemilihan telah ditutup pada <?= esc(format_waktu($election['end_at'])) ?>. Hak suara ini tidak digunakan.</p>
  <?php else: ?>
    <h2 class="vote-card__title" id="vote-card-title">Belum memilih</h2>
    <p class="vote-card__text">Jadwal pemilihan belum tersedia.</p>
  <?php endif; ?>
</section>
