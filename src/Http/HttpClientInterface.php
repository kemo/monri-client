<?php

declare(strict_types=1);

namespace Kemo\Monri\Http;

use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Exception\NetworkException;

interface HttpClientInterface
{
    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string, headers: array<string, list<string>>}
     * @throws ApiException|NetworkException
     */
    public function get(string $url, array $headers = []): array;

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return array{status: int, body: string, headers: array<string, list<string>>}
     * @throws ApiException|NetworkException
     */
    public function post(string $url, array $body, array $headers = []): array;

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string, headers: array<string, list<string>>}
     * @throws ApiException|NetworkException
     */
    public function delete(string $url, array $headers = []): array;
}
