<?php

declare(strict_types=1);

namespace Kemo\Monri\Model;

/**
 * Transaction result delivered by Monri's WP3-callback webhook.
 *
 * Only order_number and status are guaranteed; every other field is
 * hydrated when present and null otherwise, so payload additions on
 * Monri's side never break parsing.
 */
final class CallbackPayload
{
    /** @param array<string, mixed>|null $customParams */
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $status,
        public readonly ?int $id = null,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $transactionType = null,
        public readonly ?string $approvalCode = null,
        public readonly ?string $responseCode = null,
        public readonly ?string $responseMessage = null,
        public readonly ?string $referenceNumber = null,
        public readonly ?string $acquirer = null,
        public readonly ?string $chFullName = null,
        public readonly ?int $outgoingAmount = null,
        public readonly ?string $outgoingCurrency = null,
        public readonly ?string $ccType = null,
        public readonly ?string $maskedPan = null,
        public readonly ?string $panToken = null,
        public readonly ?string $issuer = null,
        public readonly ?string $eci = null,
        public readonly ?string $enrollment = null,
        public readonly ?string $authentication = null,
        public readonly ?int $numberOfInstallments = null,
        public readonly ?string $createdAt = null,
        public readonly ?array $customParams = null,
    ) {
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $raw = $data['custom_params'] ?? null;
        if (\is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = \is_array($decoded) ? $decoded : null;
        }
        $customParams = null;
        if (\is_array($raw)) {
            $customParams = [];
            foreach ($raw as $key => $value) {
                $customParams[(string) $key] = $value;
            }
        }

        return new self(
            orderNumber: self::str($data, 'order_number') ?? '',
            status: self::str($data, 'status') ?? '',
            id: self::int($data, 'id'),
            amount: self::int($data, 'amount'),
            currency: self::str($data, 'currency'),
            transactionType: self::str($data, 'transaction_type'),
            approvalCode: self::str($data, 'approval_code'),
            responseCode: self::str($data, 'response_code'),
            responseMessage: self::str($data, 'response_message'),
            referenceNumber: self::str($data, 'reference_number'),
            acquirer: self::str($data, 'acquirer'),
            chFullName: self::str($data, 'ch_full_name'),
            outgoingAmount: self::int($data, 'outgoing_amount'),
            outgoingCurrency: self::str($data, 'outgoing_currency'),
            ccType: self::str($data, 'cc_type'),
            maskedPan: self::str($data, 'masked_pan'),
            panToken: self::str($data, 'pan_token'),
            issuer: self::str($data, 'issuer'),
            eci: self::str($data, 'eci'),
            enrollment: self::str($data, 'enrollment'),
            authentication: self::str($data, 'authentication'),
            numberOfInstallments: self::int($data, 'number_of_installments'),
            createdAt: self::str($data, 'created_at'),
            customParams: $customParams,
        );
    }

    /** @param array<string, mixed> $data */
    private static function str(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_scalar($value) ? (string) $value : null;
    }

    /** @param array<string, mixed> $data */
    private static function int(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }
}
