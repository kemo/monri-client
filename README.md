# Monri PHP Client

[![CI](https://github.com/kemo/monri-client/actions/workflows/ci.yml/badge.svg)](https://github.com/kemo/monri-client/actions/workflows/ci.yml)

PHP client for the [Monri](https://monri.com) payment gateway. Supports PHP 8.1+.

## Installation

```bash
composer require kemo/monri-client
```

## Configuration

```php
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\MonriClient;

$client = new MonriClient(new Config(
    merchantKey: 'your-merchant-key',
    authenticityToken: 'your-authenticity-token',
    environment: Environment::Production, // or Environment::Test
));
```

Or from environment variables (`MONRI_MERCHANT_KEY`, `MONRI_AUTHENTICITY_TOKEN`, `MONRI_ENVIRONMENT`):

```php
$client = MonriClient::fromEnv();
```

Both credentials are available in the Monri merchant dashboard.

## Authentication

All requests are signed using the documented `WP3-v2.1` digest scheme:

```
Authorization: WP3-v2.1 {authenticity_token} {timestamp} {SHA512(key+timestamp+token+path+body)}
```

This is handled automatically — no manual token management required.

## Payments

### Create a payment

```php
$payment = $client->payments()->create([
    'order_number'     => 'ORD-' . uniqid(),
    'amount'           => 5000,        // minor units (5000 = €50.00)
    'currency'         => 'EUR',
    'order_info'       => 'GOD Club membership',
    'transaction_type' => 'purchase',  // or 'authorize'
]);

echo $payment->id;           // payment ID to pass to the frontend SDK
echo $payment->clientSecret; // client secret for frontend SDK
echo $payment->status;       // 'approved' | 'invalid-request' | 'error'
```

Optional params: `scenario`, `customer_uuid`, `supported_payment_methods`, `success_url_override`, `cancel_url_override`, `callback_url_override`.

### Update a payment (change amount before capture)

```php
$payment = $client->payments()->update('pay_123', ['amount' => 3000]);
```

### Check payment status

```php
$status = $client->payments()->status('pay_123');

echo $status->status;        // 'approved' | 'invalid-request' | 'error'
echo $status->paymentStatus; // e.g. 'completed'

if ($status->result !== null) {
    echo $status->result->amount;      // int, minor units
    echo $status->result->currency;    // 'EUR'
    echo $status->result->orderNumber;
}
```

## Customers

### Create a customer

```php
$customer = $client->customers()->create([
    'merchant_customer_id' => 'user-42', // your internal user ID
    'email'  => 'fan@god.ba',
    'name'   => 'Adnan Fest',
    'phone'  => '+38761000000',
]);

echo $customer->uuid; // Monri's UUID for this customer
```

### Find a customer

```php
// By Monri UUID
$customer = $client->customers()->find('cust-uuid-123');

// By your own customer ID
$customer = $client->customers()->findByMerchantId('user-42');
```

### Update a customer

```php
$customer = $client->customers()->update('cust-uuid-123', [
    'email' => 'new@god.ba',
    'city'  => 'Sarajevo',
]);
```

### List customers

```php
$customers = $client->customers()->list(limit: 50, offset: 0);
```

### Delete a customer

```php
$client->customers()->delete('cust-uuid-123');
```

### Saved payment methods

```php
$methods = $client->customers()->paymentMethods('cust-uuid-123');

foreach ($methods as $method) {
    echo $method->maskedPan;      // '411111******1111'
    echo $method->expirationDate; // '12/27'
    echo $method->token;          // pan_token for card-on-file payments
    echo $method->expired;        // bool
}
```

## Card tokenization (frontend)

Generate a temporary token to pass to the Monri frontend SDK:

```php
$token = $client->tokens()->generate();

// Pass these to your frontend
echo $token->id;
echo $token->timestamp;
echo $token->digest;
```

## Error handling

```php
use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Exception\MonriException;
use Kemo\Monri\Exception\NetworkException;

try {
    $payment = $client->payments()->create([...]);
} catch (ApiException $e) {
    // HTTP 4xx/5xx from Monri
    echo $e->statusCode;          // e.g. 422
    $data = $e->decodedBody();    // parsed response array
} catch (NetworkException $e) {
    // Connection failure, timeout
} catch (MonriException $e) {
    // Configuration error
}
```

## Custom HTTP client

The default transport uses cURL. To use a PSR-18 client (e.g. Guzzle):

```php
use Kemo\Monri\Http\PsrHttpClient;

$http = new PsrHttpClient($guzzle, $requestFactory, $streamFactory);

$client = new MonriClient($config, $http);
```

## Development

```bash
composer test        # PHPUnit
composer cs-check    # php-cs-fixer (dry-run)
composer cs-fix      # php-cs-fixer (apply)
composer phpcs       # PHP_CodeSniffer
composer phpcbf      # PHP_CodeSniffer (auto-fix)
composer phpstan     # PHPStan (level max)
```

## Environments

| Constant | Base URL |
|---|---|
| `Environment::Test` | `https://ipgtest.monri.com` |
| `Environment::Production` | `https://ipg.monri.com` |

## License

MIT - see [LICENSE](LICENSE).
