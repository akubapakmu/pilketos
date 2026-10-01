<?php

namespace App\Libraries;

use Config\Minify;
use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use Throwable;

/**
 * Minify keluaran ke browser + watermark view-source (Stage 20, Stage 22;
 * Config\Minify).
 *
 * - CSS/JS (Stage 22): kode sumber yang rapi & berkomentar ada di
 *   resources/assets/{css,js}/ (di luar public/, tidak dapat dibuka browser).
 *   public/assets/{css,js}/ berisi HASIL BUILD bernama sama: diperkecil +
 *   watermark, ikut di-commit, dan disajikan di SEMUA environment tanpa
 *   butuh library minify atau folder yang dapat ditulis di server. Karena
 *   folder & nama sama, url('../fonts/...') di CSS tetap benar.
 *   build() / "php spark osis:minify" membuat ulang hasil build dari sumber.
 *   assetPath() membangun ulang otomatis bila sumber / app/Config/Minify.php
 *   lebih baru (kecuali saat testing), asalkan library tersedia dan folder
 *   dapat ditulis; selain itu file ter-commit yang disajikan apa adanya.
 *   Saklar jenis dimatikan = sumber disalin apa adanya (mode debug).
 * - HTML: page() dipanggil App\Filters\MinifyHtmlFilter untuk respons text/html,
 *   HANYA bila CI_ENVIRONMENT = production. Konservatif: deret whitespace di
 *   teks menjadi SATU spasi (tampilan sama untuk white-space: normal; spasi
 *   antar-elemen inline tetap ada), komentar dibuang, isi <pre>/<textarea> dan
 *   nilai atribut (nonce CSP, data-*) tidak disentuh. Isi <script>/<style>
 *   inline ikut diperkecil.
 *
 * $environment, $root (public/) & $sourceRoot (resources/) dapat diisi untuk test.
 */
final class AssetMinifier
{
    /**
     * Folder CSS/JS (relatif terhadap public/ dan resources/).
     */
    public const FOLDERS = ['css' => 'assets/css', 'js' => 'assets/js'];

    /**
     * Folder kode sumber rapi, relatif terhadap ROOTPATH.
     */
    public const SOURCE_DIR = 'resources';

    /**
     * Type <script> yang berisi JavaScript (selain itu, mis. JSON, dibiarkan).
     */
    private const JS_TYPES = ['', 'text/javascript', 'application/javascript', 'module'];

    /**
     * Tag yang whitespace di sekitarnya tidak pernah tampil (kepala dokumen).
     */
    private const DOCUMENT_TAGS = ['!doctype', 'html', 'head', 'body', 'meta', 'link', 'title', 'base'];

    /**
     * Elemen yang dibaca utuh: isi pre/textarea tidak disentuh, isi
     * script/style diperkecil. Lalu komentar, lalu tag lain (atribut ber-kutip
     * boleh memuat ">").
     */
    private const HTML_TOKENS = '~<(pre|textarea|script|style)(?=[\s/>])((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>(.*?)</\1\s*>'
        . '|<!--.*?-->'
        . '|<[!/?]?[a-zA-Z](?:[^>"\']|"[^"]*"|\'[^\']*\')*>~is';

    private readonly Minify $config;
    private readonly string $root;
    private readonly string $sourceRoot;

    public function __construct(?Minify $config = null, private readonly string $environment = ENVIRONMENT, ?string $root = null, ?string $sourceRoot = null)
    {
        $this->config     = $config ?? config(Minify::class);
        $this->root       = rtrim($root ?? FCPATH, '\\/') . DIRECTORY_SEPARATOR;
        $this->sourceRoot = rtrim($sourceRoot ?? ROOTPATH . self::SOURCE_DIR, '\\/') . DIRECTORY_SEPARATOR;
    }

    /**
     * Apakah jenis 'html' | 'css' | 'js' dikirim ter-minify. CSS/JS di semua
     * environment (Stage 22), HTML hanya saat production.
     */
    public function active(string $type): bool
    {
        return $this->config->enabled
            && in_array($type, ['html', 'css', 'js'], true)
            && $this->config->{$type}
            && ($type !== 'html' || $this->environment === 'production');
    }

    /**
     * Library minify (composer: matthiasmullie/minify) terpasang. Tanpanya
     * file hasil build yang ter-commit tetap disajikan, hanya build ulang
     * yang tidak bisa.
     */
    public static function available(): bool
    {
        return class_exists(CSS::class) && class_exists(JS::class);
    }

