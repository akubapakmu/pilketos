<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 *
 * Stage 7: seluruh URL memakai bahasa Indonesia santai (siswa/guru/admin,
 * masuk/keluar, coblos, pilihanku, paslon, impor, jadwal, buka-kunci, ...).
 * URL bahasa Inggris lama sengaja tidak dialihkan (404). Nilai internal
 * (user_type di sesi, VoterType, nama tabel) tetap bahasa Inggris.
 */

// -----------------------------------------------------------------
// PUBLIK
// -----------------------------------------------------------------
$routes->get('/', 'Home::index');

// Jam server untuk countdown (JSON publik, tanpa data pemilih/suara).
$routes->get('jam-server', 'ElectionController::clock');

// Hitung suara publik beranda (JSON): hanya persentase per pasangan +
// partisipasi, tanpa identitas/rincian. Rincian tetap di admin/hitung-suara.
$routes->get('hitung-suara', 'Home::liveCount');

// Stage 19: detak pengunjung online (presence.js, POST + CSRF). Balasan hanya
// angka ringkas tamu & pengguna login online, tanpa identitas siapa pun.
$routes->post('kunjungan/detak', 'PresenceController::heartbeat');
// Stage 21: angka online yang sama, dibaca beranda tiap detik (GET, hanya baca).
$routes->get('kunjungan/online', 'PresenceController::online');

// Stage 26: Living Election Wall. Hanya nama, avatar, dan sudah/belum
// berpartisipasi per rombel & guru; tanpa NISN/NIP, waktu, maupun pilihan.
// 404 bila Config\Homepage::$electionWall = false.
$routes->get('partisipasi', 'WallController::index');
$routes->get('partisipasi/status', 'WallController::status');

$routes->get('siswa/masuk', 'Student\AuthController::loginForm');
$routes->post('siswa/masuk', 'Student\AuthController::attemptLogin');

$routes->get('guru/masuk', 'Teacher\AuthController::loginForm');
$routes->post('guru/masuk', 'Teacher\AuthController::attemptLogin');

$routes->get('admin/masuk', 'Admin\AuthController::loginForm');
$routes->post('admin/masuk', 'Admin\AuthController::attemptLogin');

// -----------------------------------------------------------------
// SISWA (filter studentauth). Dasbor = /siswa.
// Voting (Stage 2): identitas pemilih hanya dari sesi, tidak ada id di URL.
// -----------------------------------------------------------------
$routes->group('siswa', ['filter' => 'studentauth'], static function (RouteCollection $routes) {
    $routes->get('/', 'Student\DashboardController::index');
    // Stage 22: coblos = pembuka multi-langkah -> sekilas-paslon -> profil
    // paslon -> bilik-suara (surat suara). POST coblos tetap menyimpan suara.
    $routes->get('coblos', 'Student\VoteController::index');
    $routes->get('sekilas-paslon', 'Student\VoteController::lineup');
    $routes->get('bilik-suara', 'Student\VoteController::booth');
    $routes->post('coblos', 'Student\VoteController::submit');
    $routes->get('coblos/yakin/(:num)', 'Student\VoteController::confirm/$1');
    // Stage 15: profil lengkap paslon aktif (nomor urut, contoh siswa/paslon/01)
    $routes->get('paslon/(:num)', 'Student\VoteController::candidate/$1');
    $routes->get('pilihanku', 'Student\VoteController::myVote');
    // Stage 26: avatar Living Election Wall (unggah foto / avatar acak / bawaan)
    $routes->post('avatar', 'Student\AvatarController::upload');
    $routes->post('avatar/acak', 'Student\AvatarController::random');
    $routes->post('avatar/hapus', 'Student\AvatarController::remove');
    $routes->post('keluar', 'Student\AuthController::logout');
});

