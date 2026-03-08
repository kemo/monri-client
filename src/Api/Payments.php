<?php

declare(strict_types=1);

namespace Kemo\Monri\Api;

use Kemo\Monri\Config;
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

        $path = '/v2/payment/new';
        $body = json_encode($params, JSON_THROW_ON_ERROR);

        $response = $this->httpClient->post(
            $this->config->baseUrl() . $path,
            $params,
            ['Authorization' => $this->signer->header($path, $body)],
        );

        return Payment::fromArray(
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR),
        );
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
            $params,
            ['Authorization' => $this->signer->header($path, $body)],
        );

        return Payment::fromArray(
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR),
        );
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

        return PaymentStatus::fromArray(
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR),
        );
    }
}
