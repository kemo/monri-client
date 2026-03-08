<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests;

use Kemo\Monri\Api\Customers;
use Kemo\Monri\Api\Payments;
use Kemo\Monri\Api\Tokens;
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\MonriClient;
use PHPUnit\Framework\TestCase;

final class MonriClientTest extends TestCase
{
    private MonriClient $client;

    protected function setUp(): void
    {
        $this->client = new MonriClient(
            new Config('test_key', 'test_token', Environment::Test),
        );
    }

    public function testPaymentsReturnsSameInstance(): void
    {
        $a = $this->client->payments();
        $b = $this->client->payments();

        $this->assertInstanceOf(Payments::class, $a);
        $this->assertSame($a, $b);
    }

    public function testCustomersReturnsSameInstance(): void
    {
        $a = $this->client->customers();
        $b = $this->client->customers();

        $this->assertInstanceOf(Customers::class, $a);
        $this->assertSame($a, $b);
    }

    public function testTokensReturnsSameInstance(): void
    {
        $a = $this->client->tokens();
        $b = $this->client->tokens();

        $this->assertInstanceOf(Tokens::class, $a);
        $this->assertSame($a, $b);
    }

    public function testAllAccessorsWorkIndependently(): void
    {
        // Unlike the rapttor SDK, accessing one module must not break others
        $tokens = $this->client->tokens();
        $payments = $this->client->payments();
        $customers = $this->client->customers();

        $this->assertInstanceOf(Tokens::class, $tokens);
        $this->assertInstanceOf(Payments::class, $payments);
        $this->assertInstanceOf(Customers::class, $customers);

        // Verify they still return the same instances
        $this->assertSame($tokens, $this->client->tokens());
        $this->assertSame($payments, $this->client->payments());
        $this->assertSame($customers, $this->client->customers());
    }

    public function testConfig(): void
    {
        $config = $this->client->config();
        $this->assertSame('test_key', $config->merchantKey);
        $this->assertSame('test_token', $config->authenticityToken);
        $this->assertSame(Environment::Test, $config->environment);
    }
}
