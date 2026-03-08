<?php

declare(strict_types=1);

namespace Kemo\Monri\Http;

use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Exception\NetworkException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class PsrHttpClient implements HttpClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /** @inheritDoc */
    public function get(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, null, $headers);
    }

    /** @inheritDoc */
    public function post(string $url, array $body, array $headers = []): array
    {
        return $this->request('POST', $url, $body, $headers);
    }

    /** @inheritDoc */
    public function delete(string $url, array $headers = []): array
    {
        return $this->request('DELETE', $url, null, $headers);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     * @return array{status: int, body: string, headers: array<string, list<string>>}
     */
    private function request(string $method, string $url, ?array $body, array $headers): array
    {
        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', 'MonriClient/PHP 1.0');

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(
                    json_encode($body, JSON_THROW_ON_ERROR),
                ));
        }

        try {
            $response = $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new NetworkException($e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $responseBody = (string) $response->getBody();

        /** @var array<string, list<string>> $responseHeaders */
        $responseHeaders = $response->getHeaders();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException($statusCode, $responseBody, $responseHeaders);
        }

        return [
            'status' => $statusCode,
            'body' => $responseBody,
            'headers' => $responseHeaders,
        ];
    }
}
