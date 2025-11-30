<?php
// emkan_create_link.php – بدون cURL، باستخدام file_get_contents

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

function http_post_json($url, $jsonBody, $headers = []) {
    $defaultHeaders = ['Content-Type: application/json', 'Accept: application/json'];
    $allHeaders = array_merge($defaultHeaders, $headers);

    $context = stream_context_create([
        'http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", $allHeaders) . "\r\n",
            'content'       => $jsonBody,
            'ignore_errors' => true,
            'timeout'       => 30,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    $statusLine = $http_response_header[0] ?? 'HTTP/1.1 500 Internal Server Error';
    if (!preg_match('#\s(\d{3})\s#', $statusLine, $m)) $statusCode = 500;
    else $statusCode = (int)$m[1];

    return [$statusCode, $response];
}

$input = file_get_contents('php://input');
$data  = json_decode($input, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'provider' => 'emkan', 'error' => 'Bad JSON payload']);
    exit;
}

$amount      = isset($data['amount']) ? floatval($data['amount']) : 0;
$currency    = !empty($data['currency']) ? $data['currency'] : BNPL_DEFAULT_CURRENCY;
$phone       = trim($data['phone'] ?? '');
$email       = trim($data['email'] ?? '');
$description = trim($data['description'] ?? 'Emkan BNPL link');

if ($amount <= 0 || $phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'provider' => 'emkan', 'error' => 'الرجاء إدخال مبلغ صحيح ورقم جوال.']);
    exit;
}

$orderRef = 'EMK-' . time();

// تنبيه: هذا body افتراضي، سنعدّله لو إمكان أعطونا شكل مختلف
$requestBody = [
    'orderRef' => $orderRef,
    'amount'   => [
        'value'    => $amount,
        'currency' => $currency,
    ],
    'customer' => [
        'mobile' => $phone,
        'email'  => $email !== '' ? $email : null,
    ],
    'description' => $description,
];

$jsonBody = json_encode($requestBody, JSON_UNESCAPED_UNICODE);

// endpoint افتراضي – نضبطه لاحقًا إذا أعطتك إمكان مسار مختلف
$endpointUrl = rtrim(EMKAN_API_BASE, '/') . '/api/bnpl/orders';

list($httpCode, $response) = http_post_json(
    $endpointUrl,
    $jsonBody,
    [
        'x-api-key: '    . EMKAN_API_KEY,
        'x-api-secret: ' . EMKAN_API_SECRET,
    ]
);

if ($response === false || $response === null) {
    http_response_code(502);
    echo json_encode([
        'ok'        => false,
        'provider'  => 'emkan',
        'error'     => 'Emkan API did not return a response (network or SSL issue)',
        'http_code' => $httpCode,
    ]);
    exit;
}

$decoded = json_decode($response, true);

if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code($httpCode ?: 500);
    echo json_encode([
        'ok'        => false,
        'provider'  => 'emkan',
        'error'     => 'Emkan API returned non-JSON response',
        'http_code' => $httpCode,
        'raw_text'  => mb_substr($response, 0, 500, 'UTF-8'),
    ]);
    exit;
}

$link =
    $decoded['payment_url'] ??
    $decoded['checkout_url'] ??
    null;

if ($httpCode >= 200 && $httpCode < 300 && $link) {
    echo json_encode([
        'ok'           => true,
        'provider'     => 'emkan',
        'checkout_url' => $link,
        'order_ref'    => $orderRef,
        'raw'          => $decoded,
    ]);
    exit;
}

$errorMessage = 'Emkan API error';
if (isset($decoded['message'])) {
    $errorMessage .= ': ' . $decoded['message'];
} elseif (isset($decoded['errors'][0]['message'])) {
    $errorMessage .= ': ' . $decoded['errors'][0]['message'];
}

http_response_code($httpCode ?: 500);
echo json_encode([
    'ok'        => false,
    'provider'  => 'emkan',
    'error'     => $errorMessage,
    'http_code' => $httpCode,
    'body'      => $decoded,
]);