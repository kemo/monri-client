<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\MockServer;

use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Http\CurlHttpClient;
use Kemo\Monri\Model\Customer;
use Kemo\Monri\Model\Payment;
use Kemo\Monri\Model\PaymentMethod;
use Kemo\Monri\Model\PaymentResult;
use Kemo\Monri\Model\PaymentStatus;
use Kemo\Monri\MonriClient;
use PHPUnit\Framework\TestCase;

/**
 * Full integration tests running against the mock Monri API server.
 *
 * These tests exercise the real HTTP stack (CurlHttpClient) against
 * a local mock server, validating the entire request/response cycle
 * including auth headers, JSON encoding, and model hydration.
 */
final class IntegrationTest extends TestCase
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
        $this->resetServer();

        // Create a client pointing at the mock server
        $config = new Config(
            merchantKey: 'test_merchant_key',
            authenticityToken: 'test_auth_token',
            environment: Environment::Test,
        );

        // Override base URL by using a custom HTTP client wrapper
        $baseUrl = self::serverBaseUrl();
        $httpClient = new MockBaseUrlClient(new CurlHttpClient(), $baseUrl);

        $this->client = new MonriClient($config, $httpClient);
    }

    // ─── Payments ────────────────────────────────────────────────────────

    public function testCreatePayment(): void
    {
        $payment = $this->client->payments()->create([
            'order_number' => 'ORD-001',
            'amount' => 5000,
            'currency' => 'EUR',
            'order_info' => 'Test order',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertStringStartsWith('pay_', $payment->id);
        $this->assertStringStartsWith('cs_', $payment->clientSecret);
        $this->assertSame('approved', $payment->status);
    }

    public function testCreatePaymentWithAllFields(): void
    {
        $payment = $this->client->payments()->create([
            'order_number' => 'ORD-002',
            'amount' => 10000,
            'currency' => 'BAM',
            'order_info' => 'Full test order',
            'transaction_type' => 'authorize',
            'scenario' => 'charge',
        ]);

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame('approved', $payment->status);
    }

    public function testCreatePaymentValidation(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(422);

        // Missing required fields
        $this->client->payments()->create([
            'order_number' => 'ORD-BAD',
            'amount' => 0,
            'currency' => '',
            'order_info' => '',
        ]);
    }

    public function testUpdatePayment(): void
    {
        $payment = $this->client->payments()->create([
            'order_number' => 'ORD-UPD',
            'amount' => 5000,
            'currency' => 'EUR',
            'order_info' => 'To update',
        ]);

        $updated = $this->client->payments()->update($payment->id, ['amount' => 7500]);

        $this->assertSame($payment->id, $updated->id);
        $this->assertSame('approved', $updated->status);
    }

    public function testUpdatePaymentNotFound(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $this->client->payments()->update('pay_nonexistent', ['amount' => 1000]);
    }

    public function testPaymentStatus(): void
    {
        $payment = $this->client->payments()->create([
            'order_number' => 'ORD-STATUS',
            'amount' => 3000,
            'currency' => 'EUR',
            'order_info' => 'Status check',
        ]);

        $status = $this->client->payments()->status($payment->id);

        $this->assertInstanceOf(PaymentStatus::class, $status);
        $this->assertSame('approved', $status->status);
        $this->assertSame('created', $status->paymentStatus);
        $this->assertNotEmpty($status->clientSecret);

        // Verify payment result
        $this->assertInstanceOf(PaymentResult::class, $status->result);
        $this->assertSame('EUR', $status->result->currency);
        $this->assertSame(3000, $status->result->amount);
        $this->assertSame('ORD-STATUS', $status->result->orderNumber);
        $this->assertSame('purchase', $status->result->transactionType);
        $this->assertSame('card', $status->result->paymentMethod);
        $this->assertSame('approved', $status->result->responseMessage);
    }

    public function testPaymentStatusNotFound(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $this->client->payments()->status('pay_nonexistent');
    }

    public function testPaymentStatusReflectsUpdate(): void
    {
        $payment = $this->client->payments()->create([
            'order_number' => 'ORD-UPD-STATUS',
            'amount' => 2000,
            'currency' => 'BAM',
            'order_info' => 'Update then status',
        ]);

        $this->client->payments()->update($payment->id, ['amount' => 4000]);

        $status = $this->client->payments()->status($payment->id);
        $this->assertSame(4000, $status->result->amount);
    }

    // ─── Customers ───────────────────────────────────────────────────────

    public function testCreateCustomer(): void
    {
        $customer = $this->client->customers()->create([
            'merchant_customer_id' => 'CUST-001',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'phone' => '+38761000000',
        ]);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertNotEmpty($customer->uuid);
        $this->assertSame('CUST-001', $customer->merchantCustomerId);
        $this->assertSame('test@example.com', $customer->email);
        $this->assertSame('Test User', $customer->name);
        $this->assertSame('+38761000000', $customer->phone);
        $this->assertSame('approved', $customer->status);
        $this->assertNotNull($customer->createdAt);
        $this->assertFalse($customer->deleted);
    }

    public function testCreateCustomerWithAllFields(): void
    {
        $customer = $this->client->customers()->create([
            'merchant_customer_id' => 'CUST-FULL',
            'email' => 'full@example.com',
            'name' => 'Full User',
            'phone' => '+38762000000',
            'description' => 'VIP customer',
            'city' => 'Sarajevo',
            'country' => 'BA',
            'zip_code' => '71000',
            'address' => 'Ferhadija 1',
            'metadata' => ['tier' => 'gold', 'source' => 'web'],
        ]);

        $this->assertSame('VIP customer', $customer->description);
        $this->assertSame('Sarajevo', $customer->city);
        $this->assertSame('BA', $customer->country);
        $this->assertSame('71000', $customer->zipCode);
        $this->assertSame('Ferhadija 1', $customer->address);
        $this->assertSame(['tier' => 'gold', 'source' => 'web'], $customer->metadata);
    }

    public function testCreateCustomerValidation(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(422);

        // Missing merchant_customer_id
        $this->client->customers()->create([
            'email' => 'bad@example.com',
        ]);
    }

    public function testFindCustomer(): void
    {
        $created = $this->client->customers()->create([
            'merchant_customer_id' => 'CUST-FIND',
            'email' => 'find@example.com',
        ]);

        $found = $this->client->customers()->find($created->uuid);

        $this->assertSame($created->uuid, $found->uuid);
        $this->assertSame('find@example.com', $found->email);
    }

    public function testFindCustomerNotFound(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $this->client->customers()->find('nonexistent-uuid');
    }

    public function testFindByMerchantId(): void
    {
        $created = $this->client->customers()->create([
            'merchant_customer_id' => 'MCID-LOOKUP',
            'email' => 'merchant@example.com',
        ]);

        $found = $this->client->customers()->findByMerchantId('MCID-LOOKUP');

        $this->assertSame($created->uuid, $found->uuid);
        $this->assertSame('merchant@example.com', $found->email);
    }

    public function testFindByMerchantIdNotFound(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $this->client->customers()->findByMerchantId('NONEXISTENT');
    }

    public function testUpdateCustomer(): void
    {
        $created = $this->client->customers()->create([
            'merchant_customer_id' => 'CUST-UPD',
            'email' => 'old@example.com',
            'city' => 'Mostar',
        ]);

        $updated = $this->client->customers()->update($created->uuid, [
            'email' => 'new@example.com',
            'city' => 'Sarajevo',
            'description' => 'Updated customer',
        ]);

        $this->assertSame($created->uuid, $updated->uuid);
        $this->assertSame('new@example.com', $updated->email);
        $this->assertSame('Sarajevo', $updated->city);
        $this->assertSame('Updated customer', $updated->description);
    }

    public function testUpdateCustomerNotFound(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $this->client->customers()->update('nonexistent-uuid', ['email' => 'x@x.com']);
    }

    public function testDeleteCustomer(): void
    {
        $created = $this->client->customers()->create([
            'merchant_customer_id' => 'CUST-DEL',
        ]);

        // Should not throw
        $this->client->customers()->delete($created->uuid);

        // Subsequent find should still work (returns deleted customer)
        // but list should not include it
        $list = $this->client->customers()->list();
        $uuids = array_map(static fn (Customer $c): string => $c->uuid, $list);
        $this->assertNotContains($created->uuid, $uuids);
    }

    public function testDeleteCustomerNotFound(): void
    {
        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $this->client->customers()->delete('nonexistent-uuid');
    }

    public function testListCustomers(): void
    {
        // Create 3 customers
        $this->client->customers()->create(['merchant_customer_id' => 'LIST-1']);
        $this->client->customers()->create(['merchant_customer_id' => 'LIST-2']);
        $this->client->customers()->create(['merchant_customer_id' => 'LIST-3']);

        $all = $this->client->customers()->list();
        $this->assertCount(3, $all);
        $this->assertContainsOnlyInstancesOf(Customer::class, $all);
    }

    public function testListCustomersPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->client->customers()->create(['merchant_customer_id' => "PAGE-{$i}"]);
        }

        $page1 = $this->client->customers()->list(limit: 2, offset: 0);
        $page2 = $this->client->customers()->list(limit: 2, offset: 2);
        $page3 = $this->client->customers()->list(limit: 2, offset: 4);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertCount(1, $page3);

        // No overlap between pages
        $allUuids = array_merge(
            array_map(static fn (Customer $c): string => $c->uuid, $page1),
            array_map(static fn (Customer $c): string => $c->uuid, $page2),
            array_map(static fn (Customer $c): string => $c->uuid, $page3),
        );
        $this->assertCount(5, array_unique($allUuids));
    }

    public function testListCustomersExcludesDeleted(): void
    {
        $c1 = $this->client->customers()->create(['merchant_customer_id' => 'DEL-TEST-1']);
        $this->client->customers()->create(['merchant_customer_id' => 'DEL-TEST-2']);

        $this->client->customers()->delete($c1->uuid);

        $list = $this->client->customers()->list();
        $this->assertCount(1, $list);
        $this->assertSame('DEL-TEST-2', $list[0]->merchantCustomerId);
    }

    // ─── Payment Methods ─────────────────────────────────────────────────

    public function testPaymentMethodsEmpty(): void
    {
        $customer = $this->client->customers()->create([
            'merchant_customer_id' => 'PM-EMPTY',
        ]);

        $methods = $this->client->customers()->paymentMethods($customer->uuid);
        $this->assertSame([], $methods);
    }

    public function testPaymentMethods(): void
    {
        $customer = $this->client->customers()->create([
            'merchant_customer_id' => 'PM-TEST',
        ]);

        // Seed payment methods via test helper
        $this->seedPaymentMethod($customer->uuid, [
            'masked_pan' => '411111******1111',
            'expiration_date' => '12/29',
            'token' => 'tok_visa_001',
        ]);
        $this->seedPaymentMethod($customer->uuid, [
            'masked_pan' => '555555******4444',
            'expiration_date' => '06/28',
            'token' => 'tok_mc_001',
            'expired' => true,
        ]);

        $methods = $this->client->customers()->paymentMethods($customer->uuid);

        $this->assertCount(2, $methods);
        $this->assertContainsOnlyInstancesOf(PaymentMethod::class, $methods);

        $this->assertSame('411111******1111', $methods[0]->maskedPan);
        $this->assertSame('tok_visa_001', $methods[0]->token);
        $this->assertSame($customer->uuid, $methods[0]->customerUuid);
        $this->assertFalse($methods[0]->expired);

        $this->assertSame('555555******4444', $methods[1]->maskedPan);
        $this->assertTrue($methods[1]->expired);
    }

    public function testPaymentMethodsPagination(): void
    {
        $customer = $this->client->customers()->create([
            'merchant_customer_id' => 'PM-PAGE',
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->seedPaymentMethod($customer->uuid);
        }

        $page1 = $this->client->customers()->paymentMethods($customer->uuid, limit: 2);
        $page2 = $this->client->customers()->paymentMethods($customer->uuid, limit: 2, offset: 2);

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);
    }

    // ─── Auth Validation ─────────────────────────────────────────────────

    public function testMissingAuthHeaderReturns401(): void
    {
        // Use a raw curl request without the auth header
        $ch = curl_init(self::serverBaseUrl() . '/v2/customers');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $this->assertSame(401, $status);
        $this->assertStringContainsString('Authorization', (string) $body);
    }

    // ─── End-to-End Flow ─────────────────────────────────────────────────

    public function testFullPaymentFlow(): void
    {
        // 1. Create customer
        $customer = $this->client->customers()->create([
            'merchant_customer_id' => 'E2E-USER',
            'email' => 'e2e@example.com',
            'name' => 'E2E Test',
        ]);
        $this->assertNotEmpty($customer->uuid);

        // 2. Create payment
        $payment = $this->client->payments()->create([
            'order_number' => 'E2E-ORD-001',
            'amount' => 9900,
            'currency' => 'EUR',
            'order_info' => 'E2E test order',
            'customer_uuid' => $customer->uuid,
        ]);
        $this->assertSame('approved', $payment->status);

        // 3. Update payment amount
        $updated = $this->client->payments()->update($payment->id, ['amount' => 12500]);
        $this->assertSame($payment->id, $updated->id);

        // 4. Check status
        $status = $this->client->payments()->status($payment->id);
        $this->assertSame(12500, $status->result->amount);
        $this->assertSame('EUR', $status->result->currency);

        // 5. Verify customer persists
        $found = $this->client->customers()->find($customer->uuid);
        $this->assertSame('e2e@example.com', $found->email);

        // 6. List should include the customer
        $list = $this->client->customers()->list();
        $this->assertCount(1, $list);
    }
}
