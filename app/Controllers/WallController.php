<?php

namespace App\Controllers;

use App\Models\ElectionModel;
use App\Services\ParticipationWall;
use App\Services\VoterType;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Homepage;

/**
 * Living Election Wall (Stage 26): galeri publik nama + avatar pemilih per
 * rombel & guru yang menandai siapa yang SUDAH berpartisipasi, ditautkan dari
 * scene perolehan suara beranda. Pilihan, waktu memilih, NISN/NIP, dan
 * urutan siapa yang terakhir memilih tidak pernah dikirim
 * (App\Services\ParticipationWall).
 *
 * - index  : GET partisipasi         (halaman; tanpa JavaScript tetap terbaca)
 * - status : GET partisipasi/status  (JSON, dibaca wall.js tiap 20 detik)
 * Keduanya 404 bila Config\Homepage::$electionWall = false.
 */
class WallController extends BaseController
{
    public function index()
    {
        $config   = $this->config();
        $election = model(ElectionModel::class)->getCurrentElection();
        $wall     = service('participationWall')->page($election, $config);
        $filters  = ParticipationWall::filters((array) $this->request->getGet(), $wall);

        $html = view('wall/index', [
            'title'     => 'Living Election Wall',
            'bodyClass' => 'page-wall',
            'wallNav'   => $this->nav(),
            'wall'      => $wall,
            'filters'   => $filters,
            'scope'     => ParticipationWall::scope($wall, $filters['kelas']),
        ]);

        // Data renderer bersifat bersama (Config\View::$saveData): penanda menu
        // wall tidak boleh terbawa ke render berikutnya (lihat Home::index).
        service('renderer')->resetData();

        return $html;
    }

    public function status()
    {
        $config   = $this->config();
        $election = model(ElectionModel::class)->getCurrentElection();

        return $this->response
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setJSON(service('participationWall')->status($election, $config));
    }

    private function config(): Homepage
    {
        $config = config(Homepage::class);

        if (! $config->electionWall) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $config;
    }

    /**
     * Menu wall: kiri kembali ke scene perolehan suara, kanan dasbor sesuai
     * peran (siswa / guru / admin) atau portal masuk beranda untuk tamu.
     *
     * @return array{back: string, home: string, label: string, guest: bool}
     */
    private function nav(): array
    {
        $user = session()->get('user_type');

        return match ($user) {
            'student', 'teacher' => [
                'back'  => site_url('/') . '#perolehan',
                'home'  => site_url(VoterType::from($user)->path()),
                'label' => 'Dasbor ' . VoterType::from($user)->label(),
                'guest' => false,
            ],
            'admin' => [
                'back'  => site_url('/') . '#perolehan',
                'home'  => site_url('admin'),
                'label' => 'Dasbor Admin',
                'guest' => false,
            ],
            default => [
                'back'  => site_url('/') . '#perolehan',
                'home'  => site_url('/') . '#masuk',
                'label' => 'Masuk',
                'guest' => true,
            ],
        };
    }
}
