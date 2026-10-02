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
 *   (Stage 22: tombol 3D, bilik-suara#coblos-0X; Stage 24: kursor mengklik
 *   tombol) + "Kembali ke daftar paslon" (Stage 22: halaman sekilas-paslon);
 * - sudah memilih: "Lihat pilihanku";
 * - belum dibuka / sudah ditutup: "Kembali ke daftar paslon".
 * Stage 24: tanpa tautan kembali (px-back) di atas pembuka; navigasi situs,
 * bar pemilih paslon, dan tombol penutup sudah mengarahkan pemilih.
 *
 * @var \App\Services\VoterType $type
 * @var array                   $c       CandidateTheme::present()
 * @var list<array>             $lineup  Semua paslon aktif (CandidateTheme::presentAll())
 * @var bool                    $canVote
 * @var bool                    $voted
 */
$booth = site_url($type->path('bilik-suara'));
$list  = ['label' => 'Kembali ke daftar paslon', 'href' => site_url($type->path('sekilas-paslon')), 'icon' => 'arrow-left'];
$pick  = ! $voted && $canVote;

if ($voted) {
    $actions = [['label' => 'Lihat pilihanku', 'href' => site_url($type->path('pilihanku')), 'icon' => 'arrow-right', 'primary' => true]];
} elseif ($canVote) {
    $actions = [
        ['label' => 'Pilih Paslon ' . $c['label'], 'href' => $booth . '#coblos-' . $c['label'], 'primary' => true, 'pick3d' => true],
        $list,
    ];
} else {
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
<?= view('paslon/partials/profile', ['c' => $c, 'others' => $others, 'actions' => $actions]) ?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= asset_url('assets/js/paslon.js') ?>" defer></script>
<?php if ($pick): ?>
  <script type="module" src="<?= asset_url('assets/js/pick3d.js') ?>"></script>
<?php endif; ?>
<?= $this->endSection() ?>
