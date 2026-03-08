<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Http;

use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Exception\NetworkException;
use Kemo\Monri\Http\PsrHttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

final class PsrHttpClientTest extends TestCase
{
    private ClientInterface&MockObject $psrClient;
    private RequestFactoryInterface&MockObject $requestFactory;
    private StreamFactoryInterface&MockObject $streamFactory;
    private RequestInterface&MockObject $request;
    private PsrHttpClient $client;

    protected function setUp(): void
    {
        $this->psrClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->request = $this->createMock(RequestInterface::class);

        // The request mock needs to support fluent withHeader calls
        $this->request->method('withHeader')->willReturnSelf();

        $this->client = new PsrHttpClient(
            $this->psrClient,
            $this->requestFactory,
            $this->streamFactory,
        );
    }

    private function mockSuccessResponse(int $status, string $body, array $headers = []): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($status);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);
        $response->method('getBody')->willReturn($stream);
        $response->method('getHeaders')->willReturn($headers);

        return $response;
    }

    public function testGetReturnsSuccessfulResponse(): void
    {
        $this->requestFactory->method('createRequest')
            ->with('GET', 'https://example.com/api')
            ->willReturn($this->request);

        $response = $this->mockSuccessResponse(200, '{"ok":true}', ['x-request-id' => ['abc']]);
        $this->psrClient->method('sendRequest')->willReturn($response);

        $result = $this->client->get('https://example.com/api');

        $this->assertSame(200, $result['status']);
        $this->assertSame('{"ok":true}', $result['body']);
        $this->assertSame(['x-request-id' => ['abc']], $result['headers']);
    }

    public function testGetPassesCustomHeaders(): void
    {
        $this->requestFactory->method('createRequest')->willReturn($this->request);

        $this->request->expects($this->atLeast(3))
            ->method('withHeader')
            ->willReturnSelf();

        $response = $this->mockSuccessResponse(200, '{}');
        $this->psrClient->method('sendRequest')->willReturn($response);

        $this->client->get('https://example.com/api', ['Authorization' => 'Bearer xyz']);
    }

    public function testGetThrowsApiExceptionOnErrorStatus(): void
    {
        $this->requestFactory->method('createRequest')->willReturn($this->request);

        $response = $this->mockSuccessResponse(422, '{"error":"invalid"}', ['content-type' => ['application/json']]);
        $this->psrClient->method('sendRequest')->willReturn($response);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(422);

        $this->client->get('https://example.com/api');
    }

    public function testGetThrowsNetworkExceptionOnClientException(): void
    {
        $this->requestFactory->method('createRequest')->willReturn($this->request);

        $clientException = new class ('Connection refused') extends \RuntimeException implements
            ClientExceptionInterface {
        };
        $this->psrClient->method('sendRequest')->willThrowException($clientException);

        $this->expectException(NetworkException::class);
        $this->expectExceptionMessage('Connection refused');

        $this->client->get('https://example.com/api');
    }

    public function testPostSendsJsonBody(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->expects($this->once())
            ->method('createStream')
            ->with('{"amount":100}')
            ->willReturn($stream);

        $this->request->method('withBody')->willReturnSelf();

        $this->requestFactory->method('createRequest')
            ->with('POST', 'https://example.com/api')
            ->willReturn($this->request);

        $response = $this->mockSuccessResponse(200, '{"id":"p1"}');
        $this->psrClient->method('sendRequest')->willReturn($response);

        $result = $this->client->post('https://example.com/api', ['amount' => 100]);

        $this->assertSame(200, $result['status']);
        $this->assertSame('{"id":"p1"}', $result['body']);
    }

    public function testPostThrowsApiExceptionOnErrorStatus(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStream')->willReturn($stream);
        $this->request->method('withBody')->willReturnSelf();
        $this->requestFactory->method('createRequest')->willReturn($this->request);

        $response = $this->mockSuccessResponse(500, '{"error":"server error"}');
        $this->psrClient->method('sendRequest')->willReturn($response);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(500);

        $this->client->post('https://example.com/api', ['data' => 'test']);
    }

    public function testPostThrowsNetworkExceptionOnClientException(): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStream')->willReturn($stream);
        $this->request->method('withBody')->willReturnSelf();
        $this->requestFactory->method('createRequest')->willReturn($this->request);

        $clientException = new class ('Timeout') extends \RuntimeException implements ClientExceptionInterface {
        };
        $this->psrClient->method('sendRequest')->willThrowException($clientException);

        $this->expectException(NetworkException::class);

        $this->client->post('https://example.com/api', ['data' => 'test']);
    }

    public function testDeleteReturnsSuccessfulResponse(): void
    {
        $this->requestFactory->method('createRequest')
            ->with('DELETE', 'https://example.com/api/123')
            ->willReturn($this->request);

        $response = $this->mockSuccessResponse(200, '{}');
        $this->psrClient->method('sendRequest')->willReturn($response);

        $result = $this->client->delete('https://example.com/api/123');

        $this->assertSame(200, $result['status']);
    }

    public function testDeleteThrowsApiExceptionOnErrorStatus(): void
    {
        $this->requestFactory->method('createRequest')->willReturn($this->request);

        $response = $this->mockSuccessResponse(404, '{"error":"not found"}');
        $this->psrClient->method('sendRequest')->willReturn($response);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $this->client->delete('https://example.com/api/123');
    }

    public function testNetworkExceptionWrapsOriginalException(): void
    {
        $this->requestFactory->method('createRequest')->willReturn($this->request);

        $original = new class ('DNS lookup failed') extends \RuntimeException implements ClientExceptionInterface {
        };
        $this->psrClient->method('sendRequest')->willThrowException($original);

        try {
            $this->client->get('https://example.com/api');
            $this->fail('Expected NetworkException');
        } catch (NetworkException $e) {
            $this->assertSame($original, $e->getPrevious());
            $this->assertSame('DNS lookup failed', $e->getMessage());
        }
    }

    public function testApiExceptionContainsResponseHeaders(): void
    {
        $this->requestFactory->method('createRequest')->willReturn($this->request);

        $headers = ['x-error-code' => ['E001'], 'content-type' => ['application/json']];
        $response = $this->mockSuccessResponse(400, '{"error":"bad request"}', $headers);
        $this->psrClient->method('sendRequest')->willReturn($response);

        try {
            $this->client->get('https://example.com/api');
            $this->fail('Expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(400, $e->statusCode);
            $this->assertSame('{"error":"bad request"}', $e->responseBody);
            $this->assertSame($headers, $e->responseHeaders);
        }
    }

    public function testStatus299IsSuccess(): void
    {
        $this->requestFactory->method('createRequest')->willReturn($this->request);

        $response = $this->mockSuccessResponse(299, '{"ok":true}');
        $this->psrClient->method('sendRequest')->willReturn($response);

        $result = $this->client->get('https://example.com/api');
        $this->assertSame(299, $result['status']);
    }

    public function testStatus300ThrowsApiException(): void
    {
        $this->requestFactory->method('createRequest')->willReturn($this->request);

        $response = $this->mockSuccessResponse(300, 'redirect');
        $this->psrClient->method('sendRequest')->willReturn($response);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(300);

        $this->client->get('https://example.com/api');
    }

    public function testStatus199ThrowsApiException(): void
    {
        $this->requestFactory->method('createRequest')->willReturn($this->request);

        $response = $this->mockSuccessResponse(199, 'info');
        $this->psrClient->method('sendRequest')->willReturn($response);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(199);

        $this->client->get('https://example.com/api');
    }
}
