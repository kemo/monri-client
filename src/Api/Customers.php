<?php

declare(strict_types=1);

namespace Kemo\Monri\Api;

use Kemo\Monri\Config;
use Kemo\Monri\Exception\MonriException;
use Kemo\Monri\Http\HttpClientInterface;
use Kemo\Monri\Model\Customer;
use Kemo\Monri\Model\PaymentMethod;

final class Customers
{
    public function __construct(
        private readonly Config $config,
        private readonly HttpClientInterface $httpClient,
        private readonly RequestSigner $signer,
    ) {
    }

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
     *     metadata?: array<string, mixed>,
     * } $params
     */
    public function create(array $params): Customer
    {
        $path = '/v2/customers';
        $body = json_encode($params, JSON_THROW_ON_ERROR);

        $response = $this->httpClient->post(
            $this->config->baseUrl() . $path,
            $body,
            ['Authorization' => $this->signer->header($path, $body)],
        );

        $decoded = ResponseParser::decodeObject($response['body']);

        return Customer::fromArray($decoded);
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
     *     metadata?: array<string, mixed>,
     * } $params
     */
    public function update(string $uuid, array $params): Customer
    {
        $path = '/v2/customers/' . rawurlencode($uuid);
        $body = json_encode($params, JSON_THROW_ON_ERROR);

        $response = $this->httpClient->post(
            $this->config->baseUrl() . $path,
            $body,
            ['Authorization' => $this->signer->header($path, $body)],
        );

        $decoded = ResponseParser::decodeObject($response['body']);

        return Customer::fromArray($decoded);
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

        $decoded = ResponseParser::decodeObject($response['body']);

        return Customer::fromArray($decoded);
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

        $decoded = ResponseParser::decodeObject($response['body']);

        return Customer::fromArray($decoded);
    }

    /**
     * List customers.
     *
     * @return list<Customer>
     */
    public function list(int $limit = 50, int $offset = 0): array
    {
        // The digest must cover the request-target exactly as sent, query
        // string included: ipgtest returns 401 for a path-only digest on a
        // query-string URL (verified live 2026-07-19).
        $target = '/v2/customers?' . http_build_query(['limit' => $limit, 'offset' => $offset]);

        $response = $this->httpClient->get(
            $this->config->baseUrl() . $target,
            ['Authorization' => $this->signer->header($target)],
        );

        $data = ResponseParser::decode($response['body']);

        return array_map(
            static fn (array $c): Customer => Customer::fromArray($c),
            self::extractList($data, 'data'),
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
        // Query string is part of the signed request-target, same as list().
        $target = '/v2/customers/' . rawurlencode($customerUuid) . '/payment-methods?'
            . http_build_query(['limit' => $limit, 'offset' => $offset]);

        $response = $this->httpClient->get(
            $this->config->baseUrl() . $target,
            ['Authorization' => $this->signer->header($target)],
        );

        $data = ResponseParser::decode($response['body']);

        return array_map(
            static fn (array $pm): PaymentMethod => PaymentMethod::fromArray($pm),
            self::extractList($data, 'data'),
        );
    }

    /**
     * Pull a list of records out of a response envelope.
     *
     * Accepts either {"<key>": [...]} or a bare top-level array. Anything else
     * (an error object, a scalar under the envelope key, a list of scalars)
     * would otherwise reach a typed callback and surface as a raw TypeError.
     *
     * @param array<mixed> $data
     * @return list<array<string, mixed>>
     * @throws MonriException
     */
    private static function extractList(array $data, string $key): array
    {
        $records = $data[$key] ?? (array_is_list($data) ? $data : null);

        if (!is_array($records) || !array_is_list($records)) {
            throw new MonriException(sprintf('Unexpected response shape: expected a list under "%s"', $key));
        }

        foreach ($records as $record) {
            if (!is_array($record)) {
                throw new MonriException(sprintf('Unexpected response shape: "%s" contains a non-object entry', $key));
            }
        }

        /** @var list<array<string, mixed>> $records */
        return $records;
    }
}
