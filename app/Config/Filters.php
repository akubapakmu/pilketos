<?php

namespace Config;

use App\Filters\AdminAuthFilter;
use App\Filters\MinifyHtmlFilter;
use App\Filters\PostSizeFilter;
use App\Filters\SecurityHeadersFilter;
use App\Filters\StudentAuthFilter;
use App\Filters\TeacherAuthFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    /**
     * Alias filter bawaan CodeIgniter + filter kustom aplikasi ini.
     * Filter proteksi route (adminauth/studentauth/teacherauth) dipasang
     * langsung pada masing-masing route group di app/Config/Routes.php.
     *
     * @var array<string, class-string|list<class-string>>
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
        'adminauth'     => AdminAuthFilter::class,
        'studentauth'   => StudentAuthFilter::class,
        'teacherauth'   => TeacherAuthFilter::class,
        'postsize'      => PostSizeFilter::class,
        'appheaders'    => SecurityHeadersFilter::class,
        'minifyhtml'    => MinifyHtmlFilter::class,
    ];

    /**
     * Filter khusus framework yang selalu dijalankan (debug toolbar hanya
     * aktif di development).
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps',
            'pagecache',
        ],
        'after' => [
            'pagecache',
            'performance',
            'toolbar',
        ],
    ];

    /**
     * Filter global. CSRF aktif untuk semua request (GET dilewati,
     * POST/PUT/PATCH/DELETE wajib membawa token). "postsize" (Stage 3)
     * berjalan sebelum CSRF agar unggahan > post_max_size mendapat pesan
     * ukuran yang jelas, bukan pesan token kedaluwarsa. "appheaders"
     * (Stage 4) menambah Permissions-Policy, COOP/CORP, no-store, dan
     * menghapus X-Powered-By; CSP diatur Config\ContentSecurityPolicy.
     * "minifyhtml" (Stage 19) memperkecil HTML + watermark view-source,
     * hanya di production (saklar di Config\Minify).
     *
     * @var array{
     *     before: array<string, array{except: list<string>|string}>|list<string>,
     *     after: array<string, array{except: list<string>|string}>|list<string>
     * }
     */
    public array $globals = [
        'before' => [
            'postsize',
            'csrf',
            'invalidchars',
        ],
        'after' => [
            'secureheaders',
            'appheaders',
            'minifyhtml',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * Sengaja kosong: proteksi student/teacher/admin dipasang lewat
     * route group filter di app/Config/Routes.php agar eksplisit.
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];
}
