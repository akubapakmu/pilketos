<?php

use App\Filters\MinifyHtmlFilter;
use App\Libraries\AssetMinifier;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Minify;

/**
 * Stage 20: filter global "minifyhtml" hanya menyentuh respons text/html di
 * production.
 *
 * @internal
 */
final class MinifyHtmlFilterTest extends CIUnitTestCase
{
    private const PAGE = "<!DOCTYPE html>\n<html>\n<body>\n  <p>a</p>\n</body>\n</html>\n";

    private function filter(): MinifyHtmlFilter
    {
        $config            = new Minify();
        $config->watermark = 'UJI';

        return new MinifyHtmlFilter(new AssetMinifier($config, 'production'));
    }

    private function response(): ResponseInterface
    {
        return service('response', null, false);
    }

    public function testHtmlResponsesAreMinifiedWithWatermark(): void
    {
        $response = $this->response()->setBody(self::PAGE);

        $this->filter()->after(service('request'), $response);

        $this->assertSame("<!DOCTYPE html>\n<!--\nUJI\n-->\n<html><body><p>a</p></body></html>", $response->getBody());
    }

    public function testOtherResponsesAreLeftAlone(): void
    {
        $json = $this->response()->setJSON(['pesan' => "a\n   b"]);
        $body = $json->getBody();
        $this->assertNull($this->filter()->after(service('request'), $json));
        $this->assertSame($body, $json->getBody());

        $redirect = $this->response()->setStatusCode(302)->setBody(self::PAGE);
        $this->assertNull($this->filter()->after(service('request'), $redirect));
        $this->assertSame(self::PAGE, $redirect->getBody());
    }

    public function testDisabledOutsideProduction(): void
    {
        $response = $this->response()->setBody(self::PAGE);

        // Tanpa injeksi: ENVIRONMENT test suite = testing.
        $this->assertNull((new MinifyHtmlFilter())->after(service('request'), $response));
        $this->assertSame(self::PAGE, $response->getBody());
    }
}
