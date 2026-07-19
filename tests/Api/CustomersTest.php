<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Api;

use Kemo\Monri\Api\Customers;
use Kemo\Monri\Api\RequestSigner;
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Exception\MonriException;
use Kemo\Monri\Http\HttpClientInterface;
use Kemo\Monri\Model\Customer;
use Kemo\Monri\Model\PaymentMethod;
use PHPUnit\Framework\TestCase;

final class CustomersTest extends TestCase
{
    private Config $config;
    private RequestSigner $signer;

    protected function setUp(): void
    {
        $this->config = new Config('key', 'token', Environment::Test);
        $this->signer = new RequestSigner($this->config);
    }

    private function customerPayload(array $overrides = []): array
    {
        return array_merge([
            'uuid' => 'cust-uuid-123',
            'merchant_customer_id' => 'my-user-42',
            'email' => 'fan@god.ba',
            'name' => 'Adnan Fest',
            'phone' => '+38761000000',
            'status' => 'approved',
        ], $overrides);
    }

    private function mockGetResponse(array $payload): HttpClientInterface
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('get')->willReturn([
            'status' => 200,
            'body' => json_encode($payload),
            'headers' => [],
        ]);
        return $httpClient;
    }

    private function mockPostResponse(array $payload): HttpClientInterface
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')->willReturn([
            'status' => 200,
            'body' => json_encode($payload),
            'headers' => [],
        ]);
        return $httpClient;
    }

    public function testCreate(): void
    {
        $customers = new Customers($this->config, $this->mockPostResponse($this->customerPayload()), $this->signer);
        $result = $customers->create([
            'merchant_customer_id' => 'my-user-42',
            'email' => 'fan@god.ba',
            'name' => 'Adnan Fest',
        ]);

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertSame('cust-uuid-123', $result->uuid);
        $this->assertSame('my-user-42', $result->merchantCustomerId);
        $this->assertSame('fan@god.ba', $result->email);
        $this->assertSame('approved', $result->status);
    }

    public function testCreateSendsWp3AuthHeader(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->callback(fn ($h) => str_starts_with($h['Authorization'] ?? '', 'WP3-v2.1 ')),
            )
            ->willReturn([
                'status' => 200,
                'body' => json_encode($this->customerPayload()),
                'headers' => [],
            ]);

        $customers = new Customers($this->config, $httpClient, $this->signer);
        $customers->create(['merchant_customer_id' => 'x']);
    }

    public function testUpdate(): void
    {
        $customers = new Customers(
            $this->config,
            $this->mockPostResponse($this->customerPayload(['name' => 'Updated Name'])),
            $this->signer,
        );

        $result = $customers->update('cust-uuid-123', ['name' => 'Updated Name']);

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertSame('Updated Name', $result->name);
    }

    public function testFind(): void
    {
        $customers = new Customers($this->config, $this->mockGetResponse($this->customerPayload()), $this->signer);
        $result = $customers->find('cust-uuid-123');

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertSame('cust-uuid-123', $result->uuid);
    }

    public function testFindByMerchantId(): void
    {
        $customers = new Customers($this->config, $this->mockGetResponse($this->customerPayload()), $this->signer);
        $result = $customers->findByMerchantId('my-user-42');

        $this->assertInstanceOf(Customer::class, $result);
        $this->assertSame('my-user-42', $result->merchantCustomerId);
    }

    public function testList(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('get')->willReturn([
            'status' => 200,
            'body' => json_encode([
                'data' => [$this->customerPayload(), $this->customerPayload(['uuid' => 'c2'])],
            ]),
            'headers' => [],
        ]);

        $customers = new Customers($this->config, $httpClient, $this->signer);
        $result = $customers->list();

        $this->assertCount(2, $result);
        $this->assertInstanceOf(Customer::class, $result[0]);
    }

    public function testListAcceptsTopLevelArrayResponse(): void
    {
        $httpClient = $this->mockGetResponse([$this->customerPayload(), $this->customerPayload(['uuid' => 'c2'])]);

        $customers = new Customers($this->config, $httpClient, $this->signer);
        $result = $customers->list();

        $this->assertCount(2, $result);
        $this->assertSame('cust-uuid-123', $result[0]->uuid);
    }

    public function testListReturnsEmptyArrayForEmptyEnvelope(): void
    {
        $httpClient = $this->mockGetResponse(['data' => []]);

        $customers = new Customers($this->config, $httpClient, $this->signer);

        $this->assertSame([], $customers->list());
    }

    public function testListThrowsMonriExceptionOnUnexpectedShape(): void
    {
        $httpClient = $this->mockGetResponse(['error' => 'something went wrong']);

        $customers = new Customers($this->config, $httpClient, $this->signer);

        $this->expectException(MonriException::class);
        $this->expectExceptionMessage('Unexpected response shape');
        $customers->list();
    }

    public function testPaymentMethodsThrowsMonriExceptionOnUnexpectedShape(): void
    {
        $httpClient = $this->mockGetResponse(['data' => 'not-a-list']);

        $customers = new Customers($this->config, $httpClient, $this->signer);

        $this->expectException(MonriException::class);
        $this->expectExceptionMessage('Unexpected response shape');
        $customers->paymentMethods('cust-uuid-123');
    }

    /**
     * Behaviour change: this used to return [] via `$data['data'] ?? []`, which
     * silently turned an unrecognised envelope into an empty result set.
     */
    public function testPaymentMethodsThrowsWhenEnvelopeKeyIsMissing(): void
    {
        $httpClient = $this->mockGetResponse(['total' => 0]);

        $customers = new Customers($this->config, $httpClient, $this->signer);

        $this->expectException(MonriException::class);
        $customers->paymentMethods('cust-uuid-123');
    }

    public function testDelete(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('delete')
            ->with($this->stringContains('/v2/customers/cust-uuid-123'))
            ->willReturn(['status' => 200, 'body' => '{}', 'headers' => []]);

        $customers = new Customers($this->config, $httpClient, $this->signer);
        $customers->delete('cust-uuid-123');
    }

    public function testDeleteUsesUrlEncodedId(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('delete')
            ->with($this->stringContains(rawurlencode('uuid/with/slashes')))
            ->willReturn(['status' => 200, 'body' => '{}', 'headers' => []]);

        $customers = new Customers($this->config, $httpClient, $this->signer);
        $customers->delete('uuid/with/slashes');
    }

    public function testFindThrowsOnNotFound(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('get')->willThrowException(
            new ApiException(404, '{"status":"invalid-request","message":"Customer not found"}'),
        );

        $customers = new Customers($this->config, $httpClient, $this->signer);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);
        $customers->find('nonexistent');
    }

    public function testPaymentMethods(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('get')->willReturn([
            'status' => 200,
            'body' => json_encode([
                'status' => 'approved',
                'data' => [
                    [
                        'id' => 'pm-001',
                        'status' => 'active',
                        'masked_pan' => '411111******1111',
                        'expiration_date' => '12/27',
                        'expired' => false,
                        'token' => 'card-token-abc',
                        'customer_uuid' => 'cust-uuid-123',
                    ],
                ],
            ]),
            'headers' => [],
        ]);

        $customers = new Customers($this->config, $httpClient, $this->signer);
        $result = $customers->paymentMethods('cust-uuid-123');

        $this->assertCount(1, $result);
        $this->assertInstanceOf(PaymentMethod::class, $result[0]);
        $this->assertSame('pm-001', $result[0]->id);
        $this->assertSame('411111******1111', $result[0]->maskedPan);
        $this->assertFalse($result[0]->expired);
    }

    public function testPaymentMethodsReturnsEmptyArrayWhenNone(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('get')->willReturn([
            'status' => 200,
            'body' => json_encode(['status' => 'approved', 'data' => []]),
            'headers' => [],
        ]);

        $customers = new Customers($this->config, $httpClient, $this->signer);
        $result = $customers->paymentMethods('cust-uuid-123');

        $this->assertSame([], $result);
    }

    /**
     * Monri's WP3-v2.1 digest must cover the request-target exactly as sent,
     * query string included; ipgtest returns 401 for path-only digests on
     * query-string URLs (verified live 2026-07-19).
     */
    public function testListDigestCoversQueryString(): void
    {
        $this->assertDigestCoversRequestTarget(
            '/v2/customers?limit=3&offset=0',
            static fn (Customers $c) => $c->list(limit: 3, offset: 0),
            ['data' => []],
        );
    }

    public function testPaymentMethodsDigestCoversQueryString(): void
    {
        $this->assertDigestCoversRequestTarget(
            '/v2/customers/cust-uuid-123/payment-methods?limit=50&offset=0',
            static fn (Customers $c) => $c->paymentMethods('cust-uuid-123'),
            ['status' => 'approved', 'data' => []],
        );
    }

    /**
     * @param callable(Customers): mixed $call
     * @param array<string, mixed> $responsePayload
     */
    private function assertDigestCoversRequestTarget(
        string $expectedTarget,
        callable $call,
        array $responsePayload,
    ): void {
        $config = $this->config;

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('get')
            ->with(
                $this->identicalTo($config->baseUrl() . $expectedTarget),
                $this->callback(function (array $headers) use ($config, $expectedTarget): bool {
                    $parts = explode(' ', $headers['Authorization'] ?? '');
                    if (count($parts) !== 4 || $parts[0] !== 'WP3-v2.1') {
                        return false;
                    }
                    [, $token, $timestamp, $digest] = $parts;
                    $expected = hash(
                        'sha512',
                        $config->merchantKey . $timestamp . $config->authenticityToken . $expectedTarget,
                    );

                    return $token === $config->authenticityToken && $digest === $expected;
                }),
            )
            ->willReturn(['status' => 200, 'body' => json_encode($responsePayload), 'headers' => []]);

        $call(new Customers($config, $httpClient, $this->signer));
    }
}
