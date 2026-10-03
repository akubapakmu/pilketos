<?php

namespace App\Services;

use App\Controllers\BaseController;
use App\Filters\AuthFilter;
use App\Libraries\DeviceInfo;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use Config\Visitors;
use Throwable;
use WeakMap;

/**
 * Pencatat pengunjung online (Stage 19, Config\Visitors).
 *
 * Dua pintu masuk:
 * - pageView(): setiap halaman HTML yang berhasil dibuka (VisitorFilter);
 * - heartbeat(): detak presence.js selama halaman terbuka & terlihat,
 *   membawa data yang hanya diketahui browser (layar, viewport, rasio piksel,
 *   zona waktu, titik sentuh, jenis jaringan, tema gelap/terang).
 *
 * Satu baris per browser, dikenali dari cookie acak (Config::$cookieName).
 * Identitas pengguna SELALU dari sesi (tidak pernah dari input client):
 * user_type + id siswa/guru/admin, atau tamu. User-Agent & Client Hints
 * diurai matomo/device-detector (DeviceInfo::profile()) hanya saat baris baru
 * atau saat User-Agent berubah. Bot dan pustaka HTTP (curl, Python, ...)
 * tidak dicatat.
 *
 * Kerahasiaan pilihan: alamat halaman pemilih (siswa/*, guru/*) disimpan
 * tanpa nomor, jadi "siswa/coblos/yakin/2" dan "siswa/paslon/02" tercatat
 * sebagai "siswa/coblos/yakin" dan "siswa/paslon". Query string tidak
 * pernah disimpan. Pencatatan yang gagal (database sibuk, tabel belum
 * dimigrasi) tidak pernah menggagalkan halaman: cukup dicatat di log.
 */
final class VisitorTracker
{
    /**
     * Detak lebih rapat dari ini (detik) tidak menulis ulang baris yang sama.
     */
    public const MIN_WRITE_GAP = 15;

    /**
     * Peluang pembersihan baris lama per pengunjung baru (1 : N).
     */
    private const PRUNE_ODDS = 50;

    private const TOKEN_PATTERN = '/^[a-f0-9]{32}$/';

    private const CONNECTIONS = ['slow-2g', '2g', '3g', '4g'];

    /**
     * Label halaman untuk tabel admin (path ternormalisasi => label).
     */
    private const PAGES = [
        ''                    => 'Beranda',
        'siswa/masuk'         => 'Masuk siswa',
        'guru/masuk'          => 'Masuk guru',
        'admin/masuk'         => 'Masuk admin',
        // Stage 26: galeri partisipasi publik
        'partisipasi'         => 'Living Election Wall',
        'siswa'               => 'Dasbor siswa',
        'guru'                => 'Dasbor guru',
        // Stage 22: coblos = pembuka multi-langkah; surat suara di bilik-suara.
        'siswa/coblos'        => 'Langkah memilih (siswa)',
        'guru/coblos'         => 'Langkah memilih (guru)',
        'siswa/sekilas-paslon' => 'Sekilas paslon (siswa)',
        'guru/sekilas-paslon' => 'Sekilas paslon (guru)',
        'siswa/bilik-suara'   => 'Bilik suara siswa',
        'guru/bilik-suara'    => 'Bilik suara guru',
        'siswa/coblos/yakin'  => 'Konfirmasi pilihan siswa',
        'guru/coblos/yakin'   => 'Konfirmasi pilihan guru',
        'siswa/paslon'        => 'Profil paslon (siswa)',
        'guru/paslon'         => 'Profil paslon (guru)',
        'siswa/pilihanku'     => 'Pilihanku (siswa)',
        'guru/pilihanku'      => 'Pilihanku (guru)',
        'admin'               => 'Admin: Beranda',
        'admin/analitik'      => 'Admin: Analitik',
        'admin/analitik/suara' => 'Admin: Detail suara',
        'admin/hasil'         => 'Admin: Hasil akhir',
        'admin/paslon'        => 'Admin: Pasangan calon',
        'admin/siswa'         => 'Admin: Siswa',
        'admin/guru'          => 'Admin: Guru',
        'admin/jadwal'        => 'Admin: Jadwal pemilihan',
        'admin/halaman-utama' => 'Admin: Teks halaman utama',
        'admin/buka-kunci'    => 'Admin: Unlock hak suara',
        'admin/riwayat'       => 'Admin: Audit log',
        'admin/akun'          => 'Admin: Akun admin',
        'admin/pengunjung'    => 'Admin: Pengunjung online',
    ];

    private Visitors $config;

