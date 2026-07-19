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
     * Send a POST request.
     *
     * $body is the already-serialized request body and MUST be transmitted
     * byte-for-byte. The Monri request signature in the Authorization header is
     * computed over exactly these bytes, so re-encoding or reformatting the
     * payload here produces a signature the gateway will reject.
     *
     * @param string $body Pre-serialized JSON, sent verbatim
     * @param array<string, string> $headers
     * @return array{status: int, body: string, headers: array<string, list<string>>}
     * @throws ApiException|NetworkException
     */
    public function post(string $url, string $body, array $headers = []): array;

    /**
     * @param array<string, string> $headers
     * @return array{status: int, body: string, headers: array<string, list<string>>}
     * @throws ApiException|NetworkException
     */
    public function delete(string $url, array $headers = []): array;
}
