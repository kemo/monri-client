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
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        /** @var string $currency */
        $currency = $data['currency'];
        /** @var int $amount */
        $amount = $data['amount'];
        /** @var string $orderNumber */
        $orderNumber = $data['order_number'];
        /** @var string $createdAt */
        $createdAt = $data['created_at'];
        /** @var string $status */
        $status = $data['status'];
        /** @var string $transactionType */
        $transactionType = $data['transaction_type'];
        /** @var string|null $paymentMethod */
        $paymentMethod = $data['payment_method'] ?? null;
        /** @var string|null $responseMessage */
        $responseMessage = $data['response_message'] ?? null;

        return new self(
            currency: $currency,
            amount: $amount,
            orderNumber: $orderNumber,
            createdAt: $createdAt,
            status: $status,
            transactionType: $transactionType,
            paymentMethod: $paymentMethod,
            responseMessage: $responseMessage,
        );
    }
}
