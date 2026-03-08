<?php

declare(strict_types=1);

namespace Kemo\Monri\Api;

use Kemo\Monri\Config;
use Kemo\Monri\Http\HttpClientInterface;
use Kemo\Monri\Model\Customer;
use Kemo\Monri\Model\PaymentMethod;

final class Customers
{
    public function __construct(
        private readonly Config $config,
        private readonly HttpClientInterface $httpClient,
        private readonly RequestSigner $signer,
    ) {}

    /**
     * Create a new customer.
     *
     * @param array{
     *     merchant_customer_id: string,
     *     email?: string,
     *     name?: string,
     *     phone?: string,
     *     description?: string,
     *     city?: string,
     *     country?: string,
     *     zip_code?: string,
     *     address?: string,
     *     metadata?: array,
     * } $params
     */
    public function create(array $params): Customer
    {
        $path = '/v2/customers';
        $body = json_encode($params, JSON_THROW_ON_ERROR);

        $response = $this->httpClient->post(
            $this->config->baseUrl() . $path,
            $params,
            ['Authorization' => $this->signer->header($path, $body)],
        );

        return Customer::fromArray(
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Update an existing customer (partial update supported).
     *
     * @param array{
     *     email?: string,
     *     name?: string,
     *     phone?: string,
     *     description?: string,
     *     city?: string,
     *     country?: string,
     *     zip_code?: string,
     *     address?: string,
     *     metadata?: array,
     * } $params
     */
    public function update(string $uuid, array $params): Customer
    {
        $path = '/v2/customers/' . rawurlencode($uuid);
        $body = json_encode($params, JSON_THROW_ON_ERROR);

        $response = $this->httpClient->post(
            $this->config->baseUrl() . $path,
            $params,
            ['Authorization' => $this->signer->header($path, $body)],
        );

        return Customer::fromArray(
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Get customer details by Monri UUID.
     */
    public function find(string $uuid): Customer
    {
        $path = '/v2/customers/' . rawurlencode($uuid);

        $response = $this->httpClient->get(
            $this->config->baseUrl() . $path,
            ['Authorization' => $this->signer->header($path)],
        );

        return Customer::fromArray(
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Find customer by merchant's own customer ID.
     */
    public function findByMerchantId(string $merchantCustomerId): Customer
    {
        $path = '/v2/merchants/customers/' . rawurlencode($merchantCustomerId);

        $response = $this->httpClient->get(
            $this->config->baseUrl() . $path,
            ['Authorization' => $this->signer->header($path)],
        );

        return Customer::fromArray(
            json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * List customers.
     *
     * @return list<Customer>
     */
    public function list(int $limit = 50, int $offset = 0): array
    {
        $path = '/v2/customers';
        $query = http_build_query(['limit' => $limit, 'offset' => $offset]);

        $response = $this->httpClient->get(
            $this->config->baseUrl() . $path . '?' . $query,
            ['Authorization' => $this->signer->header($path)],
        );

        $data = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);

        return array_map(
            static fn(array $c) => Customer::fromArray($c),
            $data['customers'] ?? $data,
        );
    }

    /**
     * Delete a customer.
     */
    public function delete(string $uuid): void
    {
        $path = '/v2/customers/' . rawurlencode($uuid);

        $this->httpClient->delete(
            $this->config->baseUrl() . $path,
            ['Authorization' => $this->signer->header($path)],
        );
    }

    /**
     * Get saved payment methods for a customer.
     *
     * @return list<PaymentMethod>
     */
    public function paymentMethods(string $customerUuid, int $limit = 50, int $offset = 0): array
    {
        $path = '/v2/customers/' . rawurlencode($customerUuid) . '/payment-methods';
        $query = http_build_query(['limit' => $limit, 'offset' => $offset]);

        $response = $this->httpClient->get(
            $this->config->baseUrl() . $path . '?' . $query,
            ['Authorization' => $this->signer->header($path)],
        );

        $data = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);

        return array_map(
            static fn(array $pm) => PaymentMethod::fromArray($pm),
            $data['data'] ?? [],
        );
    }
}
