<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Api;

use Kemo\Monri\Api\Payments;
use Kemo\Monri\Api\RequestSigner;
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Http\HttpClientInterface;
use Kemo\Monri\Model\Payment;
use Kemo\Monri\Model\PaymentStatus;
use PHPUnit\Framework\TestCase;

final class PaymentsTest extends TestCase
{
    private Config $config;
    private RequestSigner $signer;

    protected function setUp(): void
    {
        $this->config = new Config('key', 'token', Environment::Test);
        $this->signer = new RequestSigner($this->config);
    }

    public function testCreate(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')->willReturn([
            'status' => 200,
            'body' => json_encode([
                'id' => 'pay_123',
                'client_secret' => 'cs_secret',
                'status' => 'approved',
            ]),
            'headers' => [],
        ]);

        $payments = new Payments($this->config, $httpClient, $this->signer);
        $result = $payments->create([
            'order_number' => 'ORD-001',
            'amount' => 5000,
            'currency' => 'EUR',
            'order_info' => 'GOD Club membership',
        ]);

        $this->assertInstanceOf(Payment::class, $result);
        $this->assertSame('pay_123', $result->id);
        $this->assertSame('cs_secret', $result->clientSecret);
        $this->assertSame('approved', $result->status);
    }

    public function testCreateSendsWp3AuthHeader(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('post')
            ->with(
                $this->stringContains('/v2/payment/new'),
                $this->anything(),
                $this->callback(fn ($headers) => str_starts_with($headers['Authorization'] ?? '', 'WP3-v2.1 ')),
            )
            ->willReturn([
                'status' => 200,
                'body' => json_encode(['id' => 'p1', 'client_secret' => 's', 'status' => 'approved']),
                'headers' => [],
            ]);

        $payments = new Payments($this->config, $httpClient, $this->signer);
        $payments->create(['order_number' => 'ORD-1', 'amount' => 100, 'currency' => 'EUR', 'order_info' => 'test']);
    }

    public function testCreateThrowsOnApiError(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')->willThrowException(
            new ApiException(422, '{"status":"invalid-request","message":"order_info required"}'),
        );

        $payments = new Payments($this->config, $httpClient, $this->signer);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(422);
        $payments->create(['order_number' => 'ORD-1', 'amount' => 100, 'currency' => 'EUR', 'order_info' => '']);
    }

    public function testDefaultTransactionTypeIsPurchase(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('post')
            ->with(
                $this->anything(),
                $this->callback(fn ($body) => ($body['transaction_type'] ?? '') === 'purchase'),
                $this->anything(),
            )
            ->willReturn([
                'status' => 200,
                'body' => json_encode(['id' => 'p1', 'client_secret' => 's', 'status' => 'approved']),
                'headers' => [],
            ]);

        $payments = new Payments($this->config, $httpClient, $this->signer);
        $payments->create(['order_number' => 'ORD-1', 'amount' => 100, 'currency' => 'EUR', 'order_info' => 'test']);
    }

    public function testUpdate(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')->willReturn([
            'status' => 200,
            'body' => json_encode([
                'id' => 'pay_123',
                'client_secret' => 'cs_secret',
                'status' => 'approved',
            ]),
            'headers' => [],
        ]);

        $payments = new Payments($this->config, $httpClient, $this->signer);
        $result = $payments->update('pay_123', ['amount' => 2000]);

        $this->assertInstanceOf(Payment::class, $result);
    }

    public function testStatus(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('get')->willReturn([
            'status' => 200,
            'body' => json_encode([
                'status' => 'approved',
                'payment_status' => 'completed',
                'client_secret' => 'cs_secret',
                'payment_result' => [
                    'currency' => 'EUR',
                    'amount' => 5000,
                    'order_number' => 'ORD-001',
                    'created_at' => '2026-03-08T12:00:00Z',
                    'status' => 'approved',
                    'transaction_type' => 'purchase',
                ],
            ]),
            'headers' => [],
        ]);

        $payments = new Payments($this->config, $httpClient, $this->signer);
        $result = $payments->status('pay_123');

        $this->assertInstanceOf(PaymentStatus::class, $result);
        $this->assertSame('approved', $result->status);
        $this->assertSame('completed', $result->paymentStatus);
        $this->assertNotNull($result->result);
        $this->assertSame(5000, $result->result->amount);
        $this->assertSame('EUR', $result->result->currency);
    }

    public function testStatusUsesUrlEncodedId(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('get')
            ->with($this->stringContains(rawurlencode('pay/special')))
            ->willReturn([
                'status' => 200,
                'body' => json_encode([
                    'status' => 'approved',
                    'payment_status' => 'completed',
                    'client_secret' => 'cs',
                ]),
                'headers' => [],
            ]);

        $payments = new Payments($this->config, $httpClient, $this->signer);
        $payments->status('pay/special');
    }
}
