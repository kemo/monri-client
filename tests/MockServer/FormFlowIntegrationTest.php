<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\MockServer;

use Kemo\Monri\Api\Callbacks;
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\Http\CurlHttpClient;
use Kemo\Monri\MonriClient;
use PHPUnit\Framework\TestCase;

/**
 * Drives the mock WebPay form flow end to end: signed form POST, hosted
 * page, completion, signed WP3-callback delivery, and an XML refund.
 */
final class FormFlowIntegrationTest extends TestCase
{
    use MockServerTrait;

    // The mock signs and verifies with MONRI_MOCK_MERCHANT_KEY (default "key").
    private const KEY = 'key';
    private const TOKEN = 'token';

    private Config $config;

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
        $this->config = new Config(self::KEY, self::TOKEN, Environment::Test);
    }

    private function base(): string
    {
        return self::serverBaseUrl();
    }

    /** @param array<string, string> $fields */
    private function postForm(string $path, array $fields): array
    {
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($fields),
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        $body = file_get_contents($this->base() . $path, false, $context);
        $status = (int) explode(' ', $http_response_header[0] ?? '0 0')[1];
        $headers = $http_response_header;

        return ['status' => $status, 'body' => (string) $body, 'headers' => $headers];
    }

    private function state(): array
    {
        return json_decode((string) file_get_contents($this->base() . '/__state'), true);
    }

    /** @return array<string, string> */
    private function formFields(string $orderNumber, int $amount): array
    {
        return [
            'utf8' => '1',
            'authenticity_token' => self::TOKEN,
            'digest' => hash('sha512', self::KEY . $orderNumber . $amount . 'BAM'),
            'order_number' => $orderNumber,
            'amount' => (string) $amount,
            'currency' => 'BAM',
            'transaction_type' => 'purchase',
            'order_info' => 'Test order',
            'language' => 'en',
            'ch_full_name' => 'Amina Testic',
            'ch_address' => 'Ferhadija 1',
            'ch_city' => 'Sarajevo',
            'ch_zip' => '71000',
            'ch_country' => 'BA',
            'ch_phone' => '+38761000000',
            'ch_email' => 'amina@example.com',
            'ip' => '203.0.113.7',
            'success_url_override' => 'https://shop.example/thanks',
            'cancel_url_override' => 'https://shop.example/cancel',
            'callback_url_override' => $this->base() . '/__callback-sink',
        ];
    }

    public function testFormRendersHostedPageAndRejectsBadDigest(): void
    {
        $good = $this->postForm('/v2/form', $this->formFields('ord-1', 39900));
        $this->assertSame(200, $good['status']);
        $this->assertStringContainsString('data-mock-pay', $good['body']);
        $this->assertStringContainsString('399.00 BAM', $good['body']);

        $bad = $this->formFields('ord-2', 39900);
        $bad['digest'] = 'wrong';
        $this->assertSame(403, $this->postForm('/v2/form', $bad)['status']);
    }

    public function testApprovedFlowDeliversVerifiableCallbackAndRedirects(): void
    {
        $this->postForm('/v2/form', $this->formFields('ord-3', 41900));
        $complete = $this->postForm('/v2/form/ord-3/complete', ['outcome' => 'approved']);

        $this->assertSame(303, $complete['status']);
        $location = '';
        foreach ($complete['headers'] as $header) {
            if (stripos($header, 'Location:') === 0) {
                $location = trim(substr($header, 9));
            }
        }
        $this->assertSame('https://shop.example/thanks', $location);

        $received = $this->state()['callbacks_received'] ?? [];
        $this->assertCount(1, $received);

        // The delivered callback must verify with the real client library.
        $callbacks = new Callbacks($this->config);
        $this->assertTrue($callbacks->verify($received[0]['body'], $received[0]['authorization']));

        $payload = $callbacks->parse($received[0]['body'], $received[0]['authorization']);
        $this->assertSame('ord-3', $payload->orderNumber);
        $this->assertSame(41900, $payload->amount);
        $this->assertSame('approved', $payload->status);
    }

    public function testDeclinedFlowRedirectsToCancelWithDeclinedCallback(): void
    {
        $this->postForm('/v2/form', $this->formFields('ord-4', 10000));
        $complete = $this->postForm('/v2/form/ord-4/complete', ['outcome' => 'declined']);

        $this->assertSame(303, $complete['status']);
        $this->assertStringContainsString('cancel', implode("\n", $complete['headers']));

        $received = $this->state()['callbacks_received'] ?? [];
        $this->assertCount(1, $received);
        $body = json_decode($received[0]['body'], true);
        $this->assertSame('declined', $body['status']);
        $this->assertSame('0005', $body['response_code']);
    }

    public function testRefundAgainstMockViaTransactionsApi(): void
    {
        $client = new MonriClient($this->config, new MockBaseUrlClient(
            new CurlHttpClient(),
            $this->base(),
        ));

        $result = $client->transactions()->refund('ord-5', 5000, 'BAM');

        $this->assertTrue($result->isApproved());
        $this->assertSame('ord-5', $result->orderNumber);
        $this->assertSame(5000, $result->amount);

        $recorded = $this->state()['transactions'] ?? [];
        $this->assertCount(1, $recorded);
        $this->assertSame('refund', $recorded[0]['action']);
    }
}
