<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Api;

use Kemo\Monri\Api\Customers;
use Kemo\Monri\Api\Payments;
use Kemo\Monri\Api\RequestSigner;
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\Http\HttpClientInterface;
use PHPUnit\Framework\TestCase;

/**
 * The Authorization digest is computed over the request body, so the bytes that
 * go out on the wire must be exactly the bytes that were signed.
 *
 * These previously could not be asserted: the API layer serialized the payload
 * to build the digest and then handed the *array* to the transport, which
 * serialized it a second time. The two encodes agreed only because both used
 * identical flags - a coincidence no test enforced.
 */
final class SignedBodyIntegrityTest extends TestCase
{
    private Config $config;
    private RequestSigner $signer;

    protected function setUp(): void
    {
        $this->config = new Config('merchant-key', 'auth-token', Environment::Test);
        $this->signer = new RequestSigner($this->config);
    }

    /**
     * Recompute the digest over the captured body and compare against the
     * Authorization header the client actually sent.
     */
    private function assertSignatureCoversBody(string $path, string $body, string $authorization): void
    {
        $parts = explode(' ', $authorization);
        $this->assertCount(4, $parts, 'Authorization header should have 4 space-separated parts');
        [$scheme, $token, $timestamp, $digest] = $parts;

        $this->assertSame('WP3-v2.1', $scheme);
        $this->assertSame('auth-token', $token);

        $expected = hash('sha512', 'merchant-key' . $timestamp . 'auth-token' . $path . $body);

        $this->assertSame(
            $expected,
            $digest,
            'Digest does not cover the transmitted body - the signed bytes and the wire bytes have diverged',
        );
    }

    /**
     * @return array{client: HttpClientInterface, captured: object}
     */
    private function spyClient(string $responseBody): array
    {
        $captured = new \stdClass();
        $captured->body = null;
        $captured->headers = [];

        $client = $this->createMock(HttpClientInterface::class);
        $client->method('post')->willReturnCallback(
            function (string $url, string $body, array $headers) use ($captured, $responseBody): array {
                $captured->body = $body;
                $captured->headers = $headers;

                return ['status' => 200, 'body' => $responseBody, 'headers' => []];
            },
        );

        return ['client' => $client, 'captured' => $captured];
    }

    public function testPaymentCreateSignsTheTransmittedBody(): void
    {
        $spy = $this->spyClient(json_encode(['id' => 'p1', 'client_secret' => 's', 'status' => 'approved']));

        $payments = new Payments($this->config, $spy['client'], $this->signer);
        $payments->create([
            'order_number' => 'ORD-1',
            'amount' => 100,
            'currency' => 'EUR',
            'order_info' => 'test order',
        ]);

        $this->assertIsString($spy['captured']->body);
        $this->assertSignatureCoversBody(
            '/v2/payment/new',
            $spy['captured']->body,
            $spy['captured']->headers['Authorization'],
        );
    }

    /**
     * Slashes and non-ASCII are exactly where two independent json_encode calls
     * with different flags would diverge.
     */
    public function testSignatureHoldsForPayloadWithSlashesAndUnicode(): void
    {
        $spy = $this->spyClient(json_encode(['id' => 'p1', 'client_secret' => 's', 'status' => 'approved']));

        $payments = new Payments($this->config, $spy['client'], $this->signer);
        $payments->create([
            'order_number' => 'ORD-1',
            'amount' => 100,
            'currency' => 'EUR',
            'order_info' => 'https://example.com/a/b - Šćžđč',
        ]);

        $body = $spy['captured']->body;
        $this->assertIsString($body);
        $this->assertStringContainsString('\\/', $body, 'Expected escaped slashes in the transmitted body');
        $this->assertSignatureCoversBody('/v2/payment/new', $body, $spy['captured']->headers['Authorization']);
    }

    public function testCustomerCreateSignsTheTransmittedBody(): void
    {
        $spy = $this->spyClient(json_encode([
            'uuid' => 'c1',
            'merchant_customer_id' => 'm1',
            'status' => 'approved',
        ]));

        $customers = new Customers($this->config, $spy['client'], $this->signer);
        $customers->create(['merchant_customer_id' => 'm1', 'email' => 'fan@god.ba']);

        $this->assertIsString($spy['captured']->body);
        $this->assertSignatureCoversBody(
            '/v2/customers',
            $spy['captured']->body,
            $spy['captured']->headers['Authorization'],
        );
    }

    public function testCustomerUpdateSignsTheTransmittedBody(): void
    {
        $spy = $this->spyClient(json_encode([
            'uuid' => 'c1',
            'merchant_customer_id' => 'm1',
            'status' => 'approved',
        ]));

        $customers = new Customers($this->config, $spy['client'], $this->signer);
        $customers->update('c1', ['email' => 'new@god.ba']);

        $this->assertIsString($spy['captured']->body);
        $this->assertSignatureCoversBody(
            '/v2/customers/c1',
            $spy['captured']->body,
            $spy['captured']->headers['Authorization'],
        );
    }
}
