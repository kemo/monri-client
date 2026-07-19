<?php

declare(strict_types=1);

namespace Kemo\Monri\Api;

use Kemo\Monri\Exception\MonriException;

/**
 * Decodes JSON response bodies into arrays.
 *
 * Exists so that a malformed body - a proxy error page served with a 200, a
 * truncated response - raises a MonriException rather than a bare
 * \JsonException, which sits outside this library's exception hierarchy and so
 * cannot be caught by callers guarding against MonriException.
 */
final class ResponseParser
{
    /**
     * @return array<mixed>
     * @throws MonriException
     */
    public static function decode(string $body, string $context = 'Monri API response'): array
    {
        try {
            $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new MonriException($context . ' is not valid JSON', 0, $e);
        }

        if (!\is_array($decoded)) {
            throw new MonriException($context . ' is not a JSON object');
        }

        return $decoded;
    }

    /**
     * Decode a response body that must be a JSON object (not a list).
     *
     * @return array<string, mixed>
     * @throws MonriException
     */
    public static function decodeObject(string $body, string $context = 'Monri API response'): array
    {
        $decoded = self::decode($body, $context);

        if ($decoded !== [] && array_is_list($decoded)) {
            throw new MonriException($context . ' is not a JSON object');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
