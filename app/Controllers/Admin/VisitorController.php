<?php

namespace App\Controllers\Admin;

use App\Services\OnlineVisitors;
use CodeIgniter\I18n\Time;
use Config\Visitors;

/**
 * Pengunjung online (Stage 19): tamu dan pengguna login (siswa/guru/admin)
 * yang sedang membuka situs, lengkap dengan perangkat, sistem operasi,
 * browser, mesin browser, User-Agent, Client Hints, IP, bahasa, layar, zona
 * waktu, halaman, dan lama kunjungan. Hanya-baca.
 *
 * Ringkasan selalu "sekarang" (online dalam Config\Visitors::$onlineSeconds)
 * termasuk persentase pengguna online terhadap seluruh akun sistem; daftar &
 * sebaran dapat disaring cakupan waktu (online / hari ini / 7 hari), jenis
 * pengunjung, dan kata kunci. admin-visitors.js memuat ulang bagian halaman
 * setiap REFRESH_SECONDS detik tanpa muat ulang penuh.
 */
class VisitorController extends AdminController
{
    public const PER_PAGE = 50;

    public const REFRESH_SECONDS = 20;

    /**
     * GET admin/pengunjung?waktu=&siapa=&q=
     */
    public function index()
    {
        $scope = (string) $this->request->getGet('waktu');
        $scope = isset(OnlineVisitors::SCOPES[$scope]) ? $scope : OnlineVisitors::SCOPE_ONLINE;
        $who   = (string) $this->request->getGet('siapa');
        $who   = isset(OnlineVisitors::WHO[$who]) ? $who : '';
        $query = mb_substr(trim((string) $this->request->getGet('q')), 0, 100);
        $page  = $this->page();

        // Catat kunjungan halaman ini lebih dulu (VisitorFilter melewatinya),
        // agar baris browser admin sendiri sudah berisi halaman & akun terbaru.
        if (! $this->request->isAJAX()) {
            service('visitorTracker')->pageView($this->request, $this->response);
        }

        $online = service('onlineVisitors');
        $list   = $online->list($scope, $who, $query, $page, self::PER_PAGE);
        $config = config(Visitors::class);

        return $this->render('admin/visitors/index', [
            'title'     => 'Pengunjung Online',
            'summary'   => $online->summary(),
            'breakdown' => $online->breakdown($scope),
            'rows'      => $list['rows'],
            'total'     => $list['total'],
            'scope'     => $scope,
            'who'       => $who,
            'query'     => $query,
            'pager'     => $this->pagerLinks($page, self::PER_PAGE, $list['total']),
            'mine'      => service('visitorTracker')->currentHash($this->request),
            'window'    => max(30, $config->onlineSeconds),
            'enabled'   => $config->enabled,
            'refresh'   => self::REFRESH_SECONDS,
            'updated'   => Time::now(),
        ], 'visitors');
    }
}
