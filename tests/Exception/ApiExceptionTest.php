<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Exception;

use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Exception\MonriException;
use PHPUnit\Framework\TestCase;

final class ApiExceptionTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $exception = new ApiException(422, '{"error":"invalid"}', ['content-type' => ['application/json']]);

        $this->assertSame(422, $exception->statusCode);
        $this->assertSame('{"error":"invalid"}', $exception->responseBody);
        $this->assertSame(['content-type' => ['application/json']], $exception->responseHeaders);
        $this->assertSame(422, $exception->getCode());
        $this->assertSame('Monri API error (HTTP 422)', $exception->getMessage());
    }

    public function testConstructorUsesCustomMessage(): void
    {
        $exception = new ApiException(500, '', [], 'Custom error message');

        $this->assertSame('Custom error message', $exception->getMessage());
    }

    public function testConstructorWithPreviousException(): void
    {
        $previous = new \RuntimeException('root cause');
        $exception = new ApiException(400, '', [], '', $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testExtendsMonriException(): void
    {
        $exception = new ApiException(400, '');

        $this->assertInstanceOf(MonriException::class, $exception);
    }

    public function testDecodedBodyReturnsDecodedJson(): void
    {
        $body = json_encode(['status' => 'declined', 'message' => 'Insufficient funds']);
        $exception = new ApiException(402, $body);

        $decoded = $exception->decodedBody();

        $this->assertSame(['status' => 'declined', 'message' => 'Insufficient funds'], $decoded);
    }

    public function testDecodedBodyReturnsEmptyArrayForInvalidJson(): void
    {
        $exception = new ApiException(500, 'not valid json {{{');

        $this->assertSame([], $exception->decodedBody());
    }

    public function testDecodedBodyReturnsEmptyArrayForEmptyBody(): void
    {
        $exception = new ApiException(500, '');

        $this->assertSame([], $exception->decodedBody());
    }

    public function testDefaultResponseHeaders(): void
    {
        $exception = new ApiException(404, 'not found');

        $this->assertSame([], $exception->responseHeaders);
    }
}
