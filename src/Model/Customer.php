<?php

declare(strict_types=1);

namespace Kemo\Monri\Model;

final class Customer
{
    public function __construct(
        public readonly string $uuid,
        public readonly ?string $merchantCustomerId,
        public readonly ?string $email,
        public readonly ?string $name,
        public readonly ?string $phone,
        public readonly string $status,
        public readonly ?string $description = null,
        public readonly ?string $city = null,
        public readonly ?string $country = null,
        public readonly ?string $zipCode = null,
        public readonly ?string $address = null,
        public readonly ?array $metadata = null,
        public readonly ?string $createdAt = null,
        public readonly ?string $updatedAt = null,
        public readonly ?string $deletedAt = null,
        public readonly bool $deleted = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            uuid: $data['uuid'],
            merchantCustomerId: $data['merchant_customer_id'] ?? null,
            email: $data['email'] ?? null,
            name: $data['name'] ?? null,
            phone: $data['phone'] ?? null,
            status: $data['status'],
            description: $data['description'] ?? null,
            city: $data['city'] ?? null,
            country: $data['country'] ?? null,
            zipCode: $data['zip_code'] ?? null,
            address: $data['address'] ?? null,
            metadata: $data['metadata'] ?? null,
            createdAt: $data['created_at'] ?? null,
            updatedAt: $data['updated_at'] ?? null,
            deletedAt: $data['deleted_at'] ?? null,
            deleted: (bool) ($data['deleted'] ?? false),
        );
    }
}
