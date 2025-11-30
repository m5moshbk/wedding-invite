<?php
// tamara_create_link.php – بدون cURL، باستخدام file_get_contents فقط

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

function normalize_sa_phone($phoneRaw) {
    $digits = preg_replace('/\D+/', '', $phoneRaw ?? '');
    if ($digits === '') return '';

    if (strpos($digits, '966') === 0) return '+' . $digits;
    if (strlen($digits) === 10 && $digits[0] === '0') return '+966' . substr($digits, 1);
    if (strlen($digits) === 9 && $digits[0] === '5') return '+966' . $digits;
    return '+' . $digits;
}

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
    echo json_encode(['ok' => false, 'error' => 'Bad JSON payload']);
    exit;
}

$amount      = isset($data['amount']) ? floatval($data['amount']) : 0;
$currency    = !empty($data['currency']) ? $data['currency'] : BNPL_DEFAULT_CURRENCY;
$phoneRaw    = trim($data['phone'] ?? '');
$email       = trim($data['email'] ?? '');
$description = trim($data['description'] ?? 'Tamara payment link');

$phone = normalize_sa_phone($phoneRaw);
if ($amount <= 0 || $phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'الرجاء إدخال مبلغ صحيح ورقم جوال.']);
    exit;
}

$orderReferenceId = 'TAM-' . time();
$orderNumber      = $orderReferenceId;

$requestBody = [
    'total_amount' => [
        'amount'   => $amount,
        'currency' => $currency,
    ],
    'phone_number'       => $phone,
    'email'              => $email !== '' ? $email : null,
    'order_reference_id' => $orderReferenceId,
    'order_number'       => $orderNumber,
    'items'              => [
        [
            'name'            => $description,
            'type'            => 'Digital',
            'reference_id'    => 'ITEM-1',
            'sku'             => 'SKU-1',
            'quantity'        => 1,
            'discount_amount' => ['amount' => 0, 'currency' => $currency],
            'unit_price'      => ['amount' => $amount, 'currency' => $currency],
            'tax_amount'      => ['amount' => 0, 'currency' => $currency],
            'total_amount'    => ['amount' => $amount, 'currency' => $currency],
        ],
    ],
];

$jsonBody = json_encode($requestBody, JSON_UNESCAPED_UNICODE);

list($httpCode, $response) = http_post_json(
    TAMARA_API_BASE . '/checkout/in-store-session',
    $jsonBody,
    ['Authorization: Bearer ' . TAMARA_MERCHANT_TOKEN]
);

if ($response === false || $response === null) {
    http_response_code(502);
    echo json_encode([
        'ok'       => false,
        'provider' => 'tamara',
        'error'    => 'Tamara API did not return a response (network or SSL issue)',
        'http_code'=> $httpCode,
    ]);
    exit;
}

$decoded = json_decode($response, true);

if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code($httpCode ?: 500);
    echo json_encode([
        'ok'        => false,
        'provider'  => 'tamara',
        'error'     => 'Tamara API returned non-JSON response',
        'http_code' => $httpCode,
        'raw_text'  => mb_substr($response, 0, 500, 'UTF-8'),
    ]);
    exit;
}

if ($httpCode >= 200 && $httpCode < 300 && isset($decoded['checkout_url'])) {
    echo json_encode([
        'ok'           => true,
        'provider'     => 'tamara',
        'checkout_url' => $decoded['checkout_url'],
        'order_id'     => $decoded['order_id']    ?? null,
        'checkout_id'  => $decoded['checkout_id'] ?? null,
        'raw'          => $decoded,
        'phone_sent'   => $phone,
    ]);
    exit;
}

$errorMessage = 'Tamara API error';
if (isset($decoded['message'])) {
    $errorMessage .= ': ' . $decoded['message'];
} elseif (isset($decoded['errors'][0]['message'])) {
    $errorMessage .= ': ' . $decoded['errors'][0]['message'];
}

http_response_code($httpCode ?: 500);
echo json_encode([
    'ok'        => false,
    'provider'  => 'tamara',
    'error'     => $errorMessage,
    'http_code' => $httpCode,
    'body'      => $decoded,
]);