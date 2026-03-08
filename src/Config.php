<?php

declare(strict_types=1);

namespace Kemo\Monri;

final class Config
{
    public function __construct(
        public readonly string $merchantKey,
        public readonly string $authenticityToken,
        public readonly Environment $environment = Environment::Test,
    ) {}

    public function baseUrl(): string
    {
        return $this->environment->baseUrl();
    }

    public function digest(string $data): string
    {
        return hash('sha512', $this->merchantKey . $data);
    }
}
