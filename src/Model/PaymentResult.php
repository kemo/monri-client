<?php

declare(strict_types=1);

namespace Kemo\Monri\Model;

final class PaymentResult
{
    public function __construct(
        public readonly string $currency,
        public readonly int $amount,
        public readonly string $orderNumber,
        public readonly string $createdAt,
        public readonly string $status,
        public readonly string $transactionType,
        public readonly ?string $paymentMethod = null,
        public readonly ?string $responseMessage = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            currency: $data['currency'],
            amount: (int) $data['amount'],
            orderNumber: $data['order_number'],
            createdAt: $data['created_at'],
            status: $data['status'],
            transactionType: $data['transaction_type'],
            paymentMethod: $data['payment_method'] ?? null,
            responseMessage: $data['response_message'] ?? null,
        );
    }
}