    /**
     * Path yang disajikan untuk file statis di public/ (selalu path yang
     * sama). CSS/JS yang sumbernya di resources/ lebih baru dibangun ulang
     * dulu bila bisa (bukan saat testing: test tidak mengubah public/).
     */
    public function assetPath(string $path): string
    {
        $path = ltrim($path, '/');

        if ($this->environment !== 'testing' && $this->hasSource($path) && ! $this->isFresh($path) && $this->canBuild($path)) {
            $this->build($path);
        }

        return $path;
    }

    /**
     * Bangun ulang satu file CSS/JS (path relatif, mis. 'assets/css/app.css'):
     * resources/<path> -> public/<path>. Isi yang sama tidak ditulis ulang,
     * hanya waktu ubahnya diperbarui. null = gagal (file lama tetap dipakai).
     *
     * @return array{target: string, before: int, after: int, changed: bool}|null
     */
    public function build(string $path): ?array
    {
        $path   = ltrim($path, '/');
        $code   = $this->expected($path);
        $target = $this->file($path);

        if ($code === null) {
            return null;
        }

        $result = ['target' => $path, 'before' => (int) filesize($this->source($path)), 'after' => strlen($code), 'changed' => true];

        if (is_file($target) && file_get_contents($target) === $code) {
            @touch($target);
            clearstatcache(true, $target);

            return ['changed' => false] + $result;
        }

        $temp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';

        // Tulis ke file sementara lalu rename: ratusan request bersamaan
        // tidak pernah menerima file yang baru setengah tertulis.
        if (@file_put_contents($temp, $code, LOCK_EX) === false) {
            log_message('warning', 'Minify: tidak dapat menulis {file}; file lama yang disajikan.', ['file' => $target]);

            return null;
        }

        if (! @rename($temp, $target)) {
            @unlink($temp);
            log_message('warning', 'Minify: tidak dapat mengganti {file}; file lama yang disajikan.', ['file' => $target]);

            return null;
        }

        clearstatcache(true, $target);

        return $result;
    }

