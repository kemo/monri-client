<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Api;

use Kemo\Monri\Api\Tokens;
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use PHPUnit\Framework\TestCase;

final class TokensTest extends TestCase
{
    private Tokens $tokens;

    protected function setUp(): void
    {
        $this->tokens = new Tokens(
            new Config('test_merchant_key', 'test_token', Environment::Test),
        );
    }

    public function testGenerateWithRandomId(): void
    {
        $token = $this->tokens->generate();

        $this->assertNotEmpty($token->id);
        $this->assertGreaterThan(0, $token->timestamp);
        $this->assertNotEmpty($token->digest);
        $this->assertSame(128, strlen($token->digest)); // SHA-512 hex
    }

    public function testGenerateWithSpecificId(): void
    {
        $token = $this->tokens->generate('my-card-id');

        $this->assertSame('my-card-id', $token->id);

        $expectedDigest = hash('sha512', 'test_merchant_key' . 'my-card-id' . $token->timestamp);
        $this->assertSame($expectedDigest, $token->digest);
    }

    public function testGenerateProducesUniqueTokens(): void
    {
        $a = $this->tokens->generate();
        $b = $this->tokens->generate();

        // IDs should differ (random)
        $this->assertNotSame($a->id, $b->id);
    }
}
