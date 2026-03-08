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

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var string $id */
        $id = $data['id'];
        /** @var string $status */
        $status = $data['status'];
        /** @var string|null $maskedPan */
        $maskedPan = $data['masked_pan'] ?? null;
        /** @var string|null $expirationDate */
        $expirationDate = $data['expiration_date'] ?? null;
        /** @var string|null $keepUntil */
        $keepUntil = $data['keep_until'] ?? null;
        /** @var string|null $token */
        $token = $data['token'] ?? null;
        /** @var string|null $customerUuid */
        $customerUuid = $data['customer_uuid'] ?? null;
        /** @var string|null $createdAt */
        $createdAt = $data['created_at'] ?? null;
        /** @var string|null $updatedAt */
        $updatedAt = $data['updated_at'] ?? null;

        return new self(
            id: $id,
            status: $status,
            maskedPan: $maskedPan,
            expirationDate: $expirationDate,
            keepUntil: $keepUntil,
            token: $token,
            customerUuid: $customerUuid,
            expired: !empty($data['expired']),
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }
}