// -----------------------------------------------------------------
// GURU (filter teacherauth). Dasbor = /guru.
// Voting (Stage 2): identitas pemilih hanya dari sesi, tidak ada id di URL.
// -----------------------------------------------------------------
$routes->group('guru', ['filter' => 'teacherauth'], static function (RouteCollection $routes) {
    $routes->get('/', 'Teacher\DashboardController::index');
    // Stage 22: coblos = pembuka multi-langkah -> sekilas-paslon -> profil
    // paslon -> bilik-suara (surat suara). POST coblos tetap menyimpan suara.
    $routes->get('coblos', 'Teacher\VoteController::index');
    $routes->get('sekilas-paslon', 'Teacher\VoteController::lineup');
    $routes->get('bilik-suara', 'Teacher\VoteController::booth');
    $routes->post('coblos', 'Teacher\VoteController::submit');
    $routes->get('coblos/yakin/(:num)', 'Teacher\VoteController::confirm/$1');
    // Stage 15: profil lengkap paslon aktif (nomor urut, contoh guru/paslon/01)
    $routes->get('paslon/(:num)', 'Teacher\VoteController::candidate/$1');
    $routes->get('pilihanku', 'Teacher\VoteController::myVote');
    // Stage 26: avatar Living Election Wall (unggah foto / avatar acak / bawaan)
    $routes->post('avatar', 'Teacher\AvatarController::upload');
    $routes->post('avatar/acak', 'Teacher\AvatarController::random');
    $routes->post('avatar/hapus', 'Teacher\AvatarController::remove');
    $routes->post('keluar', 'Teacher\AuthController::logout');
});

