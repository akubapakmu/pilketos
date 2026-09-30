<?= $this->extend('layouts/main') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= asset_url('assets/css/voting.css') ?>">
<link rel="stylesheet" href="<?= asset_url('assets/css/paslon.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
/**
 * Profil lengkap satu paslon untuk pemilih (Stage 15, siswa|guru/paslon/0X).
 * Isi halaman: paslon/partials/profile (sama dengan pratinjau admin).
 *
 * Tombol penutup mengikuti keadaan pemilih:
 * - pencoblosan dibuka: "Pilih Paslon 0X" -> kotaknya di surat suara
 *   (coblos#coblos-0X) + "Kembali ke daftar paslon" (coblos#sekilas);
 * - sudah memilih: "Lihat pilihanku";
 * - belum dibuka / sudah ditutup: "Kembali ke daftar paslon".
 *
 * @var \App\Services\VoterType $type
 * @var array                   $c       CandidateTheme::present()
 * @var list<array>             $lineup  Semua paslon aktif (CandidateTheme::presentAll())
 * @var bool                    $canVote
 * @var bool                    $voted
 */
$booth = site_url($type->path('coblos'));
$list  = ['label' => 'Kembali ke daftar paslon', 'href' => $booth . '#sekilas', 'icon' => 'arrow-left'];

if ($voted) {
    $back    = ['label' => 'Dasbor', 'href' => site_url($type->path())];
    $actions = [['label' => 'Lihat pilihanku', 'href' => site_url($type->path('pilihanku')), 'icon' => 'arrow-right', 'primary' => true]];
} elseif ($canVote) {
    $back    = ['label' => 'Semua paslon', 'href' => $list['href']];
    $actions = [
        ['label' => 'Pilih Paslon ' . $c['label'], 'href' => $booth . '#coblos-' . $c['label'], 'icon' => 'arrow-right', 'primary' => true],
        $list,
    ];
} else {
    $back    = ['label' => 'Semua paslon', 'href' => $list['href']];
    $actions = [$list + ['primary' => true]];
}

$others = array_map(static fn (array $o): array => [
    'label'   => $o['label'],
    'href'    => site_url($type->path('paslon/' . $o['label'])),
    'current' => $o['id'] === $c['id'],
    'ketua'   => $o['ketua'],
    'wakil'   => $o['wakil'],
], $lineup);
?>
<?= view('paslon/partials/profile', ['c' => $c, 'others' => $others, 'actions' => $actions, 'back' => $back]) ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_url('assets/js/paslon.js') ?>" defer></script>
<?= $this->endSection() ?>
