<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\MockServer;

use Kemo\Monri\Http\HttpClientInterface;

/**
 * Wraps an HttpClientInterface and rewrites URLs to point at a mock server.
 *
 * Replaces the Monri base URL (https://ipgtest.monri.com or https://ipg.monri.com)
 * with a local mock server URL, preserving paths and query strings.
 */
final class MockBaseUrlClient implements HttpClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $inner,
        private readonly string $mockBaseUrl,
    ) {
    }

    /** @inheritDoc */
    public function get(string $url, array $headers = []): array
    {
        return $this->inner->get($this->rewrite($url), $headers);
    }

    /** @inheritDoc */
    public function post(string $url, array $body, array $headers = []): array
    {
        return $this->inner->post($this->rewrite($url), $body, $headers);
    }

    /** @inheritDoc */
    public function delete(string $url, array $headers = []): array
    {
        return $this->inner->delete($this->rewrite($url), $headers);
    }

    private function rewrite(string $url): string
    {
        // Replace known Monri base URLs with mock
        $url = str_replace('https://ipgtest.monri.com', $this->mockBaseUrl, $url);
        $url = str_replace('https://ipg.monri.com', $this->mockBaseUrl, $url);

        return $url;
    }
}
