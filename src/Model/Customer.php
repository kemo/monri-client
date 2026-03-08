<?php

declare(strict_types=1);

namespace Kemo\Monri\Model;

final class Customer
{
    /** @param array<string, mixed>|null $metadata */
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
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var string $uuid */
        $uuid = $data['uuid'];
        /** @var string|null $merchantCustomerId */
        $merchantCustomerId = $data['merchant_customer_id'] ?? null;
        /** @var string|null $email */
        $email = $data['email'] ?? null;
        /** @var string|null $name */
        $name = $data['name'] ?? null;
        /** @var string|null $phone */
        $phone = $data['phone'] ?? null;
        /** @var string $status */
        $status = $data['status'];
        /** @var string|null $description */
        $description = $data['description'] ?? null;
        /** @var string|null $city */
        $city = $data['city'] ?? null;
        /** @var string|null $country */
        $country = $data['country'] ?? null;
        /** @var string|null $zipCode */
        $zipCode = $data['zip_code'] ?? null;
        /** @var string|null $address */
        $address = $data['address'] ?? null;
        /** @var array<string, mixed>|null $metadata */
        $metadata = $data['metadata'] ?? null;
        /** @var string|null $createdAt */
        $createdAt = $data['created_at'] ?? null;
        /** @var string|null $updatedAt */
        $updatedAt = $data['updated_at'] ?? null;
        /** @var string|null $deletedAt */
        $deletedAt = $data['deleted_at'] ?? null;

        return new self(
            uuid: $uuid,
            merchantCustomerId: $merchantCustomerId,
            email: $email,
            name: $name,
            phone: $phone,
            status: $status,
            description: $description,
            city: $city,
            country: $country,
            zipCode: $zipCode,
            address: $address,
            metadata: $metadata,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            deletedAt: $deletedAt,
            deleted: !empty($data['deleted']),
        );
    }
}
