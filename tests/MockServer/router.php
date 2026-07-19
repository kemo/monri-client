<?php

/**
 * Mock Monri API server for integration testing.
 *
 * Usage: php -S 127.0.0.1:PORT tests/MockServer/router.php
 *
 * Simulates all Monri API v2 endpoints with in-memory state.
 * Validates WP3-v2.1 authorization headers and request bodies.
 */

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$query = [];
parse_str(parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY) ?? '', $query);
$method = $_SERVER['REQUEST_METHOD'];
$body = file_get_contents('php://input');

// Shared state file for persistence across requests within the same test server
$stateFile = sys_get_temp_dir() . '/monri_mock_' . $_SERVER['SERVER_PORT'] . '.json';

/**
 * @return array{payments: array<string, mixed>, customers: array<string, mixed>, payment_methods: array<string, mixed>}
 */
function loadState(string $file): array
{
    if (file_exists($file)) {
        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            return $data;
        }
    }
    return ['payments' => [], 'customers' => [], 'payment_methods' => []];
}

/**
 * @param array<string, mixed> $state
 */
function saveState(string $file, array $state): void
{
    file_put_contents($file, json_encode($state));
}

function jsonResponse(int $status, mixed $data): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
}

function validateAuth(): bool
{
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    // Verify it starts with WP3-v2.1 and has 3 parts after prefix
    if (!str_starts_with($auth, 'WP3-v2.1 ')) {
        jsonResponse(401, ['error' => 'Missing or invalid Authorization header']);
        return false;
    }
    $parts = explode(' ', $auth);
    if (count($parts) !== 4) {
        jsonResponse(401, ['error' => 'Malformed WP3-v2.1 header']);
        return false;
    }
    return true;
}

function uuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
    );
}

function now(): string
{
    return date('c');
}

// ─── Routing ─────────────────────────────────────────────────────────────

// POST /v2/payment/new
if ($method === 'POST' && $uri === '/v2/payment/new') {
    if (!validateAuth()) {
        return;
    }

    $params = json_decode($body, true);
    $missing = !is_array($params) || empty($params['order_number'])
        || empty($params['amount']) || empty($params['currency']);
    if ($missing) {
        jsonResponse(422, ['error' => 'Missing required fields: order_number, amount, currency']);
        return;
    }

    $state = loadState($stateFile);
    $id = 'pay_' . uuid();
    $clientSecret = 'cs_' . bin2hex(random_bytes(16));

    $payment = [
        'id' => $id,
        'client_secret' => $clientSecret,
        'status' => 'approved',
        'order_number' => $params['order_number'],
        'amount' => (int) $params['amount'],
        'currency' => $params['currency'],
        'order_info' => $params['order_info'] ?? '',
        'transaction_type' => $params['transaction_type'] ?? 'purchase',
        'created_at' => now(),
        'payment_status' => 'created',
    ];

    $state['payments'][$id] = $payment;
    saveState($stateFile, $state);

    jsonResponse(200, [
        'id' => $id,
        'client_secret' => $clientSecret,
        'status' => 'approved',
    ]);
    return;
}

// POST /v2/payment/{id}/update
if ($method === 'POST' && preg_match('#^/v2/payment/([^/]+)/update$#', $uri, $m)) {
    if (!validateAuth()) {
        return;
    }

    $paymentId = rawurldecode($m[1]);
    $state = loadState($stateFile);

    if (!isset($state['payments'][$paymentId])) {
        jsonResponse(404, ['error' => 'Payment not found']);
        return;
    }

    $params = json_decode($body, true);
    if (!is_array($params)) {
        jsonResponse(422, ['error' => 'Invalid request body']);
        return;
    }

    $payment = $state['payments'][$paymentId];
    if (isset($params['amount'])) {
        $payment['amount'] = (int) $params['amount'];
    }
    $state['payments'][$paymentId] = $payment;
    saveState($stateFile, $state);

    jsonResponse(200, [
        'id' => $payment['id'],
        'client_secret' => $payment['client_secret'],
        'status' => $payment['status'],
    ]);
    return;
}