    /**
     * Request yang kunjungannya sudah dicatat (halaman Pengunjung online
     * mencatat lebih dulu agar baris admin sendiri sudah terbaru).
     *
     * @var WeakMap<IncomingRequest, true>
     */
    private WeakMap $recorded;

    public function __construct(?Visitors $config = null)
    {
        $this->config   = $config ?? config(Visitors::class);
        $this->recorded = new WeakMap();
    }

    /**
     * Satu halaman HTML dibuka. Browser tanpa cookie penanda mendapat cookie
     * baru di respons ini. Request yang sama hanya dicatat sekali.
     */
    public function pageView(IncomingRequest $request, ResponseInterface $response): void
    {
        if (! $this->config->enabled || isset($this->recorded[$request])) {
            return;
        }

        $this->recorded[$request] = true;

        $token = $this->token($request);

        if ($token === null) {
            $token = bin2hex(random_bytes(16));
            $response->setCookie(
                $this->config->cookieName,
                $token,
                max(1, $this->config->cookieDays) * DAY,
                httponly: true,
                samesite: 'Lax',
            );
        }

        $this->guard(fn () => $this->record($token, $request, [
            'path'     => self::normalizePath($request->getPath()),
            'referrer' => $this->referrer($request),
        ]));
    }

    /**
     * Detak presence.js. Browser tanpa cookie penanda tidak dicatat (cookie
     * dibuat saat membuka halaman, bukan dari detak).
     *
     * @param array<string, mixed> $client Isi JSON detak (belum dipercaya)
     */
    public function heartbeat(IncomingRequest $request, array $client): bool
    {
        $token = $this->config->enabled ? $this->token($request) : null;

        if ($token === null) {
            return false;
        }

        return (bool) $this->guard(fn () => $this->record($token, $request, ['client' => self::clientInfo($client)]));
    }

    /**
     * Hash baris pengunjung milik request ini (untuk menandai "Anda" di
     * halaman admin), atau null bila belum punya cookie.
     */
    public function currentHash(IncomingRequest $request): ?string
    {
        $token = $this->token($request);

        return $token === null ? null : hash('sha256', $token);
    }

    /**
     * Path route tanpa garis miring tepi & query string. Halaman pemilih
     * (siswa/*, guru/*) dibuang segmen angkanya agar pilihan atau minat
     * pemilih tidak terbaca dari catatan kunjungan.
     */
    public static function normalizePath(string $path): string
    {
        $path = trim((string) parse_url('/' . ltrim($path, '/'), PHP_URL_PATH), '/');
        $path = (string) preg_replace('#^index\.php(?:/|$)#', '', $path);

        if (preg_match('#^(siswa|guru)(?:/|$)#', $path) === 1) {
            $path = implode('/', array_filter(explode('/', $path), static fn (string $part): bool => $part !== '' && ! ctype_digit($part)));
        }

        return mb_substr($path, 0, 255);
    }

    /**
     * Label halaman yang ramah untuk admin, contoh "siswa/bilik-suara" menjadi
     * "Bilik suara siswa". Halaman lain tampil sebagai path-nya.
     */
    public static function pageLabel(?string $path): string
    {
        if ($path === null) {
            return '–';
        }

        if (isset(self::PAGES[$path])) {
            return self::PAGES[$path];
        }

        // Rincian di bawah menu admin: "admin/siswa/12/ubah" = "Admin: Siswa".
        for ($prefix = $path; str_contains($prefix, '/');) {
            $prefix = substr($prefix, 0, (int) strrpos($prefix, '/'));

            if ($prefix !== 'admin' && isset(self::PAGES[$prefix])) {
                return self::PAGES[$prefix];
            }
        }

        return '/' . $path;
    }

