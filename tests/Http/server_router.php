<?php

/**
 * Simple router for PHP built-in server used by CurlHttpClientTest.
 */

declare(strict_types=1);

$uri = $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];

// Route: GET/POST/DELETE /ok — returns 200 with JSON
if ($uri === '/ok') {
    header('Content-Type: application/json');
    echo '{"status":"ok"}';
    return;
}

// Route: /echo-headers — returns received headers as JSON
if ($uri === '/echo-headers') {
    header('Content-Type: application/json');
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with($key, 'HTTP_')) {
            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = $value;
        }
    }
    echo json_encode($headers);
    return;
}

// Route: POST /echo-body — returns the request body as-is
if ($uri === '/echo-body' && $method === 'POST') {
    header('Content-Type: application/json');
    echo file_get_contents('php://input');
    return;
}

// Route: /error/{code} — returns given HTTP status code
if (preg_match('#^/error/(\d+)$#', $uri, $matches)) {
    $code = (int) $matches[1];
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'error', 'code' => $code]);
    return;
}

// Route: /with-headers — returns 200 with custom response header
if ($uri === '/with-headers') {
    header('Content-Type: application/json');
    header('X-Custom-Header: custom-value');
    echo '{"status":"ok"}';
    return;
}

// Route: /error-with-headers — returns 400 with custom error header
if ($uri === '/error-with-headers') {
    http_response_code(400);
    header('Content-Type: application/json');
    header('X-Error-Code: E001');
    echo '{"error":"bad request"}';
    return;
}

// Route: /status/{code} — returns given HTTP status code with body
if (preg_match('#^/status/(\d+)$#', $uri, $matches)) {
    $code = (int) $matches[1];
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['status' => $code]);
    return;
}

// Default: 404
http_response_code(404);
header('Content-Type: application/json');
echo '{"error":"not found"}';