// GET /v2/payment/{id}/status
if ($method === 'GET' && preg_match('#^/v2/payment/([^/]+)/status$#', $uri, $m)) {
    if (!validateAuth()) {
        return;
    }

    $paymentId = rawurldecode($m[1]);
    $state = loadState($stateFile);

    if (!isset($state['payments'][$paymentId])) {
        jsonResponse(404, ['error' => 'Payment not found']);
        return;
    }

    $payment = $state['payments'][$paymentId];

    jsonResponse(200, [
        'status' => $payment['status'],
        'payment_status' => $payment['payment_status'],
        'client_secret' => $payment['client_secret'],
        'payment_result' => [
            'currency' => $payment['currency'],
            'amount' => $payment['amount'],
            'order_number' => $payment['order_number'],
            'created_at' => $payment['created_at'],
            'status' => $payment['status'],
            'transaction_type' => $payment['transaction_type'],
            'payment_method' => 'card',
            'response_message' => 'approved',
        ],
    ]);
    return;
}

// POST /v2/customers (create)
if ($method === 'POST' && $uri === '/v2/customers') {
    if (!validateAuth()) {
        return;
    }

    $params = json_decode($body, true);
    if (!is_array($params) || empty($params['merchant_customer_id'])) {
        jsonResponse(422, ['error' => 'Missing required field: merchant_customer_id']);
        return;
    }

    $state = loadState($stateFile);
    $customerUuid = uuid();

    $customer = [
        'uuid' => $customerUuid,
        'merchant_customer_id' => $params['merchant_customer_id'],
        'email' => $params['email'] ?? null,
        'name' => $params['name'] ?? null,
        'phone' => $params['phone'] ?? null,
        'status' => 'approved',
        'description' => $params['description'] ?? null,
        'city' => $params['city'] ?? null,
        'country' => $params['country'] ?? null,
        'zip_code' => $params['zip_code'] ?? null,
        'address' => $params['address'] ?? null,
        'metadata' => $params['metadata'] ?? null,
        'created_at' => now(),
        'updated_at' => now(),
        'deleted_at' => null,
        'deleted' => false,
    ];

    $state['customers'][$customerUuid] = $customer;
    saveState($stateFile, $state);

    jsonResponse(200, $customer);
    return;
}

// GET /v2/customers (list)
if ($method === 'GET' && $uri === '/v2/customers') {
    if (!validateAuth()) {
        return;
    }

    $state = loadState($stateFile);
    $limit = (int) ($query['limit'] ?? 50);
    $offset = (int) ($query['offset'] ?? 0);

    $all = array_values(array_filter(
        $state['customers'],
        static fn (array $c): bool => !$c['deleted'],
    ));

    $slice = array_slice($all, $offset, $limit);

    jsonResponse(200, ['data' => $slice]);
    return;
}

// GET/POST/DELETE /v2/customers/{uuid}
if (preg_match('#^/v2/customers/([^/]+)$#', $uri, $m)) {
    if (!validateAuth()) {
        return;
    }

    $customerUuid = rawurldecode($m[1]);
    $state = loadState($stateFile);

    // POST = update
    if ($method === 'POST') {
        if (!isset($state['customers'][$customerUuid])) {
            jsonResponse(404, ['error' => 'Customer not found']);
            return;
        }

        $params = json_decode($body, true);
        if (!is_array($params)) {
            jsonResponse(422, ['error' => 'Invalid request body']);
            return;
        }

        $customer = $state['customers'][$customerUuid];
        $updatable = ['email', 'name', 'phone', 'description', 'city', 'country', 'zip_code', 'address', 'metadata'];
        foreach ($updatable as $field) {
            if (array_key_exists($field, $params)) {
                $customer[$field] = $params[$field];
            }
        }
        $customer['updated_at'] = now();
        $state['customers'][$customerUuid] = $customer;
        saveState($stateFile, $state);

        jsonResponse(200, $customer);
        return;
    }

    // DELETE
    if ($method === 'DELETE') {
        if (!isset($state['customers'][$customerUuid])) {
            jsonResponse(404, ['error' => 'Customer not found']);
            return;
        }

        $state['customers'][$customerUuid]['deleted'] = true;
        $state['customers'][$customerUuid]['deleted_at'] = now();
        saveState($stateFile, $state);

        jsonResponse(200, ['status' => 'deleted']);
        return;
    }

    // GET = find
    if ($method === 'GET') {
        if (!isset($state['customers'][$customerUuid])) {
            jsonResponse(404, ['error' => 'Customer not found']);
            return;
        }

        jsonResponse(200, $state['customers'][$customerUuid]);
        return;
    }
}

