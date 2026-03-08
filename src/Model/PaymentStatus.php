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
    ) {}

    public static function fromArray(array $data): self
    {
        $result = isset($data['payment_result'])
            ? PaymentResult::fromArray($data['payment_result'])
            : null;

        return new self(
            status: $data['status'],
            paymentStatus: $data['payment_status'],
            clientSecret: $data['client_secret'],
            result: $result,
        );
    }
}
