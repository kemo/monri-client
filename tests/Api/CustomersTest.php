<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Api;

use Kemo\Monri\Api\Customers;
use Kemo\Monri\Api\RequestSigner;
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\Exception\ApiException;
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
                'customers' => [$this->customerPayload(), $this->customerPayload(['uuid' => 'c2'])],
            ]),
            'headers' => [],
        ]);

        $customers = new Customers($this->config, $httpClient, $this->signer);
        $result = $customers->list();

        $this->assertCount(2, $result);
        $this->assertInstanceOf(Customer::class, $result[0]);
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
}
