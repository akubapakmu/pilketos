<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Stage 19: catat setiap halaman HTML yang berhasil dibuka sebagai
 * kunjungan (App\Services\VisitorTracker, Config\Visitors).
 *
 * Yang dihitung hanya GET berstatus 200 dengan isi text/html dan bukan
 * request fetch/AJAX (X-Requested-With): JSON live count, jam server,
 * fragmen analitik, live search, PDF, dan templat Excel tidak menambah
 * jumlah halaman. Pengunjung yang diam di satu halaman tetap online lewat
 * detak presence.js (kunjungan/detak).
 */
class VisitorFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (! $request instanceof IncomingRequest
            || $request->getMethod() !== 'GET'
            || $request->isAJAX()
            || $response->getStatusCode() !== 200
            || ! str_contains(strtolower($response->getHeaderLine('Content-Type')), 'text/html')) {
            return null;
        }

        service('visitorTracker')->pageView($request, $response);

        return $response;
    }
}
