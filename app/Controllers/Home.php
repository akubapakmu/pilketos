<?php

namespace App\Controllers;

use App\Libraries\CandidateTheme;
use App\Models\CandidateModel;
use App\Models\ElectionModel;
use App\Services\PublicLiveCount;
use CodeIgniter\Exceptions\PageNotFoundException;
use Config\Homepage;
use Config\Visitors;
use Throwable;

/**
 * Beranda imersif (redesign beranda, STAGE5-NOTES.md): layar pembuka, scene
 * layar penuh (hero, pintu masuk Siswa/Guru, perolehan suara), panel status
 * bergaya terminal, dan live count publik. Stage 6 (STAGE6-NOTES.md): hero
 * berlapis lereng Muria dan warna identitas sekolah yang dijaga netral
 * terhadap warna aksen pasangan calon.
 */
class Home extends BaseController
{
    public function index()
    {
        $config   = config(Homepage::class);
        $election = model(ElectionModel::class)->getCurrentElection();
        $viewer   = session()->get('user_type');
        $themes   = [];
        $active   = [];

        foreach (model(CandidateModel::class)->getAllOrdered() as $row) {
            $theme                = CandidateTheme::present($row);
            $isActive             = (int) $row['status_aktif'] === 1;
            $theme['profile']     = self::profileUrl($theme, $isActive, is_string($viewer) ? $viewer : null);
            $themes[$theme['id']] = $theme;

            if ($isActive) {
                $active[] = $theme;
            }
        }

        $live = null;

        if ($config->publicLiveCount) {
            $live = PublicLiveCount::build($election, $config);
            // Pasangan yang dihitung (aktif + nonaktif yang sudah punya suara
            // sah) dengan tema & foto, agar persentase di layar berjumlah 100%.
            $live['pairs'] = [];

            foreach ($live['candidates'] as $count) {
                if (isset($themes[$count['id']])) {
                    $live['pairs'][] = $themes[$count['id']] + ['percent' => $count['percent']];
                }
            }
        }

        $html = view('home/index', [
            'online'     => $this->onlineCounts(),
            'election'   => $election,
            // Parijoto hanya dipakai bila tidak mirip warna pasangan mana pun.
            'identity'   => self::identityAccent($config, array_column($themes, 'accent')),
            // Teaser publik: hanya nomor, nama, foto, dan warna tema.
            'candidates' => $active,
            'live'       => $live,
            'home'       => $config,
            'immersive'  => true,
            'bodyClass'  => 'page-home',
        ]);

        // Data renderer view bersifat bersama (Config\View::$saveData): penanda
        // beranda imersif tidak boleh terbawa ke render berikutnya di proses
        // yang sama (test otomatis, worker mode).
        service('renderer')->resetData();

        return $html;
    }

    /**
     * Stage 21: tautan "Lihat profil pasangan" di foto pasangan layar penuh,
     * menuju profil paslon di panel pengguna yang sedang login (sama dengan
     * cara header membaca sesi):
     * - siswa  -> siswa/paslon/0X, guru -> guru/paslon/0X (hanya paslon aktif;
     *   paslon nonaktif yang masih dihitung tidak punya halaman profil);
     * - admin  -> pratinjau admin/paslon/{id}/intip (aktif maupun nonaktif);
     * - tamu   -> null (tautan tidak ditampilkan).
     */
    public static function profileUrl(array $theme, bool $active, ?string $viewer): ?string
    {
        return match ($viewer) {
            'student' => $active ? site_url('siswa/paslon/' . $theme['label']) : null,
            'teacher' => $active ? site_url('guru/paslon/' . $theme['label']) : null,
            'admin'   => site_url('admin/paslon/' . $theme['id'] . '/intip'),
            default   => null,
        };
    }

    /**
     * Stage 19: angka online untuk beranda (pojok kanan hero di desktop &
     * di atas footer scene perolehan suara), atau null bila dimatikan
     * (Config\Visitors). Pengunjung yang baru pertama datang (belum punya
     * cookie penanda) belum tercatat saat halaman dirender, jadi ikut
     * dihitung di sini; detak presence.js memperbarui angkanya.
     *
     * @return array{total: int, guests: int, users: int}|null
     */
    private function onlineCounts(): ?array
    {
        $config = config(Visitors::class);

        if (! $config->enabled || ! $config->publicCounter) {
            return null;
        }

        try {
            $online = service('onlineVisitors')->publicCounts();
        } catch (Throwable $e) {
            log_message('error', 'Angka pengunjung online gagal dihitung: {message}', ['message' => $e->getMessage()]);

            return null;
        }

        if (service('visitorTracker')->currentHash($this->request) === null) {
            $online[in_array(session()->get('user_type'), ['student', 'teacher', 'admin'], true) ? 'users' : 'guests']++;
            $online['total']++;
        }

        return $online;
    }

    /**
     * Warna identitas sekolah (parijoto) untuk beranda, atau null = netral
     * (Kabut). Netralitas (06-VISUAL-DIRECTION §5.1): bila warna identitas
     * mirip warna aksen salah satu pasangan (CIEDE2000 di bawah ambang),
     * beranda tidak memakainya agar tidak terkesan memihak.
     *
     * @param list<string> $accents Warna aksen semua pasangan (#RRGGBB)
     */
    public static function identityAccent(Homepage $config, array $accents): ?string
    {
        $color = $config->identityAccent;

        if (! is_string($color) || preg_match('/^#[0-9A-Fa-f]{6}$/', trim($color)) !== 1) {
            return null;
        }

        $color = strtoupper(trim($color));

        foreach ($accents as $accent) {
            if (CandidateTheme::deltaE($color, $accent) < $config->identityMinDeltaE) {
                return null;
            }
        }

        return $color;
    }

    /**
     * GET hitung-suara: angka live count publik untuk beranda (JSON, no-store).
     * Hanya persentase per pasangan + partisipasi (PublicLiveCount); 404 bila
     * Config\Homepage::$publicLiveCount = false.
     */
    public function liveCount()
    {
        $config = config(Homepage::class);

        if (! $config->publicLiveCount) {
            throw PageNotFoundException::forPageNotFound();
        }

        $election = model(ElectionModel::class)->getCurrentElection();

        return $this->response
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setJSON(PublicLiveCount::build($election, $config));
    }
}
