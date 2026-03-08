<?php

declare(strict_types=1);

namespace Kemo\Monri\Http;

use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Exception\NetworkException;

final class CurlHttpClient implements HttpClientInterface
{
    private const USER_AGENT = 'MonriClient/PHP 1.0';
    private const TIMEOUT = 30;

    public function get(string $url, array $headers = []): array
    {
        return $this->request('GET', $url, null, $headers);
    }

    public function post(string $url, array $body, array $headers = []): array
    {
        return $this->request('POST', $url, $body, $headers);
    }

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
        $ch = curl_init($url);

        if ($ch === false) {
            throw new NetworkException('Failed to initialize cURL');
        }

        $responseHeaders = [];

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => function ($ch, string $header) use (&$responseHeaders): int {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $name = strtolower(trim($parts[0]));
                    $responseHeaders[$name][] = trim($parts[1]);
                }
                return $len;
            },
        ]);

        $httpHeaders = ['Accept: application/json'];

        if ($body !== null) {
            $httpHeaders[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }

        foreach ($headers as $name => $value) {
            $httpHeaders[] = "{$name}: {$value}";
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeaders);

        $result = curl_exec($ch);
        $errno = curl_errno($ch);

        if ($errno !== 0 || $result === false) {
            $error = curl_error($ch);
            throw new NetworkException("cURL error ({$errno}): {$error}");
        }

        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        /** @var string $result */
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException($statusCode, $result, $responseHeaders);
        }

        return [
            'status' => $statusCode,
            'body' => $result,
            'headers' => $responseHeaders,
        ];
    }
}
