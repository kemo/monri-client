<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Exception;

use Kemo\Monri\Exception\AuthenticationException;
use Kemo\Monri\Exception\MonriException;
use Kemo\Monri\Exception\NetworkException;
use PHPUnit\Framework\TestCase;

final class ExceptionTest extends TestCase
{
    public function testMonriExceptionExtendsRuntimeException(): void
    {
        $exception = new MonriException('something went wrong', 42);

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertSame('something went wrong', $exception->getMessage());
        $this->assertSame(42, $exception->getCode());
    }

    public function testMonriExceptionWithPrevious(): void
    {
        $previous = new \Exception('root cause');
        $exception = new MonriException('wrapped', 0, $previous);

        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testNetworkExceptionExtendsMonriException(): void
    {
        $exception = new NetworkException('Connection timed out');

        $this->assertInstanceOf(MonriException::class, $exception);
        $this->assertSame('Connection timed out', $exception->getMessage());
    }

    public function testNetworkExceptionWithCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('curl failed');
        $exception = new NetworkException('Network error', 7, $previous);

        $this->assertSame(7, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testAuthenticationExceptionExtendsMonriException(): void
    {
        $exception = new AuthenticationException('Invalid credentials');

        $this->assertInstanceOf(MonriException::class, $exception);
        $this->assertSame('Invalid credentials', $exception->getMessage());
    }

    public function testAuthenticationExceptionWithCodeAndPrevious(): void
    {
        $previous = new \RuntimeException('auth failed');
        $exception = new AuthenticationException('Unauthorized', 401, $previous);

        $this->assertSame(401, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
