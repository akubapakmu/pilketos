<?php

namespace App\Filters;

use App\Libraries\AssetMinifier;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\DownloadResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Minify HTML + watermark view-source untuk setiap respons text/html
 * (Stage 20, Config\Minify; hanya CI_ENVIRONMENT = production).
 *
 * Yang dilewati: JSON (live count, login fetch), unduhan (PDF, Excel),
 * redirect & respons kosong. Fragmen HTML (pane analitik lewat fetch) ikut
 * diperkecil tetapi tanpa watermark (watermark hanya setelah <!DOCTYPE>).
 * Berjalan sebelum filter wajib "performance" / "toolbar" / "pagecache",
 * jadi {elapsed_time} tetap diganti dan page cache menyimpan versi minify.
 */
class MinifyHtmlFilter implements FilterInterface
{
    public function __construct(private readonly ?AssetMinifier $minifier = null)
    {
    }

    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $minifier = $this->minifier ?? new AssetMinifier();
        $status   = $response->getStatusCode();

        if (! $minifier->active('html')
            || $response instanceof DownloadResponse
            || $status === 204
            || ($status >= 300 && $status < 400)
            || ! str_contains(strtolower($response->getHeaderLine('Content-Type')), 'text/html')) {
            return null;
        }

        $body = $response->getBody();

        if (! is_string($body) || $body === '') {
            return null;
        }

        return $response->setBody($minifier->page($body));
    }
}
