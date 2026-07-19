<?php

declare(strict_types=1);

namespace Kemo\Monri\Tests\Api;

use Kemo\Monri\Api\Transactions;
use Kemo\Monri\Config;
use Kemo\Monri\Environment;
use Kemo\Monri\Exception\ApiException;
use Kemo\Monri\Http\HttpClientInterface;
use PHPUnit\Framework\TestCase;

final class TransactionsTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config('key', 'token', Environment::Test);
    }

    private function approvedXml(string $type = 'refund'): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <transaction>
                <id type="integer">845</id>
                <order-number>abcdef</order-number>
                <amount type="integer">54321</amount>
                <response-code>000</response-code>
                <approval-code>38860</approval-code>
                <response-message>authorization OK</response-message>
                <status>approved</status>
                <transaction-type>{$type}</transaction-type>
            </transaction>
            XML;
    }

    public function testRefundPostsSignedXmlToRefundEndpoint(): void
    {
        $captured = [];
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('post')
            ->willReturnCallback(function (string $url, string $body, array $headers) use (&$captured) {
                $captured = [$url, $body, $headers];

                return ['status' => 201, 'body' => $this->approvedXml(), 'headers' => []];
            });

        $result = (new Transactions($this->config, $httpClient))->refund('abcdef', 54321, 'EUR');

        [$url, $body, $headers] = $captured;
        $this->assertSame('https://ipgtest.monri.com/transactions/abcdef/refund.xml', $url);
        $this->assertSame('application/xml', $headers['Content-Type']);
        $this->assertSame('application/xml', $headers['Accept']);

        $expectedDigest = sha1('key' . 'abcdef' . 54321 . 'EUR');
        $this->assertStringContainsString("<digest>{$expectedDigest}</digest>", $body);
        $this->assertStringContainsString('<amount>54321</amount>', $body);
        $this->assertStringContainsString('<currency>EUR</currency>', $body);
        $this->assertStringContainsString('<authenticity-token>token</authenticity-token>', $body);
        $this->assertStringContainsString('<order-number>abcdef</order-number>', $body);

        $this->assertTrue($result->isApproved());
        $this->assertSame(845, $result->id);
        $this->assertSame('abcdef', $result->orderNumber);
        $this->assertSame(54321, $result->amount);
        $this->assertSame('38860', $result->approvalCode);
    }

    public function testCaptureAndVoidHitTheirEndpoints(): void
    {
        $urls = [];
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')
            ->willReturnCallback(function (string $url) use (&$urls) {
                $urls[] = $url;

                return ['status' => 201, 'body' => $this->approvedXml('capture'), 'headers' => []];
            });

        $transactions = new Transactions($this->config, $httpClient);
        $transactions->capture('ord-1', 100, 'BAM');
        $transactions->void('ord-1', 100, 'BAM');

        $this->assertSame([
            'https://ipgtest.monri.com/transactions/ord-1/capture.xml',
            'https://ipgtest.monri.com/transactions/ord-1/void.xml',
        ], $urls);
    }

    public function testNonApprovedStatusThrows(): void
    {
        $declinedXml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <transaction>
                <order-number>abcdef</order-number>
                <amount type="integer">54321</amount>
                <response-message>insufficient funds</response-message>
                <status>decline</status>
            </transaction>
            XML;

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')
            ->willReturn(['status' => 201, 'body' => $declinedXml, 'headers' => []]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('refund not approved');

        (new Transactions($this->config, $httpClient))->refund('abcdef', 54321, 'EUR');
    }

    public function testInvalidXmlThrows(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')
            ->willReturn(['status' => 500, 'body' => 'not xml at all <', 'headers' => []]);

        $this->expectException(\Kemo\Monri\Exception\MonriException::class);

        (new Transactions($this->config, $httpClient))->refund('abcdef', 54321, 'EUR');
    }
}
