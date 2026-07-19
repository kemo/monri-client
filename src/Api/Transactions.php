<?php

declare(strict_types=1);

namespace Kemo\Monri\Api;

use Kemo\Monri\Config;
use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Http\HttpClientInterface;
use Kemo\Monri\Model\TransactionResult;

/**
 * Transaction management API: capture, refund, void.
 *
 * Unlike the JSON Payments API, these endpoints speak XML and are signed
 * with SHA1(key + order_number + amount + currency) inside the body:
 *
 *   POST /transactions/:order_number/{capture|refund|void}.xml
 *   Content-Type: application/xml
 */
final class Transactions
{
    public function __construct(
        private readonly Config $config,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Refund a settled transaction (full or partial amount, in minor units).
     *
     * @throws ApiException When the gateway does not approve the refund
     */
    public function refund(string $orderNumber, int $amount, string $currency): TransactionResult
    {
        return $this->call('refund', $orderNumber, $amount, $currency);
    }

    /**
     * Capture a previously authorized transaction.
     *
     * @throws ApiException When the gateway does not approve the capture
     */
    public function capture(string $orderNumber, int $amount, string $currency): TransactionResult
    {
        return $this->call('capture', $orderNumber, $amount, $currency);
    }

    /**
     * Void an authorized, not yet settled transaction.
     *
     * @throws ApiException When the gateway does not approve the void
     */
    public function void(string $orderNumber, int $amount, string $currency): TransactionResult
    {
        return $this->call('void', $orderNumber, $amount, $currency);
    }

    private function call(string $action, string $orderNumber, int $amount, string $currency): TransactionResult
    {
        $digest = sha1($this->config->merchantKey . $orderNumber . $amount . $currency);

        $body = sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<transaction>'
            . '<amount>%d</amount>'
            . '<currency>%s</currency>'
            . '<digest>%s</digest>'
            . '<authenticity-token>%s</authenticity-token>'
            . '<order-number>%s</order-number>'
            . '</transaction>',
            $amount,
            htmlspecialchars($currency, ENT_XML1),
            $digest,
            htmlspecialchars($this->config->authenticityToken, ENT_XML1),
            htmlspecialchars($orderNumber, ENT_XML1),
        );

        $url = sprintf(
            '%s/transactions/%s/%s.xml',
            $this->config->baseUrl(),
            rawurlencode($orderNumber),
            $action,
        );

        $response = $this->httpClient->post($url, $body, [
            'Content-Type' => 'application/xml',
            'Accept' => 'application/xml',
        ]);

        $result = TransactionResult::fromXml($response['body']);

        if (!$result->isApproved()) {
            throw new ApiException(
                $response['status'],
                $response['body'],
                $response['headers'],
                sprintf(
                    'Monri %s not approved for order %s (status: %s%s)',
                    $action,
                    $orderNumber,
                    $result->status !== '' ? $result->status : 'unknown',
                    $result->responseMessage !== null ? ', ' . $result->responseMessage : '',
                ),
            );
        }

        return $result;
    }
}
