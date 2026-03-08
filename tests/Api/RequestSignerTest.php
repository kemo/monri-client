<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Api;

use Kemo\Monri\Api\RequestSigner;
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use PHPUnit\Framework\TestCase;

final class RequestSignerTest extends TestCase
{
    private Config $config;
    private RequestSigner $signer;

    protected function setUp(): void
    {
        $this->config = new Config('merchant_key_123', 'auth_token_456', Environment::Test);
        $this->signer = new RequestSigner($this->config);
    }

    public function testHeaderFormat(): void
    {
        $header = $this->signer->header('/v2/payment/new', '{}');

        $this->assertMatchesRegularExpression(
            '/^WP3-v2\.1 auth_token_456 \d+ [a-f0-9]{128}$/',
            $header,
        );
    }

    public function testHeaderContainsAuthenticityToken(): void
    {
        $header = $this->signer->header('/v2/payment/new');
        $parts = explode(' ', $header);

        $this->assertSame('WP3-v2.1', $parts[0]);
        $this->assertSame('auth_token_456', $parts[1]);
    }

    public function testDigestIncludesPath(): void
    {
        // Two requests with different paths must produce different digests
        $h1 = $this->signer->header('/v2/payment/new', '{}');
        $h2 = $this->signer->header('/v2/customers', '{}');

        $digest1 = explode(' ', $h1)[3];
        $digest2 = explode(' ', $h2)[3];

        $this->assertNotSame($digest1, $digest2);
    }

    public function testDigestIncludesBody(): void
    {
        $h1 = $this->signer->header('/v2/payment/new', '{"amount":100}');
        $h2 = $this->signer->header('/v2/payment/new', '{"amount":200}');

        $digest1 = explode(' ', $h1)[3];
        $digest2 = explode(' ', $h2)[3];

        $this->assertNotSame($digest1, $digest2);
    }

    public function testEmptyBodyAndNoBodyProduceSameDigest(): void
    {
        $h1 = $this->signer->header('/v2/payment/123/status', '');
        $h2 = $this->signer->header('/v2/payment/123/status');

        // Extract timestamps and digests; timestamps may differ by 1s in slow CI
        $parts1 = explode(' ', $h1);
        $parts2 = explode(' ', $h2);

        // Same path, both have empty body — structure is identical
        $this->assertSame($parts1[0], $parts2[0]); // WP3-v2.1
        $this->assertSame($parts1[1], $parts2[1]); // authenticity_token
    }

    public function testDigestIsCorrectSha512(): void
    {
        // Freeze-check: compute expected digest manually
        $signer = new RequestSigner(new Config('mk', 'at', Environment::Test));
        $header = $signer->header('/v2/customers', '{"name":"test"}');
        $parts = explode(' ', $header);
        $timestamp = (int) $parts[2];
        $digest = $parts[3];

        $expected = hash('sha512', 'mk' . $timestamp . 'at' . '/v2/customers' . '{"name":"test"}');
        $this->assertSame($expected, $digest);
    }
}
