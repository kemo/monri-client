<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Api;

use Kemo\Monri\Api\Callbacks;
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\Exception\AuthenticationException;
use Kemo\Monri\Exception\MonriException;
use PHPUnit\Framework\TestCase;

final class CallbacksTest extends TestCase
{
    private const MERCHANT_KEY = 'merchant_key_123';

    private Callbacks $callbacks;

    protected function setUp(): void
    {
        $this->callbacks = new Callbacks(
            new Config(self::MERCHANT_KEY, 'auth_token_456', Environment::Test),
        );
    }

    private static function sign(string $body): string
    {
        return 'WP3-callback ' . hash('sha512', self::MERCHANT_KEY . $body);
    }

    private static function approvedBody(): string
    {
        return json_encode([
            'id' => 12345,
            'order_number' => 'order-abc-123',
            'amount' => 2500,
            'currency' => 'BAM',
            'status' => 'approved',
            'approval_code' => '123456',
            'response_code' => '0000',
            'response_message' => 'approved',
            'masked_pan' => '411111-xxx-xxx-1111',
            'cc_type' => 'visa',
            'transaction_type' => 'purchase',
            'created_at' => '2026-07-18T12:00:00+02:00',
            'number_of_installments' => null,
            'custom_params' => '{"source":"e2e"}',
        ], JSON_THROW_ON_ERROR);
    }

    public function testVerifyAcceptsValidSignature(): void
    {
        $body = self::approvedBody();

        $this->assertTrue($this->callbacks->verify($body, self::sign($body)));
    }

    public function testVerifyRejectsTamperedBody(): void
    {
        $body = self::approvedBody();
        $tampered = str_replace('2500', '1', $body);

        $this->assertFalse($this->callbacks->verify($tampered, self::sign($body)));
    }

    public function testVerifyRejectsMissingHeader(): void
    {
        $this->assertFalse($this->callbacks->verify(self::approvedBody(), null));
        $this->assertFalse($this->callbacks->verify(self::approvedBody(), ''));
        $this->assertFalse($this->callbacks->verify(self::approvedBody(), '   '));
    }

    public function testVerifyRejectsWrongScheme(): void
    {
        $body = self::approvedBody();
        $digest = hash('sha512', self::MERCHANT_KEY . $body);

        $this->assertFalse($this->callbacks->verify($body, 'WP3-v2 ' . $digest));
        $this->assertFalse($this->callbacks->verify($body, 'Bearer ' . $digest));
    }

    public function testVerifyRejectsMalformedHeader(): void
    {
        $body = self::approvedBody();

        $this->assertFalse($this->callbacks->verify($body, 'WP3-callback'));
        $this->assertFalse($this->callbacks->verify($body, 'WP3-callback a b c'));
    }

    public function testVerifyIsCaseInsensitiveOnSchemeAndDigest(): void
    {
        $body = self::approvedBody();
        $digest = strtoupper(hash('sha512', self::MERCHANT_KEY . $body));

        $this->assertTrue($this->callbacks->verify($body, 'wp3-callback ' . $digest));
    }

    public function testParseReturnsHydratedPayload(): void
    {
        $body = self::approvedBody();

        $payload = $this->callbacks->parse($body, self::sign($body));

        $this->assertSame('order-abc-123', $payload->orderNumber);
        $this->assertSame('approved', $payload->status);
        $this->assertTrue($payload->isApproved());
        $this->assertSame(12345, $payload->id);
        $this->assertSame(2500, $payload->amount);
        $this->assertSame('BAM', $payload->currency);
        $this->assertSame('123456', $payload->approvalCode);
        $this->assertSame('411111-xxx-xxx-1111', $payload->maskedPan);
        $this->assertSame('visa', $payload->ccType);
        $this->assertSame(['source' => 'e2e'], $payload->customParams);
        $this->assertNull($payload->numberOfInstallments);
    }

    public function testParseDeclinedPayload(): void
    {
        $body = json_encode([
            'order_number' => 'order-declined',
            'status' => 'declined',
            'response_code' => '0005',
            'response_message' => 'declined',
        ], JSON_THROW_ON_ERROR);

        $payload = $this->callbacks->parse($body, self::sign($body));

        $this->assertFalse($payload->isApproved());
        $this->assertSame('declined', $payload->status);
        $this->assertSame('0005', $payload->responseCode);
        $this->assertNull($payload->amount);
    }

    public function testParseThrowsOnInvalidSignature(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->callbacks->parse(self::approvedBody(), 'WP3-callback deadbeef');
    }

    public function testParseThrowsOnInvalidJson(): void
    {
        $body = 'not-json';

        $this->expectException(MonriException::class);

        $this->callbacks->parse($body, self::sign($body));
    }

    public function testParseThrowsOnNonObjectJson(): void
    {
        $body = '"just a string"';

        $this->expectException(MonriException::class);

        $this->callbacks->parse($body, self::sign($body));
    }
}