// GET /v2/merchants/customers/{merchantCustomerId}
if ($method === 'GET' && preg_match('#^/v2/merchants/customers/([^/]+)$#', $uri, $m)) {
    if (!validateAuth()) {
        return;
    }

    $merchantId = rawurldecode($m[1]);
    $state = loadState($stateFile);

    foreach ($state['customers'] as $customer) {
        if (($customer['merchant_customer_id'] ?? '') === $merchantId && !$customer['deleted']) {
            jsonResponse(200, $customer);
            return;
        }
    }

    jsonResponse(404, ['error' => 'Customer not found']);
    return;
}

// GET /v2/customers/{uuid}/payment-methods
if ($method === 'GET' && preg_match('#^/v2/customers/([^/]+)/payment-methods$#', $uri, $m)) {
    if (!validateAuth()) {
        return;
    }

    $customerUuid = rawurldecode($m[1]);
    $state = loadState($stateFile);

    if (!isset($state['customers'][$customerUuid])) {
        jsonResponse(404, ['error' => 'Customer not found']);
        return;
    }

    $limit = (int) ($query['limit'] ?? 50);
    $offset = (int) ($query['offset'] ?? 0);

    $methods = array_values(array_filter(
        $state['payment_methods'] ?? [],
        static fn (array $pm): bool => ($pm['customer_uuid'] ?? '') === $customerUuid,
    ));

    $slice = array_slice($methods, $offset, $limit);

    jsonResponse(200, ['data' => $slice]);
    return;
}

// POST /v2/customers/{uuid}/payment-methods (test helper — seed payment methods)
if ($method === 'POST' && preg_match('#^/v2/customers/([^/]+)/payment-methods$#', $uri, $m)) {
    $customerUuid = rawurldecode($m[1]);
    $state = loadState($stateFile);
    $params = json_decode($body, true);

    if (!is_array($params)) {
        jsonResponse(422, ['error' => 'Invalid request body']);
        return;
    }

    $pm = [
        'id' => 'pm_' . uuid(),
        'status' => $params['status'] ?? 'active',
        'masked_pan' => $params['masked_pan'] ?? '411111******1111',
        'expiration_date' => $params['expiration_date'] ?? '12/29',
        'keep_until' => $params['keep_until'] ?? null,
        'token' => $params['token'] ?? ('tok_' . bin2hex(random_bytes(8))),
        'customer_uuid' => $customerUuid,
        'expired' => $params['expired'] ?? false,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $state['payment_methods'][] = $pm;
    saveState($stateFile, $state);

    jsonResponse(200, $pm);
    return;
}


// ─── WebPay form flow (hosted payment page) ──────────────────────────────
//
// Simulates the redirect integration: the shop POSTs the signed form to
// /v2/form, the "customer" sees a hosted page with Pay / Cancel buttons,
// completing fires the signed WP3-callback at callback_url_override and
// redirects the browser to the success/cancel URL.
//
// The merchant key used for digest checks and callback signing comes from
// MONRI_MOCK_MERCHANT_KEY (default "key", matching the test Config).

function mockMerchantKey(): string
{
    $key = getenv('MONRI_MOCK_MERCHANT_KEY');
    return $key !== false && $key !== '' ? $key : 'key';
}

function htmlResponse(int $status, string $html): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}

