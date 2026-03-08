# Migration Guide: rapttor/monri-php → kemo/monri-client

## Installation

```bash
composer remove rapttor/monri-php
composer require kemo/monri-client
```

---

## Initialization

**Before:**
```php
use Monri\Client;

$client = new Client();
$client->setMerchantKey('your-key');
$client->setAuthenticityToken('your-token');
$client->setEnvironment('production');
```

**After:**
```php
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\MonriClient;

$client = new MonriClient(new Config(
    merchantKey: 'your-key',
    authenticityToken: 'your-token',
    environment: Environment::Production,
));
```

Or via environment variables:
```php
// Reads MONRI_MERCHANT_KEY, MONRI_AUTHENTICITY_TOKEN, MONRI_ENVIRONMENT
$client = MonriClient::fromEnv();
```

---

## Authentication

The rapttor SDK used an undocumented OAuth2 flow (`POST /v2/oauth`) to obtain a bearer token before every API call, with no caching between PHP-FPM requests.

This client uses the **documented `WP3-v2.1` digest scheme** — a per-request HMAC signature. No token fetching, no caching concerns, no undocumented endpoints.

```
Authorization: WP3-v2.1 {authenticity_token} {timestamp} {SHA512(key+timestamp+token+path+body)}
```

This is handled automatically. You do not need to interact with `AccessTokensApi` or manage tokens.

---

## Payments

### Create

**Before:**
```php
$response = $client->payments()->create([
    'order_number'     => 'ORD-001',
    'amount'           => 1000,
    'currency'         => 'EUR',
    'transaction_type' => 'purchase',
]);

$response->getId();
$response->getClientSecret();
$response->getStatus();
```

**After:**
```php
$payment = $client->payments()->create([
    'order_number'     => 'ORD-001',
    'amount'           => 1000,
    'currency'         => 'EUR',
    'order_info'       => 'Order description', // required by Monri API
    'transaction_type' => 'purchase',           // defaults to 'purchase'
]);

$payment->id;
$payment->clientSecret;
$payment->status;
```

### Status

**Before:**
```php
$response = $client->payments()->status($id);
$response->getStatus();
$response->getPaymentStatus();
$response->getPaymentResult()->getCurrency();
```

**After:**
```php
$status = $client->payments()->status($id);
$status->status;
$status->paymentStatus;
$status->result?->currency;
```

### Update (new)

```php
$payment = $client->payments()->update($id, ['amount' => 2000]);
```

---

## Customers

### Create / Find

**Before:**
```php
$response = $client->customers()->create([...]);
$response->getId();       // Monri UUID
$response->getEmail();
$response->getStatus();

$client->customers()->customer($uuid)->details();
$client->customers()->findByMerchantId($id);
```

**After:**
```php
$customer = $client->customers()->create([...]);
$customer->uuid;
$customer->email;
$customer->status;

$client->customers()->find($uuid);
$client->customers()->findByMerchantId($id);
```

### New operations

```php
// Update (partial)
$client->customers()->update($uuid, ['email' => 'new@example.com']);

// Delete
$client->customers()->delete($uuid);

// List
$customers = $client->customers()->list(limit: 50, offset: 0);
```

### Payment methods

**Before:**
```php
$response = $client->customers()->customer($uuid)->paymentMethods();
foreach ($response->getData() as $pm) {
    $pm->getMaskedPan();
    $pm->getToken();
}
```

**After:**
```php
$methods = $client->customers()->paymentMethods($uuid);
foreach ($methods as $pm) {
    $pm->maskedPan;
    $pm->token;
}
```

---

## Token generation

**Before:**
```php
$token = $client->tokens()->generateToken();
$token->getId();
$token->getTimestamp();
$token->getDigest();
```

**After:**
```php
$token = $client->tokens()->generate();
$token->id;
$token->timestamp;
$token->digest;
```

---

## Error handling

**Before:** errors were returned as part of the response or threw generic `MonriException`.

**After:**

```php
use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Exception\NetworkException;

try {
    $client->payments()->create([...]);
} catch (ApiException $e) {
    $e->statusCode;       // HTTP status (422, 404, etc.)
    $e->responseBody;     // raw JSON string
    $e->decodedBody();    // parsed array
} catch (NetworkException $e) {
    // Connection failure or timeout
}
```

---

## Removed

| Old | Reason |
|---|---|
| `Client::curlXml()` | Internal helper, not part of public API |
| `Client::curlJson()` | Internal helper, not part of public API |
| `Client::setMerchantKey()` etc. | Replaced by immutable `Config` |
| `AccessTokensApi` | OAuth2 replaced by WP3-v2.1 digest auth |
| `PaymentResult::getPaymentStatus()` | Static method that made HTTP calls from a model — removed |
| Getter methods (`getId()`, `getStatus()`, etc.) | Replaced by public readonly properties |
