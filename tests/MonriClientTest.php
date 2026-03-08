<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests;

use Kemo\Monri\Api\Customers;
use Kemo\Monri\Api\Payments;
use Kemo\Monri\Api\Tokens;
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\Exception\MonriException;
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

    public function testFromEnvReadsEnvironmentVariables(): void
    {
        // Set via putenv so getenv() picks them up
        putenv('MONRI_MERCHANT_KEY=env_key');
        putenv('MONRI_AUTHENTICITY_TOKEN=env_token');
        putenv('MONRI_ENVIRONMENT=production');

        try {
            $client = MonriClient::fromEnv();
            $config = $client->config();

            $this->assertSame('env_key', $config->merchantKey);
            $this->assertSame('env_token', $config->authenticityToken);
            $this->assertSame(Environment::Production, $config->environment);
        } finally {
            putenv('MONRI_MERCHANT_KEY');
            putenv('MONRI_AUTHENTICITY_TOKEN');
            putenv('MONRI_ENVIRONMENT');
        }
    }

    public function testFromEnvDefaultsToTestEnvironment(): void
    {
        putenv('MONRI_MERCHANT_KEY=key');
        putenv('MONRI_AUTHENTICITY_TOKEN=token');
        putenv('MONRI_ENVIRONMENT');

        try {
            $client = MonriClient::fromEnv();
            $this->assertSame(Environment::Test, $client->config()->environment);
        } finally {
            putenv('MONRI_MERCHANT_KEY');
            putenv('MONRI_AUTHENTICITY_TOKEN');
        }
    }

    public function testFromEnvThrowsWhenMerchantKeyMissing(): void
    {
        putenv('MONRI_MERCHANT_KEY');
        putenv('MONRI_AUTHENTICITY_TOKEN=token');

        // Also clear $_ENV to be safe
        unset($_ENV['MONRI_MERCHANT_KEY']);

        $this->expectException(MonriException::class);
        $this->expectExceptionMessage('MONRI_MERCHANT_KEY not set');

        try {
            MonriClient::fromEnv();
        } finally {
            putenv('MONRI_AUTHENTICITY_TOKEN');
        }
    }

    public function testFromEnvThrowsWhenAuthenticityTokenMissing(): void
    {
        putenv('MONRI_MERCHANT_KEY=key');
        putenv('MONRI_AUTHENTICITY_TOKEN');

        unset($_ENV['MONRI_AUTHENTICITY_TOKEN']);

        $this->expectException(MonriException::class);
        $this->expectExceptionMessage('MONRI_AUTHENTICITY_TOKEN not set');

        try {
            MonriClient::fromEnv();
        } finally {
            putenv('MONRI_MERCHANT_KEY');
        }
    }

    public function testFromEnvReadsFromEnvSuperglobal(): void
    {
        // Clear getenv values, set via $_ENV
        putenv('MONRI_MERCHANT_KEY');
        putenv('MONRI_AUTHENTICITY_TOKEN');
        putenv('MONRI_ENVIRONMENT');

        $_ENV['MONRI_MERCHANT_KEY'] = 'superglobal_key';
        $_ENV['MONRI_AUTHENTICITY_TOKEN'] = 'superglobal_token';
        $_ENV['MONRI_ENVIRONMENT'] = 'test';

        try {
            $client = MonriClient::fromEnv();
            $config = $client->config();

            $this->assertSame('superglobal_key', $config->merchantKey);
            $this->assertSame('superglobal_token', $config->authenticityToken);
        } finally {
            unset($_ENV['MONRI_MERCHANT_KEY'], $_ENV['MONRI_AUTHENTICITY_TOKEN'], $_ENV['MONRI_ENVIRONMENT']);
        }
    }
}
