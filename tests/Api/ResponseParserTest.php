<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Api;

use Kemo\Monri\Api\ResponseParser;
use Kemo\Monri\Exception\MonriException;
use PHPUnit\Framework\TestCase;

final class ResponseParserTest extends TestCase
{
    public function testDecodesJsonObject(): void
    {
        $this->assertSame(['a' => 1], ResponseParser::decode('{"a":1}'));
    }

    public function testDecodesTopLevelArray(): void
    {
        $this->assertSame([['a' => 1]], ResponseParser::decode('[{"a":1}]'));
    }

    public function testThrowsMonriExceptionOnMalformedJson(): void
    {
        $this->expectException(MonriException::class);
        $this->expectExceptionMessage('Monri API response is not valid JSON');

        ResponseParser::decode('<html><body>502 Bad Gateway</body></html>');
    }

    public function testWrapsJsonExceptionAsPrevious(): void
    {
        try {
            ResponseParser::decode('not json');
            $this->fail('Expected MonriException');
        } catch (MonriException $e) {
            $this->assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    public function testThrowsMonriExceptionOnScalarJson(): void
    {
        $this->expectException(MonriException::class);
        $this->expectExceptionMessage('Monri API response is not a JSON object');

        ResponseParser::decode('"just a string"');
    }

    public function testThrowsMonriExceptionOnNullJson(): void
    {
        $this->expectException(MonriException::class);
        $this->expectExceptionMessage('Monri API response is not a JSON object');

        ResponseParser::decode('null');
    }

    public function testUsesCustomContextInMessage(): void
    {
        $this->expectException(MonriException::class);
        $this->expectExceptionMessage('Monri callback body is not valid JSON');

        ResponseParser::decode('nope', 'Monri callback body');
    }
}
