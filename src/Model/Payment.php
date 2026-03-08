<?php

declare(strict_types=1);

namespace Kemo\Monri\Model;

final class Payment
{
    public function __construct(
        public readonly string $id,
        public readonly string $clientSecret,
        public readonly string $status,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var string $id */
        $id = $data['id'];
        /** @var string $clientSecret */
        $clientSecret = $data['client_secret'];
        /** @var string $status */
        $status = $data['status'];

        return new self(
            id: $id,
            clientSecret: $clientSecret,
            status: $status,
        );
    }
}
