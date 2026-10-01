<?php

namespace App\Libraries;

use Config\Minify;
use MatthiasMullie\Minify\CSS;
use MatthiasMullie\Minify\JS;
use Throwable;

/**
 * Minify keluaran ke browser + watermark view-source (Stage 20, Config\Minify).
 *
 * Kode sumber tidak pernah diubah:
 * - CSS/JS: assetPath('assets/css/app.css') membuat (bila perlu) lalu
 *   mengembalikan 'assets/css/app.min.css' di folder yang SAMA, sehingga
 *   url('../fonts/...') di CSS tetap benar. Dibuat ulang otomatis bila belum
 *   ada atau lebih tua dari sumbernya / dari app/Config/Minify.php (watermark
 *   diubah). Gagal menulis (folder tidak dapat ditulis PHP) = path asli yang
 *   dipakai: browser tetap menerima kode yang benar, hanya tidak diperkecil.
 * - HTML: page() dipanggil App\Filters\MinifyHtmlFilter untuk respons text/html.
 *   Konservatif: deret whitespace di teks menjadi SATU spasi (tampilan sama
 *   untuk white-space: normal; spasi antar-elemen inline tetap ada), komentar
 *   dibuang, isi <pre>/<textarea> dan nilai atribut (nonce CSP, data-*) tidak
 *   disentuh. Isi <script>/<style> inline ikut diperkecil.
 *
 * Hanya aktif bila CI_ENVIRONMENT = production (development & testing selalu
 * mengirim kode asli). $environment & $root dapat diisi untuk test.
 */
final class AssetMinifier
{
    /**
     * Folder yang diproses "php spark osis:minify" (relatif terhadap public/).
     */
    public const FOLDERS = ['css' => 'assets/css', 'js' => 'assets/js'];

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

    public function __construct(?Minify $config = null, private readonly string $environment = ENVIRONMENT, ?string $root = null)
    {
        $this->config = $config ?? config(Minify::class);
        $this->root   = rtrim($root ?? FCPATH, '\\/') . DIRECTORY_SEPARATOR;
    }

    /**
     * Apakah jenis 'html' | 'css' | 'js' dikirim ter-minify.
     */
    public function active(string $type): bool
    {
        return $this->environment === 'production'
            && $this->config->enabled
            && in_array($type, ['html', 'css', 'js'], true)
            && $this->config->{$type};
    }

    /**
     * Path yang disajikan untuk file statis di public/: versi *.min.* (dibuat
     * bila perlu) untuk CSS/JS saat aktif, selain itu path asli.
     */
    public function assetPath(string $path): string
    {
        $path = ltrim($path, '/');
        $type = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($type, ['css', 'js'], true) || ! $this->active($type) || self::isMinified($path)) {
            return $path;
        }

        $source = $this->file($path);

        if (! is_file($source)) {
            return $path;
        }

        $target = self::minifiedPath($path);

        return $this->isFresh($source, $this->file($target)) || $this->build($path) ? $target : $path;
    }

    /**
     * Minify ulang satu file CSS/JS (path relatif public/) tanpa memeriksa
     * environment, untuk "php spark osis:minify". null = gagal.
     *
     * @return array{target: string, before: int, after: int}|null
     */
    public function build(string $path): ?array
    {
        $path   = ltrim($path, '/');
        $type   = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $source = $this->file($path);
        $target = $this->file(self::minifiedPath($path));

        try {
            $code = is_file($source) ? file_get_contents($source) : false;

            if ($code === false) {
                return null;
            }

            $code = $this->withBanner($type, $this->minifyCode($type, self::stripBom($code)));
            $temp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';

            // Tulis ke file sementara lalu rename: ratusan request bersamaan
            // tidak pernah menerima file yang baru setengah tertulis.
            if (@file_put_contents($temp, $code, LOCK_EX) === false) {
                log_message('warning', 'Minify: tidak dapat menulis {file}; file asli yang disajikan.', ['file' => $target]);

                return null;
            }

            if (! @rename($temp, $target)) {
                @unlink($temp);
                log_message('warning', 'Minify: tidak dapat mengganti {file}; file asli yang disajikan.', ['file' => $target]);

                return null;
            }

            clearstatcache(true, $target);

            return ['target' => self::minifiedPath($path), 'before' => (int) filesize($source), 'after' => strlen($code)];
        } catch (Throwable $e) {
            log_message('error', 'Minify {file} gagal: {message}', ['file' => $path, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Sumber CSS/JS di FOLDERS (tanpa *.min.*), relatif terhadap public/.
     *
     * @return list<string>
     */
    public function sources(): array
    {
        $paths = [];

        foreach (self::FOLDERS as $type => $folder) {
            foreach (glob($this->file($folder) . DIRECTORY_SEPARATOR . '*.' . $type) ?: [] as $file) {
                $path = $folder . '/' . basename($file);

                if (! self::isMinified($path)) {
                    $paths[] = $path;
                }
            }
        }

        sort($paths);

        return $paths;
    }

    /**
     * Sumber yang versi *.min.*-nya belum ada / usang padahal foldernya tidak
     * dapat ditulis PHP: tidak akan pernah diperkecil (osis:check).
     *
     * @return list<string>
     */
    public function unbuildable(): array
    {
        return array_values(array_filter($this->sources(), function (string $path): bool {
            $target = $this->file(self::minifiedPath($path));

            return ! $this->isFresh($this->file($path), $target) && ! is_writable(dirname($target));
        }));
    }

    /**
     * Hapus semua *.min.* hasil buatan (yang punya file sumber). Jumlah file.
     */
    public function clean(): int
    {
        $count = 0;

        foreach ($this->sources() as $path) {
            $target = $this->file(self::minifiedPath($path));

            if (is_file($target) && @unlink($target)) {
                $count++;
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

    /**
     * 'assets/css/app.css' => 'assets/css/app.min.css'.
     */
    public static function minifiedPath(string $path): string
    {
        $type = pathinfo($path, PATHINFO_EXTENSION);

        return substr($path, 0, -strlen($type)) . 'min.' . $type;
    }

    private static function isMinified(string $path): bool
    {
        return preg_match('/\.min\.(css|js)$/i', $path) === 1;
    }

    private function file(string $path): string
    {
        return $this->root . str_replace('/', DIRECTORY_SEPARATOR, ltrim($path, '/'));
    }

    /**
     * *.min.* ada dan LEBIH BARU dari sumbernya & dari app/Config/Minify.php.
     * Sengaja ">" (bukan ">="): sumber yang diedit pada detik yang sama
     * dengan pembuatan versi min tidak tertinggal.
     */
    private function isFresh(string $source, string $target): bool
    {
        static $configTime = null;

        $configTime ??= (int) @filemtime(APPPATH . 'Config' . DIRECTORY_SEPARATOR . 'Minify.php');

        return is_file($target) && filemtime($target) > max((int) filemtime($source), $configTime);
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
