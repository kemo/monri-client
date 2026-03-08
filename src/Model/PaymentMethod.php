<?php

declare(strict_types=1);

namespace Kemo\Monri\Model;

final class PaymentMethod
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $maskedPan = null,
        public readonly ?string $expirationDate = null,
        public readonly ?string $keepUntil = null,
        public readonly ?string $token = null,
        public readonly ?string $customerUuid = null,
        public readonly bool $expired = false,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            status: $data['status'],
            maskedPan: $data['masked_pan'] ?? null,
            expirationDate: $data['expiration_date'] ?? null,
            keepUntil: $data['keep_until'] ?? null,
            token: $data['token'] ?? null,
            customerUuid: $data['customer_uuid'] ?? null,
            expired: (bool) ($data['expired'] ?? false),
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
        );
    }
}
