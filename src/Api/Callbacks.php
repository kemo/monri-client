<?php

declare(strict_types=1);

namespace Kemo\Monri\Api;

use Kemo\Monri\Config;
use Kemo\Monri\Exception\AuthenticationException;
use Kemo\Monri\Exception\MonriException;
use Kemo\Monri\Model\CallbackPayload;

/**
 * Verifies and parses Monri WP3-callback webhook requests.
 *
 * Monri signs each callback with:
 *   Authorization: WP3-callback {digest}
 *   digest = sha512(merchant_key + raw_body)
 */
final class Callbacks
{
    private const SCHEME = 'WP3-callback';

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Check a callback signature. Safe to call with any input.
     *
     * @param string      $rawBody             Raw request body, exactly as received
     * @param string|null $authorizationHeader Full Authorization header value
     */
    public function verify(string $rawBody, ?string $authorizationHeader): bool
    {
        if ($authorizationHeader === null || trim($authorizationHeader) === '') {
            return false;
        }

        $parts = preg_split('/\s+/', trim($authorizationHeader));
        if ($parts === false || \count($parts) !== 2) {
            return false;
        }

        [$scheme, $digest] = $parts;
        if (strcasecmp($scheme, self::SCHEME) !== 0) {
            return false;
        }

        $expected = hash('sha512', $this->config->merchantKey . $rawBody);

        return hash_equals($expected, strtolower($digest));
    }

    /**
     * Verify the signature and hydrate the callback payload.
     *
     * @param string      $rawBody             Raw request body, exactly as received
     * @param string|null $authorizationHeader Full Authorization header value
     *
     * @throws AuthenticationException When the signature is missing or invalid
     * @throws MonriException          When the body is not a JSON object
     */
    public function parse(string $rawBody, ?string $authorizationHeader): CallbackPayload
    {
        if (!$this->verify($rawBody, $authorizationHeader)) {
            throw new AuthenticationException('Invalid Monri callback signature');
        }

        try {
            $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new MonriException('Monri callback body is not valid JSON', 0, $e);
        }

        if (!\is_array($decoded)) {
            throw new MonriException('Monri callback body is not a JSON object');
        }

        /** @var array<string, mixed> $decoded */
        return CallbackPayload::fromArray($decoded);
    }
}
