<?php

declare(strict_types=1);

namespace Kemo\Monri\Model;

final class PaymentStatus
{
    public function __construct(
        public readonly string $status,
        public readonly string $paymentStatus,
        public readonly string $clientSecret,
        public readonly ?PaymentResult $result = null,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var array<string, mixed>|null $paymentResult */
        $paymentResult = $data['payment_result'] ?? null;
        $result = is_array($paymentResult)
            ? PaymentResult::fromArray($paymentResult)
            : null;

        /** @var string $status */
        $status = $data['status'];
        /** @var string $paymentStatus */
        $paymentStatus = $data['payment_status'];
        /** @var string $clientSecret */
        $clientSecret = $data['client_secret'];

        return new self(
            status: $status,
            paymentStatus: $paymentStatus,
            clientSecret: $clientSecret,
            result: $result,
        );
    }
}
