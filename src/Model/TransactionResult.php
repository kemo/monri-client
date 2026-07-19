<?php

declare(strict_types=1);

namespace Kemo\Monri\Model;

use Kemo\Monri\Exception\MonriException;

/**
 * Result of a transaction management call (capture, refund, void).
 *
 * Parsed from the XML response documented for
 * /transactions/:order_number/{capture|refund|void}.xml
 */
final class TransactionResult
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $orderNumber,
        public readonly int $amount,
        public readonly string $status,
        public readonly ?string $responseCode,
        public readonly ?string $approvalCode,
        public readonly ?string $responseMessage,
        public readonly ?string $transactionType,
    ) {
    }

    /**
     * Monri: "Transaction is approved only and if only status is set to approved."
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public static function fromXml(string $xml): self
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_use_internal_errors($previous);

        if ($document === false) {
            throw new MonriException('Monri transaction response is not valid XML');
        }

        $text = static function (string $name) use ($document): ?string {
            $node = $document->{$name} ?? null;

            return $node instanceof \SimpleXMLElement ? trim((string) $node) : null;
        };

        return new self(
            id: $text('id') !== null && $text('id') !== '' ? (int) $text('id') : null,
            orderNumber: (string) $text('order-number'),
            amount: (int) $text('amount'),
            status: (string) $text('status'),
            responseCode: $text('response-code'),
            approvalCode: $text('approval-code'),
            responseMessage: $text('response-message'),
            transactionType: $text('transaction-type'),
        );
    }
}
