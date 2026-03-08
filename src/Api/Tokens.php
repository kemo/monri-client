<?php

declare(strict_types=1);

namespace Kemo\Monri\Api;

use Kemo\Monri\Config;
use Kemo\Monri\Model\TempCardToken;

final class Tokens
{
    public function __construct(
        private readonly Config $config,
    ) {}

    /**
     * Generate a temporary card tokenization token (used client-side).
     */
    public function generate(?string $id = null): TempCardToken
    {
        $id ??= (string) random_int(1_000_000, 9_999_999);
        $timestamp = time();
        $digest = $this->config->digest($id . $timestamp);

        return new TempCardToken(
            id: $id,
            timestamp: $timestamp,
            digest: $digest,
        );
    }
}
