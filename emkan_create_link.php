<?php
// emkan_create_link.php
// هيكل مبدئي لإنشاء طلب BNPL مع إمكان Emkan

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

$input = file_get_contents('php://input');
$data  = json_decode($input, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Bad JSON payload']);
    exit;
}

$amount      = isset($data['amount']) ? floatval($data['amount']) : 0;
$currency    = !empty($data['currency']) ? $data['currency'] : BNPL_DEFAULT_CURRENCY;
$phone       = trim($data['phone'] ?? '');
$email       = trim($data['email'] ?? '');
$description = trim($data['description'] ?? 'Emkan BNPL link');

if ($amount <= 0 || $phone === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'الرجاء إدخال مبلغ صحيح ورقم جوال.']);
    exit;
}

$orderReferenceId = 'EMK-' . time();

// *** مهم: هذا الـ Body يجب تعديله حسب مستندات Emkan الرسمية ***
$requestBody = [
    'amount'      => $amount,
    'currency'    => $currency,
    'customer'    => [
        'phone' => $phone,
        'email' => $email,
    ],
    'description' => $description,
    'order_ref'   => $orderReferenceId,
];

$headers = [
    'Content-Type: application/json',
    'Accept: application/json',
    'x-api-key: ' . EMKAN_API_KEY,
    'x-api-secret: ' . EMKAN_API_SECRET,
];

$endpointUrl = EMKAN_API_BASE . '/api/bnpl/orders';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $endpointUrl,
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POSTFIELDS     => json_encode($requestBody, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT        => 30,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'cURL error: ' . $curlError,
    ]);
    exit;
}

$decoded = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
    $link = $decoded['payment_url'] ?? $decoded['checkout_url'] ?? null;

    if ($link) {
        echo json_encode([
            'ok'           => true,
            'provider'     => 'emkan',
            'checkout_url' => $link,
            'order_ref'    => $orderReferenceId,
            'raw'          => $decoded,
        ]);
        exit;
    }
}

http_response_code($httpCode ?: 500);
echo json_encode([
    'ok'        => false,
    'provider'  => 'emkan',
    'error'     => 'Emkan API error (تحقق من المسار والـ body مع دعم إمكان)',
    'http_code' => $httpCode,
    'body'      => $decoded,
]);