function xmlResponse(int $status, string $xml): void
{
    http_response_code($status);
    header('Content-Type: application/xml');
    echo $xml;
}

// POST /v2/form (WebPay redirect entry)
if ($method === 'POST' && $uri === '/v2/form') {
    $f = $_POST;
    $required = ['authenticity_token', 'digest', 'order_number', 'amount', 'currency', 'transaction_type'];
    foreach ($required as $field) {
        if (empty($f[$field])) {
            htmlResponse(422, '<h1>Missing field: ' . htmlspecialchars($field) . '</h1>');
            return;
        }
    }

    $expected = hash('sha512', mockMerchantKey() . $f['order_number'] . $f['amount'] . $f['currency']);
    if (!hash_equals($expected, (string) $f['digest'])) {
        htmlResponse(403, '<h1>Invalid digest</h1>');
        return;
    }

    $state = loadState($stateFile);
    $state['form_transactions'][$f['order_number']] = [
        'order_number' => $f['order_number'],
        'amount' => (int) $f['amount'],
        'currency' => $f['currency'],
        'transaction_type' => $f['transaction_type'],
        'language' => $f['language'] ?? 'en',
        'ch_email' => $f['ch_email'] ?? '',
        'success_url' => $f['success_url_override'] ?? '',
        'cancel_url' => $f['cancel_url_override'] ?? '',
        'callback_url' => $f['callback_url_override'] ?? '',
        'status' => 'pending',
        'created_at' => now(),
    ];
    saveState($stateFile, $state);

    $order = htmlspecialchars($f['order_number']);
    $amount = number_format(((int) $f['amount']) / 100, 2);
    $currency = htmlspecialchars($f['currency']);
    $action = '/v2/form/' . rawurlencode($f['order_number']) . '/complete';
    htmlResponse(200, <<<HTML
        <!doctype html>
        <html lang="en">
        <head><meta charset="utf-8"><title>Mock Monri WebPay</title></head>
        <body>
        <h1>Mock Monri hosted payment page</h1>
        <p>Order <strong>{$order}</strong>: {$amount} {$currency}</p>
        <form method="post" action="{$action}">
            <button type="submit" name="outcome" value="approved" data-mock-pay>Pay</button>
            <button type="submit" name="outcome" value="declined" data-mock-decline>Decline</button>
        </form>
        </body>
        </html>
        HTML);
    return;
}

// POST /v2/form/{order_number}/complete (customer pressed Pay or Decline)
if ($method === 'POST' && preg_match('#^/v2/form/([^/]+)/complete$#', $uri, $m)) {
    $orderNumber = rawurldecode($m[1]);
    $state = loadState($stateFile);
    $txn = $state['form_transactions'][$orderNumber] ?? null;
    if ($txn === null) {
        htmlResponse(404, '<h1>Unknown transaction</h1>');
        return;
    }

    $approved = ($_POST['outcome'] ?? 'approved') === 'approved';
    $txn['status'] = $approved ? 'approved' : 'declined';
    $txn['completed_at'] = now();

    $callbackDelivery = null;
    if ($txn['callback_url'] !== '') {
        $payload = json_encode([
            'id' => random_int(1000, 99999),
            'acquirer' => 'mock bank',
            'order_number' => $orderNumber,
            'amount' => $txn['amount'],
            'currency' => $txn['currency'],
            'approval_code' => $approved ? (string) random_int(10000, 99999) : '',
            'response_code' => $approved ? '0000' : '0005',
            'cc_type' => 'visa',
            'status' => $approved ? 'approved' : 'declined',
            'transaction_type' => $txn['transaction_type'],
            'created_at' => now(),
        ]);
        $digest = hash('sha512', mockMerchantKey() . $payload);

        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: WP3-callback {$digest}\r\n",
            'content' => $payload,
            'timeout' => 5,
            'ignore_errors' => true,
        ]]);
        $responseBody = @file_get_contents($txn['callback_url'], false, $context);
        $statusLine = $http_response_header[0] ?? 'no response';
        $callbackDelivery = [
            'url' => $txn['callback_url'],
            'request_body' => $payload,
            'response_status' => $statusLine,
            'response_body' => $responseBody === false ? null : $responseBody,
        ];
    }

    $txn['callback_delivery'] = $callbackDelivery;
    $state['form_transactions'][$orderNumber] = $txn;
    saveState($stateFile, $state);

    $target = $approved ? $txn['success_url'] : $txn['cancel_url'];
    if ($target === '') {
        htmlResponse(200, '<h1>' . ($approved ? 'Approved' : 'Declined') . '</h1>');
        return;
    }
    http_response_code(303);
    header('Location: ' . $target);
    return;
}

