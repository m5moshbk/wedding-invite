<?php
// emkan_create_link.php – نسخة cURL مع توضيح سبب الخطأ الحقيقي

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

/**
 * إرسال POST JSON إلى إمكان باستخدام cURL
 * نعيد: [http_status, body, curl_error, curl_errno]
 */
function http_post_json_curl($url, $jsonBody, $headers = [])
{
    $defaultHeaders = [
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    $allHeaders = array_merge($defaultHeaders, $headers);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $allHeaders,
        CURLOPT_POSTFIELDS     => $jsonBody,
        CURLOPT_TIMEOUT        => 30,
        // تجاوز تحقق SSL لأغراض التجربة فقط
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $responseBody = curl_exec($ch);
    $curlErrNo    = curl_errno($ch);
    $curlErrMsg   = curl_error($ch);
    $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$httpCode, $responseBody, $curlErrMsg, $curlErrNo];
}

// قراءة البيانات من الـ frontend
$input = file_get_contents('php://input');
$data  = json_decode($input, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'ok'       => false,
        'provider' => 'emkan',
        'error'    => 'Bad JSON payload from frontend.',
    ]);
    exit;
}

$amount      = isset($data['amount']) ? floatval($data['amount']) : 0;
$currency    = !empty($data['currency']) ? $data['currency'] : BNPL_DEFAULT_CURRENCY;
$phone       = trim($data['phone'] ?? '');
$email       = trim($data['email'] ?? '');
$description = trim($data['description'] ?? 'Emkan BNPL link');

if ($amount <= 0 || $phone === '') {
    http_response_code(400);
    echo json_encode([
        'ok'       => false,
        'provider' => 'emkan',
        'error'    => 'الرجاء إدخال مبلغ صحيح ورقم جوال.',
    ]);
    exit;
}

$orderRef = 'EMK-' . time();

// جسم الطلب – شكل مبدئي
$requestBody = [
    'orderRef'    => $orderRef,
    'amount'      => [
        'value'    => $amount,
        'currency' => $currency,
    ],
    'customer'    => [
        'mobile' => $phone,
        'email'  => $email !== '' ? $email : null,
    ],
    'description' => $description,
];

$jsonBody = json_encode($requestBody, JSON_UNESCAPED_UNICODE);

// الـ endpoint (قابل للتعديل حسب مستندات إمكان)
$endpointUrl = rtrim(EMKAN_API_BASE, '/') . '/api/bnpl/orders';

list($httpCode, $responseBody, $curlErrMsg, $curlErrNo) = http_post_json_curl(
    $endpointUrl,
    $jsonBody,
    [
        'x-api-key: '    . EMKAN_API_KEY,
        'x-api-secret: ' . EMKAN_API_SECRET,
    ]
);

// لو cURL نفسه فشل (DNS, SSL, حظر ...)
if ($responseBody === false || $responseBody === null) {
    http_response_code(502);
    echo json_encode([
        'ok'        => false,
        'provider'  => 'emkan',
        'error'     => 'Emkan API did not return a response (network / SSL / firewall).',
        'http_code' => $httpCode,
        'curl_err'  => [
            'code' => $curlErrNo,
            'msg'  => $curlErrMsg,
        ],
        'endpoint'  => $endpointUrl,
    ]);
    exit;
}

// محاولة فك JSON
$decoded = json_decode($responseBody, true);

if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code($httpCode ?: 500);
    echo json_encode([
        'ok'        => false,
        'provider'  => 'emkan',
        'error'     => 'Emkan API returned non-JSON response.',
        'http_code' => $httpCode,
        'raw_text'  => mb_substr($responseBody, 0, 600, 'UTF-8'),
        'endpoint'  => $endpointUrl,
    ]);
    exit;
}

// محاولة استخراج رابط الدفع
$link =
    $decoded['payment_url'] ??
    $decoded['checkout_url'] ??
    $decoded['url'] ??
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

// في حالة خطأ من إمكان
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
    'endpoint'  => $endpointUrl,
]); 