    /**
     * Isi yang seharusnya ada di public/<path>: diperkecil + watermark bila
     * jenisnya aktif, salinan sumber apa adanya bila tidak. null = sumber
     * tidak ada, library tidak terpasang, atau minify gagal.
     */
    public function expected(string $path): ?string
    {
        $path   = ltrim($path, '/');
        $type   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $source = $this->source($path);
        $code   = is_file($source) ? file_get_contents($source) : false;

        if ($code === false) {
            return null;
        }

        if (! $this->active($type)) {
            return $code;
        }

        if (! self::available()) {
            return null;
        }

        try {
            return $this->withBanner($type, $this->minifyCode($type, self::stripBom($code)));
        } catch (Throwable $e) {
            log_message('error', 'Minify {file} gagal: {message}', ['file' => $path, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Sumber CSS/JS di resources/ (FOLDERS), sebagai path relatif yang sama
     * dengan di public/, mis. 'assets/css/app.css'.
     *
     * @return list<string>
     */
    public function sources(): array
    {
        $paths = [];

        foreach (self::FOLDERS as $type => $folder) {
            foreach (glob($this->sourceRoot . str_replace('/', DIRECTORY_SEPARATOR, $folder) . DIRECTORY_SEPARATOR . '*.' . $type) ?: [] as $file) {
                $paths[] = $folder . '/' . basename($file);
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * Sumber yang file di public/-nya belum ada atau isinya bukan hasil build
     * terbaru (osis:check). Dibandingkan menurut ISI, bukan waktu ubah, agar
     * hasil ekstrak zip / checkout git tidak dianggap usang. Tanpa library,
     * file yang jenisnya aktif cukup harus berawalan watermark (hasil build,
     * bukan salinan sumber).
     *
     * @return list<string>
     */
    public function outdated(): array
    {
        $mark = $this->watermark();

        return array_values(array_filter($this->sources(), function (string $path) use ($mark): bool {
            $target = $this->file($path);

            if (! is_file($target)) {
                return true;
            }

            $actual = (string) file_get_contents($target);

            if (! $this->active(pathinfo($path, PATHINFO_EXTENSION)) || self::available()) {
                return $this->expected($path) !== $actual;
            }

            return $mark !== '' && ! str_contains(substr($actual, 0, 256 + strlen($mark)), "/*!\n" . $mark . "\n*/");
        }));
    }

    /**
     * Hapus sisa Stage 20 di public/assets/{css,js}: <nama>.min.css /
     * <nama>.min.js (yang punya sumber) dan *.tmp tulisan yang terputus.
     * Jumlah file yang dihapus.
     */
    public function clean(): int
    {
        $count = 0;

        foreach ($this->sources() as $path) {
            $type   = pathinfo($path, PATHINFO_EXTENSION);
            $legacy = $this->file(substr($path, 0, -strlen($type)) . 'min.' . $type);
            $files  = [$legacy, ...(glob($legacy . '.*.tmp') ?: []), ...(glob($this->file($path) . '.*.tmp') ?: [])];

            foreach ($files as $file) {
                if (is_file($file) && @unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Respons HTML lengkap: diperkecil + watermark (bila aktif).
     */
    public function page(string $html): string
    {
        return $this->active('html') ? $this->withWatermark($this->html($html)) : $html;
    }

    /**
     * Perkecil HTML (tanpa watermark). Bila tokenisasi gagal, HTML asli.
     */
    public function html(string $html): string
    {
        if (preg_match_all(self::HTML_TOKENS, $html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
            return $html;
        }

        // Urutan token: ['text', ...] untuk teks di antara tag, ['tag', ...,
        // nama] untuk tag. Komentar dibuang; teks di kedua sisinya digabung.
        $tokens = [];
        $offset = 0;

        foreach ($matches as $match) {
            [$whole, $start] = $match[0];
            $this->pushText($tokens, substr($html, $offset, $start - $offset));
            $offset = $start + strlen($whole);

            if (str_starts_with($whole, '<!--')) {
                continue;
            }

            $tokens[] = isset($match[1]) && $match[1][1] !== -1
                ? ['tag', $this->rawElement(strtolower($match[1][0]), $match[2][0], $match[3][0]), strtolower($match[1][0])]
                : ['tag', self::collapseTag($whole), self::tagName($whole)];
        }

        $this->pushText($tokens, substr($html, $offset));

        $out = '';

        foreach ($tokens as $i => [$kind, $value]) {
            if ($kind === 'text') {
                $value = (string) preg_replace('/[ \t\n\r\f]+/', ' ', $value);

                if (in_array($tokens[$i - 1][2] ?? '!doctype', self::DOCUMENT_TAGS, true)) {
                    $value = ltrim($value, ' ');
                }

                if (in_array($tokens[$i + 1][2] ?? 'html', self::DOCUMENT_TAGS, true)) {
                    $value = rtrim($value, ' ');
                }
            }

            $out .= $value;
        }

        return $out;
    }

    /**
     * Watermark sebagai komentar tepat setelah <!DOCTYPE ...>. Fragmen HTML
     * (tanpa DOCTYPE, mis. pane analitik lewat fetch) dibiarkan.
     */
    public function withWatermark(string $html): string
    {
        $mark = $this->watermark();

        if ($mark === '' || preg_match('/^\s*(<!DOCTYPE[^>]*>)\s*/i', $html, $m) !== 1) {
            return $html;
        }

        return $m[1] . "\n<!--\n" . $mark . "\n-->\n" . substr($html, strlen($m[0]));
    }

    /**
     * Watermark dari config: akhir baris CRLF dinormalkan, baris kosong di
     * tepi dibuang. '' bila kosong atau memuat penutup komentar.
     */
    public function watermark(): string
    {
        $mark = trim(str_replace(["\r\n", "\r"], "\n", $this->config->watermark), "\n");

        if (trim($mark) === '' || str_contains($mark, '*/') || str_contains($mark, '-->') || str_contains($mark, '--!>')) {
            return '';
        }

        return $mark;
    }

    private function file(string $path): string
    {
        return $this->root . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
    }

    private function source(string $path): string
    {
        return $this->sourceRoot . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
    }

    /**
     * CSS/JS langsung di salah satu FOLDERS dan sumbernya ada di resources/.
     */
    private function hasSource(string $path): bool
    {
        $type = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return isset(self::FOLDERS[$type])
            && dirname($path) === self::FOLDERS[$type]
            && is_file($this->source($path));
    }

    /**
     * File di public/ ada dan LEBIH BARU dari sumbernya & dari
     * app/Config/Minify.php. Sengaja ">" (bukan ">="): sumber yang diedit
     * pada detik yang sama dengan build tidak tertinggal.
     */
    private function isFresh(string $path): bool
    {
        static $configTime = null;

        $configTime ??= (int) @filemtime(APPPATH . 'Config' . DIRECTORY_SEPARATOR . 'Minify.php');
        $target       = $this->file($path);

        return is_file($target) && filemtime($target) > max((int) filemtime($this->source($path)), $configTime);
    }

    /**
     * Build otomatis hanya bila folder/file public dapat ditulis dan, untuk
     * jenis yang aktif, library minify terpasang. Tanpa itu dilewati diam-diam
     * (osis:check yang melaporkan), jadi tidak ada log di setiap request.
     */
    private function canBuild(string $path): bool
    {
        $target = $this->file($path);

        return is_writable(dirname($target))
            && (! is_file($target) || is_writable($target))
            && (! $this->active(pathinfo($path, PATHINFO_EXTENSION)) || self::available());
    }

    private function minifyCode(string $type, string $code): string
    {
        if ($type === 'css') {
            // Hanya whitespace & komentar yang dibuang. "Penghematan" nilai
            // bawaan library dimatikan karena mengubah arti: flex: 1 1 0 ->
            // 0% (flex-basis berbeda), var(--x, 0px) -> var(--x,0) (calc()
            // tidak valid), transparent -> #fff0 (warna lain).
            $minifier = new class () extends CSS {
                protected function shortenZeroes($content)
                {
                    return $content;
                }

                protected function shortenHexColors($content)
                {
                    return $content;
                }

                protected function convertLegacyColors($content)
                {
                    return $content;
                }

                protected function cleanupModernColors($content)
                {
                    return $content;
                }
            };
            // Tanpa menyisipkan gambar/font kecil sebagai data: (CSP font-src 'self').
            $minifier->setMaxImportSize(0);
            $minifier->setImportExtensions([]);
        } else {
            $minifier = new JS();
        }

        $minifier->add($code);

        return trim($minifier->minify());
    }

    private function withBanner(string $type, string $code): string
    {
        $mark = $this->watermark();

        if ($mark === '') {
            return $code;
        }

        $banner = "/*!\n" . $mark . "\n*/\n";

        // @charset wajib berada di awal file CSS.
        if ($type === 'css' && preg_match('/^@charset\s*"[^"]*";/i', $code, $m) === 1) {
            return $m[0] . "\n" . $banner . substr($code, strlen($m[0]));
        }

        return $banner . $code;
    }

    private static function stripBom(string $code): string
    {
        return str_starts_with($code, "\xEF\xBB\xBF") ? substr($code, 3) : $code;
    }

    /**
     * @param list<array{0: string, 1: string, 2?: string}> $tokens
     */
    private function pushText(array &$tokens, string $text): void
    {
        if ($text === '') {
            return;
        }

        $last = array_key_last($tokens);

        if ($last !== null && $tokens[$last][0] === 'text') {
            $tokens[$last][1] .= $text;
        } else {
            $tokens[] = ['text', $text];
        }
    }

    /**
     * <pre>/<textarea>/<script>/<style> beserta isinya.
     */
    private function rawElement(string $name, string $attributes, string $body): string
    {
        $open = self::collapseTag('<' . $name . $attributes . '>');

        if ($name === 'script' && $this->active('js') && trim($body) !== '' && in_array(self::scriptType($attributes), self::JS_TYPES, true)) {
            $body = $this->minifyInline('js', $body);
        } elseif ($name === 'style' && $this->active('css') && trim($body) !== '') {
            $body = $this->minifyInline('css', $body);
        } elseif ($name === 'script' || $name === 'style') {
            $body = trim($body) === '' ? '' : $body;
        }

        return $open . $body . '</' . $name . '>';
    }

    private function minifyInline(string $type, string $code): string
    {
        if (! self::available()) {
            return $code;
        }

        try {
            return $this->minifyCode($type, $code);
        } catch (Throwable) {
            return $code;
        }
    }

    private static function scriptType(string $attributes): string
    {
        if (preg_match('/\btype\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $attributes, $m) !== 1) {
            return '';
        }

        return strtolower(trim($m[1] . ($m[2] ?? '') . ($m[3] ?? '')));
    }

    /**
     * Rapatkan whitespace di antara atribut sebuah tag; nilai atribut
     * ber-kutip tidak disentuh.
     */
    private static function collapseTag(string $tag): string
    {
        $tag = (string) preg_replace_callback(
            '/("[^"]*"|\'[^\']*\')|[ \t\n\r\f]+/',
            static fn (array $m): string => ($m[1] ?? '') !== '' ? $m[1] : ' ',
            $tag,
        );

        return (string) preg_replace('/ >$/', '>', $tag);
    }

    /**
     * Nama tag dalam huruf kecil: 'html', '/head' => 'head', '!doctype'.
     */
    private static function tagName(string $tag): string
    {
        return preg_match('~^</?(!?[a-zA-Z][a-zA-Z0-9-]*)~', $tag, $m) === 1 ? strtolower($m[1]) : '';
    }
}