    /**
     * Data detak yang dipercaya: setiap nilai divalidasi ketat, sisanya null.
     *
     * @param array<string, mixed> $client
     *
     * @return array<string, int|float|string|null>
     */
    public static function clientInfo(array $client): array
    {
        $size = static function (mixed $value): ?string {
            if (! is_string($value) || preg_match('/^(\d{2,5})x(\d{2,5})$/', $value, $m) !== 1) {
                return null;
            }

            return (int) $m[1] <= 20000 && (int) $m[2] <= 20000 ? $m[1] . 'x' . $m[2] : null;
        };

        $ratio = is_numeric($client['dpr'] ?? null) ? round((float) $client['dpr'], 2) : null;
        $touch = is_numeric($client['touch'] ?? null) ? (int) $client['touch'] : null;
        $zone  = is_string($client['tz'] ?? null) ? trim($client['tz']) : '';

        return [
            'screen'       => $size($client['screen'] ?? null),
            'viewport'     => $size($client['viewport'] ?? null),
            'pixel_ratio'  => $ratio !== null && $ratio >= 0.25 && $ratio <= 8 ? $ratio : null,
            'timezone'     => preg_match('#^[A-Za-z_]+(?:/[A-Za-z0-9_+\-]+){0,2}$#', $zone) === 1 && strlen($zone) <= 64 ? $zone : null,
            'touch_points' => $touch !== null && $touch >= 0 && $touch <= 255 ? $touch : null,
            'connection'   => in_array($client['net'] ?? null, self::CONNECTIONS, true) ? $client['net'] : null,
            'color_scheme' => in_array($client['scheme'] ?? null, ['dark', 'light'], true) ? $client['scheme'] : null,
        ];
    }

    /**
     * @param array{path?: string, referrer?: string|null, client?: array<string, mixed>} $event
     */
    private function record(string $token, IncomingRequest $request, array $event): bool
    {
        $agent = mb_substr(trim($request->getHeaderLine('User-Agent')), 0, 512);

        if ($agent === '') {
            return false;
        }

        $hints  = DeviceInfo::hints($request);
        $uaHash = sha1($agent . "\n" . json_encode($hints));
        $hash   = hash('sha256', $token);
        $now    = Time::now();
        $db     = db_connect();

        $row = $db->table('visitors')
            ->select('id, ua_hash, user_type, user_id, last_seen_at, screen, viewport, pixel_ratio, timezone, touch_points, connection, color_scheme')
            ->where('visitor_hash', $hash)
            ->get()
            ->getRowArray();

        [$userType, $userId] = $this->identity();

        $data = [
            'user_type'    => $userType,
            'user_id'      => $userId,
            'ip_address'   => $request->getIPAddress(),
            'language'     => self::languages($request->getHeaderLine('Accept-Language')),
            'last_seen_at' => $now->toDateTimeString(),
        ];

        if ($row === null || $row['ua_hash'] !== $uaHash) {
            $profile = DeviceInfo::profile($agent, $hints);

            if ($profile['bot'] !== null || $profile['client_type'] === 'library') {
                return false;
            }

            $data += [
                'user_agent'      => $agent,
                'ua_hash'         => $uaHash,
                'client_hints'    => $hints === [] ? null : json_encode($hints, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'device_type'     => $profile['device_type'],
                'device_brand'    => $profile['device_brand'],
                'device_model'    => $profile['device_model'],
                'os_name'         => $profile['os_name'],
                'os_version'      => $profile['os_version'],
                'os_platform'     => $profile['os_platform'],
                'browser_name'    => $profile['browser_name'],
                'browser_version' => $profile['browser_version'],
                'browser_type'    => $profile['client_type'],
                'engine'          => $profile['engine'],
                'engine_version'  => $profile['engine_version'],
            ];
        }

        if (isset($event['path'])) {
            $data['current_path'] = $event['path'];
        }

        if (isset($event['client'])) {
            $data += array_filter($event['client'], static fn ($value): bool => $value !== null);
        }

        if ($row === null) {
            try {
                $inserted = $db->table('visitors')->insert($data + [
                    'visitor_hash'  => $hash,
                    'landing_path'  => $event['path'] ?? null,
                    'referrer'      => $event['referrer'] ?? null,
                    'page_views'    => isset($event['path']) ? 1 : 0,
                    'first_seen_at' => $now->toDateTimeString(),
                ]);
            } catch (DatabaseException) {
                $inserted = false;
            }

            // Dua request pertama browser yang sama tiba bersamaan: baris sudah
            // dibuat request lain (kunci unik visitor_hash), cukup perbarui.
            if (! $inserted) {
                $db->table('visitors')->where('visitor_hash', $hash)->set($data)->update();

                return true;
            }

            if (random_int(1, self::PRUNE_ODDS) === 1) {
                $this->prune($now);
            }

            $this->countsChanged();

            return true;
        }

        // Detak berdekatan tanpa perubahan apa pun (pengguna, User-Agent, data
        // layar): cukup sekali per jeda. Detak pertama setelah halaman dibuka
        // tetap ditulis karena membawa data layar yang belum tersimpan.
        $recent  = $now->getTimestamp() - strtotime((string) $row['last_seen_at']) < self::MIN_WRITE_GAP;
        $changed = $row['user_type'] !== $userType || (int) $row['user_id'] !== (int) $userId || isset($data['ua_hash']);

        foreach ($event['client'] ?? [] as $column => $value) {
            $changed = $changed || ($value !== null && (string) $value !== (string) $row[$column] && ! (is_float($value) && abs($value - (float) $row[$column]) < 0.001));
        }

        if (! isset($event['path']) && $recent && ! $changed) {
            return true;
        }

        // Stage 21: angka online di beranda & admin diperbarui tiap detik dari
        // cache singkat. Pengunjung yang baru kembali online atau berganti akun
        // (masuk/keluar) mengubah angka: cache dibuang agar langsung terlihat.
        $wasAway = strtotime((string) $row['last_seen_at']) < $now->getTimestamp() - max(30, $this->config->onlineSeconds);
        $account = $row['user_type'] !== $userType || (int) $row['user_id'] !== (int) $userId;

        $builder = $db->table('visitors')->where('id', (int) $row['id']);

        if (isset($event['path'])) {
            $builder->set('page_views', 'page_views + 1', false);
        }

        $builder->set($data)->update();

        if ($wasAway || $account) {
            $this->countsChanged();
        }

        return true;
    }

    /**
     * [user_type, user_id] dari sesi, atau [null, null] untuk tamu. Sesi
     * pemilih yang sudah melewati batas idle (AuthFilter) dihitung tamu:
     * pemilih itu harus masuk ulang sebelum dapat membuka dasbornya lagi.
     *
     * @return array{0: string|null, 1: int|null}
     */
    private function identity(): array
    {
        $session = session();
        $type    = $session->get('user_type');

        if (! in_array($type, ['student', 'teacher', 'admin'], true)) {
            return [null, null];
        }

        $id = (int) $session->get($type . '_id');

        if ($id <= 0) {
            return [null, null];
        }

        $seen = $session->get(BaseController::AUTH_SEEN_KEY);

        if ($type !== 'admin' && is_int($seen) && Time::now()->getTimestamp() - $seen > AuthFilter::VOTER_IDLE_SECONDS) {
            return [null, null];
        }

        return [$type, $id];
    }

    private function token(IncomingRequest $request): ?string
    {
        $value = $request->getCookie($this->config->cookieName);

        return is_string($value) && preg_match(self::TOKEN_PATTERN, $value) === 1 ? $value : null;
    }

    /**
     * Situs asal dari luar aplikasi ini (skema + host + path, tanpa query).
     */
    private function referrer(IncomingRequest $request): ?string
    {
        $referer = trim($request->getHeaderLine('Referer'));
        $parts   = $referer === '' ? false : parse_url($referer);

        if ($parts === false || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true) || ($parts['host'] ?? '') === '') {
            return null;
        }

        $own = parse_url(config('App')->baseURL, PHP_URL_HOST);

        if (strcasecmp((string) $own, (string) $parts['host']) === 0 || strcasecmp($request->getUri()->getHost(), (string) $parts['host']) === 0) {
            return null;
        }

        return mb_substr($parts['scheme'] . '://' . $parts['host'] . ($parts['path'] ?? ''), 0, 255);
    }

