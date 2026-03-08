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

    jsonResponse(200, ['customers' => $slice]);
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
