<?php

declare(strict_types=1);

namespace Kemo\Monri\Model;

final class Payment
{
    public function __construct(
        public readonly string $id,
        public readonly string $clientSecret,
        public readonly string $status,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            clientSecret: $data['client_secret'],
            status: $data['status'],
        );
    }
}