// POST /transactions/{order_number}/{capture|refund|void}.xml
if ($method === 'POST' && preg_match('#^/transactions/([^/]+)/(capture|refund|void)\.xml$#', $uri, $m)) {
    $orderNumber = rawurldecode($m[1]);
    $action = $m[2];

    $previous = libxml_use_internal_errors(true);
    $doc = simplexml_load_string($body);
    libxml_use_internal_errors($previous);
    if ($doc === false) {
        xmlResponse(422, '<?xml version="1.0"?><transaction><status>invalid</status></transaction>');
        return;
    }

    $amount = (int) $doc->amount;
    $currency = (string) $doc->currency;
    $digest = (string) $doc->digest;
    $expected = sha1(mockMerchantKey() . $orderNumber . $amount . $currency);
    if (!hash_equals($expected, $digest)) {
        xmlResponse(403, '<?xml version="1.0"?><transaction><status>invalid</status>'
            . '<response-message>invalid digest</response-message></transaction>');
        return;
    }

    $state = loadState($stateFile);
    $state['transactions'][] = [
        'order_number' => $orderNumber,
        'action' => $action,
        'amount' => $amount,
        'currency' => $currency,
        'created_at' => now(),
    ];
    saveState($stateFile, $state);

    $id = random_int(100, 9999);
    $approval = random_int(10000, 99999);
    $createdAt = now();
    xmlResponse(201, <<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <transaction>
            <id type="integer">{$id}</id>
            <acquirer>mock bank</acquirer>
            <order-number>{$orderNumber}</order-number>
            <amount type="integer">{$amount}</amount>
            <currency>{$currency}</currency>
            <response-code>000</response-code>
            <approval-code>{$approval}</approval-code>
            <response-message>{$action} OK</response-message>
            <status>approved</status>
            <transaction-type>{$action}</transaction-type>
            <created-at type="datetime">{$createdAt}</created-at>
        </transaction>
        XML);
    return;
}

// POST /__callback-sink (test helper — records signed callbacks it receives)
if ($method === 'POST' && $uri === '/__callback-sink') {
    $state = loadState($stateFile);
    $state['callbacks_received'][] = [
        'authorization' => $_SERVER['HTTP_AUTHORIZATION'] ?? '',
        'body' => $body,
        'received_at' => now(),
    ];
    saveState($stateFile, $state);
    jsonResponse(200, ['status' => 'ok']);
    return;
}

// POST /__reset (test helper — clear all state)
if ($method === 'POST' && $uri === '/__reset') {
    if (file_exists($stateFile)) {
        unlink($stateFile);
    }
    jsonResponse(200, ['status' => 'reset']);
    return;
}

// GET /__state (test helper — dump full state)
if ($method === 'GET' && $uri === '/__state') {
    jsonResponse(200, loadState($stateFile));
    return;
}

// Default: 404
jsonResponse(404, ['error' => 'Not found', 'uri' => $uri, 'method' => $method]);
