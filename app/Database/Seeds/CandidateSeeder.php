<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

class CandidateSeeder extends Seeder
{
    /**
     * Seed 3 pasangan calon contoh (data pengembangan, bukan data nyata).
     * Foto & asset tema sengaja dikosongkan (null) karena upload asset
     * ditangani oleh panel admin pada Stage 3. Tanpa asset, halaman kandidat
     * memakai monogram inisial dan profil paslon dibuka judul sederhana
     * (Stage 16: satu tampilan untuk semua pasangan, tanpa layout/pola).
     *
     * Stage 15: contoh profil & kampanye (slogan, bio, karakter, program,
     * pesan). Pasangan 02 sengaja tanpa bio & pesan untuk melihat halaman
     * profil dengan data yang belum lengkap. Tanpa video (butuh ID asli).
     */
    public function run()
    {
        $existing = $this->db->table('candidates')->countAllResults();

        if ($existing > 0) {
            return;
        }

        $now = Time::now()->toDateTimeString();

        $candidates = [
            [
                'nomor_urut'       => 1,
                'nama_ketua'       => 'Arka Wibisana',
                'nama_wakil'       => 'Naya Kirana',
                'foto_ketua'       => null,
                'foto_wakil'       => null,
                'visi'             => 'Mewujudkan OSIS yang aktif, terbuka, dan dekat dengan seluruh siswa.',
                'misi'             => "1. Menghidupkan kembali kegiatan ekstrakurikuler.\n2. Membuka kanal aspirasi siswa secara rutin.\n3. Memperkuat kegiatan literasi dan seni di sekolah.",
                'slogan'           => 'Bersama berkarya, bersama berprestasi.',
                'bio_ketua'        => 'Siswa kelas 8 yang aktif di pramuka dan majalah dinding. Senang mengajak teman mencoba hal baru dan terbiasa memimpin rapat kelas.',
                'bio_wakil'        => 'Pengurus ekstrakurikuler tari dan juara lomba baca puisi tingkat kecamatan. Teliti mengatur jadwal dan dekat dengan adik kelas.',
                'karakter_ketua'   => 'Kreatif, Aktif, Bertanggung jawab',
                'karakter_wakil'   => 'Teliti, Ramah, Peduli',
                'program'          => "Student Voice — Kotak aspirasi daring dan temu OSIS tiap bulan.\nCreative Day — Pameran karya siswa setiap akhir semester.\nTalent Space — Panggung terbuka untuk bakat seni dan olahraga.\nGreen School — Gerakan kelas bersih dan bank sampah sekolah.",
                'pesan_ketua'      => 'OSIS bukan milik pengurus saja. Ayo bangun sekolah yang kita banggakan bersama.',
                'pesan_wakil'      => 'Setiap suara dan ide kalian berarti. Kami siap mendengar.',
                'theme_name'       => 'Terracotta',
                'theme_accent'     => '#C4432B',
                'theme_asset'      => null,
                'status_aktif'     => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'nomor_urut'       => 2,
                'nama_ketua'       => 'Bagas Prayoga',
                'nama_wakil'       => 'Citra Maheswari',
                'foto_ketua'       => null,
                'foto_wakil'       => null,
                'visi'             => 'OSIS sebagai wadah kolaborasi siswa yang disiplin dan berprestasi.',
                'misi'             => "1. Meningkatkan kualitas program akademik pendukung.\n2. Membangun budaya disiplin yang positif.\n3. Mendorong prestasi siswa di tingkat sekolah dan luar sekolah.",
                'slogan'           => 'Disiplin hari ini, prestasi esok hari.',
                'bio_ketua'        => null,
                'bio_wakil'        => null,
                'karakter_ketua'   => 'Disiplin, Tegas',
                'karakter_wakil'   => 'Cerdas, Tekun',
                'program'          => "Kelas Juara: Pendampingan belajar sebaya menjelang ujian.\nApel Semangat: Apel pagi bergilir yang dipimpin siswa.\nLomba Antarkelas: Kompetisi rutin akademik dan olahraga.",
                'pesan_ketua'      => null,
                'pesan_wakil'      => null,
                'theme_name'       => 'Deep Forest',
                'theme_accent'     => '#2F5D50',
                'theme_asset'      => null,
                'status_aktif'     => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
            [
                'nomor_urut'       => 3,
                'nama_ketua'       => 'Dewi Anggraini',
                'nama_wakil'       => 'Fajar Nugroho',
                'foto_ketua'       => null,
                'foto_wakil'       => null,
                'visi'             => 'Membangun lingkungan sekolah yang inklusif, kreatif, dan peduli sesama.',
                'misi'             => "1. Mengadakan program kepedulian sosial rutin.\n2. Mewadahi kreativitas siswa lintas minat.\n3. Menjaga lingkungan sekolah yang ramah bagi semua siswa.",
                'slogan'           => 'Satu sekolah, semua merasa di rumah.',
                'bio_ketua'        => 'Ketua kelas dua tahun berturut-turut dan relawan perpustakaan. Suka mendengarkan cerita teman sebelum mengambil keputusan.',
                'bio_wakil'        => 'Anggota tim futsal dan palang merah remaja. Percaya bahwa kegiatan sekolah harus bisa diikuti semua siswa.',
                'karakter_ketua'   => 'Sabar, Inklusif, Jujur',
                'karakter_wakil'   => 'Sportif, Peduli, Sigap',
                'program'          => "Jumat Berbagi — Aksi sosial rutin untuk warga sekitar sekolah.\nKlub Minat — Wadah kegiatan lintas hobi, dari musik sampai coding.\nRuang Ramah — Pojok konseling sebaya yang aman dan nyaman.",
                'pesan_ketua'      => 'Kami ingin setiap siswa merasa punya tempat di sekolah ini.',
                'pesan_wakil'      => 'Ayo gunakan hak suaramu. Pilihanmu menentukan OSIS setahun ke depan.',
                'theme_name'       => 'Midnight Navy',
                'theme_accent'     => '#1B3A6B',
                'theme_asset'      => null,
                'status_aktif'     => 1,
                'created_at'       => $now,
                'updated_at'       => $now,
            ],
        ];

        $this->db->table('candidates')->insertBatch($candidates);
    }
}
