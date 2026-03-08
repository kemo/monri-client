<?php

declare(strict_types=1);

namespace Kemo\Monri\Exception;

final class ApiException extends MonriException
{
    /** @param array<string, list<string>> $responseHeaders */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $responseBody,
        public readonly array $responseHeaders = [],
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message ?: "Monri API error (HTTP {$statusCode})",
            $statusCode,
            $previous,
        );
    }

    /** @return array<string, mixed> */
    public function decodedBody(): array
    {
        try {
            /** @var array<string, mixed> */
            return json_decode($this->responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
    }
}
