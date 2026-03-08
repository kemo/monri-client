# Mock Monri API Server

A full in-memory mock of the Monri payment gateway API for integration testing.

## What it covers

All 10 Monri API v2 endpoints:

| Method | Path | Description |
|--------|------|-------------|
| POST | `/v2/payment/new` | Create payment |
| POST | `/v2/payment/{id}/update` | Update payment amount |
| GET | `/v2/payment/{id}/status` | Get payment status + result |
| POST | `/v2/customers` | Create customer |
| GET | `/v2/customers` | List customers (paginated) |
| GET | `/v2/customers/{uuid}` | Find customer by UUID |
| POST | `/v2/customers/{uuid}` | Update customer |
| DELETE | `/v2/customers/{uuid}` | Delete customer (soft) |
| GET | `/v2/merchants/customers/{id}` | Find by merchant customer ID |
| GET | `/v2/customers/{uuid}/payment-methods` | List payment methods |

## Features

- **Stateful** — in-memory JSON state file persists across requests within a test run
- **Auth validation** — verifies WP3-v2.1 authorization header format
- **Request validation** — returns 422 for missing required fields
- **Pagination** — `limit` and `offset` query params on list endpoints
- **Soft delete** — deleted customers excluded from list but retrievable by UUID
- **Full payment flow** — create → update → status with payment result

## Usage in tests

### Using MockServerTrait

The simplest way to use the mock server in PHPUnit:

```php
use Kemo\Monri\Tests\MockServer\MockServerTrait;
use Kemo\Monri\Tests\MockServer\MockBaseUrlClient;

final class MyTest extends TestCase
{
    use MockServerTrait;

    private MonriClient $client;

    public static function setUpBeforeClass(): void
    {
        static::startServer();
    }

    public static function tearDownAfterClass(): void
    {
        static::stopServer();
    }

    protected function setUp(): void
    {
        $this->resetServer(); // clear state between tests

        $config = new Config('key', 'token', Environment::Test);
        $httpClient = new MockBaseUrlClient(
            new CurlHttpClient(),
            self::serverBaseUrl(),
        );
        $this->client = new MonriClient($config, $httpClient);
    }

    public function testSomething(): void
    {
        $payment = $this->client->payments()->create([...]);
        // ...
    }
}
```

### Standalone server

Run the mock server directly for manual testing or use with other HTTP clients:

```bash
php -S 127.0.0.1:8080 tests/MockServer/router.php
```

Then send requests:

```bash
# Create payment
curl -X POST http://127.0.0.1:8080/v2/payment/new \
  -H "Authorization: WP3-v2.1 token 1234567890 digest" \
  -H "Content-Type: application/json" \
  -d '{"order_number":"ORD-1","amount":5000,"currency":"EUR","order_info":"Test"}'

# List customers
curl http://127.0.0.1:8080/v2/customers \
  -H "Authorization: WP3-v2.1 token 1234567890 digest"
```

## Test helper endpoints

These endpoints exist only for test setup and don't require auth:

| Method | Path | Description |
|--------|------|-------------|
| POST | `/__reset` | Clear all state |
| GET | `/__state` | Dump full server state as JSON |
| POST | `/v2/customers/{uuid}/payment-methods` | Seed a payment method |

### Seeding payment methods

The Monri API doesn't have a "create payment method" endpoint (cards are tokenized client-side). Use the test helper to seed them:

```php
$this->seedPaymentMethod($customerUuid, [
    'masked_pan' => '411111******1111',
    'expiration_date' => '12/29',
    'token' => 'tok_visa_001',
    'expired' => false,
]);
```

## Components

| File | Description |
|------|-------------|
| `router.php` | The mock server — handles routing, state, validation |
| `MockServerTrait.php` | PHPUnit trait — starts/stops server, helpers |
| `MockBaseUrlClient.php` | HTTP client wrapper — rewrites URLs to mock |
| `IntegrationTest.php` | 27 integration tests covering all endpoints |
