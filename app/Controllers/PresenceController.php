<?php

namespace App\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Config\Visitors;
use Throwable;

/**
 * Detak pengunjung online (Stage 19, presence.js).
 *
 * POST kunjungan/detak (CSRF lewat header X-CSRF-TOKEN) dikirim setiap
 * Config\Visitors::$heartbeatSeconds selama halaman terbuka & terlihat.
 * Isinya hanya data tampilan browser (ukuran layar & viewport, rasio piksel,
 * zona waktu, titik sentuh, jenis jaringan, tema), divalidasi ketat oleh
 * VisitorTracker::clientInfo(). Identitas pengunjung tetap dari cookie
 * penanda & sesi, bukan dari isi detak.
 *
 * Balasan: irama detak berikutnya + angka ringkas online (tamu, pengguna
 * login, total) untuk penghitung di beranda. Tidak ada nama, IP, atau
 * perangkat siapa pun.
 */
class PresenceController extends BaseController
{
    public function heartbeat(): ResponseInterface
    {
        $config = config(Visitors::class);

        if (! $config->enabled) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false]);
        }

        try {
            $client = $this->request->getJSON(true);
        } catch (Throwable) {
            $client = null;
        }

        $tracked = service('visitorTracker')->heartbeat($this->request, is_array($client) ? $client : []);
        $data    = [
            'ok'       => true,
            'tracked'  => $tracked,
            'interval' => max(30, $config->heartbeatSeconds),
        ];

        if ($config->publicCounter) {
            try {
                $data['online'] = service('onlineVisitors')->publicCounts();
            } catch (Throwable $e) {
                log_message('error', 'Angka pengunjung online gagal dihitung: {message}', ['message' => $e->getMessage()]);
            }
        }

        return $this->response->setJSON($data);
    }
}
