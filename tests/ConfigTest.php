<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests;

use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testBaseUrlTest(): void
    {
        $config = new Config('key', 'token', Environment::Test);
        $this->assertSame('https://ipgtest.monri.com', $config->baseUrl());
    }

    public function testBaseUrlProduction(): void
    {
        $config = new Config('key', 'token', Environment::Production);
        $this->assertSame('https://ipg.monri.com', $config->baseUrl());
    }

    public function testDigest(): void
    {
        $config = new Config('merchant_key', 'token');
        $expected = hash('sha512', 'merchant_key' . 'some_data');
        $this->assertSame($expected, $config->digest('some_data'));
    }
}
