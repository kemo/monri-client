<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\MockServer;

/**
 * Starts a local PHP mock server for integration testing.
 *
 * Use in PHPUnit test classes:
 *
 *     use MockServerTrait;
 *
 *     public static function setUpBeforeClass(): void { static::startServer(); }
 *     public static function tearDownAfterClass(): void { static::stopServer(); }
 *     protected function setUp(): void { $this->resetServer(); }
 */
trait MockServerTrait
{
    private static string $serverHost = '127.0.0.1';
    private static int $serverPort = 0;
    /** @var resource|false */
    private static $serverProcess = false;

    protected static function startServer(): void
    {
        // Find a free port
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_bind($sock, self::$serverHost, 0);
        socket_getsockname($sock, $addr, $port);
        self::$serverPort = $port;
        socket_close($sock);

        $router = __DIR__ . '/router.php';
        $cmd = sprintf('php -S %s:%d %s', self::$serverHost, self::$serverPort, escapeshellarg($router));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        self::$serverProcess = proc_open($cmd, $descriptors, $pipes);

        // Wait for server to become available
        $maxWait = 50;
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
            throw new \RuntimeException('Mock server failed to start');
        }
    }

    protected static function stopServer(): void
    {
        if (self::$serverProcess !== false) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }
    }

    protected static function serverBaseUrl(): string
    {
        return sprintf('http://%s:%d', self::$serverHost, self::$serverPort);
    }

    protected function resetServer(): void
    {
        $ch = curl_init(self::serverBaseUrl() . '/__reset');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        curl_exec($ch);
    }

    /**
     * Seed a payment method via the test helper endpoint.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    protected function seedPaymentMethod(string $customerUuid, array $params = []): array
    {
        $ch = curl_init(self::serverBaseUrl() . '/v2/customers/' . rawurlencode($customerUuid) . '/payment-methods');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($params),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $result = curl_exec($ch);

        /** @var array<string, mixed> */
        return json_decode($result, true);
    }
}
