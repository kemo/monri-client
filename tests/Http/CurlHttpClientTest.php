<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Http;

use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Exception\NetworkException;
use Kemo\Monri\Http\CurlHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CurlHttpClient.
 *
 * Since CurlHttpClient wraps real curl calls, these tests use a local HTTP
 * server to verify actual behavior without hitting external services.
 */
final class CurlHttpClientTest extends TestCase
{
    private static string $serverHost = '127.0.0.1';
    private static int $serverPort = 0;
    /** @var resource|false */
    private static $serverProcess = false;

    public static function setUpBeforeClass(): void
    {
        // Find a free port
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($sock, self::$serverHost, 0);
        socket_getsockname($sock, $addr, $port);
        self::$serverPort = $port;
        socket_close($sock);

        $routerPath = __DIR__ . '/server_router.php';
        $cmd = sprintf(
            'php -S %s:%d %s',
            self::$serverHost,
            self::$serverPort,
            escapeshellarg($routerPath),
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        self::$serverProcess = proc_open($cmd, $descriptors, $pipes);

        // Wait for the server to be ready
        $maxWait = 50; // 5 seconds
        while ($maxWait > 0) {
            $conn = @fsockopen(self::$serverHost, self::$serverPort);
            if ($conn) {
                fclose($conn);
                break;
            }
            usleep(100_000);
            $maxWait--;
        }

        if ($maxWait === 0) {
            throw new \RuntimeException('Test HTTP server failed to start');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess !== false) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    private function url(string $path): string
    {
        return sprintf('http://%s:%d%s', self::$serverHost, self::$serverPort, $path);
    }

    public function testGetReturnsSuccessfulResponse(): void
    {
        $client = new CurlHttpClient();
        $result = $client->get($this->url('/ok'));

        $this->assertSame(200, $result['status']);
        $this->assertSame('{"status":"ok"}', $result['body']);
        $this->assertIsArray($result['headers']);
    }

    public function testGetPassesCustomHeaders(): void
    {
        $client = new CurlHttpClient();
        $result = $client->get($this->url('/echo-headers'), ['X-Custom' => 'test-value']);

        $decoded = json_decode($result['body'], true);
        $this->assertSame('test-value', $decoded['x-custom'] ?? null);
    }

    public function testGetThrowsApiExceptionOnErrorStatus(): void
    {
        $client = new CurlHttpClient();

        try {
            $client->get($this->url('/error/422'));
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(422, $e->statusCode);
            $this->assertNotEmpty($e->responseBody);
        }
    }

    public function testGetThrowsNetworkExceptionOnConnectionFailure(): void
    {
        $client = new CurlHttpClient();

        $this->expectException(NetworkException::class);

        // Connect to a port that is definitely not listening
        $client->get('http://127.0.0.1:1/nope');
    }

    public function testPostSendsJsonBody(): void
    {
        $client = new CurlHttpClient();
        $result = $client->post($this->url('/echo-body'), ['amount' => 500, 'currency' => 'EUR']);

        $decoded = json_decode($result['body'], true);
        $this->assertSame(500, $decoded['amount']);
        $this->assertSame('EUR', $decoded['currency']);
    }

    public function testPostThrowsApiExceptionOnErrorStatus(): void
    {
        $client = new CurlHttpClient();

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(400);

        $client->post($this->url('/error/400'), ['data' => 'test']);
    }

    public function testDeleteReturnsSuccessfulResponse(): void
    {
        $client = new CurlHttpClient();
        $result = $client->delete($this->url('/ok'));

        $this->assertSame(200, $result['status']);
    }

    public function testDeleteThrowsApiExceptionOnErrorStatus(): void
    {
        $client = new CurlHttpClient();

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $client->delete($this->url('/error/404'));
    }

    public function testResponseHeadersAreParsed(): void
    {
        $client = new CurlHttpClient();
        $result = $client->get($this->url('/with-headers'));

        $this->assertArrayHasKey('x-custom-header', $result['headers']);
        $this->assertSame(['custom-value'], $result['headers']['x-custom-header']);
    }

    public function testApiExceptionContainsResponseHeaders(): void
    {
        $client = new CurlHttpClient();

        try {
            $client->get($this->url('/error-with-headers'));
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(400, $e->statusCode);
            $this->assertArrayHasKey('x-error-code', $e->responseHeaders);
        }
    }

    public function testStatus299IsSuccess(): void
    {
        $client = new CurlHttpClient();
        $result = $client->get($this->url('/status/299'));

        $this->assertSame(299, $result['status']);
    }

    public function testStatus300ThrowsApiException(): void
    {
        $client = new CurlHttpClient();

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(300);

        $client->get($this->url('/status/300'));
    }

    public function testStatus100RangeThrowsException(): void
    {
        $client = new CurlHttpClient();

        // PHP's built-in server cannot serve 1xx status codes,
        // so curl will raise a NetworkException. Verify some exception
        // from the Monri hierarchy is thrown for non-standard statuses.
        $this->expectException(\Kemo\Monri\Exception\MonriException::class);

        $client->get($this->url('/status/199'));
    }
}
