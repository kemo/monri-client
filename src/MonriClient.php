<?php

declare(strict_types=1);

namespace Kemo\Monri;

use Kemo\Monri\Api\Customers;
use Kemo\Monri\Api\Payments;
use Kemo\Monri\Api\RequestSigner;
use Kemo\Monri\Api\Tokens;
use Kemo\Monri\Exception\MonriException;
use Kemo\Monri\Http\CurlHttpClient;
use Kemo\Monri\Http\HttpClientInterface;

final class MonriClient
{
    private readonly HttpClientInterface $httpClient;
    private readonly RequestSigner $signer;

    private ?Payments $payments = null;
    private ?Customers $customers = null;
    private ?Tokens $tokens = null;

    public function __construct(
        private readonly Config $config,
        ?HttpClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new CurlHttpClient();
        $this->signer = new RequestSigner($this->config);
    }

    /**
     * Create a client from environment variables.
     *
     * Reads MONRI_MERCHANT_KEY, MONRI_AUTHENTICITY_TOKEN, and MONRI_ENVIRONMENT.
     */
    public static function fromEnv(): self
    {
        $key = $_ENV['MONRI_MERCHANT_KEY']
            ?? getenv('MONRI_MERCHANT_KEY')
            ?: throw new MonriException('MONRI_MERCHANT_KEY not set');

        $token = $_ENV['MONRI_AUTHENTICITY_TOKEN']
            ?? getenv('MONRI_AUTHENTICITY_TOKEN')
            ?: throw new MonriException('MONRI_AUTHENTICITY_TOKEN not set');

        $env = $_ENV['MONRI_ENVIRONMENT']
            ?? getenv('MONRI_ENVIRONMENT')
            ?: 'test';

        return new self(new Config(
            merchantKey: $key,
            authenticityToken: $token,
            environment: Environment::from($env),
        ));
    }

    public function payments(): Payments
    {
        return $this->payments ??= new Payments(
            $this->config,
            $this->httpClient,
            $this->signer,
        );
    }

    public function customers(): Customers
    {
        return $this->customers ??= new Customers(
            $this->config,
            $this->httpClient,
            $this->signer,
        );
    }

    public function tokens(): Tokens
    {
        return $this->tokens ??= new Tokens($this->config);
    }

    public function config(): Config
    {
        return $this->config;
    }
}
