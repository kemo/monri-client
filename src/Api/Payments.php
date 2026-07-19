<?php

declare(strict_types=1);

namespace Kemo\Monri\Api;

use Kemo\Monri\Config;
use Kemo\Monri\Exception\MonriException;
use Kemo\Monri\Http\HttpClientInterface;
use Kemo\Monri\Model\Payment;
use Kemo\Monri\Model\PaymentStatus;

final class Payments
{
    public function __construct(
        private readonly Config $config,
        private readonly HttpClientInterface $httpClient,
        private readonly RequestSigner $signer,
    ) {
    }

    /**
     * Create a new payment.
     *
     * @param array{
     *     order_number: string,
     *     amount: int,
     *     currency: string,
     *     order_info: string,
     *     transaction_type?: string,
     *     scenario?: string,
     *     customer_uuid?: string,
     *     supported_payment_methods?: list<string>,
     *     success_url_override?: string,
     *     cancel_url_override?: string,
     *     callback_url_override?: string,
     * } $params
     */
    public function create(array $params): Payment
    {
        $params['transaction_type'] ??= 'purchase';

        $this->assertValidCreateParams($params);

        $path = '/v2/payment/new';
        $body = json_encode($params, JSON_THROW_ON_ERROR);

        $response = $this->httpClient->post(
            $this->config->baseUrl() . $path,
            $body,
            ['Authorization' => $this->signer->header($path, $body)],
        );

        $decoded = ResponseParser::decodeObject($response['body']);

        return Payment::fromArray($decoded);
    }

    /**
     * Update an existing payment (e.g. change amount before capture).
     *
     * @param array{amount: int} $params
     */
    public function update(string $paymentId, array $params): Payment
    {
        $path = '/v2/payment/' . rawurlencode($paymentId) . '/update';
        $body = json_encode($params, JSON_THROW_ON_ERROR);

        $response = $this->httpClient->post(
            $this->config->baseUrl() . $path,
            $body,
            ['Authorization' => $this->signer->header($path, $body)],
        );

        $decoded = ResponseParser::decodeObject($response['body']);

        return Payment::fromArray($decoded);
    }

    /**
     * Get the status of a payment.
     */
    public function status(string $paymentId): PaymentStatus
    {
        $path = '/v2/payment/' . rawurlencode($paymentId) . '/status';

        $response = $this->httpClient->get(
            $this->config->baseUrl() . $path,
            ['Authorization' => $this->signer->header($path)],
        );

        $decoded = ResponseParser::decodeObject($response['body']);

        return PaymentStatus::fromArray($decoded);
    }

    /**
     * Fail fast on constraints Monri enforces server-side, so callers get a
     * clear exception before any network round-trip.
     *
     * @param array<string, mixed> $params
     */
    private function assertValidCreateParams(array $params): void
    {
        $orderInfo = $params['order_info'] ?? null;
        if (\is_string($orderInfo)) {
            $length = mb_strlen($orderInfo);
            if ($length < 3 || $length > 100) {
                throw new MonriException(
                    sprintf('order_info must be 3-100 characters, got %d', $length),
                );
            }
        }

        $orderNumber = $params['order_number'] ?? null;
        if (\is_string($orderNumber)) {
            $length = mb_strlen($orderNumber);
            if ($length < 1 || $length > 40) {
                throw new MonriException(
                    sprintf('order_number must be 1-40 characters, got %d', $length),
                );
            }
        }

        $amount = $params['amount'] ?? null;
        if (\is_int($amount) && $amount < 1) {
            throw new MonriException('amount must be a positive integer in minor units');
        }
    }
}
