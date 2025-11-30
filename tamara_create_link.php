<?php
// tamara_create_link.php
// إنشاء رابط دفع تمارا (In-Store Session)

// إلغاء عرض الأخطاء على المخرجات (حتى لا يخرب JSON)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// دالة لتطبيع رقم الجوال لصيغة دولية +9665XXXXXXX
function normalize_sa_phone($phoneRaw) {
    // إزالة أي شيء غير أرقام
    $digits = preg_replace('/\D+/', '', $phoneRaw ?? '');

    if ($digits === '') {
        return '';
    }

    // يبدأ بـ 966 بالفعل
    if (strpos($digits, '966') === 0) {
        return '+' . $digits;
    }

    // يبدأ بـ 0 وطوله 10 أرقام (مثل 0507190799)
    if (strlen($digits) === 10 && $digits[0] === '0') {
        return '+966' . substr($digits, 1);
    }

    // يبدأ بـ 5 وطوله 9 أرقام (مثل 507190799)
    if (strlen($digits) === 9 && $digits[0] === '5') {
        return '+966' . $digits;
    }

    // أي حالة أخرى نضيف لها +
    return '+' . $digits;
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

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => TAMARA_API_BASE . '/checkout/in-store-session',
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . TAMARA_MERCHANT_TOKEN,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($requestBody, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT        => 30,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'cURL error: ' . $curlError]);
    exit;
}

$decoded = json_decode($response, true);

// تجهيز رسالة خطأ أوضح
$errorMessage = 'Tamara API error';
if (is_array($decoded)) {
    if (isset($decoded['message']) && is_string($decoded['message'])) {
        $errorMessage .= ': ' . $decoded['message'];
    } elseif (isset($decoded['errors'][0]['message'])) {
        $errorMessage .= ': ' . $decoded['errors'][0]['message'];
    }
}

if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded) && isset($decoded['checkout_url'])) {
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

http_response_code($httpCode ?: 500);
echo json_encode([
    'ok'        => false,
    'provider'  => 'tamara',
    'error'     => $errorMessage,
    'http_code' => $httpCode,
    'body'      => $decoded,
]);