    /**
     * Tiga bahasa teratas Accept-Language, contoh "id-ID, id, en-US".
     */
    private static function languages(string $header): ?string
    {
        $languages = [];

        foreach (explode(',', $header) as $part) {
            $tag = trim(explode(';', $part)[0]);

            if (preg_match('/^[A-Za-z]{1,8}(?:-[A-Za-z0-9]{1,8}){0,3}$/', $tag) === 1 && ! in_array($tag, $languages, true)) {
                $languages[] = $tag;
            }

            if (count($languages) === 3) {
                break;
            }
        }

        return $languages === [] ? null : mb_substr(implode(', ', $languages), 0, 64);
    }

    private function prune(Time $now): void
    {
        $days = max(1, $this->config->retentionDays);

        db_connect()->table('visitors')
            ->where('last_seen_at <', $now->subDays($days)->toDateTimeString())
            ->delete();
    }

    /**
     * Buang cache angka online (OnlineVisitors::counts()) agar perubahan
     * jumlah pengunjung terbaca pada pembaruan berikutnya (Stage 21).
     */
    private function countsChanged(): void
    {
        if ($this->config->countCacheSeconds > 0) {
            cache()->delete(OnlineVisitors::CACHE_KEY);
        }
    }

    /**
     * Pencatatan tidak pernah menggagalkan request.
     */
    private function guard(callable $work): mixed
    {
        try {
            return $work();
        } catch (Throwable $e) {
            log_message('error', 'Pencatatan pengunjung gagal: {message}', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
