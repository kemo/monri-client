<?php

declare(strict_types=1);

namespace Kemo\Monri\Api;

use Kemo\Monri\Config;

/**
 * Builds the WP3-v2.1 Authorization header required by the Monri API.
 *
 * Formula: SHA512(merchant_key + timestamp + authenticity_token + path + body)
 * Header:  WP3-v2.1 {authenticity_token} {timestamp} {digest}
 */
final class RequestSigner
{
    public function __construct(private readonly Config $config) {}

    /**
     * @param string $path     URL path only, e.g. "/v2/payment/new"
     * @param string $body     Raw JSON request body (empty string for GET/DELETE)
     */
    public function header(string $path, string $body = ''): string
    {
        $timestamp = time();
        $digest = hash(
            'sha512',
            $this->config->merchantKey
            . $timestamp
            . $this->config->authenticityToken
            . $path
            . $body,
        );

        return sprintf(
            'WP3-v2.1 %s %d %s',
            $this->config->authenticityToken,
            $timestamp,
            $digest,
        );
    }
}