// -----------------------------------------------------------------
// ADMIN (filter adminauth). Dasbor = /admin.
// Panel admin Stage 3. Semua POST melewati CSRF global. Tidak ada route
// admin yang menulis suara: buka-kunci hanya membuka hak suara.
// -----------------------------------------------------------------
$routes->group('admin', ['filter' => 'adminauth'], static function (RouteCollection $routes) {
    $routes->get('/', 'Admin\DashboardController::index');
    $routes->post('keluar', 'Admin\AuthController::logout');

    // Hitung suara (AJAX JSON) & analitik
    $routes->get('hitung-suara', 'Admin\LiveCountController::index');
    $routes->get('analitik', 'Admin\AnalyticsController::index');
    $routes->get('analitik/suara', 'Admin\AnalyticsController::votes');
    // Stage 11: bagian analitik (pill section header), dimuat juga lewat fetch
    $routes->get('analitik/(:segment)', 'Admin\AnalyticsController::index/$1');

    // Hasil akhir + confetti (Stage 4): aktif hanya saat pemilihan FINISHED
    $routes->get('hasil', 'Admin\ResultController::index');
    // Stage 13: PDF hasil akhir (dompdf), dibuka di tab baru untuk dicetak/diunduh
    $routes->get('hasil/cetak', 'Admin\ResultController::pdf');

    // Pasangan calon (paslon) + tema
    $routes->get('paslon', 'Admin\CandidateController::index');
    $routes->get('paslon/tambah', 'Admin\CandidateController::new');
    $routes->post('paslon', 'Admin\CandidateController::create');
    // Stage 15: batas jumlah pasangan aktif (per pemilihan)
    $routes->post('paslon/batas', 'Admin\CandidateController::limit');
    $routes->get('paslon/(:num)/ubah', 'Admin\CandidateController::edit/$1');
    $routes->get('paslon/(:num)/intip', 'Admin\CandidateController::preview/$1');
    $routes->post('paslon/(:num)', 'Admin\CandidateController::update/$1');
    $routes->post('paslon/(:num)/hapus', 'Admin\CandidateController::delete/$1');

    // Siswa: daftar, tambah/ubah (Stage 11), impor Excel
    $routes->get('siswa', 'Admin\StudentController::index');
    $routes->get('siswa/impor', 'Admin\StudentImportController::index');
    $routes->post('siswa/impor', 'Admin\StudentImportController::upload');
    $routes->get('siswa/impor/templat', 'Admin\StudentImportController::template');
    $routes->get('siswa/impor/cek/(:segment)', 'Admin\StudentImportController::preview/$1');
    $routes->post('siswa/impor/simpan', 'Admin\StudentImportController::commit');
    $routes->get('siswa/impor/selesai', 'Admin\StudentImportController::result');
    // Stage 11: tambah & ubah satu per satu langsung dari panel admin
    $routes->get('siswa/tambah', 'Admin\StudentController::new');
    $routes->post('siswa', 'Admin\StudentController::create');
    $routes->get('siswa/(:num)/ubah', 'Admin\StudentController::edit/$1');
    $routes->post('siswa/(:num)', 'Admin\StudentController::update/$1');
    $routes->get('siswa/(:num)', 'Admin\StudentController::show/$1');
    $routes->post('siswa/(:num)/status', 'Admin\StudentController::status/$1');
    $routes->post('siswa/(:num)/hapus', 'Admin\StudentController::delete/$1');
    // Stage 26: moderasi foto avatar Living Election Wall
    $routes->post('siswa/(:num)/avatar/hapus', 'Admin\StudentController::removeAvatar/$1');

    // Guru: daftar, tambah/ubah (Stage 11), impor Excel
    $routes->get('guru', 'Admin\TeacherController::index');
    $routes->get('guru/impor', 'Admin\TeacherImportController::index');
    $routes->post('guru/impor', 'Admin\TeacherImportController::upload');
    $routes->get('guru/impor/templat', 'Admin\TeacherImportController::template');
    $routes->get('guru/impor/cek/(:segment)', 'Admin\TeacherImportController::preview/$1');
    $routes->post('guru/impor/simpan', 'Admin\TeacherImportController::commit');
    $routes->get('guru/impor/selesai', 'Admin\TeacherImportController::result');
    // Stage 11: tambah & ubah satu per satu langsung dari panel admin
    $routes->get('guru/tambah', 'Admin\TeacherController::new');
    $routes->post('guru', 'Admin\TeacherController::create');
    $routes->get('guru/(:num)/ubah', 'Admin\TeacherController::edit/$1');
    $routes->post('guru/(:num)', 'Admin\TeacherController::update/$1');
    $routes->get('guru/(:num)', 'Admin\TeacherController::show/$1');
    $routes->post('guru/(:num)/status', 'Admin\TeacherController::status/$1');
    $routes->post('guru/(:num)/hapus', 'Admin\TeacherController::delete/$1');
    // Stage 26: moderasi foto avatar Living Election Wall
    $routes->post('guru/(:num)/avatar/hapus', 'Admin\TeacherController::removeAvatar/$1');

    // Jadwal pemilihan (status dihitung dari jadwal & jam server)
    $routes->get('jadwal', 'Admin\ElectionController::index');
    $routes->post('jadwal', 'Admin\ElectionController::save');
    $routes->post('jadwal/tutup', 'Admin\ElectionController::close');
    $routes->post('jadwal/buka', 'Admin\ElectionController::open');

    // Stage 24: teks hero halaman utama (diketik bergantian, satu baris = satu kalimat)
    $routes->get('halaman-utama', 'Admin\HomepageController::index');
    $routes->post('halaman-utama', 'Admin\HomepageController::save');

    // Buka kunci hak suara & riwayat (audit log). Segmen jenis pemilih di URL
    // (siswa/guru) dipetakan ke nilai internal VoterType (student/teacher).
    $routes->get('buka-kunci', 'Admin\UnlockController::index');
    $routes->get('buka-kunci/siswa/(:num)', 'Admin\UnlockController::form/student/$1');
    $routes->post('buka-kunci/siswa/(:num)', 'Admin\UnlockController::unlock/student/$1');
    $routes->get('buka-kunci/guru/(:num)', 'Admin\UnlockController::form/teacher/$1');
    $routes->post('buka-kunci/guru/(:num)', 'Admin\UnlockController::unlock/teacher/$1');
    $routes->get('riwayat', 'Admin\AuditController::index');

    // Stage 19: pengunjung online (tamu & pengguna login), perangkat & browser
    $routes->get('pengunjung', 'Admin\VisitorController::index');
    // Stage 21: papan angka saja (fragmen), dimuat ulang tiap detik
    $routes->get('pengunjung/ringkas', 'Admin\VisitorController::summary');

    // Stage 13: akun admin yang sedang masuk (ganti nama pengguna & kata sandi)
    $routes->get('akun', 'Admin\AccountController::index');
    $routes->post('akun/nama-pengguna', 'Admin\AccountController::username');
    $routes->post('akun/kata-sandi', 'Admin\AccountController::password');
});
